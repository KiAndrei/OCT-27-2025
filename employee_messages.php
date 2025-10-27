<?php
require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';

// Fetch pending requests count for notification badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM client_request_form WHERE status = 'Pending'");
$stmt->execute();
$pending_requests_count = $stmt->get_result()->fetch_row()[0];
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$employee_id = $_SESSION['user_id'];

// Check if specific conversation is requested
$specific_conversation_id = null;
if (isset($_GET['conversation_id'])) {
    $specific_conversation_id = intval($_GET['conversation_id']);
}

// Fetch employee profile image, email, and name
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$employee_email = '';
$employee_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $employee_email = $row['email'];
    $employee_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'images/default-avatar.jpg';
}

// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $conversation_id = intval($_POST['conversation_id']);
    $msgs = [];
    
    // Fetch employee profile image
    $employee_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $employee_img = $row['profile_image'];
    if (!$employee_img || !file_exists($employee_img)) $employee_img = 'images/default-avatar.jpg';
    
    // Fetch client profile image
    $stmt = $conn->prepare("SELECT client_id FROM client_employee_conversations WHERE id = ?");
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
    $stmt = $conn->prepare("SELECT sender_id, sender_type, message, sent_at FROM client_employee_messages WHERE conversation_id = ? ORDER BY sent_at ASC");
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sent = $row['sender_type'] === 'employee';
        $row['profile_image'] = $sent ? $employee_img : $client_img;
        $msgs[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}

// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $conversation_id = intval($_POST['conversation_id']);
    $msg = $_POST['message'];
    
    $stmt = $conn->prepare("INSERT INTO client_employee_messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, 'employee', ?)");
    $stmt->bind_param('iis', $conversation_id, $employee_id, $msg);
    $stmt->execute();
    
    $result = $stmt->affected_rows > 0 ? 'success' : 'error';
    
    if ($result === 'success') {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $employee_id,
            $employee_name,
            'employee',
            'Message Send',
            'Communication',
            "Sent message in conversation ID: $conversation_id",
            'success',
            'low'
        );
        
        // Notify client about the new message
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get client info from conversation
            $stmt = $conn->prepare("SELECT client_id FROM client_employee_conversations WHERE id = ?");
            $stmt->bind_param('i', $conversation_id);
            $stmt->execute();
            $client_id = $stmt->get_result()->fetch_assoc()['client_id'];
            
            if ($client_id) {
                // Get employee name for notification
                $stmt_employee = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                $stmt_employee->bind_param('i', $employee_id);
                $stmt_employee->execute();
                $employee_name = $stmt_employee->get_result()->fetch_assoc()['name'];
                
                $nTitle = 'New Message Received';
                $nMsg = "You received a new message from employee: $employee_name - " . substr($msg, 0, 50) . (strlen($msg) > 50 ? '...' : '');
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

// Handle AJAX mark individual messages as seen when conversation is selected
if (isset($_POST['action']) && $_POST['action'] === 'mark_conversation_messages_seen') {
    $conversation_id = intval($_POST['conversation_id']);
    
    // Mark all client messages in this conversation as seen
    $stmt = $conn->prepare("UPDATE client_employee_messages SET is_seen = 1 WHERE conversation_id = ? AND sender_type = 'client' AND is_seen = 0");
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    
    $result = $stmt->error ? 'error' : 'success';
    echo $result;
    exit();
}

// Handle AJAX get conversations for updating list
if (isset($_GET['action']) && $_GET['action'] === 'get_conversations') {
    header('Content-Type: application/json');
    
    // Fetch active conversations for this employee
    $stmt = $conn->prepare("
        SELECT cec.id, cec.conversation_status, cec.concern_identified, cec.concern_description,
               u.name as client_name, u.profile_image as client_image,
               crf.request_id, crf.full_name, crf.address,
               (SELECT COUNT(*) FROM client_employee_messages cem 
                WHERE cem.conversation_id = cec.id 
                AND cem.sender_type = 'client' 
                AND cem.is_seen = 0) as unseen_count
        FROM client_employee_conversations cec
        JOIN user_form u ON cec.client_id = u.id
        JOIN client_request_form crf ON cec.request_form_id = crf.id
        WHERE cec.employee_id = ?
        ORDER BY cec.created_at DESC
    ");
    $stmt->bind_param("i", $employee_id);
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
    
    echo json_encode($conversations);
    exit();
}

// Fetch active conversations for this employee
$stmt = $conn->prepare("
    SELECT cec.id, cec.conversation_status, cec.concern_identified, cec.concern_description,
           u.name as client_name, u.profile_image as client_image,
           crf.request_id, crf.full_name, crf.address,
           (SELECT COUNT(*) FROM client_employee_messages cem 
            WHERE cem.conversation_id = cec.id 
            AND cem.sender_type = 'client' 
            AND cem.is_seen = 0) as unseen_count
    FROM client_employee_conversations cec
    JOIN user_form u ON cec.client_id = u.id
    JOIN client_request_form crf ON cec.request_form_id = crf.id
    WHERE cec.employee_id = ?
    ORDER BY cec.created_at DESC
");
$stmt->bind_param("i", $employee_id);
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

// If no conversations found, try to create them from approved requests
if (empty($conversations)) {
    // Get approved requests for this employee
    $stmt = $conn->prepare("
        SELECT crf.id as request_id, crf.client_id, crf.request_id as request_number
        FROM client_request_form crf
        JOIN employee_request_reviews err ON crf.id = err.request_form_id
        WHERE err.employee_id = ? AND err.action = 'Approved' AND crf.status = 'Approved'
        ORDER BY err.reviewed_at DESC
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($request = $res->fetch_assoc()) {
        // Check if conversation already exists
        $stmt2 = $conn->prepare("SELECT id FROM client_employee_conversations WHERE request_form_id = ? AND employee_id = ?");
        $stmt2->bind_param("ii", $request['request_id'], $employee_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        
        if (!$res2->fetch_assoc()) {
            // Create conversation
            $stmt3 = $conn->prepare("INSERT INTO client_employee_conversations (request_form_id, client_id, employee_id, conversation_status) VALUES (?, ?, ?, 'Active')");
            $stmt3->bind_param("iii", $request['request_id'], $request['client_id'], $employee_id);
            $stmt3->execute();
        }
    }
    
    // Fetch conversations again
    $stmt = $conn->prepare("
        SELECT cec.id, cec.conversation_status, cec.concern_identified, cec.concern_description,
               u.name as client_name, u.profile_image as client_image,
               crf.request_id, crf.full_name, crf.address
        FROM client_employee_conversations cec
        JOIN user_form u ON cec.client_id = u.id
        JOIN client_request_form crf ON cec.request_form_id = crf.id
        WHERE cec.employee_id = ?
        ORDER BY cec.created_at DESC
    ");
    $stmt->bind_param("i", $employee_id);
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.parentElement;
                    submenu.classList.toggle('open');
                });
            });
        });
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employee_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="employee_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-file-alt"></i><span>Document Generations</span><i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="employee_document_generation.php"><i class="fas fa-file-plus"></i><span>Generate Documents</span></a></li>
                    <li><a href="employee_send_files.php"><i class="fas fa-paper-plane"></i><span>Send Files</span></a></li>
                </ul>
            </li>
            <li><a href="employee_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="employee_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="employee_request_management.php"><i class="fas fa-clipboard-check"></i><span>Request Review</span><?php if ($pending_requests_count > 0): ?><span class="notification-badge"><?= $pending_requests_count ?></span><?php endif; ?></a></li>
            <li><a href="employee_messages.php" class="active"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Messages';
        $page_subtitle = 'Communicate with approved clients';
        include 'components/profile_header.php'; 
        ?>

        <div class="chat-container">
            <!-- Client List -->
            <div class="client-list">
                <h3>Active Conversations</h3>
                <ul id="clientList">
                    <?php if (empty($conversations)): ?>
                        <li class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No active conversations</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <li class="client-item <?= $conv['unseen_count'] > 0 ? 'has-unseen' : '' ?>" data-id="<?= $conv['id'] ?>" onclick="selectClient(<?= $conv['id'] ?>, '<?= htmlspecialchars($conv['client_name']) ?>')">
                                <img src='<?= htmlspecialchars($conv['client_image']) ?>' alt='Client' style='width:32px;height:32px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>
                                <div class="client-info">
                                    <span><?= htmlspecialchars($conv['client_name']) ?></span>
                                    <small>Request ID: <?= htmlspecialchars($conv['request_id']) ?></small>
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
            gap: 12px; 
            padding: 10px 16px; 
            cursor: pointer; 
            border-radius: 12px; 
            transition: all 0.3s ease; 
            margin: 0 12px 6px 12px;
            border: 1px solid transparent;
            position: relative;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
        
        .client-item:hover { 
            background: linear-gradient(135deg, #e3f2fd 0%, #f3f8ff 100%); 
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.1);
        }
        
        .client-item.active { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            color: white;
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.2);
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
            margin-bottom: 2px;
        }
        
        .client-item.active .client-info span {
            color: white;
        }
        
        .client-info small {
            color: #666;
            font-size: 0.75rem;
            display: block;
            margin-bottom: 4px;
        }
        
        .client-item.active .client-info small {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .status-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
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
        
        .client-item.active .status-badge.identified {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .client-item.active .status-badge.pending {
            background: rgba(255, 255, 255, 0.2);
            color: white;
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
            transform: translateY(-1px);
            box-shadow: 
                0 4px 12px rgba(93, 14, 38, 0.25),
                0 2px 6px rgba(93, 14, 38, 0.15);
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

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #999;
        }

        .close-modal:hover {
            color: var(--primary-color);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(93, 14, 38, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
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
                margin: 0 8px 4px 8px;
                padding: 8px 12px;
            }
            .chat-messages {
                padding: 20px;
            }
            .chat-compose {
                padding: 20px;
            }
        }

        /* Notification Badge Styles */
        .notification-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: 8px;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
            animation: pulseBadge 2s infinite;
        }

        .notification-badge:empty {
            display: none;
        }

        @keyframes pulseBadge {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
    </style>

    <script>
        let selectedConversationId = null;
        let selectedClientName = '';

        function selectClient(conversationId, clientName) {
            selectedConversationId = conversationId;
            selectedClientName = clientName;
            
            // Update UI
            document.getElementById('selectedClient').innerText = clientName;
            document.getElementById('chatCompose').style.display = 'flex';
            
            // Update active state
            document.querySelectorAll('.client-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-id="${conversationId}"]`).classList.add('active');
            
            // Mark messages as seen when conversation is selected
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
            
            fetch('employee_messages.php', { method: 'POST', body: fd })
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
            
            fetch('employee_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    
                    msgs.forEach(m => {
                        const sent = m.sender_type === 'employee';
                        chat.innerHTML += `
                            <div class='message-bubble ${sent ? 'sent' : 'received'}'>
                                ${sent ? '' : `<img src='${m.profile_image}' alt='Client' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-right:12px;'>`}
                                <div class='message-content'>
                                    <div class='message-text'><p>${m.message}</p></div>
                                    <div class='message-meta'><span>${m.sent_at}</span></div>
                                </div>
                                ${sent ? `<img src='${m.profile_image}' alt='Employee' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-left:12px;'>` : ''}
                            </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                })
                .catch(error => {
                    console.error('Error fetching messages:', error);
                });
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

        function markConversationMessagesSeen(conversationId) {
            const fd = new FormData();
            fd.append('action', 'mark_conversation_messages_seen');
            fd.append('conversation_id', conversationId);
            
            fetch('employee_messages.php', { method: 'POST', body: fd })
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
            // Fetch updated conversation list
            fetch('employee_messages.php?action=get_conversations')
                .then(r => r.json())
                .then(conversations => {
                    const clientList = document.getElementById('clientList');
                    clientList.innerHTML = '';
                    
                    if (conversations.length === 0) {
                        clientList.innerHTML = `
                            <li class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No active conversations</p>
                            </li>
                        `;
                        return;
                    }
                    
                    conversations.forEach(conv => {
                        const hasUnseen = conv.unseen_count > 0;
                        clientList.innerHTML += `
                            <li class="client-item ${hasUnseen ? 'has-unseen' : ''}" data-id="${conv.id}" onclick="selectClient(${conv.id}, '${conv.client_name}')">
                                <img src='${conv.client_image}' alt='Client' style='width:32px;height:32px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>
                                <div class="client-info">
                                    <span>${conv.client_name}</span>
                                    <small>Request ID: ${conv.request_id}</small>
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
        
        // Auto-select conversation if specified in URL
        <?php if ($specific_conversation_id): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const conversationItem = document.querySelector('[data-id="<?= $specific_conversation_id ?>"]');
            if (conversationItem) {
                const clientName = conversationItem.querySelector('span').textContent;
                const concernIdentified = conversationItem.getAttribute('data-concern-identified') === 'true';
                selectClient(<?= $specific_conversation_id ?>, clientName, concernIdentified);
            }
        });
        <?php endif; ?>
    </script>
<script src="assets/js/unread-messages.js?v=1761535513"></script></body>
</html>
