<?php
require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$admin_id = $_SESSION['user_id'];

// Ensure is_seen column exists in message tables and seen_status in assignments
$conn->query("ALTER TABLE client_attorney_messages ADD COLUMN IF NOT EXISTS is_seen TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE client_employee_messages ADD COLUMN IF NOT EXISTS is_seen TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE client_attorney_assignments ADD COLUMN IF NOT EXISTS seen_status TINYINT(1) DEFAULT 0");

// Check if specific conversation is requested
$specific_conversation_id = null;
if (isset($_GET['conversation_id'])) {
    $specific_conversation_id = intval($_GET['conversation_id']);
}

// Fetch admin profile image, email, and name
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$admin_email = '';
$admin_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $admin_email = $row['email'];
    $admin_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'images/default-avatar.jpg';
}

// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $conversation_id = intval($_POST['conversation_id']);
    $msgs = [];
    
    // Fetch admin profile image
    $admin_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $admin_img = $row['profile_image'];
    if (!$admin_img || !file_exists($admin_img)) $admin_img = 'images/default-avatar.jpg';
    
    // Fetch client profile image
    $stmt = $conn->prepare("SELECT caa.client_id FROM client_attorney_assignments caa JOIN client_attorney_conversations cac ON caa.id = cac.assignment_id WHERE cac.id = ?");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $client_id = $res->fetch_assoc()['client_id'];
    
    $client_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'images/default-avatar.jpg';
    
    // Fetch messages
    $stmt = $conn->prepare("SELECT sender_id, sender_type, message, sent_at, is_seen FROM client_attorney_messages WHERE conversation_id = ? ORDER BY sent_at ASC");
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sent = $row['sender_type'] === 'attorney';
        $row['profile_image'] = $sent ? $admin_img : $client_img;
        $msgs[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}

// Handle AJAX get conversations
if (isset($_GET['action']) && $_GET['action'] === 'get_conversations') {
    header('Content-Type: application/json');
    
    $stmt = $conn->prepare("
        SELECT cac.id, cac.conversation_status,
               u.name as client_name, u.profile_image as client_image,
               crf.request_id, crf.full_name, crf.address,
               caa.assigned_at,
               emp.name as employee_name,
               u.id as client_id,
               (SELECT COUNT(*) FROM client_attorney_messages cam 
                WHERE cam.conversation_id = cac.id 
                AND cam.sender_type = 'client' 
                AND cam.is_seen = 0) as unseen_count
        FROM client_attorney_assignments caa
        JOIN client_attorney_conversations cac ON caa.id = cac.assignment_id
        JOIN user_form u ON caa.client_id = u.id
        JOIN client_employee_conversations cec ON caa.conversation_id = cec.id
        JOIN client_request_form crf ON cec.request_form_id = crf.id
        JOIN user_form emp ON caa.employee_id = emp.id
        WHERE caa.attorney_id = ? AND cac.conversation_status = 'Active'
        ORDER BY caa.assigned_at DESC
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $conversations = [];
    while ($row = $res->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    echo json_encode($conversations);
    exit();
}



// Handle AJAX mark individual messages as seen when conversation is selected
if (isset($_POST['action']) && $_POST['action'] === 'mark_conversation_messages_seen') {
    $conversation_id = intval($_POST['conversation_id']);
    
    // Mark all client messages in this conversation as seen
    $stmt = $conn->prepare("UPDATE client_attorney_messages SET is_seen = 1 WHERE conversation_id = ? AND sender_type = 'client' AND is_seen = 0");
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    
    // Also update the assignment seen status to 'Seen'
    $stmt2 = $conn->prepare("UPDATE client_attorney_assignments SET seen_status = 'Seen' WHERE id = ? AND attorney_id = ?");
    $stmt2->bind_param('ii', $conversation_id, $admin_id);
    $stmt2->execute();
    
    $result = $stmt->error ? 'error' : 'success';
    
    if ($result === 'success' && $stmt2->affected_rows > 0) {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $_SESSION['user_id'],
            $_SESSION['name'],
            'admin',
            'Auto Mark as Seen',
            'Communication',
            "Automatically marked client conversation as seen - Assignment ID: $conversation_id",
            'success',
            'low'
        );
        
        // Notify client that admin has seen their conversation
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get client info
            $stmt_client = $conn->prepare("SELECT client_id FROM client_attorney_assignments WHERE id = ? AND attorney_id = ?");
            $stmt_client->bind_param('ii', $conversation_id, $admin_id);
            $stmt_client->execute();
            $client_id = $stmt_client->get_result()->fetch_assoc()['client_id'];
            
            if ($client_id) {
                $nTitle = 'Message Seen';
                $nMsg = "Your attorney has seen your conversation.";
                $userType = 'client';
                $notificationType = 'info';
                
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                $stmtN->bind_param('issss', $client_id, $userType, $nTitle, $nMsg, $notificationType);
                $stmtN->execute();
            }
        }
    }
    
    echo $result;
    exit();
}

// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $conversation_id = intval($_POST['conversation_id']);
    $msg = $_POST['message'];
    
    $stmt = $conn->prepare("INSERT INTO client_attorney_messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, 'attorney', ?)");
    $stmt->bind_param('iis', $conversation_id, $admin_id, $msg);
    $stmt->execute();
    
    $result = $stmt->affected_rows > 0 ? 'success' : 'error';
    
    if ($result === 'success') {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $admin_name,
            'admin',
            'Message Send',
            'Communication',
            "Sent message in attorney conversation ID: $conversation_id",
            'success',
            'low'
        );
        
        // Notify client about the new message
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get client info from conversation
            $stmt = $conn->prepare("SELECT caa.client_id FROM client_attorney_assignments caa JOIN client_attorney_conversations cac ON caa.id = cac.assignment_id WHERE cac.id = ?");
            $stmt->bind_param('i', $conversation_id);
            $stmt->execute();
            $client_id = $stmt->get_result()->fetch_assoc()['client_id'];
            
            if ($client_id) {
                // Get admin name for notification
                $stmt_admin = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                $stmt_admin->bind_param('i', $admin_id);
                $stmt_admin->execute();
                $admin_name = $stmt_admin->get_result()->fetch_assoc()['name'];
                
                $nTitle = 'New Message Received';
                $nMsg = "You received a new message from admin: $admin_name - " . substr($msg, 0, 50) . (strlen($msg) > 50 ? '...' : '');
                $userType = 'client';
                $notificationType = 'info';
                
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                $stmtN->bind_param('issss', $client_id, $userType, $nTitle, $nMsg, $notificationType);
                $stmtN->execute();
            }
        }
    }
    
    echo $result;
    exit();
}

