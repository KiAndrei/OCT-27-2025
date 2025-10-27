<?php
require_once 'session_manager.php';
validateUserAccess('client');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$client_id = $_SESSION['user_id'];

// Check if client has an approved request
$stmt = $conn->prepare("SELECT id, status, review_notes, reviewed_at FROM client_request_form WHERE client_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$request_status = $res->fetch_assoc();

// Set flag to show request access page instead of redirecting
$show_request_access = (!$request_status || $request_status['status'] !== 'Approved');

// Determine if client can submit a new request
$can_submit_request = true;
if ($request_status) {
    // Client can only submit a new request if:
    // 1. No existing request, OR
    // 2. Previous request was rejected
    $can_submit_request = ($request_status['status'] === 'Rejected');
}

// Check if this is a new approval (approved today)
$is_new_approval = false;
if ($request_status && isset($request_status['reviewed_at']) && $request_status['reviewed_at']) {
    $is_new_approval = (date('Y-m-d', strtotime($request_status['reviewed_at'])) === date('Y-m-d'));
}

// Get employee conversation - simplified approach
$employee_conversation = null;

// First, get the approved request
$stmt = $conn->prepare("SELECT id, client_id FROM client_request_form WHERE client_id = ? AND status = 'Approved' ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$approved_request = $res->fetch_assoc();

if ($approved_request) {
    // Get employee conversation for this request
    $stmt = $conn->prepare("
        SELECT cec.id as conversation_id, cec.conversation_status, cec.concern_identified, cec.concern_description,
               u.name as employee_name, u.profile_image as employee_image,
               (SELECT COUNT(*) FROM client_employee_messages cem 
                WHERE cem.conversation_id = cec.id 
                AND cem.sender_type = 'employee' 
                AND cem.is_seen = 0) as unseen_count
        FROM client_employee_conversations cec
        JOIN user_form u ON cec.employee_id = u.id
        WHERE cec.request_form_id = ?
    ");
    $stmt->bind_param("i", $approved_request['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $employee_conversation = $res->fetch_assoc();
    
    // If no conversation found, create one
    if (!$employee_conversation) {
        // Get the employee who approved this request
        $stmt = $conn->prepare("SELECT employee_id FROM employee_request_reviews WHERE request_form_id = ? AND action = 'Approved' ORDER BY reviewed_at DESC LIMIT 1");
        $stmt->bind_param("i", $approved_request['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $review = $res->fetch_assoc();
        
        if ($review) {
            // Create the conversation
            $stmt = $conn->prepare("INSERT INTO client_employee_conversations (request_form_id, client_id, employee_id, conversation_status) VALUES (?, ?, ?, 'Active')");
            $stmt->bind_param("iii", $approved_request['id'], $client_id, $review['employee_id']);
            $stmt->execute();
            
            // Get the newly created conversation
            $stmt = $conn->prepare("
                SELECT cec.id as conversation_id, cec.conversation_status, cec.concern_identified, cec.concern_description,
                       u.name as employee_name, u.profile_image as employee_image,
                       (SELECT COUNT(*) FROM client_employee_messages cem 
                        WHERE cem.conversation_id = cec.id 
                        AND cem.sender_type = 'employee' 
                        AND cem.is_seen = 0) as unseen_count
                FROM client_employee_conversations cec
                JOIN user_form u ON cec.employee_id = u.id
                WHERE cec.request_form_id = ?
            ");
            $stmt->bind_param("i", $approved_request['id']);
            $stmt->execute();
            $res = $stmt->get_result();
            $employee_conversation = $res->fetch_assoc();
        }
    }
}

// Fix employee image path
if ($employee_conversation) {
    $img = $employee_conversation['employee_image'];
    if (!$img || !file_exists($img)) {
        $employee_conversation['employee_image'] = 'images/default-avatar.jpg';
    }
}

// Get attorney conversation if assigned
$attorney_conversation = null;
$stmt = $conn->prepare("
    SELECT cac.id as conversation_id, cac.conversation_status,
           u.name as attorney_name, u.profile_image as attorney_image, u.user_type,
           (SELECT COUNT(*) FROM client_attorney_messages cam 
            WHERE cam.conversation_id = cac.id 
            AND cam.sender_type = 'attorney' 
            AND cam.is_seen = 0) as unseen_count
    FROM client_attorney_assignments caa
    JOIN client_attorney_conversations cac ON caa.id = cac.assignment_id
    JOIN user_form u ON cac.attorney_id = u.id
    WHERE caa.client_id = ? AND cac.conversation_status = 'Active'
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$attorney_conversation = $res->fetch_assoc();

// Fix attorney image path
if ($attorney_conversation) {
    $img = $attorney_conversation['attorney_image'];
    if (!$img || !file_exists($img)) {
        $attorney_conversation['attorney_image'] = 'images/default-avatar.jpg';
    }
}

// Fetch client profile image, email, and name
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$client_email = '';
$client_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $client_email = $row['email'];
    $client_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Handle AJAX mark individual messages as seen when conversation is selected
if (isset($_POST['action']) && $_POST['action'] === 'mark_conversation_messages_seen') {
    $conversation_id = intval($_POST['conversation_id']);
    $conversation_type = $_POST['conversation_type']; // 'employee' or 'attorney'
    
    if ($conversation_type === 'employee') {
        // Mark all employee messages in this conversation as seen
        $stmt = $conn->prepare("UPDATE client_employee_messages SET is_seen = 1 WHERE conversation_id = ? AND sender_type = 'employee' AND is_seen = 0");
    } else {
        // Mark all attorney/admin messages in this conversation as seen (both use 'attorney' sender_type)
        $stmt = $conn->prepare("UPDATE client_attorney_messages SET is_seen = 1 WHERE conversation_id = ? AND sender_type = 'attorney' AND is_seen = 0");
    }
    
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    
    $result = $stmt->error ? 'error' : 'success';
    echo $result;
    exit();
}

// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $conversation_type = $_POST['conversation_type']; // 'employee' or 'attorney'
    $conversation_id = intval($_POST['conversation_id']);
    $msgs = [];
    
    // Fetch client profile image
    $client_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'images/default-avatar.jpg';
    
    // Fetch other party profile image
    $other_img = '';
    if ($conversation_type === 'employee') {
        $stmt = $conn->prepare("SELECT u.profile_image FROM client_employee_conversations cec JOIN user_form u ON cec.employee_id = u.id WHERE cec.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT u.profile_image FROM client_attorney_assignments caa JOIN user_form u ON caa.attorney_id = u.id WHERE caa.id = ?");
    }
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $other_img = $row['profile_image'];
    if (!$other_img || !file_exists($other_img)) $other_img = 'images/default-avatar.jpg';
    
    // Fetch messages based on conversation type
    if ($conversation_type === 'employee') {
        $stmt = $conn->prepare("SELECT sender_id, sender_type, message, sent_at FROM client_employee_messages WHERE conversation_id = ? ORDER BY sent_at ASC");
    } else {
        $stmt = $conn->prepare("SELECT sender_id, sender_type, message, sent_at FROM client_attorney_messages WHERE conversation_id = ? ORDER BY sent_at ASC");
    }
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sent = $row['sender_type'] === 'client';
        $row['profile_image'] = $sent ? $client_img : $other_img;
        $msgs[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}

// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $conversation_type = $_POST['conversation_type'];
    $conversation_id = intval($_POST['conversation_id']);
    $msg = $_POST['message'];
    
    if ($conversation_type === 'employee') {
        $stmt = $conn->prepare("INSERT INTO client_employee_messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, 'client', ?)");
        } else {
        $stmt = $conn->prepare("INSERT INTO client_attorney_messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, 'client', ?)");
        }
    $stmt->bind_param('iis', $conversation_id, $client_id, $msg);
        $stmt->execute();
    
        $result = $stmt->affected_rows > 0 ? 'success' : 'error';
        
        if ($result === 'success') {
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $client_id,
                $client_name,
                'client',
                'Message Send',
                'Communication',
            "Sent message to " . ($conversation_type === 'employee' ? 'employee' : 'attorney') . " in conversation ID: $conversation_id",
                'success',
                'low'
            );
            
            // Notify recipient about the new message
            if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
                // Get recipient info based on conversation type
                if ($conversation_type === 'employee') {
                    $stmt = $conn->prepare("SELECT employee_id FROM client_employee_conversations WHERE id = ?");
                    $stmt->bind_param('i', $conversation_id);
                    $stmt->execute();
                    $recipient_id = $stmt->get_result()->fetch_assoc()['employee_id'];
                    $userType = 'employee';
                } else {
                    $stmt = $conn->prepare("SELECT attorney_id FROM client_attorney_conversations WHERE id = ?");
                    $stmt->bind_param('i', $conversation_id);
                    $stmt->execute();
                    $recipient_id = $stmt->get_result()->fetch_assoc()['attorney_id'];
                    $userType = 'attorney';
                }
                
                if ($recipient_id) {
                    // Get client name for notification
                    $stmt_client = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                    $stmt_client->bind_param('i', $client_id);
                    $stmt_client->execute();
                    $client_name = $stmt_client->get_result()->fetch_assoc()['name'];
                    
                    $nTitle = 'New Message Received';
                    $nMsg = "You received a new message from client: $client_name - " . substr($msg, 0, 50) . (strlen($msg) > 50 ? '...' : '');
                    $notificationType = 'info';
                    
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                    $stmtN->bind_param('issss', $recipient_id, $userType, $nTitle, $nMsg, $notificationType);
                    $stmtN->execute();
                }
            }
        }
        
        echo $result;
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
                <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="client_dashboard.php" title="View your case overview, statistics, and recent activities">
                    <div class="button-content">
                        <i class="fas fa-home"></i>
                        <div class="text-content">
                            <span>Dashboard</span>
                            <small>Overview & Statistics</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_documents.php" title="Generate legal documents like affidavits and sworn statements">
                    <div class="button-content">
                        <i class="fas fa-file-alt"></i>
                        <div class="text-content">
                            <span>Document Generation</span>
                            <small>Create Legal Documents</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_cases.php" title="Track your legal cases, view case details, and upload documents">
                    <div class="button-content">
                        <i class="fas fa-gavel"></i>
                        <div class="text-content">
                            <span>My Cases</span>
                            <small>Track Legal Cases</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_schedule.php" title="View your upcoming appointments, hearings, and court schedules">
                    <div class="button-content">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="text-content">
                            <span>My Schedule</span>
                            <small>Appointments & Hearings</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_messages.php" class="active" title="Communicate with your attorney and legal team">
                    <div class="button-content">
                        <i class="fas fa-envelope"></i>
                        <div class="text-content">
                            <span>Messages</span><small>Chat with Attorney</small></div></div><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a>
            </li>
            <li>
                <a href="client_about.php" title="Learn more about Opiña Law Office and our team">
                    <div class="button-content">
                        <i class="fas fa-info-circle"></i>
                        <div class="text-content">
                            <span>About Us</span>
                            <small>Our Story & Team</small>
                        </div>
                    </div>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Messages';
        $page_subtitle = 'Communicate with our legal team';
        include 'components/profile_header.php'; 
        ?>

        <?php if ($show_request_access): ?>
            <!-- Request Access Page -->
            <div class="request-access-container">
                <div class="request-access-card">
                    <div class="request-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2>Access Required</h2>
                    <p>To start messaging with our legal team, you need to request access first. This helps us verify your identity and provide better service.</p>
                    
                    <?php if ($request_status): ?>
                        <?php if ($request_status['status'] === 'Pending'): ?>
                            <div class="status-info pending">
                                <i class="fas fa-clock"></i>
                                <h3>Request Under Review</h3>
                                <p>Your request is currently being reviewed by our team. You will be notified once it's approved.</p>
                            </div>
                        <?php elseif ($request_status['status'] === 'Rejected'): ?>
                            <div class="status-info rejected">
                                <i class="fas fa-times-circle"></i>
                                <h3>Previous Request Rejected</h3>
                                <p>Your previous request was rejected. Please submit a new request with updated information.</p>
                                <?php if ($request_status['review_notes']): ?>
                                    <div class="rejection-details">
                                        <strong>Rejection Reason:</strong><br>
                                        <div class="rejection-notes">
                                            <?= nl2br(htmlspecialchars($request_status['review_notes'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="request-actions">
                        <?php if ($can_submit_request): ?>
                            <button onclick="openDocumentRequestModal()" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Request Access
                            </button>
                        <?php else: ?>
                            <?php if ($request_status['status'] === 'Pending'): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-clock"></i>
                                    Request Pending Review
                                </button>
                                <div class="request-status-info">
                                    <small>Your request is currently being reviewed. You will be notified once a decision is made.</small>
                                </div>
                            <?php elseif ($request_status['status'] === 'Approved'): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    Request Approved
                                </button>
                                <div class="request-status-info">
                                    <small>Your request has been approved. You can now access the messaging system.</small>
                                </div>
                            <?php elseif ($request_status['status'] === 'Rejected'): ?>
                                <button onclick="openDocumentRequestModal()" class="btn btn-warning">
                                    <i class="fas fa-redo"></i>
                                    Submit New Request
                                </button>
                                <div class="request-status-info">
                                    <small>Your previous request was rejected. You can submit a new request with updated information.</small>
                                    <?php if (!empty($request_status['review_notes'])): ?>
                                        <br><br>
                                        <strong>Review Notes:</strong><br>
                                        <em><?= nl2br(htmlspecialchars($request_status['review_notes'])) ?></em>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-info-circle"></i>
                                    Request Status: <?= htmlspecialchars($request_status['status']) ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Messages Page -->
            <div class="chat-container">
                <!-- Conversation List -->
                <div class="conversation-list">
                    <h3>Your Conversations</h3>
                    <ul id="conversationList">
                        <?php if ($employee_conversation && isset($employee_conversation['conversation_id']) && isset($employee_conversation['employee_name'])): ?>
                            <li class="conversation-item <?= $employee_conversation['unseen_count'] > 0 ? 'has-unseen' : '' ?>" data-type="employee" data-id="<?= $employee_conversation['conversation_id'] ?>" onclick="selectConversation('employee', <?= $employee_conversation['conversation_id'] ?>, '<?= htmlspecialchars($employee_conversation['employee_name']) ?>')">
                                <img src='<?= htmlspecialchars($employee_conversation['employee_image'] ?? 'images/default-avatar.jpg') ?>' alt='Employee' style='width:28px;height:28px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>
                                <div class="conversation-info">
                                    <span><?= htmlspecialchars($employee_conversation['employee_name']) ?></span>
                                    <small>Employee</small>
                                </div>
                                <?php if ($employee_conversation['unseen_count'] > 0): ?>
                                    <div class="unseen-badge">
                                        <span><?= $employee_conversation['unseen_count'] ?></span>
                                    </div>
                                <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($attorney_conversation && isset($attorney_conversation['conversation_id']) && isset($attorney_conversation['attorney_name'])): ?>
                            <li class="conversation-item <?= $attorney_conversation['unseen_count'] > 0 ? 'has-unseen' : '' ?>" data-type="attorney" data-id="<?= $attorney_conversation['conversation_id'] ?>" onclick="selectConversation('attorney', <?= $attorney_conversation['conversation_id'] ?>, '<?= htmlspecialchars($attorney_conversation['attorney_name']) ?>')">
                                <img src='<?= htmlspecialchars($attorney_conversation['attorney_image'] ?? 'images/default-avatar.jpg') ?>' alt='Attorney' style='width:28px;height:28px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>
                                <div class="conversation-info">
                                    <span><?= htmlspecialchars($attorney_conversation['attorney_name']) ?></span>
                                    <div class="status-badge assigned">
                                        <i class="fas fa-user-tie"></i>
                                        (assigned attorney)
                                    </div>
                                </div>
                                <?php if ($attorney_conversation['unseen_count'] > 0): ?>
                                    <div class="unseen-badge">
                                        <span><?= $attorney_conversation['unseen_count'] ?></span>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ((!$employee_conversation || !isset($employee_conversation['conversation_id'])) && (!$attorney_conversation || !isset($attorney_conversation['conversation_id']))): ?>
                            <li class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No conversations available</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-area">
                    <div class="chat-header">
                        <h2 id="selectedConversation">Select a conversation</h2>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <p style="color:#888;text-align:center;">Select a conversation to start messaging.</p>
                    </div>
                    <div class="chat-compose" id="chatCompose" style="display:none;">
                        <textarea id="messageInput" placeholder="Type your message..."></textarea>
                        <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Approval Notes Popup Modal -->
        <?php if ($request_status && $request_status['status'] === 'Approved'): ?>
            <div id="approvalNotesModal" class="approval-modal" style="display: none;">
                <div class="approval-modal-content">
                    <div class="approval-modal-header">
                        <div class="header-content">
                            <i class="fas fa-check-circle header-icon"></i>
                            <div class="header-text">
                                <h2>Request Approved!</h2>
                                <p>Your request has been approved. Here are the notes from our team.</p>
                            </div>
                        </div>
                        <button class="close-modal" onclick="closeApprovalModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="approval-modal-body">
                        <?php if (!empty($request_status['review_notes'])): ?>
                        <div class="approval-notes-content">
                            <h4>Approval Notes:</h4>
                            <div class="notes-text">
                                <?= nl2br(htmlspecialchars($request_status['review_notes'])) ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="approval-notes-content">
                            <h4>Request Approved!</h4>
                            <div class="notes-text">
                                Your request has been approved and an attorney has been assigned to your case. You can now start messaging with our team.
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="modal-actions">
                            <button class="btn btn-primary" onclick="closeApprovalModal()">
                                <i class="fas fa-check"></i>
                                Got it, let's start messaging!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Request Access Modal -->
        <div id="requestAccessModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-lock"></i> Access Required</h2>
                    <span class="close" onclick="closeRequestAccessModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Request Status View -->
                    <div id="requestStatusView" class="request-access-content" style="display: <?= $can_submit_request ? 'none' : 'block' ?>;">
                        <div class="request-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <p>To start messaging with our legal team, you need to request access first. This helps us verify your identity and provide better service.</p>
                        
                        <?php if ($request_status): ?>
                            <?php if ($request_status['status'] === 'Pending'): ?>
                                <div class="status-info pending">
                                    <i class="fas fa-clock"></i>
                                    <h3>Request Under Review</h3>
                                    <p>Your request is currently being reviewed by our team. You will be notified once it's approved.</p>
                                </div>
                            <?php elseif ($request_status['status'] === 'Rejected'): ?>
                                <div class="status-info rejected">
                                    <i class="fas fa-times-circle"></i>
                                    <h3>Request Rejected</h3>
                                    <p>Your request was not approved. You can submit a new request with updated information.</p>
                                    <?php if ($request_status['review_notes']): ?>
                                        <div class="review-notes">
                                            <strong>Review Notes:</strong>
                                            <p><?= htmlspecialchars($request_status['review_notes']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="request-actions">
                            <?php if ($can_submit_request): ?>
                                <button onclick="showRequestForm()" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                    Request Access
                                </button>
                            <?php else: ?>
                                <?php if ($request_status['status'] === 'Pending'): ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-clock"></i>
                                        Request Pending Review
                                    </button>
                                    <div class="request-status-info">
                                        <small>Your request is currently being reviewed. You will be notified once a decision is made.</small>
                                    </div>
                                <?php elseif ($request_status['status'] === 'Approved'): ?>
                                    <button class="btn btn-success" disabled>
                                        <i class="fas fa-check-circle"></i>
                                        Request Approved
                                    </button>
                                    <div class="request-status-info">
                                        <small>Your request has been approved. You can now access the messaging system.</small>
                                    </div>
                                <?php elseif ($request_status['status'] === 'Rejected'): ?>
                                    <button onclick="showRequestForm()" class="btn btn-warning">
                                        <i class="fas fa-redo"></i>
                                        Submit New Request
                                    </button>
                                    <div class="request-status-info">
                                        <small>Your previous request was rejected. You can submit a new request with updated information.</small>
                                        <?php if (!empty($request_status['review_notes'])): ?>
                                            <br><br>
                                            <strong>Review Notes:</strong><br>
                                            <em><?= nl2br(htmlspecialchars($request_status['review_notes'])) ?></em>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-info-circle"></i>
                                        Request Status: <?= htmlspecialchars($request_status['status']) ?>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <button onclick="closeRequestAccessModal()" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Close
                            </button>
                        </div>
                    </div>

                    <!-- Request Form View -->
                    <div id="requestFormView" class="request-form-content" style="display: <?= $can_submit_request ? 'block' : 'none' ?>;">
                        <form id="requestAccessForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="submit_request">
                            
                            <!-- Personal Information Section -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-user"></i>
                                    Personal Information
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="full_name">Full Name *</label>
                                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($client_name) ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="sex">Sex *</label>
                                        <select id="sex" name="sex" required>
                                            <option value="">Select Sex</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Contact Information Section -->
                                <div class="form-group">
                                    <label for="street_address">Street Address *</label>
                                    <input type="text" id="street_address" name="street_address" placeholder="House/Building No., Street Name" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="barangay">Barangay *</label>
                                        <input type="text" id="barangay" name="barangay" placeholder="Barangay" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="city">City/Municipality *</label>
                                        <input type="text" id="city" name="city" placeholder="City/Municipality" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="province">Province *</label>
                                        <input type="text" id="province" name="province" placeholder="Province" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="zip_code">ZIP Code *</label>
                                        <input type="text" id="zip_code" name="zip_code" placeholder="ZIP Code" maxlength="4" pattern="[0-9]{4}" title="Please enter a 4-digit ZIP code" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Legal Concern Section -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-gavel"></i>
                                    Legal Concern
                                </div>
                                <div class="form-group">
                                    <label for="concern_description">Legal Concern/Issue *</label>
                                    <textarea id="concern_description" name="concern_description" rows="6" placeholder="Please describe your legal concern or issue in detail. Include relevant facts, dates, and any specific questions you have. The more information you provide, the better we can assist you." required></textarea>
                                    <div class="field-help">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Include key details such as: What happened? When did it occur? Who is involved? What outcome are you seeking?</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Document Upload Section -->
                            <div class="form-group full-width-field">
                                <div class="form-section-title">
                                    <i class="fas fa-file-upload"></i>
                                    Valid ID
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="valid_id_front">Valid ID Front *</label>
                                <div class="file-upload-container">
                                    <input type="file" id="valid_id_front" name="valid_id_front" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" onchange="showFrontPreview(this)" required>
                                    <label for="valid_id_front" class="file-upload-label">
                                        <i class="fas fa-upload"></i>
                                        <span>Choose Front Image</span>
                                    </label>
                                    <div class="file-info">
                                        <small>Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                                    </div>
                                    
                                    <!-- Image Preview Container -->
                                    <div id="front-image-container" style="margin-top: 15px; text-align: center; display: none;">
                                        <div style="background: #d4edda; padding: 20px; border-radius: 10px; border: 2px solid #28a745; position: relative;">
                                            <button onclick="removeFrontImage()" style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 14px;" title="Remove Image">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <h4 style="color: #155724; margin-bottom: 15px;">
                                                <i class="fas fa-check-circle"></i> Front ID Uploaded Successfully!
                                            </h4>
                                            <img id="front-image" src="" alt="Front ID" style="max-width: 100%; max-height: 120px; border-radius: 8px; border: 2px solid #28a745;">
                                            <div style="margin-top: 10px; color: #155724; font-weight: bold;">
                                                <span id="front-filename"></span>
                                            </div>
                                            <div style="margin-top: 10px;">
                                                <button onclick="removeFrontImage()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-size: 14px;">
                                                    <i class="fas fa-trash"></i> Remove & Upload Different Image
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="valid_id_back">Valid ID Back *</label>
                                <div class="file-upload-container">
                                    <input type="file" id="valid_id_back" name="valid_id_back" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" onchange="showBackPreview(this)" required>
                                    <label for="valid_id_back" class="file-upload-label">
                                        <i class="fas fa-upload"></i>
                                        <span>Choose Back Image</span>
                                    </label>
                                    <div class="file-info">
                                        <small>Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                                    </div>
                                    
                                    <!-- Image Preview Container -->
                                    <div id="back-image-container" style="margin-top: 15px; text-align: center; display: none;">
                                        <div style="background: #d4edda; padding: 20px; border-radius: 10px; border: 2px solid #28a745; position: relative;">
                                            <button onclick="removeBackImage()" style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 14px;" title="Remove Image">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <h4 style="color: #155724; margin-bottom: 15px;">
                                                <i class="fas fa-check-circle"></i> Back ID Uploaded Successfully!
                                            </h4>
                                            <img id="back-image" src="" alt="Back ID" style="max-width: 100%; max-height: 120px; border-radius: 8px; border: 2px solid #28a745;">
                                            <div style="margin-top: 10px; color: #155724; font-weight: bold;">
                                                <span id="back-filename"></span>
                                            </div>
                                            <div style="margin-top: 10px;">
                                                <button onclick="removeBackImage()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-size: 14px;">
                                                    <i class="fas fa-trash"></i> Remove & Upload Different Image
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Privacy Consent -->
                            <div class="form-group full-width-field">
                                <div class="form-section-title">
                                    <i class="fas fa-shield-alt"></i>
                                    Privacy Consent
                                </div>
                                
                                <div class="privacy-checkbox-container">
                                    <input type="checkbox" id="privacy_consent" name="privacy_consent" required>
                                    <label for="privacy_consent" class="privacy-label">
                                        <i class="fas fa-shield-alt"></i>
                                        <span>I agree to the Data Privacy Act (Philippines - RA 10173)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="button" onclick="showRequestStatus()" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Back
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                    Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Success Modal -->
    <div id="uploadSuccessModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-width: 350px; width: 90%; text-align: center;">
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border-radius: 12px 12px 0 0; padding: 15px;">
                <h3 style="margin: 0; font-size: 1rem;">
                    <i class="fas fa-check-circle"></i> Upload Successful
                </h3>
            </div>
            <div style="padding: 15px;">
                <div style="font-size: 0.9rem; color: #333; margin-bottom: 15px;" id="uploadSuccessMessage">
                    <!-- Message will be inserted here -->
                </div>
                <button onclick="closeUploadSuccessModal()" style="background: linear-gradient(135deg, #5D0E26, #8B1538); border: none; padding: 8px 16px; border-radius: 6px; color: white; cursor: pointer; font-size: 0.9rem;">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>

    <!-- Remove Success Modal -->
    <div id="removeSuccessModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-width: 350px; width: 90%; text-align: center;">
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border-radius: 12px 12px 0 0; padding: 15px;">
                <h3 style="margin: 0; font-size: 1rem;">
                    <i class="fas fa-trash-alt"></i> Image Removed
                </h3>
            </div>
            <div style="padding: 15px;">
                <div style="font-size: 0.9rem; color: #333; margin-bottom: 15px;" id="removeSuccessMessage">
                    <!-- Message will be inserted here -->
                </div>
                <button onclick="closeRemoveSuccessModal()" style="background: linear-gradient(135deg, #5D0E26, #8B1538); border: none; padding: 8px 16px; border-radius: 6px; color: white; cursor: pointer; font-size: 0.9rem;">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>

    <!-- Request Submission Success Modal -->
    <div id="requestSubmissionSuccessModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); max-width: 450px; width: 90%; text-align: center;">
            <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; border-radius: 16px 16px 0 0; padding: 25px;">
                <div style="font-size: 3rem; margin-bottom: 15px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700;">
                    Request Submitted Successfully!
                </h3>
            </div>
            <div style="padding: 25px;">
                <div style="font-size: 1rem; color: #333; margin-bottom: 20px; line-height: 1.5;" id="requestSubmissionMessage">
                    Your access request has been submitted successfully. Our team will review your request and notify you once a decision is made.
                </div>
                <div style="background: rgba(40, 167, 69, 0.1); padding: 15px; border-radius: 10px; border-left: 4px solid #28a745; margin-bottom: 20px;">
                    <div style="color: #155724; font-size: 0.9rem; font-weight: 600;">
                        <i class="fas fa-info-circle"></i> What happens next?
                    </div>
                    <div style="color: #155724; font-size: 0.85rem; margin-top: 8px;">
                        • Your request will be reviewed by our legal team<br>
                        • You will receive a notification once approved<br>
                        • You can then start messaging with our team
                    </div>
                </div>
                <button onclick="closeRequestSubmissionSuccessModal()" style="background: linear-gradient(135deg, #28a745, #20c997); border: none; padding: 12px 24px; border-radius: 8px; color: white; cursor: pointer; font-size: 1rem; font-weight: 600; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                    <i class="fas fa-check"></i> Got it!
                </button>
            </div>
        </div>
    </div>

    <style>
        .request-access-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
            padding: 40px 20px;
        }

        .request-access-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.15);
            border: 2px solid rgba(93, 14, 38, 0.1);
            max-width: 500px;
            width: 100%;
        }

        .request-icon {
            font-size: 4rem;
            color: #5D0E26;
            margin-bottom: 20px;
        }

        .request-access-card h2 {
            color: #5D0E26;
            margin: 0 0 12px 0;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .request-access-card p {
            color: #666;
            margin: 0 0 30px 0;
            font-size: 1.1rem;
            line-height: 1.6;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }

        .status-info {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid;
        }

        .status-info.pending {
            border-color: #8B1538;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
        }

        .status-info.rejected {
            border-color: #e74c3c;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }

        .status-info i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .status-info.pending i {
            color: #8B1538;
        }

        .status-info.rejected i {
            color: #dc3545;
        }

        .status-info h3 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: #5D0E26;
            font-family: "Playfair Display", serif;
        }

        .status-info p {
            margin: 0;
            font-size: 1rem;
        }

        .rejection-details {
            margin-top: 15px;
            text-align: left;
        }

        .rejection-notes {
            background: rgba(220, 53, 69, 0.1);
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            margin-top: 8px;
            font-style: italic;
            color: #721c24;
            font-size: 0.9rem;
        }

        .request-actions {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }

        /* Match button sizing from Documents page */
        .request-actions .btn {
            min-width: 200px;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
        }
        .request-actions .btn.btn-primary {
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }
        .request-actions .btn.btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.4);
        }

        /* Approval Notes Modal */
        .approval-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .approval-modal-content {
            background: white;
            border-radius: 20px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            margin: 5% auto;
        }

        .approval-modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            padding: 24px 32px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
            font-size: 2rem;
            color: white;
        }

        .header-text h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
        }

        .header-text p {
            margin: 4px 0 0 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            font-size: 1.2rem;
            color: white;
            cursor: pointer;
            padding: 8px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .approval-modal-body {
            padding: 32px;
            overflow-y: auto;
            flex: 1;
        }

        .approval-notes-content h4 {
            margin: 0 0 16px 0;
            color: #28a745;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .notes-text {
            background: rgba(40, 167, 69, 0.1);
            padding: 16px;
            border-radius: 12px;
            border-left: 4px solid #28a745;
            color: #155724;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.4);
        }

        .chat-container { 
            display: flex; 
            height: 75vh; 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%); 
            border-radius: 20px; 
            box-shadow: 
                0 8px 32px rgba(93, 14, 38, 0.12),
                0 4px 16px rgba(93, 14, 38, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8); 
            overflow: hidden; 
            border: 1px solid rgba(93, 14, 38, 0.1);
            margin-top: 20px;
            position: relative;
        }
        
        .conversation-list { 
            width: 300px; 
            background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%); 
            border-right: 2px solid rgba(93, 14, 38, 0.08); 
            padding: 24px 0; 
            position: relative;
            overflow: hidden;
        }
        
        .conversation-list h3 { 
            text-align: center; 
            margin-bottom: 24px; 
            color: var(--primary-color); 
            font-size: 1.4rem;
            font-weight: 700;
            padding: 0 20px;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .conversation-list ul { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 0 8px 4px 8px;
            border: 1px solid transparent;
            position: relative;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
        
        .conversation-item:hover { 
            background: linear-gradient(135deg, #e3f2fd 0%, #f3f8ff 100%); 
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.1);
        }
        
        .conversation-item.active { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .conversation-info {
            flex: 1;
        }
        
        .conversation-info span {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-color);
            display: block;
            margin-bottom: 1px;
        }
        
        .conversation-item.active .conversation-info span {
            color: white;
        }
        
        .conversation-info small {
            color: #666;
            font-size: 0.65rem;
            display: block;
            margin-bottom: 2px;
        }
        
        .conversation-item.active .conversation-info small {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .status-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.65rem;
            font-weight: 500;
            padding: 3px 6px;
            border-radius: 8px;
            width: fit-content;
        }
        
        .status-badge.identified {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.assigned {
            background: #cce5ff;
            color: #004085;
        }
        
        .conversation-item.active .status-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .conversation-item.has-unseen {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
            border: 2px solid #f39c12 !important;
            animation: pulseUnseen 2s infinite;
        }
        
        .unseen-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        @keyframes pulseUnseen {
            0% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(243, 156, 18, 0); }
            100% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0); }
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .chat-area { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
        }
        
        .chat-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 24px 32px; 
            border-bottom: 2px solid rgba(93, 14, 38, 0.08); 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
            border-radius: 0 20px 0 0;
        }
        
        .chat-header h2 { 
            margin: 0; 
            font-size: 1.5rem; 
            color: var(--primary-color); 
            font-weight: 700;
        }

        .chat-messages { 
            flex: 1; 
            padding: 16px; 
            overflow-y: auto; 
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .message-bubble { 
            max-width: 55%; 
            margin-bottom: 10px; 
            padding: 8px 12px; 
            border-radius: 14px; 
            font-size: 0.75rem; 
            position: relative; 
            line-height: 1.3;
            box-shadow: 
                0 1px 4px rgba(0, 0, 0, 0.05),
                0 1px 2px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }
        
        .message-bubble.sent { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            margin-left: auto; 
            color: white;
            border-bottom-right-radius: 12px;
        }
        
        .message-bubble.received { 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); 
            border: 2px solid rgba(93, 14, 38, 0.08); 
            color: var(--text-color);
            border-bottom-left-radius: 12px;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-text p {
            margin: 0;
            word-wrap: break-word;
            font-size: 0.75rem;
            line-height: 1.3;
        }
        
        .message-meta { 
            font-size: 0.65rem; 
            color: rgba(255, 255, 255, 0.9); 
            margin-top: 4px; 
            text-align: right; 
            font-weight: 500;
        }
        
        .message-bubble.received .message-meta {
            color: #666;
        }
        
        .chat-compose { 
            display: flex; 
            gap: 8px; 
            padding: 12px 16px; 
            border-top: 2px solid rgba(93, 14, 38, 0.08); 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
            border-radius: 0 0 20px 20px;
        }
        
        .chat-compose textarea { 
            flex: 1; 
            border-radius: 8px; 
            border: 1px solid rgba(93, 14, 38, 0.1); 
            padding: 8px 12px; 
            resize: none; 
            font-size: 0.75rem; 
            font-family: inherit;
            line-height: 1.3;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            min-height: 40px;
            max-height: 80px;
        }
        
        .chat-compose textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 
                0 0 0 4px rgba(93, 14, 38, 0.1),
                inset 0 2px 4px rgba(93, 14, 38, 0.05);
            background: white;
            transform: translateY(-1px);
        }
        
        .chat-compose button { 
            padding: 8px 16px; 
            border-radius: 8px; 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            color: #fff; 
            border: none; 
            font-weight: 600; 
            cursor: pointer; 
            font-size: 0.75rem;
            transition: all 0.3s ease;
            min-width: 60px;
            box-shadow: 
                0 1px 4px rgba(93, 14, 38, 0.1),
                0 1px 2px rgba(93, 14, 38, 0.05);
        }
        
        .chat-compose button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 
                0 8px 25px rgba(93, 14, 38, 0.3),
                0 4px 15px rgba(93, 14, 38, 0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
        }

        .request-status-info {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 15px;
            border: 1px solid rgba(93, 14, 38, 0.1);
            text-align: left;
        }
        .request-status-info small {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        @media (max-width: 900px) { 
            .chat-container { 
                flex-direction: column; 
                height: auto; 
                margin: 20px 10px;
            } 
            .conversation-list { 
                width: 100%; 
                border-right: none; 
                border-bottom: 1px solid #e9ecef; 
                padding: 20px 0;
            }
            .conversation-item {
                margin: 0 6px 3px 6px;
                padding: 5px 10px;
            }
            .chat-messages {
                padding: 20px;
            }
            .chat-compose {
                padding: 20px;
            }
        }

        /* Request Form Styles */
        .request-form-content {
            max-height: 70vh;
            overflow-y: auto;
            padding: 20px 0;
        }
        
        .form-section {
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            color: #5D0E26;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: "Playfair Display", serif;
        }
        
        .form-section-title i {
            color: #8B1538;
            font-size: 1.2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        
        .form-group.full-width-field {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #5D0E26;
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group label::before {
            content: '';
            width: 3px;
            height: 14px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 2px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.95);
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            background: white;
            transform: translateY(-1px);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            line-height: 1.6;
        }
        
        .field-help {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 8px;
            padding: 10px 12px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 8px;
            border-left: 3px solid #8B1538;
        }
        
        .field-help i {
            color: #8B1538;
            font-size: 0.9rem;
            margin-top: 2px;
        }
        
        .field-help span {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .file-upload-container {
            position: relative;
        }
        
        .file-upload-container input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: rgba(93, 14, 38, 0.05);
            border: 2px dashed rgba(93, 14, 38, 0.2);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            color: #5D0E26;
            font-weight: 500;
        }
        
        .file-upload-label:hover {
            background: rgba(93, 14, 38, 0.1);
            border-color: rgba(93, 14, 38, 0.3);
        }
        
        .file-upload-label i {
            color: #8B1538;
        }
        
        .file-info {
            margin-top: 8px;
        }
        
        .file-info small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .privacy-checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 20px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .privacy-checkbox-container input[type="checkbox"] {
            margin: 0;
            width: 18px;
            height: 18px;
            accent-color: #5D0E26;
        }
        
        .privacy-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #5D0E26;
            font-weight: 500;
            cursor: pointer;
            margin: 0;
        }
        
        .privacy-label::before {
            display: none;
        }
        
        .privacy-label i {
            color: #8B1538;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .form-actions .btn {
            min-width: 120px;
        }
    </style>

    <script>
        // PHP variables for JavaScript
        const isNewApproval = <?= $is_new_approval ? 'true' : 'false' ?>;
        
        let selectedConversationType = null;
        let selectedConversationId = null;
        let selectedConversationName = '';

        function selectConversation(type, id, name) {
            selectedConversationType = type;
            selectedConversationId = id;
            selectedConversationName = name;
            
            // Update UI
            document.getElementById('selectedConversation').innerText = name;
            document.getElementById('chatCompose').style.display = 'flex';
            
            // Update active state
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-type="${type}"][data-id="${id}"]`).classList.add('active');
            
            // Mark messages as seen when conversation is selected
            markConversationMessagesSeen(type, id);
            
            // Trigger event for unread badge update
            document.dispatchEvent(new CustomEvent('conversationOpened'));
            
            fetchMessages();
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedConversationId) return;
            
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('conversation_type', selectedConversationType);
            fd.append('conversation_id', selectedConversationId);
            fd.append('message', input.value);
            
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }

        function fetchMessages() {
            if (!selectedConversationId) return;
            
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('conversation_type', selectedConversationType);
            fd.append('conversation_id', selectedConversationId);
            
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    
                    msgs.forEach(m => {
                        const sent = m.sender_type === 'client';
                        chat.innerHTML += `
                <div class='message-bubble ${sent ? 'sent' : 'received'}'>
                                ${sent ? '' : `<img src='${m.profile_image}' alt='${selectedConversationType === 'employee' ? 'Employee' : 'Attorney'}' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-right:12px;'>`}
                    <div class='message-content'>
                        <div class='message-text'><p>${m.message}</p></div>
                                    <div class='message-meta'><span>${m.sent_at}</span></div>
                    </div>
                    ${sent ? `<img src='${m.profile_image}' alt='Client' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-left:12px;'>` : ''}
                </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                })
                .catch(error => {
                    console.error('Error fetching messages:', error);
                });
        }

        // Approval Notes Modal Functions
        function closeApprovalModal() {
            const modal = document.getElementById('approvalNotesModal');
            if (modal) {
                modal.style.display = 'none';
                // Mark as shown in localStorage so it won't show again for this specific request
                localStorage.setItem('approvalModalShown_<?= $request_status['id'] ?? 0 ?>', 'true');
            }
        }

        // Show approval modal only for new approvals and only once
        function checkAndShowApprovalModal() {
            const modal = document.getElementById('approvalNotesModal');
            if (modal) {
                // Check if modal was already shown for this request
                const modalShownKey = 'approvalModalShown_<?= $request_status['id'] ?? 0 ?>';
                const alreadyShown = localStorage.getItem(modalShownKey);
                
                if (isNewApproval && !alreadyShown) {
                    modal.style.display = 'flex';
                }
            }
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('approvalNotesModal');
            if (event.target === modal) {
                closeApprovalModal();
            }
        });

        // Check and show approval modal on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAndShowApprovalModal();
        });

        // Request Access Modal Functions
        function openDocumentRequestModal() {
            document.getElementById('requestAccessModal').style.display = 'block';
            // Only blur the sidebar
            document.querySelector('.sidebar').style.filter = 'blur(8px)';
            document.querySelector('.sidebar').style.transition = 'filter 0.3s ease';
        }
        
        function closeRequestAccessModal() {
            document.getElementById('requestAccessModal').style.display = 'none';
            // Remove blur from sidebar
            document.querySelector('.sidebar').style.filter = 'none';
        }
        
        function showRequestForm() {
            document.getElementById('requestStatusView').style.display = 'none';
            document.getElementById('requestFormView').style.display = 'block';
        }
        
        function showRequestStatus() {
            document.getElementById('requestFormView').style.display = 'none';
            document.getElementById('requestStatusView').style.display = 'block';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('requestAccessModal');
            if (event.target === modal) {
                closeRequestAccessModal();
            }
        });

        // Handle form submission via AJAX
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('requestAccessForm');
            if (form) {
                // ZIP Code validation - only allow numbers
                const zipCodeField = document.getElementById('zip_code');
                if (zipCodeField) {
                    zipCodeField.addEventListener('input', function(e) {
                        // Remove any non-numeric characters
                        this.value = this.value.replace(/[^0-9]/g, '');
                        
                        // Limit to 4 digits
                        if (this.value.length > 4) {
                            this.value = this.value.substring(0, 4);
                        }
                    });
                }
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Validate required fields
                    const requiredFields = ['full_name', 'street_address', 'barangay', 'city', 'province', 'zip_code', 'sex', 'concern_description'];
                    let hasErrors = false;
                    
                    requiredFields.forEach(fieldName => {
                        const field = this.querySelector(`[name="${fieldName}"]`);
                        if (field && !field.value.trim()) {
                            field.style.borderColor = '#dc3545';
                            hasErrors = true;
                        } else if (field) {
                            field.style.borderColor = '';
                        }
                    });
                    
                    // Check privacy consent
                    const privacyConsent = this.querySelector('[name="privacy_consent"]');
                    if (!privacyConsent.checked) {
                        alert('Please agree to the Data Privacy Act to continue.');
                        hasErrors = true;
                    }
                    
                    // Check file uploads
                    const frontFile = this.querySelector('[name="valid_id_front"]').files[0];
                    const backFile = this.querySelector('[name="valid_id_back"]').files[0];
                    
                    if (!frontFile) {
                        alert('Please upload a front ID image.');
                        hasErrors = true;
                    }
                    
                    if (!backFile) {
                        alert('Please upload a back ID image.');
                        hasErrors = true;
                    }
                    
                    if (hasErrors) {
                        return;
                    }
                    
                    const formData = new FormData(this);
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    submitBtn.disabled = true;
                    
                    fetch('client_request_access.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(data => {
                        if (data.includes('success') || data.includes('submitted')) {
                            // Success - close modal and show success modal
                            closeRequestAccessModal();
                            showRequestSubmissionSuccessModal();
                        } else {
                            // Error - show error message
                            alert('Error submitting request. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error submitting request. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
                });
            }
        });


        function markConversationMessagesSeen(type, conversationId) {
            const fd = new FormData();
            fd.append('action', 'mark_conversation_messages_seen');
            fd.append('conversation_id', conversationId);
            fd.append('conversation_type', type);
            
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(result => {
                    if (result === 'success') {
                        // Update conversation list to remove notification badge
                        updateConversationList();
                    }
                })
                .catch(error => {
                    console.error('Error marking conversation messages as seen:', error);
                });
        }

        function updateConversationList() {
            // Just remove the notification badge locally without page reload
            const activeItem = document.querySelector('.conversation-item.active');
            if (activeItem) {
                const unseenBadge = activeItem.querySelector('.unseen-badge');
                if (unseenBadge) {
                    unseenBadge.remove();
                }
                activeItem.classList.remove('has-unseen');
            }
        }
        
        // Image preview functions for Request Access modal
        function showFrontPreview(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('front-image').src = e.target.result;
                    document.getElementById('front-filename').textContent = file.name;
                    document.getElementById('front-image-container').style.display = 'block';
                    
                    // Update the upload button text
                    document.querySelector('label[for="valid_id_front"] span').textContent = file.name;
                    
                    // Show success message
                    showUploadSuccessModal('Front ID uploaded successfully! You can see your image below.');
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        function showBackPreview(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('back-image').src = e.target.result;
                    document.getElementById('back-filename').textContent = file.name;
                    document.getElementById('back-image-container').style.display = 'block';
                    
                    // Update the upload button text
                    document.querySelector('label[for="valid_id_back"] span').textContent = file.name;
                    
                    // Show success message
                    showUploadSuccessModal('Back ID uploaded successfully! You can see your image below.');
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        function removeFrontImage() {
            // Clear the file input
            document.getElementById('valid_id_front').value = '';
            
            // Hide the preview container
            document.getElementById('front-image-container').style.display = 'none';
            
            // Reset the upload button text
            document.querySelector('label[for="valid_id_front"] span').textContent = 'Choose Front Image';
            
            // Clear the image source
            document.getElementById('front-image').src = '';
            document.getElementById('front-filename').textContent = '';
            
            showRemoveSuccessModal('Front ID image removed. You can now upload a different image.');
        }
        
        function removeBackImage() {
            // Clear the file input
            document.getElementById('valid_id_back').value = '';
            
            // Hide the preview container
            document.getElementById('back-image-container').style.display = 'none';
            
            // Reset the upload button text
            document.querySelector('label[for="valid_id_back"] span').textContent = 'Choose Back Image';
            
            // Clear the image source
            document.getElementById('back-image').src = '';
            document.getElementById('back-filename').textContent = '';
            
            showRemoveSuccessModal('Back ID image removed. You can now upload a different image.');
        }
        
        // Modal functions for upload and remove success messages
        function showUploadSuccessModal(message) {
            document.getElementById('uploadSuccessMessage').textContent = message;
            document.getElementById('uploadSuccessModal').style.display = 'flex';
        }
        
        function closeUploadSuccessModal() {
            document.getElementById('uploadSuccessModal').style.display = 'none';
        }
        
        function showRemoveSuccessModal(message) {
            document.getElementById('removeSuccessMessage').textContent = message;
            document.getElementById('removeSuccessModal').style.display = 'flex';
        }
        
        function closeRemoveSuccessModal() {
            document.getElementById('removeSuccessModal').style.display = 'none';
        }
        
        // Request Submission Success Modal Functions
        function showRequestSubmissionSuccessModal() {
            document.getElementById('requestSubmissionSuccessModal').style.display = 'flex';
        }
        
        function closeRequestSubmissionSuccessModal() {
            document.getElementById('requestSubmissionSuccessModal').style.display = 'none';
            // Reload page to show updated status after closing modal
            window.location.reload();
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const uploadModal = document.getElementById('uploadSuccessModal');
            const removeModal = document.getElementById('removeSuccessModal');
            const requestSubmissionModal = document.getElementById('requestSubmissionSuccessModal');
            
            if (event.target === uploadModal) {
                closeUploadSuccessModal();
            }
            if (event.target === removeModal) {
                closeRemoveSuccessModal();
            }
            if (event.target === requestSubmissionModal) {
                closeRequestSubmissionSuccessModal();
            }
        });
        
        // Profile dropdown functions removed - profile is non-clickable on this page
    </script>
<script src="assets/js/unread-messages.js?v=1761535514"></script></body>
</html> 
</html> 