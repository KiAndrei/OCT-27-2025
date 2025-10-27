<?php
require_once 'session_manager.php';
validateUserAccess('attorney');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$attorney_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image FROM user_form WHERE id=$attorney_id");
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }
// Fetch all clients for dropdown
$clients = [];
$stmt = $conn->prepare("SELECT id, name FROM user_form WHERE user_type='client'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;
// Ensure tables for document request workflow
$conn->query("CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    attorney_id INT NOT NULL,
    client_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE NULL,
    status ENUM('Requested','Submitted','Reviewed','Approved','Rejected','Called') DEFAULT 'Requested',
    attorney_comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES attorney_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (attorney_id) REFERENCES user_form(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES user_form(id) ON DELETE CASCADE
);");

// Add attorney_comment column if it doesn't exist
$conn->query("ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS attorney_comment TEXT NULL AFTER status");
$conn->query("CREATE TABLE IF NOT EXISTS document_request_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    client_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES user_form(id) ON DELETE CASCADE
);");

// Handle AJAX add case
if (isset($_POST['action']) && $_POST['action'] === 'add_case') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $client_id = intval($_POST['client_id']);
    $case_type = $_POST['case_type'];
    $status = 'Pending'; // Automatically set to Pending
    $next_hearing = null; // No next hearing field anymore
    
    // Debug: Check if attorney_id is set
    if (!isset($attorney_id) || empty($attorney_id)) {
        echo 'error: Attorney ID not found. Session user_id: ' . ($_SESSION['user_id'] ?? 'not set');
        exit();
    }
    
    // Debug: Log the values being inserted
    error_log("Case creation attempt - Title: $title, Client ID: $client_id, Attorney ID: $attorney_id, Case Type: $case_type");
    
    $stmt = $conn->prepare("INSERT INTO attorney_cases (title, description, attorney_id, client_id, case_type, status, next_hearing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo 'error: Failed to prepare statement - ' . $conn->error;
        exit();
    }
    
    $stmt->bind_param('ssiisss', $title, $description, $attorney_id, $client_id, $case_type, $status, $next_hearing);
    $result = $stmt->execute();
    
    if (!$result) {
        echo 'error: Failed to execute statement - ' . $stmt->error;
        exit();
    }

    // Notify client about the new case
    if ($stmt->affected_rows > 0) {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $attorney_id,
            $_SESSION['attorney_name'],
            'attorney',
            'Case Create',
            'Case Management',
            "Created new case: $title (Type: $case_type, Status: Pending, Client ID: $client_id)",
            'success',
            'medium'
        );
        
        // Get attorney name for notification
        $stmt_attorney = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
        $stmt_attorney->bind_param('i', $attorney_id);
        $stmt_attorney->execute();
        $attorney_name = $stmt_attorney->get_result()->fetch_assoc()['name'];
        
        $notif_msg = "A new case has been created for you by attorney: $attorney_name - $title";
        // Also write to notifications table if present
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $titleN = 'New Case Assigned';
            $userType = 'client';
            $notificationType = 'info';
            $stmtN->bind_param('issss', $client_id, $userType, $titleN, $notif_msg, $notificationType);
            $stmtN->execute();
        }
    }

    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle creating a document request for a case