// Fetch active conversations for this admin
$stmt = $conn->prepare("
    SELECT cac.id, cac.conversation_status,
           u.name as client_name, u.profile_image as client_image,
           crf.request_id, crf.full_name, crf.address,
           caa.assigned_at,
           emp.name as employee_name,
           u.id as client_id,
           (SELECT COUNT(*) FROM client_attorney_messages cam 
            WHERE cam.conversation_id = cac.id 
            AND cam.sender_type = 'client' 
            AND cam.is_seen = 0) as unseen_count
    FROM client_attorney_assignments caa
    JOIN client_attorney_conversations cac ON caa.id = cac.assignment_id
    JOIN user_form u ON caa.client_id = u.id
    JOIN client_employee_conversations cec ON caa.conversation_id = cec.id
    JOIN client_request_form crf ON cec.request_form_id = crf.id
    JOIN user_form emp ON caa.employee_id = emp.id
    WHERE caa.attorney_id = ? AND cac.conversation_status = 'Active'
    ORDER BY caa.assigned_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$conversations = [];
while ($row = $res->fetch_assoc()) {
    $img = $row['client_image'];
    if (!$img || !file_exists($img)) {
        $img = 'images/default-avatar.jpg';
    }
    $row['client_image'] = $img;
    $conversations[] = $row;
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
    <!-- Hamburger Menu Button -->
    <button class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="admin_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="admin_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generations</span></a></li>
            <li><a href="admin_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="admin_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="admin_usermanagement.php"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
            <li><a href="admin_managecases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="admin_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="admin_messages.php" class="active"><i class="fas fa-comments"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Messages';
        $page_subtitle = 'Communicate with assigned clients';
        include 'components/profile_header.php'; 
        ?>

        <div class="chat-container">
            <!-- Client List -->
            <div class="client-list">
                <h3>Assigned Clients</h3>
                <ul id="clientList">
                    <?php if (empty($conversations)): ?>
                        <li class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No assigned clients</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <li class="client-item <?= $conv['unseen_count'] > 0 ? 'has-unseen' : '' ?>" data-id="<?= $conv['id'] ?>" data-client-id="<?= $conv['client_id'] ?>" onclick="selectClient(<?= $conv['id'] ?>, '<?= htmlspecialchars($conv['client_name']) ?>', <?= $conv['client_id'] ?>)">
                                <img src='<?= htmlspecialchars($conv['client_image']) ?>' alt='Client' style='width:28px;height:28px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;' onerror="this.src='images/default-avatar.jpg'">
                                <div class="client-info">
                                    <span><?= htmlspecialchars($conv['client_name']) ?></span>
                                    <small>Request ID: <?= htmlspecialchars($conv['request_id']) ?></small>
                                    <div class="status-badge assigned">
                                        <i class="fas fa-user-tie"></i>
                                        Assigned by <?= htmlspecialchars($conv['employee_name']) ?>
                                    </div>
                                </div>
                                <?php if ($conv['unseen_count'] > 0): ?>
                                    <div class="unseen-badge">
                                        <span><?= $conv['unseen_count'] ?></span>
                                    </div>
                                <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <h2 id="selectedClient">Select a client</h2>
                    <div class="chat-actions" id="chatActions" style="display:none;">
                        <button class="btn btn-secondary" onclick="openCreateCaseModal()">
                            <i class="fas fa-plus"></i>
                            Create Case
                        </button>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <p style="color:#888;text-align:center;">Select a client to start conversation.</p>
                </div>
                <div class="chat-compose" id="chatCompose" style="display:none;">
                    <textarea id="messageInput" placeholder="Type your message..."></textarea>
                    <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
        
        <!-- Create Case Modal -->
        <div id="createCaseModal" class="modal" style="display:none;">
            <div class="modal-content case-modal">
                <div class="modal-header case-header">
                    <div class="header-content">
                        <div class="case-icon">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="header-text">
                            <h2>Create New Case</h2>
                            <p>Add a new case to your portfolio</p>
                        </div>
                    </div>
                    <span class="close" onclick="closeCreateCaseModal()">&times;</span>
                </div>
                <div class="modal-body case-body">
                    <form id="createCaseForm">
                        <input type="hidden" id="caseClientId" name="client_id">
                        <input type="hidden" name="attorney_id" value="<?= $admin_id ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="caseClient">
                                    <i class="fas fa-user"></i>
                                    CLIENT
                                </label>
                                <select id="caseClient" name="client_id" required>
                                    <option value="">Select Client</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="caseType">
                                    <i class="fas fa-gem"></i>
                                    CASE TYPE
                                </label>
                                <select id="caseType" name="case_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Criminal">Criminal</option>
                                    <option value="Civil">Civil</option>
                                    <option value="Family">Family</option>
                                    <option value="Corporate">Corporate</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="caseTitle">
                                <i class="fas fa-file-alt"></i>
                                CASE TITLE
                            </label>
                            <input type="text" id="caseTitle" name="title" required placeholder="Enter case title">
                        </div>
                        
                        <div class="form-group">
                            <label for="caseDescription">
                                <i class="fas fa-list"></i>
                                SUMMARY
                            </label>
                            <textarea id="caseDescription" name="description" rows="4" required placeholder="Provide a brief summary of the case"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-cancel" onclick="closeCreateCaseModal()">
                                <i class="fas fa-times"></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-create">
                                <i class="fas fa-plus"></i>
                                Create Case
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
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

        .client-list { 
            width: 300px; 
            background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%); 
            border-right: 2px solid rgba(93, 14, 38, 0.08); 
            padding: 24px 0; 
            position: relative;
            overflow: hidden;
        }
        
        .client-list h3 { 
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
        
        .client-list ul { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        
        .client-item { 
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
        
        .client-item:hover { 
            background: linear-gradient(135deg, #e3f2fd 0%, #f3f8ff 100%); 
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.1);
        }
        
        .client-item.active { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .client-info {
            flex: 1;
        }
        
        .client-info span {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-color);
            display: block;
            margin-bottom: 1px;
        }
        
        .client-item.active .client-info span {
            color: white;
        }
        
        .client-info small {
            color: #666;
            font-size: 0.65rem;
            display: block;
            margin-bottom: 2px;
        }
        
        .client-item.active .client-info small {
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
        
        .status-badge.assigned {
            background: #cce5ff;
            color: #004085;
        }
        
        .client-item.active .status-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
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
        
        .message-bubble.unseen {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
            border: 2px solid #f39c12 !important;
            animation: pulseUnseen 2s infinite;
        }
        
        .unseen-indicator {
            background: #e74c3c;
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 8px;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @keyframes pulseUnseen {
            0% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(243, 156, 18, 0); }
            100% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0); }
        }
        
        .client-item.has-unseen {
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
        
        .chat-actions .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            border: none;
        }
        
        .chat-actions .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #A52A2A 100%);
            color: white;
        }
        
        .chat-actions .btn-primary:hover {
            background: linear-gradient(135deg, #660000 0%, #8B0000 100%);
            transform: translateY(-2px);
        }
        
        .chat-actions .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            cursor: not-allowed;
        }
        
        .chat-actions .btn-success:hover {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            transform: none;
        }
        
        .chat-actions .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }
        
        .chat-actions .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            border-left: 4px solid;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification-success {
            border-left-color: #28a745;
            color: #28a745;
        }
        
        .notification-error {
            border-left-color: #dc3545;
            color: #dc3545;
        }
        
        .notification-info {
            border-left-color: #17a2b8;
            color: #17a2b8;
        }
        
        .notification i {
            font-size: 1.2rem;
        }
        
        .notification span {
            font-weight: 600;
            font-size: 0.9rem;
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

        @media (max-width: 900px) {
            .chat-container { 
                flex-direction: column;
                height: auto; 
                margin: 20px 10px;
            }
            .client-list { 
                width: 100%;
                border-right: none; 
                border-bottom: 1px solid #e9ecef; 
                padding: 20px 0;
            }
            .client-item {
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
        
        /* Create Case Modal Styles */
        .chat-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 3%;
            z-index: 9999;
        }
        
        .case-modal {
            background: white;
            border-radius: 20px;
            padding: 0;
            max-width: 750px;
            width: 90%;
            height: auto;
            max-height: 50vh;
            overflow: visible;
            box-shadow: 0 30px 100px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            margin: 0 auto;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .case-header {
            background: linear-gradient(135deg, #800000 0%, #A52A2A 100%);
            color: white;
            padding: 20px 24px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .case-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .header-text h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .header-text p {
            margin: 4px 0 0 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
            background: none;
            border: none;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        .case-body {
            padding: 16px;
            overflow: visible;
            flex: 1;
            background: #fafafa;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            font-weight: 700;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group label i {
            color: #666;
            font-size: 0.9rem;
            width: 16px;
            text-align: center;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            box-sizing: border-box;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 14px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 45px;
            cursor: pointer;
            background-color: white;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
            background-color: #1976d2;
            color: white;
        }
        
        .form-group select option {
            background-color: white;
            color: black;
            padding: 8px 12px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            line-height: 1.5;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #eee;
            background: white;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .btn-create {
            background: linear-gradient(135deg, #800000 0%, #A52A2A 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .btn-create:hover {
            background: linear-gradient(135deg, #660000 0%, #8B0000 100%);
            transform: translateY(-1px);
        }
        
        /* Responsive modal */
        @media (max-width: 768px) {
            .modal {
                padding-top: 2%;
            }
            
            .case-modal {
                max-width: 95%;
                max-height: 55vh;
                margin: 0 auto;
            }
            
            .case-header {
                padding: 16px 20px;
            }
            
            .case-body {
                padding: 14px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 16px;
            }
            
            .form-group {
                margin-bottom: 16px;
            }
        }
    </style>

    <script>
        let selectedConversationId = null;
        let selectedClientName = '';
        let selectedClientId = null;

        function selectClient(conversationId, clientName, clientId) {
            selectedConversationId = conversationId;
            selectedClientName = clientName;
            selectedClientId = clientId;
            
            // Update UI
            document.getElementById('selectedClient').innerText = clientName;
            document.getElementById('chatCompose').style.display = 'flex';
            document.getElementById('chatActions').style.display = 'flex';
            
            // Update active state
            document.querySelectorAll('.client-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-id="${conversationId}"]`).classList.add('active');
            
            // Automatically mark messages as seen when conversation is selected
            markConversationMessagesSeen(conversationId);
            
            // Trigger event for unread badge update
            document.dispatchEvent(new CustomEvent('conversationOpened'));
            
            fetchMessages();
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedConversationId) return;
            
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('conversation_id', selectedConversationId);
            fd.append('message', input.value);
            
            fetch('admin_messages.php', { method: 'POST', body: fd })
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
            fd.append('conversation_id', selectedConversationId);
            
            fetch('admin_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    
                    let hasUnseenMessages = false;
                    
                    msgs.forEach(m => {
                        const sent = m.sender_type === 'attorney';
                        const isUnseen = !sent && (m.is_seen == 0 || m.is_seen == false);
                        if (isUnseen) hasUnseenMessages = true;
                        
                        chat.innerHTML += `
                            <div class='message-bubble ${sent ? 'sent' : 'received'} ${isUnseen ? 'unseen' : ''}'>
                                ${sent ? '' : `<img src='${m.profile_image || 'images/default-avatar.jpg'}' alt='Client' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-right:12px;' onerror=\"this.src='images/default-avatar.jpg'\">`}
                                <div class='message-content'>
                                    <div class='message-text'><p>${m.message}</p></div>
                                    <div class='message-meta'>
                                        <span>${m.sent_at}</span>
                                        ${isUnseen ? '<span class="unseen-indicator">NEW</span>' : ''}
                                    </div>
                                </div>
                                ${sent ? `<img src='${m.profile_image || 'images/default-avatar.jpg'}' alt='Admin' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-left:12px;' onerror=\"this.src='images/default-avatar.jpg'\">` : ''}
                            </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                    
                    // Don't auto-mark messages as seen - let admin manually mark them
                    // This ensures proper tracking of when messages are actually viewed
                })
                .catch(error => {
                    console.error('Error fetching messages:', error);
                });
        }
        

        function markConversationMessagesSeen(conversationId) {
            const fd = new FormData();
            fd.append('action', 'mark_conversation_messages_seen');
            fd.append('conversation_id', conversationId);
            
            fetch('admin_messages.php', { method: 'POST', body: fd })
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
        
        
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 3000);
        }

        function updateConversationList() {
            // Fetch updated conversation list
            fetch('admin_messages.php?action=get_conversations')
                .then(r => r.json())
                .then(conversations => {
                    const clientList = document.getElementById('clientList');
                    clientList.innerHTML = '';
                    
                    if (conversations.length === 0) {
                        clientList.innerHTML = `
                            <li class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No assigned clients</p>
                            </li>
                        `;
                        return;
                    }
                    
                    conversations.forEach(conv => {
                        const hasUnseen = conv.unseen_count > 0;
                        clientList.innerHTML += `
                            <li class="client-item ${hasUnseen ? 'has-unseen' : ''}" data-id="${conv.id}" data-client-id="${conv.client_id}" onclick="selectClient(${conv.id}, '${conv.client_name}', ${conv.client_id})">
                                <img src='${conv.client_image || 'images/default-avatar.jpg'}' alt='Client' style='width:28px;height:28px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;' onerror="this.src='images/default-avatar.jpg'">
                                <div class="client-info">
                                    <span>${conv.client_name}</span>
                                    <small>Request ID: ${conv.request_id}</small>
                                    <div class="status-badge assigned">
                                        <i class="fas fa-user-tie"></i>
                                        Assigned by ${conv.employee_name}
                                    </div>
                                </div>
                                ${hasUnseen ? `<div class="unseen-badge"><span>${conv.unseen_count}</span></div>` : ''}
                            </li>
                        `;
                    });
                })
                .catch(error => {
                    console.error('Error updating conversation list:', error);
                });
        }
        
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 3000);
        }

        // Profile Dropdown Functions
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        function editProfile() {
            alert('Profile editing functionality will be implemented.');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('img') && !event.target.closest('.profile-dropdown')) {
                const dropdowns = document.getElementsByClassName('profile-dropdown-content');
                for (let dropdown of dropdowns) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                }
            }
        }

        // Auto-select conversation if specified in URL
        <?php if ($specific_conversation_id): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const conversationItem = document.querySelector('[data-id="<?= $specific_conversation_id ?>"]');
            if (conversationItem) {
                const clientName = conversationItem.querySelector('span').textContent;
                selectClient(<?= $specific_conversation_id ?>, clientName);
            }
        });
        <?php endif; ?>
        
        // Create Case Modal Functions
        function openCreateCaseModal() {
            if (!selectedConversationId || !selectedClientName || !selectedClientId) {
                alert('Please select a client first.');
                return;
            }
            
            // Populate client dropdown with selected client
            const clientSelect = document.getElementById('caseClient');
            clientSelect.innerHTML = '<option value="">Select Client</option>';
            
            // Add the selected client as an option
            const option = document.createElement('option');
            option.value = selectedClientId; // Using actual client ID
            option.textContent = selectedClientName;
            option.selected = true;
            clientSelect.appendChild(option);
            
            document.getElementById('createCaseModal').style.display = 'flex';
        }
        
        function closeCreateCaseModal() {
            document.getElementById('createCaseModal').style.display = 'none';
            document.getElementById('createCaseForm').reset();
        }
        
        // Handle Create Case Form Submission
        document.getElementById('createCaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_case');
            
            fetch('admin_managecases.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                if (result === 'success') {
                    alert('Case created successfully!');
                    closeCreateCaseModal();
                } else {
                    alert('Error creating case. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating case. Please try again.');
            });
        });
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html>