if (isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $case_id = intval($_POST['case_id']);
    $client_id = intval($_POST['client_id']);
    $titleR = trim($_POST['title']);
    $descR = trim($_POST['description'] ?? '');
    $dueR = empty($_POST['due_date']) ? null : $_POST['due_date'];
    $stmt = $conn->prepare("INSERT INTO document_requests (case_id, attorney_id, client_id, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiisss', $case_id, $attorney_id, $client_id, $titleR, $descR, $dueR);
    $ok = $stmt->execute();
    if ($ok) {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $attorney_id,
            $_SESSION['attorney_name'],
            'attorney',
            'Document Request Create',
            'Document Management',
            "Created document request: $titleR for case ID: $case_id (Client ID: $client_id)",
            'success',
            'medium'
        );
        
        // Notify client
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'New Document Request';
            $nMsg = "Your attorney requested: " . $titleR . (empty($dueR) ? '' : " (Due: $dueR)");
            $userType = 'warning';
            $notificationType = 'warning';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $client_id, $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
    }
    // Clean response with no extra whitespace
    header('Content-Type: text/plain');
    echo $ok ? 'success' : 'error';
    exit();
}
// Handle fetching requests for a case
if (isset($_POST['action']) && $_POST['action'] === 'list_requests') {
    $case_id = intval($_POST['case_id']);
    $stmt = $conn->prepare("SELECT dr.*, (
        SELECT COUNT(*) FROM document_request_files f WHERE f.request_id = dr.id
    ) as upload_count FROM document_requests dr WHERE dr.case_id=? ORDER BY dr.created_at DESC");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}
// Update a document request status
if (isset($_POST['action']) && $_POST['action'] === 'update_request_status') {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['status'] ?? 'Requested';
    $comment = trim($_POST['comment'] ?? '');
    $allowed = ['Requested','Submitted','Reviewed','Approved','Rejected','Called'];
    if (!in_array($new_status, $allowed, true)) { echo 'error'; exit(); }
    
    // Update status and add comment if provided
    $stmt = $conn->prepare("UPDATE document_requests SET status=?, attorney_comment=? WHERE id=? AND attorney_id=?");
    $stmt->bind_param('ssii', $new_status, $comment, $request_id, $attorney_id);
    $ok = $stmt->execute();
    
    if ($ok && $comment) {
        // Store comment in a separate table for better tracking
        $conn->query("CREATE TABLE IF NOT EXISTS document_request_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            attorney_id INT NOT NULL,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE
        )");
        
        $stmtC = $conn->prepare("INSERT INTO document_request_comments (request_id, attorney_id, comment) VALUES (?, ?, ?)");
        $stmtC->bind_param('iis', $request_id, $attorney_id, $comment);
        $stmtC->execute();
    }
    
    // Notify client about the status change
    if ($ok && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
        $stmtN = $conn->prepare("SELECT client_id, title FROM document_requests WHERE id=?");
        $stmtN->bind_param('i', $request_id);
        $stmtN->execute();
        $row = $stmtN->get_result()->fetch_assoc();
        if ($row) {
            $statusText = ucfirst($new_status);
            $nTitle = "Document Request $statusText";
            $nMsg = "Your document request '{$row['title']}' has been $statusText" . ($comment ? ": $comment" : "");
            $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $userType = 'client';
            $type = ($new_status === 'Approved') ? 'success' : (($new_status === 'Rejected') ? 'error' : 'warning');
            $stmtNotif->bind_param('issss', $row['client_id'], $userType, $nTitle, $nMsg, $type);
            $stmtNotif->execute();
        }
    }
    
    echo $ok ? 'success' : 'error';
    exit();
}
// List uploaded files for a request
if (isset($_POST['action']) && $_POST['action'] === 'list_request_files') {
    $request_id = intval($_POST['request_id']);
    $stmt = $conn->prepare("SELECT id, file_path, original_name, uploaded_at FROM document_request_files WHERE request_id=? ORDER BY uploaded_at DESC");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}
// Handle AJAX fetch conversation for a case
if (isset($_POST['action']) && $_POST['action'] === 'fetch_conversation') {
    $client_id = intval($_POST['client_id']);
    $msgs = [];
    
    // Check if new schema tables exist and have data
    $checkTable = function($name) use ($conn) {
        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($name) . "'");
        return $res && $res->num_rows > 0;
    };
    
    // Try new schema first (client_attorney_messages)
    if ($checkTable('client_attorney_messages') && $checkTable('client_attorney_conversations') && $checkTable('client_attorney_assignments')) {
        $stmt = $conn->prepare("SELECT cam.message, cam.sent_at, cam.sender_type AS sender
            FROM client_attorney_messages cam
            JOIN client_attorney_conversations cac ON cam.conversation_id = cac.id
            JOIN client_attorney_assignments caa ON cac.assignment_id = caa.id
            WHERE caa.attorney_id = ? AND caa.client_id = ?
            ORDER BY cam.sent_at ASC");
        $stmt->bind_param('ii', $attorney_id, $client_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $msgs[] = $row;
        }
    }
    
    // Fallback to old tables if no messages found
    if (empty($msgs)) {
        // Attorney to client (all messages)
        if ($checkTable('attorney_messages')) {
            $stmt1 = $conn->prepare("SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?");
            $stmt1->bind_param('ii', $attorney_id, $client_id);
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            while ($row = $result1->fetch_assoc()) $msgs[] = $row;
        }
        // Client to attorney (all messages)
        if ($checkTable('client_messages')) {
            $stmt2 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?");
            $stmt2->bind_param('ii', $client_id, $attorney_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            while ($row = $result2->fetch_assoc()) $msgs[] = $row;
        }
    }
    
    // Sort by sent_at
    usort($msgs, function($a, $b) { return strtotime($a['sent_at']) - strtotime($b['sent_at']); });
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}
// Handle AJAX update case (edit)
if (isset($_POST['action']) && $_POST['action'] === 'edit_case') {
    $case_id = intval($_POST['case_id']);
    $status = $_POST['status'];
    
    // Get current case info for notification
    $stmt = $conn->prepare("SELECT title, client_id FROM attorney_cases WHERE id=?");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    $case_info = $stmt->get_result()->fetch_assoc();
    
    if ($case_info) {
        $stmt = $conn->prepare("UPDATE attorney_cases SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $case_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $attorney_id,
                $_SESSION['attorney_name'],
                'attorney',
                'Case Status Update',
                'Case Management',
                "Updated case status: {$case_info['title']} to $status (Case ID: $case_id)",
                'warning',
                'medium'
            );
            
            // Notify client about case status change
            if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
                $nTitle = 'Case Status Updated';
                $nMsg = "Your case '{$case_info['title']}' status has been updated to: $status";
                $userType = 'client';
                $notificationType = 'info';
                
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                $stmtN->bind_param('issss', $case_info['client_id'], $userType, $nTitle, $nMsg, $notificationType);
                $stmtN->execute();
            }
            
            echo 'success';
        } else {
            echo 'error';
        }
    } else {
        echo 'error';
    }
    exit();
}
// Handle AJAX delete case
if (isset($_POST['action']) && $_POST['action'] === 'delete_case') {
    $case_id = intval($_POST['case_id']);
    $stmt = $conn->prepare("DELETE FROM attorney_cases WHERE id=?");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle AJAX get client profile
if (isset($_POST['action']) && $_POST['action'] === 'get_client_profile') {
    $client_id = intval($_POST['client_id']);
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=? AND user_type='client'");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $profile_image = $row['profile_image'];
        if (!$profile_image || !file_exists($profile_image)) {
            $profile_image = 'images/default-avatar.jpg';
        }
        echo $profile_image;
    } else {
        echo 'images/default-avatar.jpg';
    }
    exit();
}
// Fetch all cases (with client name and assigned attorney) - all attorneys can see all cases
$cases = [];
$sql = "SELECT ac.*, uf.name as client_name, attorney.name as attorney_name 
        FROM attorney_cases ac 
        LEFT JOIN user_form uf ON ac.client_id = uf.id 
        LEFT JOIN user_form attorney ON ac.attorney_id = attorney.id 
        ORDER BY ac.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Case Tracking - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .content { padding: 20px; }
        .btn-primary { 
            background: #5D0E26 !important; 
            color: white !important; 
            border: 2px solid white !important; 
            padding: 10px 20px; 
            border-radius: 5px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; }
        .type-filters { display: flex; gap: 8px; flex-wrap: wrap; }
        
        .action-bar { 
            display: flex; 
            flex-direction: column;
            gap: 20px; 
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }
        
        .primary-action {
            display: flex;
            justify-content: flex-start;
            align-items: center;
        }
        
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            width: 100%;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 120px;
        }
        
        .filter-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .case-scope, .status-scope {
            min-width: 140px;
        }
        
        .type-scope {
            min-width: 160px;
            flex: 1;
        }
        
        .search-scope {
            min-width: 200px;
            flex: 1;
        }
        
        .case-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .case-filter-btn, .filter-btn {
            padding: 8px 16px;
            border: 2px solid #e9ecef;
            background: white;
            color: #495057;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .case-filter-btn:hover, .filter-btn:hover {
            border-color: #5D0E26;
            color: #5D0E26;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(93, 14, 38, 0.15);
        }
        
        .case-filter-btn.active, .filter-btn.active {
            background: #5D0E26;
            color: white;
            border-color: #5D0E26;
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .type-filters select {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            background: white;
            color: #495057;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
        }
        
        .type-filters select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 4px;
            transition: all 0.3s ease;
        }
        
        .search-bar:focus-within {
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }
        
        .search-bar input {
            flex: 1;
            border: none;
            outline: none;
            padding: 8px 12px;
            font-size: 0.85rem;
            background: transparent;
        }
        
        .search-bar button {
            background: #5D0E26;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-bar button:hover {
            background: #4A0B1E;
            transform: scale(1.05);
        }
        
        .cases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .case-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .case-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            border-color: #5D0E26;
        }
        
        .case-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .case-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .case-status.status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .case-status.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .case-status.status-closed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .client-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .client-name i {
            color: #5D0E26;
            font-size: 1.2rem;
        }
        
        .case-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-view, .btn-edit {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #4A0B1E, #5D0E26);
            transform: scale(1.1);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .btn-edit:hover {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            transform: scale(1.1);
        }
        
        .no-cases {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-cases i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .no-cases h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .no-cases p {
            font-size: 1rem;
            color: #6c757d;
        }

        /* Case Tracking Modal Styles */
        .case-overview {
            text-align: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .case-overview h3 {
            margin: 0 0 0.5rem 0;
            color: #1a202c;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .status-banner {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-banner.status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-banner.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-banner.status-closed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .case-description {
            color: #5a6c7d;
            font-size: 0.875rem;
            line-height: 1.6;
            margin: 0;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            border-left: 4px solid #5D0E26;
        }
        
        .case-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .section-header h4 {
            margin: 0;
            color: #1a202c;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .section-icon {
            width: 2rem;
            height: 2rem;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            color: #5a6c7d;
            font-size: 0.875rem;
        }
        
        .info-label i {
            color: #5D0E26;
            width: 1rem;
        }
        
        .info-value {
            font-weight: 600;
            color: #1a202c;
            text-align: right;
            font-size: 0.875rem;
        }
        
        .schedule-section, .documents-section, .timeline-section {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .btn-upload-doc {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-upload-doc:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .schedule-loading, .document-loading {
            text-align: center;
            padding: 2rem;
            color: #5a6c7d;
        }
        
        .schedule-item, .document-item {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #5D0E26;
            transition: all 0.3s ease;
        }
        
        .schedule-item:hover, .document-item:hover {
            background: #e9ecef;
            transform: translateX(4px);
        }
        
        /* Success Modal Specific Styles */
        .success-modal .modal-content {
            max-width: 250px !important;
            width: 250px !important;
            margin: 10% auto !important;
        }
        
        .success-modal .modal-header {
            padding: 0.5rem !important;
        }
        
        .success-modal .modal-body {
            padding: 0.5rem !important;
        }
        
        .success-modal .btn-primary {
            padding: 6px 12px !important;
            font-size: 0.8rem !important;
            width: 60px !important;
        }
        
        .success-modal h2 {
            font-size: 1rem !important;
        }
        
        .success-modal p {
            font-size: 0.8rem !important;
        }
        
        .success-modal .case-icon {
            width: 1.5rem !important;
            height: 1.5rem !important;
            font-size: 0.8rem !important;
        }
        
        .success-modal .fa-check-circle {
            font-size: 1.5rem !important;
        }
        
        /* Horizontal layout for documents */
        .document-item.horizontal-layout {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #e2e8f0;
            border-left: 4px solid #5D0E26;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.1);
        }
        
        .document-item.horizontal-layout:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(93, 14, 38, 0.15);
        }
        
        .document-item.horizontal-layout .document-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .document-item.horizontal-layout .document-info {
            flex: 1;
            min-width: 0;
        }
        
        .document-item.horizontal-layout .document-name {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .document-item.horizontal-layout .document-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .document-item.horizontal-layout .document-category {
            background: #5D0E26;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .document-item.horizontal-layout .document-size, 
        .document-item.horizontal-layout .document-date {
            color: #5a6c7d;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .document-item.horizontal-layout .document-description {
            margin-top: 0.5rem;
            font-style: italic;
            color: #6c757d;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .document-item.horizontal-layout .document-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
            align-items: center;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border: none;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #4A0B1E 0%, #5D0E26 100%);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.4);
        }
        
        .btn-download {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .schedule-type {
            background: #5D0E26;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .schedule-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .schedule-status.scheduled {
            background: #d4edda;
            color: #155724;
        }
        
        .schedule-status.completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .schedule-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .schedule-details {
            margin-top: 0.5rem;
        }
        
        .schedule-date, .schedule-title, .schedule-description, .schedule-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            color: #5a6c7d;
        }
        
        .schedule-date i, .schedule-title i, .schedule-description i, .schedule-location i {
            color: #5D0E26;
            width: 1rem;
        }
        
        .document-meta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .document-category {
            background: #5D0E26;
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .document-size, .document-date {
            color: #5a6c7d;
            font-size: 0.75rem;
        }
        
        .document-description {
            margin-top: 0.5rem;
            font-style: italic;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .document-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .timeline-list {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-list::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .timeline-marker {
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 0.75rem;
            height: 0.75rem;
            background: #5D0E26;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #5D0E26;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 6px;
            border-left: 4px solid #5D0E26;
        }
        
        .timeline-date {
            font-size: 0.75rem;
            color: #5D0E26;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }
        
        .timeline-description {
            color: #5a6c7d;
            font-size: 0.875rem;
        }
        
        .modal-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid #e5e7eb;
            gap: 1rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* Upload Modal Styles */
        .upload-area {
            border: 2px dashed #5D0E26;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .upload-area:hover {
            background: #e9ecef;
            border-color: #8B1538;
        }
        
        .upload-content i {
            font-size: 3rem;
            color: #5D0E26;
            margin-bottom: 1rem;
        }
        
        .upload-content h3 {
            margin: 0 0 0.5rem 0;
            color: #1a202c;
        }
        
        .upload-content p {
            margin: 0;
            color: #5a6c7d;
        }
        
        .file-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .file-info i {
            color: #5D0E26;
            font-size: 1.5rem;
        }
        
        .file-name {
            font-weight: 600;
            color: #1a202c;
            flex: 1;
        }
        
        .file-size {
            color: #5a6c7d;
            font-size: 0.875rem;
        }
        
        .file-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .input-group {
            margin-bottom: 0.75rem;
        }
        
        .input-group.full-width {
            grid-column: 1 / -1;
        }
        
        .input-group label {
            display: block;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 2px rgba(93, 14, 38, 0.1);
        }
        
        .input-group textarea {
            resize: vertical;
            min-height: 60px;
        }
    </style>

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
            <li><a href="attorney_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php" class="active"><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Case Management';
        $page_subtitle = 'Manage all cases in the system';
        include 'components/profile_header.php'; 
        ?>

        <div class="content">
            <div class="action-bar">
                <!-- Primary Action -->
                <div class="primary-action">
                    <button class="btn-primary" onclick="openAddCaseModal()">
                        <i class="fas fa-plus"></i> Add New Case
                    </button>
                </div>
                
                <!-- Filter Controls -->
                <div class="filter-controls">
                    <!-- Case Scope Filters -->
                    <div class="filter-group case-scope">
                        <label class="filter-label">Scope:</label>
                        <div class="case-filters">
                            <button class="case-filter-btn active" data-filter="all">All Cases</button>
                            <button class="case-filter-btn" data-filter="my">My Cases</button>
                        </div>
                    </div>
                    
                    <!-- Status Filters -->
                    <div class="filter-group status-scope">
                        <label class="filter-label">Status:</label>
                        <div class="filters">
                            <button class="filter-btn active" data-status="">All</button>
                            <button class="filter-btn" data-status="Active">Active</button>
                            <button class="filter-btn" data-status="Pending">Pending</button>
                            <button class="filter-btn" data-status="Closed">Closed</button>
                        </div>
                    </div>
                    
                    <!-- Type Filter -->
                    <div class="filter-group type-scope">
                        <label class="filter-label">Type:</label>
                        <div class="type-filters">
                            <select id="typeFilter">
                                <option value="">All Types</option>
                                <option value="Criminal">Criminal</option>
                                <option value="Civil">Civil</option>
                                <option value="Family">Family</option>
                                <option value="Corporate">Corporate</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Search -->
                    <div class="filter-group search-scope">
                        <label class="filter-label">Search:</label>
                        <div class="search-bar">
                            <input type="text" id="searchInput" placeholder="Search cases...">
                            <button><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination Controls - Compact Top Version -->
            <div class="pagination-container pagination-top" id="paginationContainer">
                <div class="pagination-info">
                    <span id="paginationInfo">Showing 1-10 of 50 cases</span>
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn" onclick="changePage(-1)">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <div class="pagination-numbers" id="paginationNumbers">
                        <!-- Page numbers will be generated here -->
                    </div>
                    <button class="pagination-btn" id="nextBtn" onclick="changePage(1)">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="pagination-settings">
                    <label for="itemsPerPage">Per page:</label>
                    <select id="itemsPerPage" onchange="updateItemsPerPage()">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <div class="cases-grid" id="casesGrid">
                <?php if (empty($cases)): ?>
                <div class="no-cases">
                    <i class="fas fa-folder-open"></i>
                    <h3>No cases found</h3>
                    <p>Add your first case using the button above</p>
                </div>
                <?php else: ?>
                <?php foreach ($cases as $case): ?>
                <div class="case-card" data-status="<?= htmlspecialchars($case['status']) ?>" data-type="<?= htmlspecialchars($case['case_type']) ?>" data-attorney-id="<?= htmlspecialchars($case['attorney_id']) ?>">
                    <div class="case-header">
                        <div class="case-status status-<?= strtolower($case['status']) ?>"><?= htmlspecialchars($case['status']) ?></div>
                    </div>
                    
                    <div class="client-name">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($case['client_name'] ?? 'N/A') ?>
                    </div>
                    
                    <div class="case-actions">
                        <button class="btn-view" onclick="openCaseView(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title'] ?? '') ?>', '<?= htmlspecialchars($case['client_name'] ?? '') ?>', '<?= htmlspecialchars($case['description'] ?? '') ?>', '<?= htmlspecialchars($case['status'] ?? '') ?>', '<?= htmlspecialchars($case['case_type'] ?? '') ?>', <?= $case['client_id'] ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-edit" onclick="openEditCaseModal(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title'] ?? '') ?>', '<?= htmlspecialchars($case['client_name'] ?? '') ?>', '<?= htmlspecialchars($case['description'] ?? '') ?>', '<?= htmlspecialchars($case['status'] ?? '') ?>', <?= $case['client_id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- Document Requests Modal -->
        <div class="modal" id="requestModal" style="display:none; z-index: 10001 !important;">
            <div class="modal-content" style="max-height: 90vh; overflow-y: auto; z-index: 10002 !important;">
                <div class="modal-header" style="z-index: 10002 !important;">
                    <h2>Document Requests</h2>
                    <button class="close-modal" onclick="closeRequestModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 10002 !important;">
                    <form id="requestForm" style="margin-bottom:14px;">
                        <input type="hidden" name="case_id" id="reqCaseId">
                        <input type="hidden" name="client_id" id="reqClientId">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3" placeholder="e.g. Please upload a scanned copy of your ID, PSA, etc."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date">
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">Close</button>
                            <button type="submit" class="btn btn-primary">Create Request</button>
                        </div>
                    </form>
                    <div id="requestsList"></div>
                </div>
            </div>
        </div>
        <!-- Add Case Modal -->
        <div class="modal" id="addCaseModal" style="display:none; z-index: 10001 !important;">
            <div class="modal-content add-case-modal" style="z-index: 10002 !important; max-width: 550px; width: 90%;">
                <div class="modal-header add-case-header" style="z-index: 10002 !important;">
                    <div class="header-content">
                        <div class="header-icon">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="header-text">
                            <h2>Create New Case</h2>
                            <p>Add a new case to your portfolio</p>
                        </div>
                    </div>
                    <button class="close-modal" onclick="closeAddCaseModal()">&times;</button>
                </div>
                <div class="modal-body add-case-body" style="z-index: 10002 !important;">
                    <form id="addCaseForm" class="add-case-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Client</label>
                                <select name="client_id" id="clientSelect" required>
                                    <option value="">Select Client</option>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-tags"></i> Case Type</label>
                                <select name="case_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Criminal">Criminal</option>
                                    <option value="Civil">Civil</option>
                                    <option value="Family">Family</option>
                                    <option value="Corporate">Corporate</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-file-alt"></i> Case Title</label>
                            <input type="text" name="title" id="caseTitle" required placeholder="Enter case title">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Summary</label>
                            <textarea name="description" id="caseDescription" required placeholder="Provide a brief summary of the case"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeAddCaseModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Case
                            </button>
                        </div>
                    </form>
                    <div id="caseSuccessMsg" class="success-message" style="display:none;">
                        <i class="fas fa-check-circle"></i> Case created successfully!
                    </div>
                </div>
            </div>
        </div>
        <!-- Conversation Modal -->
        <div class="modal" id="conversationModal" style="display:none; z-index:10002 !important;">
            <div class="modal-content" style="z-index: 10003 !important; max-width: 760px; width: 96%; margin: 0 auto;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Conversation with Client</h2>
                    <button class="close-modal" onclick="closeConversationModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important; padding: 10px 14px;">
                    <!-- Chat Styles (scoped to modal) -->
                    <style>
                        /* Scoped styles: only affect elements inside #conversationModal */
                        #conversationModal .chat-wrapper {
                            display: flex;
                            flex-direction: column;
                            gap: 6px;
                            padding: 12px;
                            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                            border-radius: 12px;
                            min-height: 320px;
                            max-height: 360px;
                        }
                        
                        #conversationModal .date-separator {
                            text-align: center;
                            color: #64748b;
                            font-size: 11px;
                            margin: 15px 0 8px 0;
                            position: relative;
                            font-weight: 600;
                            background: rgba(255,255,255,0.8);
                            padding: 4px 12px;
                            border-radius: 12px;
                            display: inline-block;
                            margin-left: 50%;
                            transform: translateX(-50%);
                        }
                        
                        /* Side-by-side layout */
                        #conversationModal .message-container {
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                            gap: 10px;
                            width: 100%;
                            margin: 6px 0;
                        }
                        #conversationModal .message-left,
                        #conversationModal .message-right { flex: 1; display: flex; }
                        #conversationModal .message-left { justify-content: flex-start; }
                        #conversationModal .message-right { justify-content: flex-end; }
                        
                        #conversationModal .bubble {
                            max-width: 60%;
                            padding: 10px 12px;
                            border-radius: 18px;
                            line-height: 1.4;
                            font-size: 14px;
                            position: relative;
                            word-wrap: break-word;
                            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                            backdrop-filter: blur(10px);
                        }
                        
                        #conversationModal .bubble.sent {
                            background: linear-gradient(135deg, #7C0F2F 0%, #9a1a3a 100%);
                            color: #ffffff;
                            border-bottom-right-radius: 4px;
                            margin-left: 8px;
                        }
                        
                        #conversationModal .bubble.received {
                            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                            color: #1e293b;
                            border: 1px solid #e2e8f0;
                            border-bottom-left-radius: 4px;
                            margin-right: 8px;
                        }
                        
                        #conversationModal .bubble.sent::after {
                            content: '';
                            position: absolute;
                            bottom: 0;
                            right: -6px;
                            width: 0;
                            height: 0;
                            border: 8px solid transparent;
                            border-left-color: #7C0F2F;
                            border-bottom-color: #7C0F2F;
                            border-right: 0;
                            border-top: 0;
                            transform: rotate(45deg);
                        }
                        
                        #conversationModal .bubble.received::after {
                            content: '';
                            position: absolute;
                            bottom: 0;
                            left: -6px;
                            width: 0;
                            height: 0;
                            border: 8px solid transparent;
                            border-right-color: #ffffff;
                            border-bottom-color: #ffffff;
                            border-left: 0;
                            border-top: 0;
                            transform: rotate(-45deg);
                        }
                        
                        #conversationModal .meta {
                            margin-top: 4px;
                            font-size: 10px;
                            opacity: 0.8;
                            font-weight: 500;
                        }
                        
                        #conversationModal .bubble.sent .meta {
                            color: rgba(255,255,255,0.9);
                            text-align: right;
                        }
                        
                        #conversationModal .bubble.received .meta {
                            color: #64748b;
                            text-align: left;
                        }
                        
                        #conversationModal .avatar {
                            width: 24px;
                            height: 24px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 10px;
                            font-weight: 700;
                            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
                            flex-shrink: 0;
                            border: 1px solid rgba(255,255,255,0.9);
                        }
                        
                        #conversationModal .message-row.sent .avatar {
                            background: linear-gradient(135deg, #7C0F2F 0%, #9a1a3a 100%);
                            color: #ffffff;
                        }
                        
                        #conversationModal .message-row.received .avatar {
                            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
                            color: #ffffff;
                        }
                        
                        #conversationModal .chat-messages {
                            max-height: 340px;
                            overflow-y: auto;
                            padding-right: 4px;
                            scroll-behavior: smooth;
                        }
                        /* Responsive stacking on narrow screens */
                        @media (max-width: 576px) {
                            #conversationModal .bubble { max-width: 100%; }
                            #conversationModal .message-container { flex-direction: column; }
                            #conversationModal .message-right, #conversationModal .message-left { justify-content: flex-start; }
                        }
                        
                        #conversationModal .chat-messages::-webkit-scrollbar {
                            width: 6px;
                        }
                        
                        #conversationModal .chat-messages::-webkit-scrollbar-track {
                            background: rgba(0,0,0,0.05);
                            border-radius: 10px;
                        }
                        
                        #conversationModal .chat-messages::-webkit-scrollbar-thumb {
                            background: linear-gradient(135deg, #7C0F2F 0%, #9a1a3a 100%);
                            border-radius: 10px;
                        }
                        
                        #conversationModal .chat-messages::-webkit-scrollbar-thumb:hover {
                            background: linear-gradient(135deg, #5a0b22 0%, #7C0F2F 100%);
                        }
                    </style>
                    <div class="chat-wrapper">
                        <div class="chat-messages" id="modalChatMessages" style="max-height:350px;overflow-y:auto;">
                            <!-- Dynamic chat here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="z-index: 9999 !important;">
                    <button class="btn btn-secondary" onclick="closeConversationModal()">Close</button>
                </div>
            </div>
        </div>
        <!-- Edit Case Modal -->
        <div class="modal" id="editCaseModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Edit Case Status</h2>
                    <button class="close-modal" onclick="closeEditCaseModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <form id="editCaseForm">
                        <input type="hidden" name="case_id" id="editCaseId">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="editCaseStatus" required>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeEditCaseModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                    <div id="editCaseSuccessMsg" style="display:none; color:green; margin-top:10px;">Status updated successfully!</div>
                </div>
            </div>
        </div>
                 <!-- Add this modal after the Edit Case Modal -->
        <div class="modal" id="summaryModal" style="display:none; z-index:9999 !important;">
            <div class="modal-content" style="z-index: 10000 !important; max-width: 600px; width: 90%; max-height: 70vh; overflow-y: auto;">
                <div class="modal-header" style="z-index: 10000 !important; padding: 12px 20px;">
                     <h2 style="font-size: 1.3rem; margin: 0;">Case Summary</h2>
                     <button class="close-modal" onclick="closeSummaryModal()">&times;</button>
                 </div>
                 <div class="modal-body" style="z-index: 9999 !important; padding: 16px 20px;">
                     <div id="summaryText"></div>
                 </div>
             </div>
        </div>
        
        <!-- Case View Modal -->
        <div class="modal" id="caseModal" style="display:none; z-index: 10001 !important;">
            <div class="modal-content" style="z-index: 10002 !important; max-width: 900px; width: 95%; max-height: 95vh; overflow-y: auto;">
                <div class="modal-header" style="z-index: 10002 !important;">
                    <h2>Case Tracking</h2>
                    <button class="close-modal" onclick="closeViewModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 10002 !important;">
                    <!-- Modal content will be dynamically inserted here -->
                </div>
            </div>
        </div>
        
        <!-- Document Request Status Update Modal -->
         <div class="modal" id="statusUpdateModal" style="display:none; z-index: 10003 !important;">
             <div class="modal-content" style="z-index: 10004 !important;">
                 <div class="modal-header" style="z-index: 10004 !important;">
                     <h2>Update Document Request Status</h2>
                     <button class="close-modal" onclick="closeStatusUpdateModal()">&times;</button>
                 </div>
                 <div class="modal-body" style="z-index: 10004 !important;">
                     <form id="statusUpdateForm">
                         <input type="hidden" name="request_id" id="statusUpdateRequestId">
                         <div class="form-group">
                             <label>Status</label>
                             <select name="status" id="statusUpdateStatus" required>
                                 <option value="Approved">Approved</option>
                                 <option value="Rejected">Rejected</option>
                                 <option value="Called">Called for Additional Documents</option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label>Comment (Optional)</label>
                             <textarea name="comment" id="statusUpdateComment" rows="3" placeholder="Add your comment or feedback..."></textarea>
                         </div>
                         <div class="form-actions">
                             <button type="button" class="btn btn-secondary" onclick="closeStatusUpdateModal()">Cancel</button>
                             <button type="submit" class="btn btn-primary">Update Status</button>
                         </div>
                     </form>
                 </div>
             </div>
         </div>
    </div>
    <style>
        .cases-container { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 24px; margin-top: 24px; }
        .cases-header { display: flex; align-items: center; margin-bottom: 18px; }
        .filter-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #6c757d;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filter-btn:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            color: #495057;
        }
        .filter-btn.active {
            background: #7C0F2F;
            border-color: #7C0F2F;
            color: white;
        }
        .filter-btn.active:hover {
            background: #8B1538;
            border-color: #8B1538;
        }
                 .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; }
         .status-active { background: #28a745; color: white; }
         .status-pending { background: #ffc107; color: #333; }
         .status-closed { background: #999; color: #fff; }
         .status-requested { background: #ffc107; color: #333; }
         .status-submitted { background: #17a2b8; color: white; }
         .status-reviewed { background: #6f42c1; color: white; }
         .status-approved { background: #28a745; color: white; }
         .status-rejected { background: #dc3545; color: white; }
         .status-called { background: #fd7e14; color: white; }
        .btn-xs { font-size: 0.9em; padding: 4px 10px; margin-right: 4px; }
        .btn-sm { font-size: 0.95em; padding: 6px 12px; }
        .cases-grid { 
            display: grid; 
            grid-template-columns: repeat(5, 1fr); 
            gap: 15px; 
            margin-top: 20px;
            justify-content: center;
        }
        .case-card { 
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%); 
            border-radius: 16px; 
            padding: 12px; 
            min-height: 120px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); 
            border: 1px solid rgba(229, 231, 235, 0.8); 
            position: relative; 
            overflow: hidden; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .case-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 16px 16px 0 0;
        }
        
        .case-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .case-card-header { 
            padding: 6px 10px; 
            border-bottom: 1px solid #f0f2f5; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            background: #f8f9fa;
        }
        .case-card-body { 
            padding: 8px 10px; 
            flex-grow: 1;
        }
        .case-client { 
            margin: 0 0 4px 0; 
            font-size: 10px; 
            color: #6c757d; 
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .case-title { 
            margin: 0 0 6px 0; 
            font-size: 13px; 
            color: #2c3e50; 
            font-weight: 600;
            line-height: 1.2;
        }
        .case-type {
            margin: 0;
            font-size: 9px;
            color: #28a745;
            font-weight: 500;
            background: #e8f5e9;
            padding: 1px 5px;
            border-radius: 6px;
            display: inline-block;
        }
        .case-card-footer { 
            padding: 6px 10px; 
            border-top: 1px solid #f0f2f5; 
            display: flex; 
            justify-content: center;
            gap: 6px;
            background: #f8f9fa;
        }
        .btn-view-case {
            background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .btn-view-case:hover {
            background: linear-gradient(135deg, #8B1538 0%, #7C0F2F 100%);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(124, 15, 47, 0.3);
        }
        .btn-edit-status {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .btn-edit-status:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(40, 167, 69, 0.3);
        }
        /* New Admin-style Layout */
        .case-header {
            display: flex;
            justify-content: center;
            margin-bottom: 6px;
        }
        
        .case-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7em;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .case-status::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .case-status:hover::before {
            left: 100%;
        }
        
        .status-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-closed {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .client-name {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 1em;
            font-weight: 600;
            color: #1a202c;
            padding: 8px 6px;
            margin-bottom: 8px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            background: linear-gradient(135deg, rgba(93, 14, 38, 0.05) 0%, rgba(139, 21, 56, 0.05) 100%);
            border-radius: 6px;
            text-align: center;
        }
        
        .client-name i {
            color: #5D0E26;
            font-size: 1.1em;
            filter: drop-shadow(0 1px 2px rgba(93, 14, 38, 0.3));
        }
        
        .case-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        
        .btn-view, .btn-edit {
            padding: 12px;
            border-radius: 12px;
            width: 44px;
            height: 44px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: #333;
        }
        
        .btn-view::before, .btn-edit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-view:hover::before, .btn-edit:hover::before {
            left: 100%;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .cases-grid { 
                grid-template-columns: repeat(4, 1fr); 
            }
        }
        
        @media (max-width: 768px) {
            .cases-grid { 
                grid-template-columns: repeat(2, 1fr); 
            }
        }
        
        @media (max-width: 400px) {
            .cases-grid { 
                grid-template-columns: 1fr; 
            }
        }
        
        /* Pagination Styles */
        .pagination-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }
        
        /* Compact Top Pagination */
        .pagination-top {
            margin-top: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
        }
        
        .pagination-top .pagination-info {
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .pagination-top .pagination-controls {
            gap: 0.5rem;
        }
        
        .pagination-top .pagination-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .pagination-top .page-number {
            padding: 0.4rem 0.6rem;
            min-width: 35px;
            font-size: 0.85rem;
        }
        
        .pagination-top .pagination-settings {
            padding-top: 0;
            border-top: none;
            gap: 0.25rem;
        }
        
        .pagination-top .pagination-settings label {
            font-size: 0.8rem;
        }
        
        .pagination-top .pagination-settings select {
            padding: 0.4rem 0.6rem;
            font-size: 0.8rem;
        }
        
        .pagination-info {
            text-align: center;
            color: #5a6c7d;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.4);
        }
        
        .pagination-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }
        
        .pagination-numbers {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .page-number {
            background: white;
            color: #5D0E26;
            border: 2px solid #e9ecef;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .page-number:hover {
            border-color: #5D0E26;
            color: #5D0E26;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(93, 14, 38, 0.15);
        }
        
        .page-number.active {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border-color: #5D0E26;
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .page-number.active:hover {
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 100%);
            transform: translateY(-2px);
        }
        
        .page-ellipsis {
            color: #6c757d;
            font-weight: 600;
            padding: 0.5rem;
            user-select: none;
        }
        
        .pagination-settings {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .pagination-settings label {
            color: #5a6c7d;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .pagination-settings select {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            color: #5D0E26;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pagination-settings select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }
        
        /* Responsive pagination */
        @media (max-width: 768px) {
            .pagination-top {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .pagination-controls {
                flex-direction: column;
                gap: 1rem;
            }
            
            .pagination-numbers {
                order: 2;
            }
            
            .pagination-btn {
                order: 1;
                width: 100%;
                justify-content: center;
            }
            
            .pagination-settings {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .pagination-container {
                padding: 1rem;
            }
            
            .page-number {
                padding: 0.4rem 0.6rem;
                min-width: 35px;
                font-size: 0.9rem;
            }
            
            .pagination-btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
        }
        
        /* Enhanced Add Case Modal Styling */
        .add-case-modal {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            box-shadow: 
                0 25px 80px rgba(93, 14, 38, 0.3),
                0 15px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(93, 14, 38, 0.1);
            overflow: hidden;
        }
        
        .add-case-header {
            background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%);
            padding: 15px 20px;
            border-bottom: none;
            position: relative;
        }
        
        .add-case-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C0F2F, #8B1538, #7C0F2F);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .header-icon i {
            font-size: 1.2rem;
            color: white;
        }
        
        .header-text h2 {
            margin: 0;
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .header-text p {
            margin: 2px 0 0 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            font-weight: 400;
        }
        
        .add-case-body {
            padding: 20px;
            background: white;
        }
        
        .add-case-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .add-case-form .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        .add-case-form .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #7C0F2F;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .add-case-form .form-group label i {
            color: #8B1538;
            font-size: 0.9rem;
        }
        
        .add-case-form .form-group input,
        .add-case-form .form-group select,
        .add-case-form .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 
                inset 0 2px 4px rgba(93, 14, 38, 0.05),
                0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .add-case-form .form-group input:focus,
        .add-case-form .form-group select:focus,
        .add-case-form .form-group textarea:focus {
            outline: none;
            border-color: #7C0F2F;
            box-shadow: 
                0 0 0 4px rgba(93, 14, 38, 0.1),
                inset 0 2px 4px rgba(93, 14, 38, 0.05);
            background: white;
            transform: translateY(-2px);
        }
        
        .add-case-form .form-group select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%237C0F2F' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 48px;
            appearance: none;
        }
        
        .add-case-form .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
            font-size: 0.95rem;
            padding: 12px 16px;
        }
        
        .add-case-form .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid rgba(93, 14, 38, 0.08);
        }
        
        .add-case-form .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .add-case-form .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .add-case-form .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .add-case-form .btn-primary {
            background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }
        
        .add-case-form .btn-primary:hover {
            background: linear-gradient(135deg, #8B1538 0%, #7C0F2F 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
        }
        
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 16px 20px;
            border-radius: 12px;
            border: 2px solid #c3e6cb;
            text-align: center;
            font-weight: 600;
            margin-top: 20px;
            animation: successFadeIn 0.5s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        @keyframes successFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .add-case-form .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .add-case-form .form-actions {
                flex-direction: column;
                gap: 12px;
            }
            
            .add-case-form .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <script>
        // Enhanced filtering system matching admin layout
        function initializeFilters() {
            // Case scope filters
            document.querySelectorAll('.case-filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.case-filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    applyFilters();
                });
            });
            
            // Status filters
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    applyFilters();
                });
            });
            
            // Type filter
            document.getElementById('typeFilter').addEventListener('change', applyFilters);
            
            // Search filter
            document.getElementById('searchInput').addEventListener('input', applyFilters);
        }
        
        function applyFilters() {
            const caseCards = document.querySelectorAll('.case-card');
            const scopeFilter = document.querySelector('.case-filter-btn.active').dataset.filter;
            const statusFilter = document.querySelector('.filter-btn.active').dataset.status;
            const typeFilter = document.getElementById('typeFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const currentAttorneyId = <?= $attorney_id ?>;
            
            caseCards.forEach(card => {
                const cardStatus = card.dataset.status;
                const cardType = card.dataset.type;
                const cardAttorneyId = card.dataset.attorneyId;
                const clientName = card.querySelector('.client-name').textContent.toLowerCase();
                
                let show = true;
                
                // Scope filter
                if (scopeFilter === 'my' && cardAttorneyId != currentAttorneyId) {
                    show = false;
                }
                
                // Status filter
                if (statusFilter && cardStatus !== statusFilter) {
                    show = false;
                }
                
                // Type filter
                if (typeFilter && cardType !== typeFilter) {
                    show = false;
                }
                
                // Search filter
                if (searchTerm && !clientName.includes(searchTerm)) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // Legacy function for backward compatibility
        function filterCases(status) {
            // Update the active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.status === status || (status === 'all' && btn.dataset.status === '')) {
                    btn.classList.add('active');
                }
            });
            applyFilters();
        }
        
        function openAddCaseModal() {
            document.getElementById('addCaseModal').style.display = 'block';
        }
        function closeAddCaseModal() {
            document.getElementById('addCaseModal').style.display = 'none';
        }
        document.getElementById('addCaseForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_case');
            fetch('attorney_cases.php', {
                method: 'POST',
                body: formData
            }).then(r => r.text()).then(res => {
                console.log('Response:', res); // Debug log
                if (res === 'success') {
                    document.getElementById('caseSuccessMsg').style.display = 'block';
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    // Show detailed error message
                    const errorMsg = res.startsWith('error:') ? res.replace('error:', '').trim() : 'Error adding case.';
                    alert('Error: ' + errorMsg);
                }
            }).catch(error => {
                console.error('Fetch error:', error);
                alert('Network error occurred while creating case.');
            });
        };
        function openConversationModal(clientId) {
            // Generic: fetch all messages between attorney and client
            const fd = new FormData();
            fd.append('action', 'fetch_conversation');
            fd.append('client_id', clientId);
            fetch('attorney_cases.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('modalChatMessages');
                    chat.innerHTML = '';
                    if (msgs.length === 0) {
                        chat.innerHTML = '<div class="chat-wrapper"><p style="color:#888;text-align:center;padding:20px;">No conversation yet.</p></div>';
                    } else {
                        // Group by date for readability
                        const byDate = {};
                        msgs.forEach(m => {
                            const d = new Date(m.sent_at.replace(' ', 'T'));
                            const key = d.toLocaleDateString();
                            if (!byDate[key]) byDate[key] = [];
                            byDate[key].push({ ...m, _time: d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
                        });

                        const sections = [];
                        Object.keys(byDate).forEach(dateKey => {
                            sections.push(`<div class='date-separator'>${dateKey}</div>`);
                            byDate[dateKey].forEach(m => {
                                const sent = (m.sender || '').toLowerCase() === 'attorney';
                                const initials = sent ? 'A' : 'C';
                                sections.push(`
                                    <div class='message-container'>
                                        <div class='message-left'>
                                            ${sent ? '' : `<div class='avatar'>${initials}</div>`}
                                            ${sent ? '' : `<div class='bubble received'><div>${escapeHtml(m.message || '')}</div><div class='meta'>${m._time}</div></div>`}
                                        </div>
                                        <div class='message-right'>
                                            ${sent ? `<div class='bubble sent'><div>${escapeHtml(m.message || '')}</div><div class='meta'>${m._time}</div></div>` : ''}
                                            ${sent ? `<div class='avatar'>${initials}</div>` : ''}
                                        </div>
                                    </div>
                                `);
                            });
                        });
                        chat.innerHTML = `<div class='chat-wrapper'>${sections.join('')}</div>`;
                        // Auto-scroll to bottom
                        chat.scrollTop = chat.scrollHeight;
                    }
                    // Ensure chat modal sits above summary modal
                    const convo = document.getElementById('conversationModal');
                    if (convo) {
                        convo.style.zIndex = '10002';
                        convo.querySelector('.modal-content').style.zIndex = '10003';
                        convo.style.display = 'block';
                    }
                    // Push summary modal behind
                    const summary = document.getElementById('summaryModal');
                    if (summary) {
                        summary.style.zIndex = '10000';
                        summary.querySelector('.modal-content').style.zIndex = '10001';
                    }
                });
        }
        // small encoder to prevent HTML injection inside messages
        function escapeHtml(str) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
            return String(str).replace(/[&<>"']/g, s => map[s]);
        }
        function closeConversationModal() {
            const convo = document.getElementById('conversationModal');
            if (convo) convo.style.display = 'none';
            // Restore summary modal default z-index
            const summary = document.getElementById('summaryModal');
            if (summary) {
                summary.style.zIndex = '9999';
                const sc = summary.querySelector('.modal-content');
                if (sc) sc.style.zIndex = '9999';
            }
        }
        function openEditCaseModal(caseId, title, clientName, description, status, clientId) {
            document.getElementById('editCaseId').value = caseId;
            document.getElementById('editCaseStatus').value = status || 'Active';
            document.getElementById('editCaseModal').style.display = 'block';
        }
        function closeEditCaseModal() {
            document.getElementById('editCaseModal').style.display = 'none';
        }
        document.getElementById('editCaseForm').onsubmit = function(e) {
            e.preventDefault();
            
            // First confirmation
            const confirm1 = confirm('⚠️ WARNING: You are about to change the case status.\n\nAre you sure you want to proceed?');
            if (!confirm1) return;
            
            // Second confirmation with more details
            const formData = new FormData(this);
            const caseId = formData.get('case_id');
            const newStatus = formData.get('status');
            const confirm2 = confirm(`🚨 FINAL CONFIRMATION 🚨\n\nCase ID: ${caseId}\nNew Status: ${newStatus}\n\nThis action will permanently change the case status.\n\nClick OK to confirm, or Cancel to abort.`);
            if (!confirm2) return;
            
            formData.append('action', 'edit_case');
            fetch('attorney_cases.php', {
                method: 'POST',
                body: formData
            }).then(r => r.text()).then(res => {
                if (res === 'success') {
                    document.getElementById('editCaseSuccessMsg').style.display = 'block';
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    alert('❌ Error updating case status.');
                }
            });
        };
        function deleteCase(caseId) {
            if (!confirm('Are you sure you want to delete this case?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_case');
            fd.append('case_id', caseId);
            fetch('attorney_cases.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        location.reload();
                    } else {
                        alert('Error deleting case.');
                    }
                });
        }
        function openSummaryModal(summary) {
            document.getElementById('summaryText').innerText = summary;
            document.getElementById('summaryModal').style.display = 'block';
        }
        function closeSummaryModal() {
            document.getElementById('summaryModal').style.display = 'none';
        }
        // Override the existing openCaseView function to use enhanced modal
        function openCaseView(caseId, title, clientName, description, status, caseType, clientId) {
            // Get attorney_id from the cases array
            const cases = <?= json_encode($cases) ?>;
            const fullCaseData = cases.find(c => c.id == caseId);
            
            // Create comprehensive modal content
            const modalContent = `
                <div class="modal-header">
                    <div class="header-content">
                        <div class="case-icon">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="header-text">
                            <h2>Case Tracking</h2>
                            <p>Complete case information and management</p>
                        </div>
                    </div>
                    <span class="close" onclick="closeViewModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Case Overview -->
                    <div class="case-overview">
                        <h3>${title}</h3>
                        <div class="status-banner status-${status.toLowerCase()}">
                            <i class="fas fa-circle"></i> ${status}
                        </div>
                        <p class="case-description">${description || 'No description available'}</p>
                    </div>
                    
                    <!-- Case Information Grid -->
                    <div class="case-info-grid">
                        <div class="info-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4>Client Information</h4>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-user"></i> Client Name:</span>
                                <span class="info-value">${clientName}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar"></i> Date Filed:</span>
                                <span class="info-value">${new Date().toLocaleDateString()}</span>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-gavel"></i>
                                </div>
                                <h4>Case Details</h4>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-tag"></i> Case Type:</span>
                                <span class="info-value">${caseType}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-user-tie"></i> Assigned Attorney:</span>
                                <span class="info-value">${fullCaseData ? fullCaseData.attorney_name || 'N/A' : 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Schedule Section -->
                    <div class="schedule-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h4>Case Schedule</h4>
                        </div>
                        <div class="schedule-list" id="scheduleList${caseId}">
                            <div class="schedule-loading">
                                <i class="fas fa-spinner fa-spin"></i> Loading schedules...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents Section -->
                    <div class="documents-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h4>Case Documents</h4>
                            <button class="btn-upload-doc" onclick="openDocumentUpload(${caseId})">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                        </div>
                        <div class="documents-list" id="documentsList${caseId}">
                            <div class="document-loading">
                                <i class="fas fa-spinner fa-spin"></i> Loading documents...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Case Timeline -->
                    <div class="timeline-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h4>Case Timeline</h4>
                        </div>
                        <div class="timeline-list">
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-date">${new Date().toLocaleDateString()}</div>
                                    <div class="timeline-title">Case Filed</div>
                                    <div class="timeline-description">Case was filed and assigned to <?= $_SESSION['attorney_name'] ?? 'Attorney' ?></div>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-date">Current</div>
                                    <div class="timeline-title">Status: ${status}</div>
                                    <div class="timeline-description">Case is currently ${status.toLowerCase()}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeViewModal()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            `;
            
            // Set modal content and show
            const caseModal = document.getElementById('caseModal');
            if (!caseModal) {
                console.error('caseModal not found in DOM');
                return;
            }
            
            const modalContentElement = caseModal.querySelector('.modal-content');
            if (!modalContentElement) {
                console.error('Modal content element not found in caseModal');
                return;
            }
            
            modalContentElement.innerHTML = modalContent;
            caseModal.style.display = 'block';
            
            // Load schedule and documents data with a small delay to ensure DOM is ready
            setTimeout(() => {
                loadCaseSchedule(caseId);
                loadCaseDocuments(caseId);
            }, 100);
        }
        
        function closeViewModal() {
            const caseModal = document.getElementById('caseModal');
            if (caseModal) {
                caseModal.style.display = 'none';
            }
        }
        
        // Helper functions for case tracking
        function loadCaseSchedule(caseId) {
            const formData = new FormData();
            formData.append('case_id', caseId);
            
            fetch('get_case_schedules.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(schedules => {
                    const scheduleList = document.getElementById(`scheduleList${caseId}`);
                    
                    if (!scheduleList) {
                        console.error(`scheduleList${caseId} element not found`);
                        return;
                    }
                    
                    if (schedules.length === 0) {
                        scheduleList.innerHTML = `
                            <div class="schedule-item">
                                <div class="schedule-date">
                                    <i class="fas fa-calendar"></i>
                                    <span>No scheduled events</span>
                                </div>
                            </div>
                        `;
                    } else {
                        scheduleList.innerHTML = schedules.map(schedule => `
                            <div class="schedule-item">
                                <div class="schedule-header">
                                    <div class="schedule-type">${schedule.type}</div>
                                    <div class="schedule-status status-${schedule.status.toLowerCase()}">${schedule.status}</div>
                                </div>
                                <div class="schedule-details">
                                    <div class="schedule-date">
                                        <i class="fas fa-calendar"></i>
                                        <span>${schedule.date} at ${schedule.start_time}</span>
                                    </div>
                                    ${schedule.title ? `
                                    <div class="schedule-title">
                                        <i class="fas fa-tag"></i>
                                        <span>${schedule.title}</span>
                                    </div>
                                    ` : ''}
                                    ${schedule.description ? `
                                    <div class="schedule-description">
                                        <i class="fas fa-file-alt"></i>
                                        <span>${schedule.description}</span>
                                    </div>
                                    ` : ''}
                                    ${schedule.location ? `
                                    <div class="schedule-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>${schedule.location}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('');
                    }
                })
                .catch(error => {
                    console.error('Error loading schedules:', error);
                    const scheduleList = document.getElementById(`scheduleList${caseId}`);
                    if (scheduleList) {
                        scheduleList.innerHTML = `
                            <div class="schedule-item">
                                <div class="schedule-date">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Error loading schedules</span>
                                </div>
                            </div>
                        `;
                    }
                });
        }
        
        function loadCaseDocuments(caseId) {
            const formData = new FormData();
            formData.append('case_id', caseId);
            
            fetch('get_case_documents.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(documents => {
                    const documentsList = document.getElementById(`documentsList${caseId}`);
                    
                    if (!documentsList) {
                        console.error(`documentsList${caseId} element not found`);
                        return;
                    }
                    
                    if (documents.length === 0) {
                        documentsList.innerHTML = `
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="document-info">
                                    <div class="document-name">No documents uploaded</div>
                                    <div class="document-meta">Upload documents to track case files</div>
                                </div>
                            </div>
                        `;
                    } else {
                        documentsList.innerHTML = documents.map(doc => `
                            <div class="document-item horizontal-layout">
                                <div class="document-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="document-info">
                                    <div class="document-name">${doc.file_name}</div>
                                    <div class="document-meta">
                                        <span class="document-category">${doc.category}</span>
                                        <span class="document-size">${formatFileSize(doc.file_size)}</span>
                                        <span class="document-date">${formatDate(doc.uploaded_at || doc.upload_date)}</span>
                                    </div>
                                    ${doc.description ? `
                                    <div class="document-description">${doc.description}</div>
                                    ` : ''}
                                </div>
                                <div class="document-actions">
                                    <button class="btn-view" onclick="viewDocument('${doc.file_path}', '${doc.file_name}')" title="View Document">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-download" onclick="downloadDocument('${doc.file_path}', '${doc.file_name}')" title="Download Document">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    }
                })
                .catch(error => {
                    console.error('Error loading documents:', error);
                    const documentsList = document.getElementById(`documentsList${caseId}`);
                    if (documentsList) {
                        documentsList.innerHTML = `
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="document-info">
                                    <div class="document-name">Error loading documents</div>
                                    <div class="document-meta">Please try again later</div>
                                </div>
                            </div>
                        `;
                    }
                });
        }
        
        function openDocumentUpload(caseId) {
            // Check if attorney is assigned to this case
            const cases = <?= json_encode($cases) ?>;
            const caseData = cases.find(c => c.id == caseId);
            const attorneyId = <?= $_SESSION['user_id'] ?>;
            
            if (!caseData) {
                alert('Case not found.');
                return;
            }
            
            // Create upload modal
            const uploadModal = document.createElement('div');
            uploadModal.className = 'modal';
            uploadModal.style.display = 'block';
            uploadModal.innerHTML = `
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <div class="header-content">
                            <div class="case-icon">
                                <i class="fas fa-upload"></i>
                            </div>
                            <div class="header-text">
                                <h2>Upload Document</h2>
                                <p>Upload documents for this case (Max 10MB per file)</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body">
                        <form id="documentUploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="case_id" value="${caseId}">
                            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                                <input type="file" id="fileInput" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                                <div class="upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                <h3>Click to select files</h3>
                                <p>PDF, DOC, DOCX, JPG, PNG files are supported</p>
                                <p style="color: #dc3545; font-size: 0.9rem; margin-top: 0.5rem;"><strong>Maximum file size: 10MB per file</strong></p>
                            </div>
                        </div>
                        <div id="fileList"></div>
                        <div id="fileSizeError" style="display: none; color: #dc3545; background: #f8d7da; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #dc3545;"></div>
                            <div class="form-actions">
                                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                                <button type="submit" class="btn-primary" id="uploadBtn">Upload Documents</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(uploadModal);
            
            // Handle file selection
            document.getElementById('fileInput').addEventListener('change', function(e) {
                const fileList = document.getElementById('fileList');
                const fileSizeError = document.getElementById('fileSizeError');
                const uploadBtn = document.getElementById('uploadBtn');
                fileList.innerHTML = '';
                fileSizeError.style.display = 'none';
                
                const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                let hasErrors = false;
                let errorMessage = '';
                
                Array.from(e.target.files).forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    
                    // Check file size
                    if (file.size > maxSize) {
                        hasErrors = true;
                        errorMessage += `• ${file.name} (${formatFileSize(file.size)}) - exceeds 10MB limit<br>`;
                        fileItem.style.borderLeft = '4px solid #dc3545';
                        fileItem.style.backgroundColor = '#f8d7da';
                    }
                    
                    fileItem.innerHTML = `
                        <div class="file-info">
                            <i class="fas fa-file"></i>
                            <span class="file-name">${file.name}</span>
                            <span class="file-size">${formatFileSize(file.size)}</span>
                        </div>
                        <div class="file-inputs">
                            <div class="input-group">
                                <label>Document Name:</label>
                                <input type="text" name="doc_names[]" value="${file.name.replace(/\.[^/.]+$/, '')}" required>
                            </div>
                            <div class="input-group">
                                <label>Category:</label>
                                <select name="categories[]" required>
                                    <option value="">Select Category</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Financial Document">Financial Document</option>
                                    <option value="Legal Document">Legal Document</option>
                                    <option value="Evidence">Evidence</option>
                                    <option value="Correspondence">Correspondence</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="input-group full-width">
                                <label>Description:</label>
                                <textarea name="descriptions[]" placeholder="Optional description"></textarea>
                            </div>
                        </div>
                    `;
                    fileList.appendChild(fileItem);
                });
                
                if (hasErrors) {
                    fileSizeError.innerHTML = `<strong>File size errors:</strong><br>${errorMessage}`;
                    fileSizeError.style.display = 'block';
                    uploadBtn.disabled = true;
                    uploadBtn.style.opacity = '0.5';
                } else {
                    uploadBtn.disabled = false;
                    uploadBtn.style.opacity = '1';
                }
            });
            
            // Handle form submission
            document.getElementById('documentUploadForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                
                fetch('enhanced_document_upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Only show success modal if view case modal is still open
                        const caseModal = document.getElementById('caseModal');
                        if (caseModal && caseModal.style.display === 'block') {
                            showSuccessModal('File uploaded successfully!', () => {
                                closeUploadModal();
                                loadCaseDocuments(caseId); // Reload documents
                            });
                        } else {
                            // If view case modal is closed, just show alert
                            alert('File uploaded successfully!');
                            closeUploadModal();
                        }
                    } else {
                        alert('Error: ' + (data.error || 'Upload failed'));
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    alert('Upload failed. Please try again.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Upload Documents';
                });
            });
        }
        
        function closeUploadModal() {
            // Only remove upload modals, preserve caseModal
            const uploadModals = document.querySelectorAll('.modal');
            uploadModals.forEach(modal => {
                if (modal.id !== 'caseModal' && modal.id !== 'addCaseModal' && modal.id !== 'editCaseModal') {
                    modal.remove();
                }
            });
        }
        
        function showSuccessModal(message, onClose) {
            // Remove any existing success modals first
            const existingModals = document.querySelectorAll('.success-modal');
            existingModals.forEach(modal => modal.remove());
            
            const successModal = document.createElement('div');
            successModal.className = 'modal success-modal';
            successModal.style.display = 'block';
            successModal.innerHTML = `
                <div class="modal-content" style="max-width: 250px !important; width: 250px !important;">
                    <div class="modal-header" style="padding: 0.5rem;">
                        <div class="header-content" style="gap: 0.5rem;">
                            <div class="case-icon" style="background: linear-gradient(135deg, #28a745, #20c997); width: 1.5rem; height: 1.5rem; font-size: 0.8rem;">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="header-text">
                                <h2 style="font-size: 1rem; margin: 0;">Success!</h2>
                                <p style="font-size: 0.8rem; margin: 0;">${message}</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body" style="text-align: center; padding: 0.5rem;">
                        <div style="font-size: 1.5rem; color: #28a745; margin-bottom: 0.25rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <p style="font-size: 0.8rem; color: #333; margin-bottom: 0.5rem;">${message}</p>
                        <button class="btn-primary" onclick="closeSuccessModal()" style="padding: 6px 12px; font-size: 0.8rem; width: 60px;">OK</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(successModal);
            
            // Store the onClose callback
            window.successModalCallback = onClose;
        }
        
        function closeSuccessModal() {
            // Close success modal
            const successModal = document.querySelector('.success-modal');
            if (successModal) {
                successModal.remove();
            }
            
            // Close any upload modal that might still be open (but preserve caseModal)
            const uploadModals = document.querySelectorAll('.modal:not(.success-modal)');
            uploadModals.forEach(modal => {
                if (modal.id !== 'caseModal' && (modal.style.display === 'block' || modal.style.display === '')) {
                    modal.remove();
                }
            });
            
            // Also try to close by ID if it exists (but not caseModal)
            const uploadModalById = document.getElementById('uploadModal');
            if (uploadModalById) {
                uploadModalById.remove();
            }
            
            // Execute the callback if it exists and the case modal is still open
            if (window.successModalCallback) {
                const caseModal = document.getElementById('caseModal');
                if (caseModal && caseModal.style.display === 'block') {
                    window.successModalCallback();
                }
                window.successModalCallback = null;
            }
        }
        
        function viewDocument(filePath, fileName) {
            window.open(`view_file.php?path=${encodeURIComponent(filePath)}&name=${encodeURIComponent(fileName)}`, '_blank');
        }
        
        function downloadDocument(filePath, fileName) {
            const link = document.createElement('a');
            link.href = `view_file.php?path=${encodeURIComponent(filePath)}&name=${encodeURIComponent(fileName)}&download=1`;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'No date';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return 'Invalid Date';
            }
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
        
        
        function fetchClientProfile(clientId) {
            return fetch('attorney_cases.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_client_profile&client_id=${clientId}`
            })
            .then(response => response.text())
            .then(profileImage => {
                return profileImage || 'images/default-avatar.jpg';
            })
            .catch(() => 'images/default-avatar.jpg');
        }
        function openRequestModal(caseId, clientId) {
            // Close the view case modal first
            document.getElementById('summaryModal').style.display = 'none';
            
            document.getElementById('reqCaseId').value = caseId;
            document.getElementById('reqClientId').value = clientId;
            document.getElementById('requestsList').innerHTML = '';
            document.getElementById('requestModal').style.display = 'block';
            fetchRequests(caseId);
        }
        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
        }
        function fetchRequests(caseId) {
            const fd = new FormData();
            fd.append('action','list_requests');
            fd.append('case_id', caseId);
            fetch('attorney_cases.php', { method: 'POST', body: fd })
                .then(r=>r.json()).then(rows=>{
                    const wrap = document.getElementById('requestsList');
                    if (!rows.length) { wrap.innerHTML = '<p style="color:#888;">No requests yet.</p>'; return; }
                                         wrap.innerHTML = rows.map(r=>`
                         <div style="border:1px solid #eee;border-radius:8px;padding:10px;margin-bottom:8px;">
                             <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                 <div style="display:flex;align-items:center;gap:8px;">
                                     <strong>${r.title}</strong>
                                     <span class="status-badge status-${(r.status||'Requested').toLowerCase()}">${r.status}</span>
                                 </div>
                                 <div style="display:flex;gap:6px;">
                                     <button class="btn btn-info btn-xs" onclick="viewRequestFiles(${r.id})"><i class='fas fa-folder-open'></i> View Files</button>
                                     <button class="btn btn-warning btn-xs" onclick="openStatusModal(${r.id}, '${r.status||'Requested'}')"><i class='fas fa-edit'></i> Update Status</button>
                                 </div>
                             </div>
                             <div style="color:#555;margin-top:4px;">${r.description || ''}</div>
                             <div style="color:#888;margin-top:4px;">Due: ${r.due_date || '—'} • Uploads: ${r.upload_count}</div>
                             <div style="color:#aaa;margin-top:2px;">Created: ${r.created_at}</div>
                             ${r.attorney_comment ? `<div style="color:#1976d2;margin-top:4px;font-style:italic;">Attorney Comment: ${r.attorney_comment}</div>` : ''}
                             <div id="reqFiles-${r.id}" style="margin-top:8px;display:none;background:#fafafa;border:1px dashed #ddd;padding:8px;border-radius:8px;"></div>
                         </div>
                     `).join('');
                });
        }
        document.getElementById('requestForm').onsubmit = function(e){
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action','create_request');
            fetch('attorney_cases.php', { method:'POST', body: fd })
                .then(r=>r.text()).then(res=>{
                    // Trim whitespace and check response
                    const trimmedRes = res.trim();
                    console.log('Response:', trimmedRes); // Debug log
                    
                    if (trimmedRes === 'success') {
                        alert('Document request created and client notified.');
                        fetchRequests(document.getElementById('reqCaseId').value);
                        this.reset();
                    } else {
                        console.error('Unexpected response:', res); // Debug log
                        alert('Error creating request');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Network error occurred');
                });
        };
        function viewRequestFiles(requestId) {
            const box = document.getElementById('reqFiles-'+requestId);
            const fd = new FormData();
            fd.append('action','list_request_files');
            fd.append('request_id', requestId);
            fetch('attorney_cases.php', { method:'POST', body: fd })
                .then(r=>r.json()).then(files=>{
                    if (files.length===0) { box.innerHTML = '<em style="color:#888;">No files uploaded yet.</em>'; }
                    else {
                        box.innerHTML = files.map(f=>`<div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:6px;"><a href="${f.file_path}" target="_blank">${f.original_name}</a><span style="color:#888;">${f.uploaded_at}</span></div>`).join('');
                    }
                    box.style.display = 'block';
                });
        }
                 function openStatusModal(requestId, currentStatus) {
             document.getElementById('statusUpdateRequestId').value = requestId;
             document.getElementById('statusUpdateStatus').value = currentStatus;
             document.getElementById('statusUpdateComment').value = '';
             document.getElementById('statusUpdateModal').style.display = 'block';
         }
         
         function closeStatusUpdateModal() {
             document.getElementById('statusUpdateModal').style.display = 'none';
         }
         
         document.getElementById('statusUpdateForm').onsubmit = function(e) {
             e.preventDefault();
             const formData = new FormData(this);
             formData.append('action', 'update_request_status');
             
             fetch('attorney_cases.php', { method: 'POST', body: formData })
                 .then(r => r.text()).then(res => {
                     if (res === 'success') {
                         alert('Status updated successfully!');
                         closeStatusUpdateModal();
                         fetchRequests(document.getElementById('reqCaseId').value);
                     } else {
                         alert('Failed to update status');
                     }
                 });
         };
    </script>
    <script>
        // Set global variables for the enhanced modal
        window.userRole = 'attorney';
        window.userId = <?= $attorney_id ?>;
        
        // Pagination variables
        let currentPage = 1;
        let itemsPerPage = 10;
        let totalItems = 0;
        let filteredItems = [];
        let allCases = [];
        
        // Initialize pagination
        function initializePagination() {
            // Get all case cards
            const caseCards = document.querySelectorAll('.case-card');
            allCases = Array.from(caseCards);
            totalItems = allCases.length;
            filteredItems = [...allCases];
            
            // Always show pagination
            document.getElementById('paginationContainer').style.display = 'flex';
            updatePagination();
        }
        
        // Update pagination display
        function updatePagination() {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, filteredItems.length);
            
            // Update pagination info
            document.getElementById('paginationInfo').textContent = 
                `Showing ${startItem}-${endItem} of ${filteredItems.length} cases`;
            
            // Update page numbers
            updatePageNumbers(totalPages);
            
            // Update prev/next buttons
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage === totalPages;
            
            // Show/hide cards based on current page
            showCurrentPageCards();
        }
        
        // Update page numbers display
        function updatePageNumbers(totalPages) {
            const paginationNumbers = document.getElementById('paginationNumbers');
            let html = '';
            
            if (totalPages <= 7) {
                // Show all pages if 7 or fewer
                for (let i = 1; i <= totalPages; i++) {
                    html += `<span class="page-number ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</span>`;
                }
            } else {
                // Show first page
                html += `<span class="page-number ${currentPage === 1 ? 'active' : ''}" onclick="goToPage(1)">1</span>`;
                
                if (currentPage > 3) {
                    html += '<span class="page-ellipsis">...</span>';
                }
                
                // Show pages around current page
                const start = Math.max(2, currentPage - 1);
                const end = Math.min(totalPages - 1, currentPage + 1);
                
                for (let i = start; i <= end; i++) {
                    html += `<span class="page-number ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</span>`;
                }
                
                if (currentPage < totalPages - 2) {
                    html += '<span class="page-ellipsis">...</span>';
                }
                
                // Show last page
                if (totalPages > 1) {
                    html += `<span class="page-number ${currentPage === totalPages ? 'active' : ''}" onclick="goToPage(${totalPages})">${totalPages}</span>`;
                }
            }
            
            paginationNumbers.innerHTML = html;
        }
        
        // Show cards for current page
        function showCurrentPageCards() {
            // Hide all cards first
            allCases.forEach(card => {
                card.style.display = 'none';
            });
            
            // Show cards for current page
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            
            filteredItems.slice(startIndex, endIndex).forEach(card => {
                card.style.display = 'block';
            });
        }
        
        // Go to specific page
        function goToPage(page) {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                updatePagination();
                // Scroll to top of cases grid
                document.getElementById('casesGrid').scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Change page (previous/next)
        function changePage(direction) {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
            const newPage = currentPage + direction;
            
            if (newPage >= 1 && newPage <= totalPages) {
                goToPage(newPage);
            }
        }
        
        // Update items per page
        function updateItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
            currentPage = 1; // Reset to first page
            updatePagination();
        }
        
        // Enhanced filtering system with pagination
        function applyFiltersWithPagination() {
            const caseCards = document.querySelectorAll('.case-card');
            const scopeFilter = document.querySelector('.case-filter-btn.active').dataset.filter;
            const statusFilter = document.querySelector('.filter-btn.active').dataset.status;
            const typeFilter = document.getElementById('typeFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const currentAttorneyId = <?= $attorney_id ?>;
            
            filteredItems = [];
            
            caseCards.forEach(card => {
                const cardStatus = card.dataset.status;
                const cardType = card.dataset.type;
                const cardAttorneyId = card.dataset.attorneyId;
                const clientName = card.querySelector('.client-name').textContent.toLowerCase();
                
                let show = true;
                
                // Scope filter
                if (scopeFilter === 'my' && cardAttorneyId != currentAttorneyId) {
                    show = false;
                }
                
                // Status filter
                if (statusFilter && cardStatus !== statusFilter) {
                    show = false;
                }
                
                // Type filter
                if (typeFilter && cardType !== typeFilter) {
                    show = false;
                }
                
                // Search filter
                if (searchTerm && !clientName.includes(searchTerm)) {
                    show = false;
                }
                
                if (show) {
                    filteredItems.push(card);
                }
            });
            
            // Reset to first page and update pagination
            currentPage = 1;
            
            // Always show pagination
            document.getElementById('paginationContainer').style.display = 'flex';
            updatePagination();
        }
        
        // Override the existing applyFilters function
        function applyFilters() {
            applyFiltersWithPagination();
        }
        
        // Initialize filters when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            initializePagination();
        });
        
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html> 