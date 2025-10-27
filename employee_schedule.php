<?php
// Handle AJAX for filtering attorneys by client
if (isset($_POST['action']) && $_POST['action'] === 'get_attorneys_for_client') {
    header('Content-Type: application/json');
    require_once 'config.php';
    $client_id = intval($_POST['client_id'] ?? 0);
    $list = [];
    if ($client_id > 0) {
        $stmt = $conn->prepare("SELECT DISTINCT uf.id, uf.name FROM client_attorney_assignments caa JOIN user_form uf ON caa.attorney_id = uf.id WHERE caa.client_id = ? ORDER BY uf.name");
        $stmt->bind_param('i', $client_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $list[] = $row; }
        }
    }
    echo json_encode(['success' => true, 'attorneys' => $list]);
    exit();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session_manager.php';
require_once 'config.php';

// Fetch pending requests count for notification badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM client_request_form WHERE status = 'Pending'");
$stmt->execute();
$pending_requests_count = $stmt->get_result()->fetch_row()[0];

require_once 'audit_logger.php';
require_once 'color_manager.php';
require_once 'color_assignment.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Check session variables (after session is started)
error_log("Session variables: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login_form.php');
    exit;
}

// Validate user access
if ($_SESSION['user_type'] !== 'employee') {
    header('Location: login_form.php');
    exit;
}

$employee_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Fetch all registered attorneys and admins for dropdown
$attorneys_and_admins = [];
$stmt = $conn->prepare("SELECT id, name, user_type FROM user_form WHERE user_type IN ('attorney', 'admin') ORDER BY user_type, name");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $attorneys_and_admins[] = $row;

// Fetch all cases for dropdown
$cases = [];
$stmt = $conn->prepare("SELECT ac.id, ac.title, uf.name as client_name FROM attorney_cases ac LEFT JOIN user_form uf ON ac.client_id = uf.id ORDER BY ac.id DESC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $cases[] = $row;

// Fetch only clients with approved attorney assignments
$clients = [];
$stmt = $conn->prepare("
    SELECT DISTINCT uf.id, uf.name, uf.email 
    FROM user_form uf 
    WHERE uf.user_type = 'client' 
    AND uf.id IN (SELECT client_id FROM client_attorney_assignments)
    ORDER BY uf.name
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Handle add event
if (isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $type = $_POST['type'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);
    $case_id = !empty($_POST['case_id']) ? intval($_POST['case_id']) : null;
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $description = trim($_POST['description']);
    $selected_user_id = intval($_POST['selected_user_id']);
    
    // Validate required fields
    if (empty($location)) {
        echo 'error: Location is required';
        exit;
    }
    
    if (empty($description)) {
        echo 'error: Description is required';
        exit;
    }
    
    if (empty($date)) {
        echo 'error: Date is required';
        exit;
    }
    
    if (empty($start_time)) {
        echo 'error: Start time is required';
        exit;
    }
    
    if (empty($end_time)) {
        echo 'error: End time is required';
        exit;
    }
    
    if ($end_time <= $start_time) {
        echo 'error: End time must be after start time';
        exit;
    }
    
    if (!$client_id) {
        echo 'error: Client selection is required';
        exit;
    }
    
    if (!$selected_user_id) {
        echo 'error: Attorney selection is required';
        exit;
    }
    
    // Check for time conflicts - prevent double booking
    $stmt = $conn->prepare("SELECT id, type, start_time, end_time FROM case_schedules WHERE attorney_id = ? AND date = ? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?)) AND status != 'Cancelled'");
    $stmt->bind_param("isssssss", $selected_user_id, $date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $conflict_result = $stmt->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $conflict_event = $conflict_result->fetch_assoc();
        $conflict_start = date('g:i A', strtotime($conflict_event['start_time']));
        $conflict_end = date('g:i A', strtotime($conflict_event['end_time']));
        echo 'error: The selected attorney already has a ' . $conflict_event['type'] . ' scheduled from ' . $conflict_start . ' to ' . $conflict_end . ' on ' . $date . ' that conflicts with your time slot. Please choose a different time.';
        exit;
    }
    
    // If case_id is provided, get client_id from case
    if ($case_id) {
        $stmt = $conn->prepare("SELECT client_id FROM attorney_cases WHERE id=?");
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $q = $stmt->get_result();
        if ($r = $q->fetch_assoc()) $client_id = $r['client_id'];
    }
    
    $stmt = $conn->prepare("INSERT INTO case_schedules (case_id, attorney_id, client_id, type, description, date, start_time, end_time, location, created_by_employee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiissssssi', $case_id, $selected_user_id, $client_id, $type, $description, $date, $start_time, $end_time, $location, $employee_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log to audit trail
        try {
            $auditLogger = new AuditLogger($conn);
            // Fetch attorney and client names for richer audit context
            $attorney_name = '';
            $stmt_att = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_att->bind_param('i', $selected_user_id);
            $stmt_att->execute();
            $attorney_row = $stmt_att->get_result()->fetch_assoc();
            if ($attorney_row && !empty($attorney_row['name'])) { $attorney_name = $attorney_row['name']; }

            $client_name = '';
            if (!empty($client_id)) {
                $stmt_cli = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                $stmt_cli->bind_param('i', $client_id);
                $stmt_cli->execute();
                $client_row = $stmt_cli->get_result()->fetch_assoc();
                if ($client_row && !empty($client_row['name'])) { $client_name = $client_row['name']; }
            }

            $details = "Created by: " . ($_SESSION['user_name'] ?? 'Employee') . "; Attorney: " . ($attorney_name !== '' ? $attorney_name : (string)$selected_user_id) . "; Client: " . ($client_name !== '' ? $client_name : ($client_id ? (string)$client_id : 'N/A')) . "; Type: $type; Date: $date; Time: $start_time-$end_time; Location: $location";
            $auditLogger->logAction(
                $employee_id,
                $_SESSION['user_name'] ?? 'Employee',
                'employee',
                'Schedule Created',
                'Schedule Management',
                $details,
                'success',
                'medium'
            );
        } catch (Exception $auditError) {
            error_log("Audit logging failed: " . $auditError->getMessage());
        }
        
        // Notify attorney about the new schedule
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get employee name for notification
            $stmt_employee = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_employee->bind_param('i', $employee_id);
            $stmt_employee->execute();
            $employee_name = $stmt_employee->get_result()->fetch_assoc()['name'];
            
            $nTitle = 'New Schedule Assigned';
            $nMsg = "A new $type has been scheduled for you by employee: $employee_name on $date from $start_time to $end_time at $location";
            $userType = 'attorney';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $selected_user_id, $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
        
        // Notify client if client_id exists
        if ($client_id && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get employee name for notification
            $stmt_employee = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_employee->bind_param('i', $employee_id);
            $stmt_employee->execute();
            $employee_name = $stmt_employee->get_result()->fetch_assoc()['name'];
            
            $nTitle = 'New Schedule Created';
            $nMsg = "A new $type has been scheduled for you by employee: $employee_name on $date from $start_time to $end_time at $location";
            $userType = 'client';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $client_id, $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
        
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

// Handle edit event
if (isset($_POST['action']) && $_POST['action'] === 'edit_event') {
    $event_id = intval($_POST['event_id']);
    $type = $_POST['type'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    
    // Validate time range (8AM-6PM)
    $startHour = intval(substr($start_time, 0, 2));
    $endHour = intval(substr($end_time, 0, 2));
    
    if ($startHour < 8 || $startHour >= 18) {
        echo 'error: You can only schedule between 8:00 AM and 6:00 PM.';
        exit;
    }
    
    if ($endHour < 8 || $endHour > 18) {
        echo 'error: You can only schedule between 8:00 AM and 6:00 PM.';
        exit;
    }
    
    // Validate date restriction (no current day)
    $today = date('Y-m-d');
    if ($date <= $today) {
        echo 'error: You can\'t create a schedule for today.';
        exit;
    }
    
    // Validate required fields
    if (empty($location)) {
        echo 'error: Location is required';
        exit;
    }
    
    if (empty($description)) {
        echo 'error: Description is required';
        exit;
    }
    
    if (empty($date)) {
        echo 'error: Date is required';
        exit;
    }
    
    if (empty($start_time)) {
        echo 'error: Start time is required';
        exit;
    }
    
    if (empty($end_time)) {
        echo 'error: End time is required';
        exit;
    }
    
    if ($end_time <= $start_time) {
        echo 'error: End time must be after start time';
        exit;
    }
    
    // Validate that date is not in the past
    $today = date('Y-m-d');
    if ($date < $today) {
        echo 'error: Cannot schedule events in the past';
        exit;
    }
    
    // Check if event is completed
    $stmt = $conn->prepare("SELECT status FROM case_schedules WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['status'] && strtolower($row['status']) === 'completed') {
            echo 'error: Cannot edit completed schedules';
            exit;
        }
    }
    
    // Update the event (case_id and client_id remain unchanged)
    $stmt = $conn->prepare("UPDATE case_schedules SET type=?, description=?, date=?, start_time=?, end_time=?, location=? WHERE id=?");
    if (!$stmt) {
        echo 'error: Failed to prepare statement: ' . $conn->error;
        exit;
    }
    
    $stmt->bind_param('ssssssi', $type, $description, $date, $start_time, $end_time, $location, $event_id);
    if (!$stmt->execute()) {
        echo 'error: Failed to execute statement: ' . $stmt->error;
        exit;
    }
    
    if ($stmt->affected_rows > 0) {
        // Get schedule details for notification
        $stmt_info = $conn->prepare("SELECT attorney_id, client_id, walkin_client_name FROM case_schedules WHERE id = ?");
        $stmt_info->bind_param('i', $event_id);
        $stmt_info->execute();
        $schedule_info = $stmt_info->get_result()->fetch_assoc();
        
        // Get employee name for notification
        $stmt_employee = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
        $stmt_employee->bind_param('i', $employee_id);
        $stmt_employee->execute();
        $employee_name = $stmt_employee->get_result()->fetch_assoc()['name'];
        
        // Notify attorney about schedule update
        if ($schedule_info['attorney_id'] && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'Schedule Updated';
            $nMsg = "Your schedule has been updated by employee: $employee_name on $date from $start_time to $end_time at $location";
            $userType = 'attorney';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $schedule_info['attorney_id'], $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
        
        // Notify client about schedule update (if client exists)
        if ($schedule_info['client_id'] && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'Schedule Updated';
            $nMsg = "Your schedule has been updated by employee: $employee_name on $date from $start_time to $end_time at $location";
            $userType = 'client';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $schedule_info['client_id'], $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
    }
    
    // Always return success for employees (like admin)
    echo 'success';
    exit();
}

// Handle walk-in schedule
if (isset($_POST['action']) && $_POST['action'] === 'add_walkin') {
    $client_surname = trim($_POST['client_surname']);
    $client_first_name = trim($_POST['client_first_name']);
    $client_middle_name = trim($_POST['client_middle_name']);
    
    // Combine names into full name
    $client_name = trim($client_surname . ', ' . $client_first_name . ($client_middle_name ? ' ' . $client_middle_name : ''));
    
    $client_contact = trim($_POST['client_contact']);
    $client_contact_confirm = trim($_POST['client_contact_confirm']);
    $type = $_POST['type'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    $selected_user_id = intval($_POST['selected_user_id']);
    
    // Validate required fields
    if (empty($client_surname)) {
        echo 'error: Surname is required';
        exit;
    }
    
    if (empty($client_first_name)) {
        echo 'error: First name is required';
        exit;
    }
    
    if (empty($client_contact)) {
        echo 'error: Contact number is required';
        exit;
    }
    
    if (empty($client_contact_confirm)) {
        echo 'error: Contact number confirmation is required';
        exit;
    }
    
    if ($client_contact !== $client_contact_confirm) {
        echo 'error: Contact numbers do not match';
        exit;
    }
    
    if (!preg_match('/^[0-9]{11}$/', $client_contact)) {
        echo 'error: Contact number must be exactly 11 digits';
        exit;
    }
    
    if (empty($location)) {
        echo 'error: Location is required';
        exit;
    }
    
    if (empty($description)) {
        echo 'error: Description is required';
        exit;
    }
    
    if (empty($date)) {
        echo 'error: Date is required';
        exit;
    }
    
    if (empty($start_time)) {
        echo 'error: Start time is required';
        exit;
    }
    
    if (empty($end_time)) {
        echo 'error: End time is required';
        exit;
    }
    
    if ($end_time <= $start_time) {
        echo 'error: End time must be after start time';
        exit;
    }
    
    if (!$selected_user_id) {
        echo 'error: Attorney selection is required';
        exit;
    }
    
    // Check for time conflicts - prevent double booking
    $stmt = $conn->prepare("SELECT id, type, start_time, end_time FROM case_schedules WHERE attorney_id = ? AND date = ? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?)) AND status != 'Cancelled'");
    $stmt->bind_param("isssssss", $selected_user_id, $date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $conflict_result = $stmt->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $conflict_event = $conflict_result->fetch_assoc();
        $conflict_start = date('g:i A', strtotime($conflict_event['start_time']));
        $conflict_end = date('g:i A', strtotime($conflict_event['end_time']));
        echo 'error: The selected attorney already has a ' . $conflict_event['type'] . ' scheduled from ' . $conflict_start . ' to ' . $conflict_end . ' on ' . $date . ' that conflicts with your time slot. Please choose a different time.';
        exit;
    }
    
    // For walk-in clients, we don't have a client_id, so we'll use NULL
    $client_id = null;
    $case_id = null;
    
    // Insert walk-in schedule
    $stmt = $conn->prepare("INSERT INTO case_schedules (case_id, attorney_id, client_id, walkin_client_name, walkin_client_contact, type, description, date, start_time, end_time, location, created_by_employee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiissssssssi', $case_id, $selected_user_id, $client_id, $client_name, $client_contact, $type, $description, $date, $start_time, $end_time, $location, $employee_id);
    
    if ($stmt->execute()) {
        $schedule_id = $conn->insert_id;
        
        // Log the walk-in schedule creation with full details
        try {
            $auditLogger = new AuditLogger($conn);
            
            // Get employee name
            $employee_name = $_SESSION['user_name'] ?? 'Employee';
            
            // Fetch attorney name
            $attorney_name = '';
            $stmt_att = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_att->bind_param('i', $selected_user_id);
            $stmt_att->execute();
            $attorney_row = $stmt_att->get_result()->fetch_assoc();
            if ($attorney_row && !empty($attorney_row['name'])) { 
                $attorney_name = $attorney_row['name']; 
            }
            
            $details = "Created by: $employee_name; Attorney: " . ($attorney_name !== '' ? $attorney_name : (string)$selected_user_id) . "; Walk-in Client: $client_name (Contact: $client_contact); Type: $type; Date: $date; Time: $start_time-$end_time; Location: $location";
            
            $auditLogger->logAction(
                $employee_id,
                $employee_name,
                'employee',
                'Walk-in Schedule Created',
                'Schedule Management',
                $details,
                'success',
                'medium'
            );
        } catch (Exception $auditError) {
            error_log("Audit logging failed: " . $auditError->getMessage());
        }
        
        // Notify attorney about the new schedule
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get employee name for notification
            $stmt_employee = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_employee->bind_param('i', $employee_id);
            $stmt_employee->execute();
            $employee_name = $stmt_employee->get_result()->fetch_assoc()['name'];
            
            $nTitle = 'New Schedule Assigned';
            $nMsg = "A new $type has been scheduled for you by employee: $employee_name on $date from $start_time to $end_time at $location";
            $userType = 'attorney';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $selected_user_id, $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
        
        echo 'success';
    } else {
        echo 'error: Failed to create walk-in schedule';
    }
    
    $stmt->close();
    exit();
}

// Get schedule details for edit form
if (isset($_POST['action']) && $_POST['action'] === 'get_schedule_details') {
    header('Content-Type: application/json');
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    
    if ($schedule_id > 0) {
        $stmt = $conn->prepare("SELECT client_id FROM case_schedules WHERE id = ?");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'client_id' => $row['client_id']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Schedule not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    }
    exit();
}

// Fetch all events with joins
$events = [];
try {
    $stmt = $conn->prepare("SELECT cs.*, ac.title as case_title, 
        CASE 
            WHEN cs.attorney_id IS NOT NULL THEN cs.attorney_id 
            ELSE ac.attorney_id 
        END as final_attorney_id,
        uf1.name as attorney_name, uf1.user_type as attorney_user_type, 
        CASE 
            WHEN cs.client_id IS NOT NULL THEN uf2.name 
            WHEN cs.walkin_client_name IS NOT NULL THEN cs.walkin_client_name
            ELSE 'Walk-in Client'
        END as client_name, 
        uf3.name as created_by_name,
        CASE 
            WHEN cs.walkin_client_name IS NOT NULL THEN cs.walkin_client_name
            ELSE NULL
        END as walkin_client_name,
        CASE 
            WHEN cs.walkin_client_contact IS NOT NULL THEN cs.walkin_client_contact
            ELSE NULL
        END as walkin_client_contact
        FROM case_schedules cs
        LEFT JOIN attorney_cases ac ON cs.case_id = ac.id
        LEFT JOIN user_form uf1 ON (
            CASE 
                WHEN cs.attorney_id IS NOT NULL THEN cs.attorney_id 
                ELSE ac.attorney_id 
            END
        ) = uf1.id
        LEFT JOIN user_form uf2 ON cs.client_id = uf2.id
        LEFT JOIN user_form uf3 ON cs.created_by_employee_id = uf3.id
        ORDER BY CASE WHEN uf1.user_type = 'admin' THEN 1 WHEN uf1.user_type = 'attorney' THEN 2 ELSE 3 END, cs.date, cs.start_time");
    
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $events[] = $row;
        error_log("Events query executed successfully. Found " . count($events) . " events.");
    } else {
        error_log("Failed to prepare events query");
    }
} catch (Exception $e) {
    error_log("Error fetching events: " . $e->getMessage());
    $events = [];
}

// Debug: Log the events count
error_log("Total events fetched: " . count($events));

// Get all admin IDs to check if event is assigned to admin
$admin_ids = [];
$stmt = $conn->prepare("SELECT id FROM user_form WHERE user_type = 'admin'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $admin_ids[] = $row['id'];
}

$js_events = [];
$calendar_events = []; // Separate array for calendar view (excludes completed events)
foreach ($events as $ev) {
    // Debug: Log the attorney information
    error_log("Employee Schedule Event: " . $ev['type'] . " - Attorney ID: " . ($ev['final_attorney_id'] ?? 'NULL') . " - Attorney Name: " . ($ev['attorney_name'] ?? 'NULL') . " - Status: " . ($ev['status'] ?? 'NULL'));
    
    // Get colors for the attorney/admin assigned to this event
    $attorneyId = $ev['final_attorney_id'] ?? 0;
    $attorneyUserType = $ev['attorney_user_type'] ?? 'attorney';
    $eventColors = getEventColors($attorneyId, $attorneyUserType);
    
    // Check if this is an admin event
    $isAdminEvent = in_array($ev['final_attorney_id'], $admin_ids);
    
    $js_events[] = [
        'id' => $ev['id'], // Add ID for calendar event removal
        'title' => $ev['type'] . ': ' . ($ev['case_title'] ?? ''),
        'start' => $ev['date'] . 'T' . $ev['start_time'],
        'end' => $ev['date'] . 'T' . $ev['end_time'],
        'type' => $ev['type'],
        'description' => $ev['description'],
        'location' => $ev['location'], // Full location for modal
        'location_display' => '', // Short display for cards
        'case' => $ev['case_title'],
        'attorney' => $ev['attorney_name'],
        'attorney_user_type' => $ev['attorney_user_type'] ?? 'unknown',
        'client' => $ev['client_name'],
        'employee' => $ev['created_by_name'],
        'color' => $eventColors['calendar_event_color'],
        'backgroundColor' => $eventColors['calendar_event_color'],
        'borderColor' => $eventColors['calendar_event_color'],
        'textColor' => $isAdminEvent ? '#ffffff' : '#000000',
        'extendedProps' => [
            'eventType' => $ev['type'],
            'attorneyName' => $ev['attorney_name'] ?? 'Unknown',
            'attorneyUserType' => $ev['attorney_user_type'] ?? 'unknown',
            'attorneyId' => $ev['final_attorney_id'] ?? 0,
            'createdBy' => $ev['created_by_name'] ?? 'Unknown',
            'scheduleCardColor' => $eventColors['schedule_card_color'],
            'calendarEventColor' => $eventColors['calendar_event_color'],
            'colorName' => $eventColors['color_name']
        ]
    ];
    
    // Only add non-completed events to calendar view
    $status = $ev['status'] ?? 'scheduled';
    $status_lower = strtolower($status);
    
    if ($status_lower !== 'completed') {
        $calendar_events[] = [
            'id' => $ev['id'],
            'title' => $ev['type'] . ': ' . ($ev['case_title'] ?? ''),
            'start' => $ev['date'] . 'T' . $ev['start_time'],
            'end' => $ev['date'] . 'T' . $ev['end_time'],
            'type' => $ev['type'],
            'description' => $ev['description'],
            'location' => $ev['location'],
            'location_display' => '',
            'case' => $ev['case_title'],
            'attorney' => $ev['attorney_name'],
            'attorney_user_type' => $ev['attorney_user_type'] ?? 'unknown',
            'client' => $ev['client_name'],
            'employee' => $ev['created_by_name'],
            'color' => $eventColors['calendar_event_color'],
            'backgroundColor' => $eventColors['calendar_event_color'],
            'borderColor' => $eventColors['calendar_event_color'],
            'textColor' => $isAdminEvent ? '#ffffff' : '#000000',
            'extendedProps' => [
                'eventType' => $ev['type'],
                'attorneyName' => $ev['attorney_name'] ?? 'Unknown',
                'attorneyUserType' => $ev['attorney_user_type'] ?? 'unknown',
                'attorneyId' => $ev['final_attorney_id'] ?? 0,
                'createdBy' => $ev['created_by_name'] ?? 'Unknown',
                'scheduleCardColor' => $eventColors['schedule_card_color'],
                'calendarEventColor' => $eventColors['calendar_event_color'],
                'colorName' => $eventColors['color_name']
            ]
        ];
        error_log("Added to calendar_events - ID: " . $ev['id'] . ", Status: '$status'");
    } else {
        error_log("SKIPPED calendar_events - ID: " . $ev['id'] . ", Status: '$status' (COMPLETED)");
    }
}

// Debug: Log the final counts
error_log("Total events: " . count($events) . ", Calendar events: " . count($calendar_events));
error_log("Total js_events: " . count($js_events));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <style>
        /* Calendar container styles */
        .calendar-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            min-height: 600px;
        }
        
        #calendar {
            width: 100%;
            height: 100%;
            min-height: 500px;
        }
        
        /* FullCalendar custom styles */
        .fc {
            font-family: 'Poppins', sans-serif;
        }
        
        .fc-toolbar {
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .fc-toolbar-title {
            color: #5D0E26;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .fc-button {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
        }
        
        .fc-button:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .fc-button.active {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.4);
        }
        
        .fc-button:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.2) !important;
        }
        
        .fc-daygrid-day {
            border: 1px solid #e9ecef;
        }
        
        .fc-event {
            border-radius: 6px;
            border: none;
            font-size: 12px;
            padding: 2px 6px;
            cursor: pointer;
        }
    </style>
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
            <li><a href="employee_schedule.php" class="active"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="employee_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="employee_request_management.php"><i class="fas fa-clipboard-check"></i><span>Request Review</span><?php if ($pending_requests_count > 0): ?><span class="notification-badge"><?= $pending_requests_count ?></span><?php endif; ?></a></li>
            <li><a href="employee_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Schedule Management';
        $page_subtitle = 'Create and manage court hearings, meetings, and appointments';
        include 'components/profile_header.php'; 
        ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary" id="addEventBtn">
                <i class="fas fa-plus"></i> Add Schedule
            </button>

            <button class="btn btn-walkin" id="addWalkinBtn">
                <i class="fas fa-walking"></i> Walk-in Schedule
            </button>
            <div class="view-options">
                <button class="btn btn-secondary active" data-view="month">
                    <i class="fas fa-calendar"></i> Month
                </button>
                <button class="btn btn-secondary" data-view="week">
                    <i class="fas fa-calendar-week"></i> Week
                </button>
                <button class="btn btn-secondary" data-view="day">
                    <i class="fas fa-calendar-day"></i> Day
                </button>
            </div>
        </div>
        
        <!-- Calendar Container -->
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>

        <!-- Events Filter Controls -->
        <div class="events-filter-section">
            <div class="filter-controls">
                <!-- Status Filter -->
                <div class="status-filter-container">
                    <span class="filter-label">Filter by Status:</span>
                    <div class="status-filter-buttons">
                        <button class="status-filter-btn active" data-status="all">All</button>
                        <button class="status-filter-btn" data-status="scheduled">Scheduled</button>
                        <button class="status-filter-btn" data-status="rescheduled">Rescheduled</button>
                        <button class="status-filter-btn" data-status="cancelled">Cancelled</button>
                    </div>
                </div>

                <!-- Show Completed Button -->
                <button class="show-completed-btn" onclick="toggleCompletedSection()">
                    <i class="fas fa-check-circle"></i> Show Completed
                </button>
            </div>
        </div>

        <!-- Enhanced Upcoming Events -->
        <div class="upcoming-events-section">
            <div class="section-header">
                <div class="header-text">
                    <h2><i class="fas fa-calendar-check"></i> Upcoming Events</h2>
                    <p>Manage and monitor all scheduled activities</p>
                </div>
            </div>
            
            <?php if (empty($events)): ?>
                <div class="no-events">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Upcoming Events</h3>
                    <p>No events are currently scheduled. Start by adding new events to your calendar.</p>
                </div>
            <?php else: ?>
                <?php
                // Group events by priority
                $admin_events = [];
                $attorney_events = [];
                $other_events = [];
                
                foreach ($events as $ev) {
                    if ($ev['attorney_user_type'] == 'admin') {
                        $admin_events[] = $ev;
                    } elseif ($ev['attorney_user_type'] == 'attorney') {
                        $attorney_events[] = $ev;
                    } else {
                        $other_events[] = $ev;
                    }
                }
                ?>
                
                <!-- Admin's Own Schedules -->
                <?php if (!empty($admin_events)): ?>
                <div class="priority-section admin-priority" style="margin-bottom: 3rem;">
                    <div class="priority-header">
                        <i class="fas fa-crown"></i>
                        <h3>Admin Schedules</h3>
                        <span class="priority-badge">Priority 1</span>
                    </div>

                    <div class="events-grid">
                        <?php foreach ($admin_events as $ev): ?>
                            <div class="event-card admin-event status-<?= strtolower($ev['status'] ?? 'scheduled') ?><?= (strtolower($ev['status'] ?? 'scheduled') === 'completed') ? ' completed-schedule' : '' ?>" data-event-id="<?= $ev['id'] ?>" data-event-type="<?= htmlspecialchars($ev['type']) ?>" data-title="<?= htmlspecialchars($ev['title'] ?? '') ?>" data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? 'Admin') ?>" data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>" data-user-id="<?= $ev['final_attorney_id'] ?? 0 ?>">
                                <div class="event-card-header">
                                    <div class="event-avatar">
                                        <div class="avatar-placeholder">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                    </div>
                                    <div class="event-info">
                                        <h3><?= htmlspecialchars($ev['type']) ?></h3>
                                        <p class="event-title"><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($ev['start_time'] ?? '00:00:00')) ?> - <?= date('g:i A', strtotime($ev['end_time'] ?? '00:00:00')) ?></p>
                                        <p class="case-detail"><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($ev['date'] ?? date('Y-m-d'))) ?></p>
                                        <p class="client-detail"><i class="fas fa-user"></i> <?= htmlspecialchars($ev['client_name'] ?? 'No Client') ?></p>
                                    </div>
                                </div>

                                <div class="event-actions">
                                    <div class="status-edit-section">
                                        <select class="status-select" data-event-id="<?= $ev['id'] ?>" onchange="updateEventStatus(this)" data-previous-status="<?= htmlspecialchars($ev['status']) ?>">
                                            <option value="Scheduled" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                                            <option value="Completed" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'completed') ? 'selected' : '' ?>>Completed</option>
                                            <option value="Cancelled" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                            <option value="Rescheduled" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'rescheduled') ? 'selected' : '' ?>>Rescheduled</option>
                                        </select>
                                        <button class="btn btn-warning btn-sm edit-event-btn" onclick="editEvent(this)" 
                                            data-event-id="<?= $ev['id'] ?>"
                                            data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"
                                            data-type="<?= htmlspecialchars($ev['type']) ?>"
                                            data-date="<?= htmlspecialchars($ev['date']) ?>"
                                            data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"
                                            data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"
                                            data-location="<?= htmlspecialchars($ev['location'] ?? '') ?>"
                                            data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                                            data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"
                                            data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"
                                            data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"
                                            data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"
                                            data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                            data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-info btn-sm view-info-btn" 
                                            data-event-id="<?= $ev['id'] ?>"
                                            data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"
                                            data-type="<?= htmlspecialchars($ev['type']) ?>"
                                            data-date="<?= htmlspecialchars($ev['date']) ?>"
                                            data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"
                                            data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"
                                            data-location="<?= htmlspecialchars($ev['location'] ?? '') ?>"
                                            data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                                            data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"
                                            data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"
                                            data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"
                                            data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"
                                            data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"
                                            data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="items-per-page">
                            <label for="admin-items-per-page">Show:</label>
                            <select id="admin-items-per-page">
                                <option value="6">6</option>
                                <option value="12">12</option>
                                <option value="18">18</option>
                                <option value="24">24</option>
                            </select>
                        </div>
                        <span class="pagination-info" id="admin-pagination-info">Page 1</span>
                        <div class="pagination-buttons">
                            <button class="btn-pagination" id="admin-prev-btn" disabled>Previous</button>
                            <button class="btn-pagination" id="admin-next-btn">Next</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Other Attorneys' Schedules - Grouped by Attorney -->
                <?php if (!empty($attorney_events)): ?>
                <div class="priority-section attorney-priority" style="margin-top: 2rem;">
                    <div class="priority-header">
                        <i class="fas fa-user-tie"></i>
                        <h3>Attorney Schedules</h3>
                        <span class="priority-badge">Priority 2</span>
                    </div>
                    
                    <?php
                    // Group attorney events by attorney name
                    $attorney_groups = [];
                    foreach ($attorney_events as $ev) {
                        $attorney_name = $ev['attorney_name'] ?? 'Unknown Attorney';
                        if (!isset($attorney_groups[$attorney_name])) {
                            $attorney_groups[$attorney_name] = [];
                        }
                        $attorney_groups[$attorney_name][] = $ev;
                    }
                    
                    // Display each attorney's schedules separately
                    foreach ($attorney_groups as $attorney_name => $attorney_schedules):
                    ?>
                    <div class="attorney-schedule-group">
                        <div class="attorney-group-header">
                            <div class="attorney-info">
                                <i class="fas fa-user-tie"></i>
                                <h4><?= htmlspecialchars($attorney_name) ?></h4>
                                <span class="schedule-count"><?= count($attorney_schedules) ?> schedule<?= count($attorney_schedules) > 1 ? 's' : '' ?></span>
                            </div>
                        </div>
                        
                        <div class="events-grid">
                            <?php foreach ($attorney_schedules as $ev): ?>
                                <div class="event-card attorney-event status-<?= strtolower($ev['status'] ?? 'scheduled') ?><?= (strtolower($ev['status'] ?? 'scheduled') === 'completed') ? ' completed-schedule' : '' ?>" data-event-id="<?= $ev['id'] ?>" data-event-type="<?= htmlspecialchars($ev['type']) ?>" data-title="<?= htmlspecialchars($ev['title'] ?? '') ?>" data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? 'Unknown Attorney') ?>" data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>" data-user-id="<?= $ev['final_attorney_id'] ?? 0 ?>">
                                    <div class="event-card-header">
                                        <div class="event-avatar">
                                            <div class="avatar-placeholder">
                                                <i class="fas fa-calendar-check"></i>
                                            </div>
                                        </div>
                                        <div class="event-info">
                                            <h3><?= htmlspecialchars($ev['type']) ?></h3>
                                            <p class="event-title"><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($ev['start_time'] ?? '00:00:00')) ?> - <?= date('g:i A', strtotime($ev['end_time'] ?? '00:00:00')) ?></p>
                                            <p class="case-detail"><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($ev['date'] ?? date('Y-m-d'))) ?></p>
                                            <p class="client-detail"><i class="fas fa-user"></i> <?= htmlspecialchars($ev['client_name'] ?? 'No Client') ?></p>
                                        </div>
                                    </div>

                                    <div class="event-actions">
                                        <div class="status-edit-section">
                                            <select class="status-select" data-event-id="<?= $ev['id'] ?>" onchange="updateEventStatus(this)" data-previous-status="<?= htmlspecialchars($ev['status']) ?>">
                                                <option value="Scheduled" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                                                <option value="Completed" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'completed') ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                                <option value="Rescheduled" <?= (strtolower($ev['status'] ?? 'Scheduled') === 'rescheduled') ? 'selected' : '' ?>>Rescheduled</option>
                                            </select>
                                            <button class="btn btn-warning btn-sm edit-event-btn" onclick="editEvent(this)" 
                                                data-event-id="<?= $ev['id'] ?>"
                                                data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"
                                                data-type="<?= htmlspecialchars($ev['type']) ?>"
                                                data-date="<?= htmlspecialchars($ev['date']) ?>"
                                                data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                                data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"
                                                data-location="<?= htmlspecialchars($ev['location'] ?? '') ?>"
                                                data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                                                data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"
                                            data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"
                                            data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"
                                            data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"
                                            data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                            data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-info btn-sm view-info-btn" 
                                                data-event-id="<?= $ev['id'] ?>"
                                                data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"
                                                data-type="<?= htmlspecialchars($ev['type']) ?>"
                                                data-date="<?= htmlspecialchars($ev['date']) ?>"
                                                data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                                data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"
                                                data-location="<?= htmlspecialchars($ev['location'] ?? '') ?>"
                                                data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                                                data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"
                                                data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"
                                                data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"
                                                data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"
                                                data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"
                                                data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Completed Schedules Section -->
        <div class="completed-schedules-section" style="display: none;">
            <div class="section-header">
                <div class="header-text">
                    <h2><i class="fas fa-check-circle"></i> Completed Schedules</h2>
                    <p>View previously completed schedules and appointments</p>
                </div>
                
                <button class="toggle-completed-btn" onclick="toggleCompletedSection()">
                    <i class="fas fa-eye-slash"></i> Hide Completed
                </button>
            </div>
            
            <div class="completed-schedules-container">
                <div class="no-completed" style="display: none;">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Completed Schedules</h3>
                    <p>Completed schedules will appear here when marked as done.</p>
                </div>
                <div class="completed-events-grid">
                    <!-- Completed events will be moved here -->
                </div>

                <!-- Pagination Controls -->
                <div class="pagination-controls">
                    <div class="items-per-page">
                        <label for="completed-items-per-page">Show:</label>
                        <select id="completed-items-per-page">
                            <option value="6">6</option>
                            <option value="12">12</option>
                            <option value="18">18</option>
                            <option value="24">24</option>
                        </select>
                    </div>
                    <span class="pagination-info" id="completed-pagination-info">Page 1</span>
                    <div class="pagination-buttons">
                        <button class="btn-pagination" id="completed-prev-btn" disabled>Previous</button>
                        <button class="btn-pagination" id="completed-next-btn">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal" id="addEventModal">
        <div class="modal-content add-event-modal">
            <div class="modal-header">
                <h2>Add New Schedule</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="eventForm" class="event-form-grid">
                        <div class="form-group">
                            <label for="eventDate">Date</label>
                            <input type="date" id="eventDate" name="date" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label for="eventStartTime">Start Time</label>
                            <input type="time" id="eventStartTime" name="start_time">
                        </div>
                        <div class="form-group">
                            <label for="eventEndTime">End Time</label>
                            <input type="time" id="eventEndTime" name="end_time">
                        </div>
                    <div class="form-group">
                        <label for="eventLocation">Location</label>
                        <input type="text" id="eventLocation" name="location" placeholder="Enter specific location">
                    </div>
                    <div class="form-group">
                        <label for="eventClient">Client Selection</label>
                        <select id="eventClient" name="client_id">
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selectedUserId">Assigned Attorney</label>
                        <select id="selectedUserId" name="selected_user_id">
                            <option value="">Assigned Attorney</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="eventCase">Related Case (Optional)</label>
                        <select id="eventCase" name="case_id">
                            <option value="">Select Case</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="eventType">Event Type</label>
                        <select id="eventType" name="type">
                            <option value="Hearing">Hearing</option>
                            <option value="Appointment">Appointment</option>
                            <option value="Free Legal Advice">Free Legal Advice</option>
                        </select>
                    </div>
                    <script>
                        (function(){
                            const clientSel = document.getElementById('eventClient');
                            const attorneySel = document.getElementById('selectedUserId');
                            const caseSel = document.getElementById('eventCase');
                            
                            function resetAttorney(){ 
                                attorneySel.innerHTML = '<option value="">Assigned Attorney</option>'; 
                            }
                            
                            function resetCases(){ 
                                caseSel.innerHTML = '<option value="">Select Case</option>'; 
                            }
                            
                            function loadCasesForClient(clientId) {
                                if (!clientId) {
                                    resetCases();
                                    return;
                                }
                                
                                fetch('get_cases_by_client.php', { 
                                    method:'POST', 
                                    headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                                    body:'client_id='+encodeURIComponent(clientId) 
                                })
                                .then(r=>r.json())
                                .then(data=>{
                                    resetCases();
                                    if(data && data.success && data.cases){
                                        data.cases.forEach(caseItem=>{ 
                                            const option = document.createElement('option'); 
                                            option.value = caseItem.id; 
                                            option.textContent = `${caseItem.title} (${caseItem.case_type}) - ${caseItem.status}`; 
                                            caseSel.appendChild(option); 
                                        });
                                    }
                                })
                                .catch(console.error);
                            }
                            
                            if(clientSel && attorneySel && caseSel){
                                clientSel.addEventListener('change', function(){
                                    const cid = this.value; 
                                    resetAttorney(); 
                                    resetCases();
                                    
                                    if(!cid) return;
                                    
                                    // Load attorneys for client
                                    fetch('employee_schedule.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_attorneys_for_client&client_id=' + encodeURIComponent(cid) })
                                        .then(r=>r.json()).then(data=>{
                                            if(data && data.success){
                                                (data.attorneys||[]).forEach(a=>{ const o=document.createElement('option'); o.value=a.id; o.textContent=a.name; attorneySel.appendChild(o); });
                                                if((data.attorneys||[]).length===1){ attorneySel.value=data.attorneys[0].id; }
                                            }
                                        }).catch(console.error);
                                    
                                    // Load cases for client
                                    loadCasesForClient(cid);
                                });
                            }
                        })();
                    </script>
                    <div class="form-group full-width">
                        <label for="eventDescription">Description</label>
                        <textarea id="eventDescription" name="description" rows="3" placeholder="Enter detailed description of this schedule..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEvent">Cancel</button>
                <button class="btn btn-primary" id="saveEvent">Save Schedule</button>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal" id="editEventModal">
        <div class="modal-content add-event-modal">
            <div class="modal-header">
                <h2>Edit Schedule</h2>
            </div>
            <div class="modal-body">
                <form id="editEventForm" class="event-form-grid">
                    <input type="hidden" id="editEventId" name="event_id">
                    <div class="form-group">
                        <label for="editEventDate">Date</label>
                        <input type="date" id="editEventDate" name="date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="editEventStartTime">Start Time</label>
                        <input type="time" id="editEventStartTime" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="editEventEndTime">End Time</label>
                        <input type="time" id="editEventEndTime" name="end_time" required>
                    </div>
                    <div class="form-group">
                        <label for="editEventLocation">Location</label>
                        <input type="text" id="editEventLocation" name="location" required>
                    </div>
                    <div class="form-group">
                        <label for="editEventType">Event Type</label>
                        <select id="editEventType" name="type" required>
                            <option value="Hearing">Hearing</option>
                            <option value="Appointment">Appointment</option>
                            <option value="Free Legal Advice">Free Legal Advice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editEventDescription">Description</label>
                        <textarea id="editEventDescription" name="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveEventChanges()">Save Schedule</button>
            </div>
        </div>
    </div>

    <!-- Walk-in Schedule Modal -->
    <div class="modal" id="addWalkinModal">
        <div class="modal-content add-event-modal">
            <div class="modal-header">
                <h2><i class="fas fa-walking"></i> Walk-in Schedule</h2>
            </div>
            <div class="modal-body">
                <form id="walkinForm" class="event-form-grid">
                    <div class="form-group">
                        <label for="walkinClientSurname">Surname <span style="color: red;">*</span></label>
                        <input type="text" id="walkinClientSurname" name="client_surname" required placeholder="Enter surname">
                    </div>
                    
                    <div class="form-group">
                        <label for="walkinClientFirstName">First Name <span style="color: red;">*</span></label>
                        <input type="text" id="walkinClientFirstName" name="client_first_name" required placeholder="Enter first name">
                    </div>
                    
                    <div class="form-group">
                        <label for="walkinClientMiddleName">Middle Name</label>
                        <input type="text" id="walkinClientMiddleName" name="client_middle_name" placeholder="Enter middle name (optional)">
                    </div>
                    <div class="form-group">
                        <label for="walkinClientContact">Contact Number <span style="color: red;">*</span></label>
                        <input type="text" id="walkinClientContact" name="client_contact" required placeholder="Enter 11-digit contact number" maxlength="11" pattern="[0-9]{11}">
                    </div>
                    <div class="form-group">
                        <label for="walkinClientContactConfirm">Confirm Contact Number <span style="color: red;">*</span></label>
                        <input type="text" id="walkinClientContactConfirm" name="client_contact_confirm" required placeholder="Confirm 11-digit contact number" maxlength="11" pattern="[0-9]{11}">
                    </div>
                    <div class="form-group">
                        <label for="walkinType">Service Type <span style="color: red;">*</span></label>
                        <select id="walkinType" name="type" required>
                            <option value="">Select service type</option>
                            <option value="Appointment">Appointment</option>
                            <option value="Free Legal Advice">Free Legal Advice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="walkinDate">Date <span style="color: red;">*</span></label>
                        <input type="date" id="walkinDate" name="date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="walkinStartTime">Start Time <span style="color: red;">*</span></label>
                        <input type="time" id="walkinStartTime" name="start_time" required min="08:00" max="18:00">
                    </div>
                    <div class="form-group">
                        <label for="walkinEndTime">End Time <span style="color: red;">*</span></label>
                        <input type="time" id="walkinEndTime" name="end_time" required min="08:00" max="18:00">
                    </div>
                    <div class="form-group">
                        <label for="walkinLocation">Location <span style="color: red;">*</span></label>
                        <input type="text" id="walkinLocation" name="location" required placeholder="Enter location">
                    </div>
                    <div class="form-group">
                        <label for="walkinAttorney">Assigned Attorney <span style="color: red;">*</span></label>
                        <select id="walkinAttorney" name="selected_user_id" required>
                            <option value="">Assigned Attorney</option>
                            <?php foreach ($attorneys_and_admins as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="walkinDescription">Description <span style="color: red;">*</span></label>
                        <textarea id="walkinDescription" name="description" rows="3" required placeholder="Enter detailed description of this walk-in schedule..."></textarea>
                        <small style="color: #666; font-style: italic;">Required: Provide detailed information about this walk-in schedule</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelWalkinEvent">Cancel</button>
                <button class="btn btn-primary" id="saveWalkinEvent">Save Walk-in Schedule</button>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal" id="eventInfoModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="header-text">
                        <h2>Event Details</h2>
                        <p>Complete event information and case details</p>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div class="event-overview">
                    <div class="event-type-display">
                        <span class="type-badge" id="modalEventType">Event</span>
                    </div>
                    <div class="event-datetime">
                        <div class="date-display" id="modalEventDate">Date</div>
                        <div class="time-display" id="modalEventTime">Time</div>
                    </div>
                </div>
                <div class="event-details-grid">
                    <div class="detail-section">
                        <h3><i class="fas fa-info-circle"></i> Event Information</h3>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-tag"></i> Type:</span>
                            <span class="detail-value" id="modalType">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-calendar"></i> Date:</span>
                            <span class="detail-value" id="modalDate">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-clock"></i> Time:</span>
                            <span class="detail-value" id="modalTime">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Location:</span>
                            <span class="detail-value" id="modalLocation">-</span>
                        </div>
                    </div>
                    <div class="detail-section" id="caseDetailsSection">
                        <h3><i class="fas fa-folder-open"></i> Case Details</h3>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user-tie"></i> Attorney/Admin:</span>
                            <span class="detail-value" id="modalAttorney">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user"></i> Client:</span>
                            <span class="detail-value" id="modalClient">-</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-file-alt"></i> Description:</span>
                            <span class="detail-value" id="modalDescription">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user-plus"></i> Created By:</span>
                            <span class="detail-value" id="modalCreatedBy">-</span>
                        </div>
                    </div>

                    <!-- Walk-in Client Details Section -->
                    <div class="detail-section" id="walkinDetailsSection" style="display: none;">
                        <h3><i class="fas fa-walking"></i> Walk-in Client Details</h3>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user"></i> Client Name:</span>
                            <span class="detail-value" id="modalWalkinClientName">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-phone"></i> Contact Number:</span>
                            <span class="detail-value" id="modalWalkinClientContact">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user-tie"></i> Assigned Attorney/Admin:</span>
                            <span class="detail-value" id="modalWalkinAttorney">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-file-alt"></i> Description:</span>
                            <span class="detail-value" id="modalWalkinDescription">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user-plus"></i> Created By:</span>
                            <span class="detail-value" id="modalWalkinCreatedBy">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-close-modal" id="closeEventInfoModal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <style>
        .action-buttons {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .view-options {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            border: none;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .btn-walkin {
            background: #28a745;
            color: white;
            border: none;
        }
        
        .btn-walkin:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .btn-secondary:hover, .btn-secondary.active {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            border-color: #5D0E26;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
        }
        
        .btn-secondary:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.2) !important;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .calendar-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 24px;
            margin-bottom: 24px;
        }

        /* Enhanced Upcoming Events Styles */
        .upcoming-events-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }

        .section-header {
            margin-bottom: 2rem;
        }

        .section-header .header-text {
            text-align: left;
        }

        .section-header h2 {
            color: #5d0e26;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header p {
            color: #666;
            font-size: 1.1rem;
            margin: 0;
        }

        .no-events {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .no-events i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .no-events h3 {
            margin: 0 0 0.5rem 0;
            color: #999;
        }

        .no-events p {
            margin: 0;
            color: #bbb;
        }

        /* Pagination Controls */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .items-per-page {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .items-per-page label {
            font-weight: 500;
            color: #5d0e26;
        }

        .items-per-page select {
            padding: 0.4rem 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            background: white;
            color: #5d0e26;
            font-weight: 600;
            cursor: pointer;
        }

        .items-per-page select:focus {
            outline: none;
            border-color: #5d0e26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }

        .pagination-info {
            font-weight: 500;
            color: #5d0e26;
        }

        .pagination-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-pagination {
            padding: 0.5rem 1rem;
            background: #5d0e26;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-pagination:hover:not(:disabled) {
            background: #4A0B1E;
            transform: translateY(-1px);
        }

        .btn-pagination:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            background: #5d0e26;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #4A0B1E;
            transform: translateY(-1px);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            align-items: start;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .event-card-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .event-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .event-info h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1.2rem;
            font-weight: 600;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            line-height: 1.3;
        }

        .event-info p {
            margin: 0 0 0.25rem 0;
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .event-title {
            color: #5d0e26 !important;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .case-detail {
            color: #5d0e26 !important;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .client-detail {
            color: #43a047 !important;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
        }

        .attorney-detail {
            color: #9c27b0 !important;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .event-info i {
            font-size: 0.8rem;
            width: 16px;
            text-align: center;
        }

        /* Priority Sections */
        .priority-section {
            margin-bottom: 2rem;
        }

        .priority-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
        }

        .admin-priority .priority-header {
            background: linear-gradient(135deg, rgba(93, 14, 38, 0.05) 0%, rgba(139, 21, 56, 0.1) 100%);
        }

        .attorney-priority .priority-header {
            background: linear-gradient(135deg, rgba(93, 14, 38, 0.05) 0%, rgba(139, 21, 56, 0.1) 100%);
        }

        .other-priority .priority-header {
            background: linear-gradient(135deg, rgba(108, 117, 125, 0.05) 0%, rgba(108, 117, 125, 0.1) 100%);
        }

        .priority-header i {
            font-size: 1.5rem;
            color: #5d0e26;
        }

        .admin-priority .priority-header i {
            color: #5d0e26;
        }

        .attorney-priority .priority-header i {
            color: #5d0e26;
        }

        .other-priority .priority-header i {
            color: #6c757d;
        }

        .priority-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        .priority-badge {
            background: #5d0e26;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: auto;
        }

        .admin-priority .priority-badge {
            background: #5d0e26;
        }

        .attorney-priority .priority-badge {
            background: #5d0e26;
        }

        .other-priority .priority-badge {
            background: #6c757d;
        }

        /* Attorney Schedule Group Styling */
        .attorney-schedule-group {
            margin-bottom: 2rem;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
        }

        .attorney-group-header {
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, rgba(93, 14, 38, 0.08) 0%, rgba(139, 21, 56, 0.15) 100%);
            border-radius: 10px;
        }

        .attorney-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .attorney-info i {
            font-size: 1.5rem;
            color: #5d0e26;
        }

        .attorney-info h4 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #5d0e26;
        }

        .schedule-count {
            background: rgba(93, 14, 38, 0.1);
            color: #5d0e26;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(93, 14, 38, 0.3);
            margin-left: auto;
        }

        /* Attorney-based Color Coding for Event Cards */
        .event-card {
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            cursor: pointer;
        }

        /* Admin and Attorney Event Cards - Colors applied dynamically via JavaScript */

        /* Other Event Cards - Light Blue Background */
        .event-card.other-event {
            background: linear-gradient(135deg, rgba(116, 192, 252, 0.08) 0%, rgba(116, 192, 252, 0.15) 100%);
            border-left: 4px solid #74c0fc;
        }

        /* Attorney-based avatar colors */
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        /* Admin and Attorney avatars - Colors applied dynamically via JavaScript */

        /* Other event avatars - Light Blue */
        .event-card.other-event .avatar-placeholder {
            background: linear-gradient(135deg, #74c0fc 0%, #4dabf7 100%);
        }

        /* Dynamic User Color Coding - Colors loaded from database */
        /* Specific colors will be applied via JavaScript based on current database users */

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: #5d0e26;
        }

        /* Dynamic Color Coding - Generated by Color Manager */
        <?php echo generateColorCSS(); ?>

        .event-info h3 {
            margin: 0 0 0.2rem 0;
            color: #5d0e26;
            font-weight: 600;
            font-size: 0.9rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            line-height: 1.3;
        }

        .status-edit-section {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .status-select {
            padding: 0.3rem 0.6rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            background-color: #fff;
            font-size: 0.75rem;
            color: #495057;
            cursor: pointer;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .status-select:focus {
            border-color: #5d0e26;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(93, 14, 38, 0.25);
        }

        .status-select option[value="Scheduled"] {
            color: #1976d2;
        }

        .status-select option[value="Completed"] {
            color: #4caf50;
        }

        .status-select option[value="Cancelled"] {
            color: #f44336;
        }

        .status-select option[value="Rescheduled"] {
            color: #ff9800;
        }

        .edit-event-btn {
            background: #ffc107;
            border: 1px solid #ffc107;
            color: #212529;
            font-weight: 500;
        }

        .edit-event-btn:hover {
            background: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }



        .event-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .status-management {
            flex: 1;
        }

        .status-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            background: white;
            color: #333;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .status-select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        /* Status-based Card Styling */
        .event-card.status-completed {
        }



        .event-card.status-rescheduled {
        }

        .event-card.status-cancelled {
        }

        /* Professional Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 800px;
            width: 90%;
            margin: 2% auto;
            overflow: hidden;
            max-height: 90vh;
        }

        .add-event-modal {
            max-width: 900px !important;
            max-height: 90vh !important;
            margin: 1.5% auto !important;
            width: 95% !important;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            background: #5d0e26;
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-icon {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .header-text h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .header-text p {
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 1rem;
        }

        .event-overview {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
        }

        .event-type-display .type-badge {
            background: #9c27b0;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .event-datetime {
            display: flex;
            gap: 0.75rem;
        }

        .date-display, .time-display {
            background: white;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            text-align: center;
            min-width: 70px;
            border: 1px solid #e0e0e0;
            font-size: 0.9rem;
        }

        .date-display {
            color: #1976d2;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .time-display {
            color: #43a047;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .event-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .detail-section {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1.2rem;
            border: 1px solid #e9ecef;
        }

        .detail-section h3 {
            color: #1976d2;
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e3f2fd;
        }

        .detail-section h3 i {
            color: #9c27b0;
            font-size: 1.2rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #555;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 100px;
            font-size: 0.85rem;
        }

        .detail-label i {
            color: #9c27b0;
            width: 18px;
            text-align: center;
            font-size: 1rem;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
            text-align: right;
            max-width: 250px;
            word-wrap: break-word;
            font-size: 0.85rem;
            padding-left: 0.5rem;
            line-height: 1.3;
        }

        .modal-footer {
            padding: 1rem;
            background: #f8f9fa;
            text-align: right;
            border-top: 1px solid #e0e0e0;
            border-radius: 0 0 8px 8px;
            position: sticky;
            bottom: 0;
            z-index: 100;
        }

        .btn-close-modal {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-close-modal:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        /* Add Schedule Modal Specific Styles */
        .add-event-modal .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .add-event-modal .event-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .add-event-modal .form-group {
            margin-bottom: 0.6rem;
        }

        .add-event-modal .form-group.full-width {
            grid-column: 1 / -1;
        }

        .add-event-modal .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 600;
            color: #5d0e26;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1px;
        }

        .add-event-modal .form-group input,
        .add-event-modal .form-group select,
        .add-event-modal .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.8rem;
            background: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        .add-event-modal .form-group input:focus,
        .add-event-modal .form-group select:focus,
        .add-event-modal .form-group textarea:focus {
            outline: none;
            border-color: #5d0e26;
            background: white;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            transform: translateY(-1px);
        }

        .add-event-modal .form-group input:hover,
        .add-event-modal .form-group select:hover,
        .add-event-modal .form-group textarea:hover {
            border-color: #5d0e26;
            background: white;
        }

        .add-event-modal .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
            background: #f8f9fa;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        .add-event-modal .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .add-event-modal .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .add-event-modal .btn-primary {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
        }
        
        .add-event-modal .btn-primary:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }

        .add-event-modal .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .add-event-modal .btn:active {
            transform: translateY(0);
        }



        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-left: 4px solid;
            z-index: 100001;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification-success {
            border-left-color: #2e7d32;
            color: #2e7d32;
        }

        .notification-error {
            border-left-color: #d32f2f;
            color: #d32f2f;
        }

        .notification i {
            font-size: 1.2rem;
        }

        #calendar {
            height: 600px;
        }

        .fc-event {
            cursor: pointer;
        }

        .fc-event-title {
            font-weight: 500;
        }

        .fc-event-time {
            font-size: 0.8em;
        }

        @media (max-width: 700px) {
            .modal-content { 
                max-width: 98vw; 
                padding: 10px 4vw; 
            }
            .event-form-grid { 
                grid-template-columns: 1fr; 
                gap: 12px; 
            }
            .event-details-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }

        /* Events Filter Section */
        .events-filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .filter-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        /* Status Filter Container */
        .status-filter-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .filter-label {
            color: #5D0E26;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Global Status Filter Styles */
        .global-status-filter-container {
            margin: 1.5rem 0;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        /* Status Filter Styles */
        .status-filter-container {
            margin: 1.5rem 0;
            padding: 0 1rem;
        }

        .status-filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .status-filter-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .status-filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Default status colors for buttons */
        .status-filter-btn[data-status="scheduled"] {
            border-color: #1976d2;
            color: #1976d2;
            background: rgba(25, 118, 210, 0.1);
        }

        .status-filter-btn[data-status="completed"] {
            border-color: #4caf50;
            color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        .status-filter-btn[data-status="cancelled"] {
            border-color: #f44336;
            color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }

        .status-filter-btn[data-status="rescheduled"] {
            border-color: #ff9800;
            color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
        }

        .status-filter-btn[data-status="all"] {
            border-color: #1976d2;
            color: #1976d2;
            background: rgba(25, 118, 210, 0.1);
        }

        .status-filter-btn.active {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            border-color: #1976d2;
            color: white;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
        }

        .status-filter-btn.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25, 118, 210, 0.4);
        }

        /* Status-specific button hover effects */
        .status-filter-btn[data-status="scheduled"]:hover {
            background: rgba(25, 118, 210, 0.2);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
        }

        .status-filter-btn[data-status="completed"]:hover {
            background: rgba(76, 175, 80, 0.2);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .status-filter-btn[data-status="cancelled"]:hover {
            background: rgba(244, 67, 54, 0.2);
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
        }

        .status-filter-btn[data-status="rescheduled"]:hover {
            background: rgba(255, 152, 0, 0.2);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .status-filter-btn[data-status="all"]:hover {
            background: rgba(25, 118, 210, 0.2);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
        }

        /* Event Card Transitions */
        .event-card {
            transition: all 0.4s ease;
            opacity: 1;
            transform: scale(1);
        }

        .event-card.filtered-out {
            opacity: 0;
            transform: scale(0.95);
            pointer-events: none;
            height: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        /* Completed Schedule Styling */
        .event-card.completed-schedule {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(76, 175, 80, 0.05) 100%);
            position: relative;
        }

        .event-card.completed-schedule::after {
            content: '✓ COMPLETED';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #4caf50;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .event-card.completed-schedule .status-select:disabled,
        .event-card.completed-schedule button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Completed Schedules Section */
        .completed-schedules-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            clear: both;
            width: 100%;
            display: block;
        }

        .completed-schedules-section .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .completed-schedules-section .section-header .header-text {
            flex: 1;
            text-align: left;
        }

        .completed-schedules-section .section-header .toggle-completed-btn {
            flex-shrink: 0;
        }

        .show-completed-btn, .toggle-completed-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .show-completed-btn:hover, .toggle-completed-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .completed-events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .no-completed {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .no-completed i {
            font-size: 3rem;
            color: #4caf50;
            margin-bottom: 1rem;
        }

        .no-completed h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .no-completed p {
            margin: 0;
            color: #999;
        }

        /* Responsive Filter Buttons */
        @media (max-width: 768px) {
            .filter-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
            }

            .status-filter-container {
                width: 100%;
            }

            .status-filter-buttons {
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.5rem;
                width: 100%;
            }

            .status-filter-btn {
                flex-shrink: 0;
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
            }

            .section-header h2 {
                font-size: 1.5rem;
            }
            
            .completed-schedules-section {
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding: 1rem;
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

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="assets/js/color-manager.js"></script>
    <script>
        // Submenu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const submenu = this.parentElement;
                    submenu.classList.toggle('open');
                });
            });
        });
    </script>
    <script>
        // Store attorneys and admins data for JavaScript
        var attorneysAndAdmins = <?php echo json_encode($attorneys_and_admins); ?>;
        
        // Load cases for edit schedule (get client_id from schedule)
        function loadCasesForEditSchedule(scheduleId, currentCase = null) {
            const editCaseSelect = document.getElementById('editEventCase');
            if (!editCaseSelect) return;
            
            function resetEditCases() {
                editCaseSelect.innerHTML = '<option value="">Select Case</option>';
            }
            
            // Get schedule details to find client_id
            fetch('employee_schedule.php', { 
                method:'POST', 
                headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                body:'action=get_schedule_details&schedule_id='+encodeURIComponent(scheduleId) 
            })
            .then(r=>r.json())
            .then(data=>{
                if(data && data.success && data.client_id){
                    // Now load cases for this client
                    fetch('get_cases_by_client.php', { 
                        method:'POST', 
                        headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                        body:'client_id='+encodeURIComponent(data.client_id) 
                    })
                    .then(r=>r.json())
                    .then(caseData=>{
                        resetEditCases();
                        if(caseData && caseData.success && caseData.cases){
                            caseData.cases.forEach(caseItem=>{ 
                                const option = document.createElement('option'); 
                                option.value = caseItem.id; 
                                option.textContent = `${caseItem.title} (${caseItem.case_type}) - ${caseItem.status}`; 
                                editCaseSelect.appendChild(option); 
                            });
                            
                            // Set current case if provided
                            if (currentCase && currentCase !== '-') {
                                const caseOptions = editCaseSelect.options;
                                for (let i = 0; i < caseOptions.length; i++) {
                                    if (caseOptions[i].textContent.includes(currentCase)) {
                                        editCaseSelect.value = caseOptions[i].value;
                                        break;
                                    }
                                }
                            }
                        }
                    })
                    .catch(console.error);
                }
            })
            .catch(console.error);
        }
        
        // Define toggleCompletedSection function immediately for onclick handlers
        function toggleCompletedSection() {
            const completedSection = document.querySelector('.completed-schedules-section');
            const showBtn = document.querySelector('.show-completed-btn');
            const hideBtn = document.querySelector('.toggle-completed-btn');
            
            if (completedSection) {
                if (completedSection.style.display === 'none') {
                    // Show completed section
                    completedSection.style.display = 'block';
                    if (showBtn) showBtn.style.display = 'none';
                    if (hideBtn) hideBtn.style.display = 'flex';
                } else {
                    // Hide completed section
                    completedSection.style.display = 'none';
                    if (showBtn) showBtn.style.display = 'flex';
                    if (hideBtn) hideBtn.style.display = 'none';
                }
            }
        }

        // Make function globally accessible
        window.toggleCompletedSection = toggleCompletedSection;
        
        // Function to update user dropdown based on selected user type
        function updateUserDropdown() {
            const userTypeSelect = document.getElementById('selectedUserType');
            const userIdSelect = document.getElementById('selectedUserId');
            const selectedType = userTypeSelect.value;
            
            // Clear current options
            userIdSelect.innerHTML = '<option value="">Select ' + (selectedType === 'attorney' ? 'Attorney' : 'Admin') + '</option>';
            
            if (selectedType) {
                // Filter users by selected type
                const filteredUsers = attorneysAndAdmins.filter(user => user.user_type === selectedType);
                
                // Add options for filtered users
                filteredUsers.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name + ' (' + user.user_type.charAt(0).toUpperCase() + user.user_type.slice(1) + ')';
                    userIdSelect.appendChild(option);
                });
                
                // Enable the dropdown
                userIdSelect.disabled = false;
            } else {
                // Disable and reset dropdown
                userIdSelect.disabled = true;
                userIdSelect.innerHTML = '<option value="">First select user type</option>';
            }
        }

        // Enhanced confirmation with typing requirement
        function showTypingConfirmation(message, status) {
            // Create modal overlay
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(5px);
            `;
            
            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 12px;
                padding: 2rem;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                text-align: center;
                border: 3px solid #e74c3c;
            `;
            
            // Get confirmation text based on status
            let confirmText = '';
            let inputPlaceholder = '';
            
            switch(status.toLowerCase()) {
                case 'completed':
                    confirmText = 'COMPLETED';
                    inputPlaceholder = 'Type "COMPLETED" to confirm';
                    break;
                case 'rescheduled':
                    confirmText = 'RESCHEDULED';
                    inputPlaceholder = 'Type "RESCHEDULED" to confirm';
                    break;
                case 'cancelled':
                    confirmText = 'CANCELLED';
                    inputPlaceholder = 'Type "CANCELLED" to confirm';
                    break;
                case 'edit':
                    confirmText = 'EDIT';
                    inputPlaceholder = 'Type "EDIT" to confirm';
                    break;
                default:
                    confirmText = 'CONFIRM';
                    inputPlaceholder = 'Type "CONFIRM" to proceed';
            }
            
            modalContent.innerHTML = `
                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <h3 style="color: #e74c3c; margin: 0 0 1rem 0; font-size: 1.3rem;">SECURITY CONFIRMATION REQUIRED</h3>
                    <p style="color: #666; margin: 0; line-height: 1.5; white-space: pre-line;">${message}</p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">
                        To proceed, type: <strong style="color: #e74c3c;">${confirmText}</strong>
                    </label>
                    <input type="text" id="confirmationInput" placeholder="${inputPlaceholder}" 
                           style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; text-align: center; font-weight: 600; letter-spacing: 1px;">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button id="cancelBtn" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                        ❌ Cancel
                    </button>
                    <button id="confirmBtn" disabled style="padding: 12px 24px; background: #e74c3c; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: not-allowed; opacity: 0.5; transition: all 0.3s;">
                        ✅ Confirm ${status.toUpperCase()}
                    </button>
                </div>
            `;
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            // Focus on input
            const input = modalContent.querySelector('#confirmationInput');
            const confirmBtn = modalContent.querySelector('#confirmBtn');
            const cancelBtn = modalContent.querySelector('#cancelBtn');
            
            input.focus();
            
            // Handle input validation
            input.addEventListener('input', function() {
                const typedValue = this.value.trim().toUpperCase();
                const isValid = typedValue === confirmText;
                
                if (isValid) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.background = '#27ae60';
                    confirmBtn.style.cursor = 'pointer';
                    confirmBtn.style.opacity = '1';
                    this.style.borderColor = '#27ae60';
                    this.style.backgroundColor = '#f8fff8';
                } else {
                    confirmBtn.disabled = true;
                    confirmBtn.style.background = '#e74c3c';
                    confirmBtn.style.cursor = 'not-allowed';
                    confirmBtn.style.opacity = '0.5';
                    this.style.borderColor = '#e74c3c';
                    this.style.backgroundColor = '#fff5f5';
                }
            });
            
            // Handle Enter key
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !confirmBtn.disabled) {
                    confirmBtn.click();
                }
            });
            
            // Return promise
            return new Promise((resolve) => {
                confirmBtn.addEventListener('click', function() {
                    if (!this.disabled) {
                        document.body.removeChild(modal);
                        resolve(true);
                    }
                });
                
                cancelBtn.addEventListener('click', function() {
                    document.body.removeChild(modal);
                    resolve(false);
                });
                
                // Close on outside click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        document.body.removeChild(modal);
                        resolve(false);
                    }
                });
            });
        }

        // Double confirmation modal system for status changes
        function showDoubleConfirmation(status, previousStatus) {
            return new Promise((resolve) => {
                // Get status-specific configuration
                const statusConfig = getStatusConfig(status);
                
                // First confirmation modal
                const firstModal = createConfirmationModal(
                    statusConfig.firstTitle,
                    statusConfig.firstMessage,
                    statusConfig.color,
                    ['Cancel', 'Proceed']
                );
                
                document.body.appendChild(firstModal);
                
                const firstButtons = firstModal.querySelectorAll('button');
                const firstCancelBtn = firstButtons[0];
                const firstProceedBtn = firstButtons[1];
                
                firstProceedBtn.addEventListener('click', () => {
                    document.body.removeChild(firstModal);
                    
                    // Second confirmation modal
                    const secondModal = createConfirmationModal(
                        'Final Confirmation',
                        `Please confirm again to finalize this status change.`,
                        statusConfig.color,
                        ['Go Back', `Confirm ${status}`]
                    );
                    
                    document.body.appendChild(secondModal);
                    
                    const secondButtons = secondModal.querySelectorAll('button');
                    const secondGoBackBtn = secondButtons[0];
                    const secondConfirmBtn = secondButtons[1];
                    
                    secondGoBackBtn.addEventListener('click', () => {
                        document.body.removeChild(secondModal);
                        resolve(false);
                    });
                    
                    secondConfirmBtn.addEventListener('click', () => {
                        document.body.removeChild(secondModal);
                        resolve(true);
                    });
                    
                    // Close second modal on overlay click
                    secondModal.addEventListener('click', (e) => {
                        if (e.target === secondModal) {
                            document.body.removeChild(secondModal);
                            resolve(false);
                        }
                    });
                });
                
                firstCancelBtn.addEventListener('click', () => {
                    document.body.removeChild(firstModal);
                    resolve(false);
                });
                
                // Close first modal on overlay click
                firstModal.addEventListener('click', (e) => {
                    if (e.target === firstModal) {
                        document.body.removeChild(firstModal);
                        resolve(false);
                    }
                });
            });
        }

        // Double confirmation modal system for edit operations
        function showEditDoubleConfirmation() {
            return new Promise((resolve) => {
                // Edit-specific configuration
                const editConfig = {
                    color: '#f39c12',
                    firstTitle: 'Update Schedule',
                    firstMessage: '⚠️ Are you sure you want to update this schedule?\n\nThis action will modify the schedule details and cannot be easily undone.'
                };
                
                // First confirmation modal
                const firstModal = createConfirmationModal(
                    editConfig.firstTitle,
                    editConfig.firstMessage,
                    editConfig.color,
                    ['Cancel', 'Proceed']
                );
                
                document.body.appendChild(firstModal);
                
                const firstButtons = firstModal.querySelectorAll('button');
                const firstCancelBtn = firstButtons[0];
                const firstProceedBtn = firstButtons[1];
                
                firstProceedBtn.addEventListener('click', () => {
                    document.body.removeChild(firstModal);
                    
                    // Second confirmation modal
                    const secondModal = createConfirmationModal(
                        'Final Confirmation',
                        `Please confirm again to finalize this schedule update.`,
                        editConfig.color,
                        ['Go Back', 'Confirm Update']
                    );
                    
                    document.body.appendChild(secondModal);
                    
                    const secondButtons = secondModal.querySelectorAll('button');
                    const secondGoBackBtn = secondButtons[0];
                    const secondConfirmBtn = secondButtons[1];
                    
                    secondGoBackBtn.addEventListener('click', () => {
                        document.body.removeChild(secondModal);
                        resolve(false);
                    });
                    
                    secondConfirmBtn.addEventListener('click', () => {
                        document.body.removeChild(secondModal);
                        resolve(true);
                    });
                    
                    // Close second modal on overlay click
                    secondModal.addEventListener('click', (e) => {
                        if (e.target === secondModal) {
                            document.body.removeChild(secondModal);
                            resolve(false);
                        }
                    });
                });
                
                firstCancelBtn.addEventListener('click', () => {
                    document.body.removeChild(firstModal);
                    resolve(false);
                });
                
                // Close first modal on overlay click
                firstModal.addEventListener('click', (e) => {
                    if (e.target === firstModal) {
                        document.body.removeChild(firstModal);
                        resolve(false);
                    }
                });
            });
        }
        
        // Get status-specific configuration
        function getStatusConfig(status) {
            const configs = {
                'completed': {
                    color: '#27ae60',
                    firstTitle: 'Mark as Completed',
                    firstMessage: '⚠️ Are you sure you want to mark this schedule as Completed?\n\nThis action will update the record and cannot be easily undone.'
                },
                'cancelled': {
                    color: '#e74c3c',
                    firstTitle: 'Cancel Schedule',
                    firstMessage: '⚠️ Are you sure you want to cancel this schedule?\n\nThis action will update the record and cannot be easily undone.'
                },
                'rescheduled': {
                    color: '#f39c12',
                    firstTitle: 'Reschedule Appointment',
                    firstMessage: '⚠️ Are you sure you want to reschedule this appointment?\n\nThis action will update the record and cannot be easily undone.'
                },
                'scheduled': {
                    color: '#3498db',
                    firstTitle: 'Mark as Scheduled',
                    firstMessage: '⚠️ Are you sure you want to mark this schedule as Scheduled?\n\nThis action will update the record and cannot be easily undone.'
                }
            };
            
            return configs[status.toLowerCase()] || configs['scheduled'];
        }
        
        // Create confirmation modal
        function createConfirmationModal(title, message, color, buttonTexts) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(5px);
            `;
            
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 12px;
                padding: 2rem;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                text-align: center;
                border: 3px solid ${color};
            `;
            
            modalContent.innerHTML = `
                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <h3 style="color: ${color}; margin: 0 0 1rem 0; font-size: 1.3rem;">${title}</h3>
                    <p style="color: #666; margin: 0; line-height: 1.5; white-space: pre-line;">${message}</p>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                        ${buttonTexts[0]}
                    </button>
                    <button style="padding: 12px 24px; background: ${color}; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                        ${buttonTexts[1]}
                    </button>
                </div>
            `;
            
            modal.appendChild(modalContent);
            return modal;
        }

        // Global function for updating event status
        async function updateEventStatus(selectElement) {
            const newStatus = selectElement.value;
            const previousStatus = selectElement.dataset.previousStatus;
            const eventCard = selectElement.closest('.event-card');
            
            // Don't show confirmation if status didn't change
            if (newStatus === previousStatus) {
                return;
            }
            
            // Show double confirmation modal
            const confirmed = await showDoubleConfirmation(newStatus, previousStatus);
            if (!confirmed) {
                // Revert dropdown to previous value
                selectElement.value = previousStatus;
                return;
            }
            
            // Get event ID from the card
            const eventId = eventCard.dataset.eventId || '1';
            
            // Show processing notification
            showNotification(`Updating event status to ${newStatus.toUpperCase()}...`, 'info');
            
            // Send AJAX request to update status
            fetch('update_event_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `event_id=${eventId}&new_status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Special handling for completed status
                    if (newStatus.toLowerCase() === 'completed') {
                        handleCompletedEvent(selectElement, eventId);
                    } else {
                        showNotification(`✅ Event status successfully updated to ${newStatus.toUpperCase()}!`, 'success');
                        updateEventCardUI(selectElement, newStatus);
                        selectElement.dataset.previousStatus = newStatus;
                        
                        // Show additional warning for critical statuses
                        if (newStatus === 'cancelled') {
                            setTimeout(() => {
                                showNotification('⚠️ Remember to reschedule this cancelled event!', 'warning');
                            }, 2000);
                        }
                    }
                } else {
                    showNotification(`❌ Failed to update event status: ${data.message || 'Unknown error'}`, 'error');
                    selectElement.value = previousStatus;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error updating event status. Please try again.', 'error');
                selectElement.value = previousStatus;
            });
        }

        // Global function for showing notifications
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Hide and remove notification
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Function to update event card UI based on status
        function updateEventCardUI(selectElement, newStatus) {
            const eventCard = selectElement.closest('.event-card');
            
            // Remove previous status classes
            eventCard.classList.remove('status-scheduled', 'status-completed', 'status-rescheduled', 'status-cancelled');
            
            // Add new status class
            eventCard.classList.add(`status-${newStatus}`);
            
            // Update border color based on status
            const borderColors = {
                'scheduled': '#1976d2',
                'completed': '#2e7d32',
                'rescheduled': '#f57c00',
                'cancelled': '#6c757d'
            };
            
            eventCard.style.borderLeftColor = borderColors[newStatus] || '#1976d2';
        }

        // Wait for FullCalendar to be available
        function waitForFullCalendar() {
            console.log('waitForFullCalendar called');
            if (typeof FullCalendar !== 'undefined') {
                console.log('FullCalendar is available, creating calendar...');
                var calendarEl = document.getElementById('calendar');
                console.log('Calendar element:', calendarEl);
                console.log('Events data:', <?= json_encode($js_events) ?>);
                
                // Ensure we have events data, even if empty
                const eventsData = <?= json_encode($calendar_events) ?> || [];
                console.log('Events data length:', eventsData.length);
                
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: eventsData,
                    // Ensure navigation works properly
                    navLinks: true,
                    dayMaxEvents: true,
                    eventClick: function(info) {
                        showEventDetails(info.event);
                    },
                    eventColor: null,
                    height: 'auto',
                    eventDidMount: function(info) {
                        // Add data attributes for event identification and color coding
                        info.el.setAttribute('data-attorney-name', info.event.extendedProps.attorneyName);
                        info.el.setAttribute('data-attorney-id', info.event.extendedProps.attorneyId);
                        info.el.setAttribute('data-user-id', info.event.extendedProps.attorneyId);
                        
                        // Apply colors using the color manager
                        if (window.colorManager && info.event.extendedProps.attorneyId) {
                            window.colorManager.applyCalendarEventColors(info.el, info.event.extendedProps.attorneyId);
                        }

                        // Force white text color for admin events
                        if (info.event.textColor === '#ffffff') {
                            info.el.style.color = '#ffffff';
                            const titleEl = info.el.querySelector('.fc-event-title');
                            if (titleEl) {
                                titleEl.style.color = '#ffffff';
                            }
                            const mainFrameEl = info.el.querySelector('.fc-event-main-frame');
                            if (mainFrameEl) {
                                mainFrameEl.style.color = '#ffffff';
                            }
                        }
                    }
                });
                console.log('Calendar created, rendering...');
                calendar.render();
                console.log('Calendar rendered successfully');
                
                // Store calendar instance globally for access
                window.calendar = calendar;
                
                // Show message if no events
                if (eventsData.length === 0) {
                    console.log('No events to display');
                    // Add a message to the calendar
                    calendarEl.innerHTML += '<div style="text-align: center; padding: 40px; color: #666; font-size: 16px;"><i class="fas fa-calendar-times"></i><br>No events scheduled yet.<br><small>Click "Add Schedule" to create your first event.</small></div>';
                }
                
                // Initialize calendar functionality
                initializeCalendarFunctions(calendar);
                
                // Real-time synchronization removed - only reload after creating new schedule
            } else {
                console.error('FullCalendar is not available');
            }
        }
        
        // Function to close walk-in modal properly (defined globally)
        function closeAddWalkinModal() {
            const modal = document.getElementById('addWalkinModal');
            if (modal) {
                modal.style.display = 'none';
                // Also remove any backdrop/overlay effects
                modal.classList.remove('show');
            }
            // Reset form
            const form = document.getElementById('walkinForm');
            if (form) {
                form.reset();
            }
            // Restore body overflow
            document.body.style.overflow = 'auto';
        }
        
        // Initialize calendar functions
        function initializeCalendarFunctions(calendar) {
            // View buttons functionality
            document.querySelectorAll('.view-options .btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.view-options .btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const view = this.dataset.view;
                    if (view === 'month') {
                        calendar.changeView('dayGridMonth');
                    } else if (view === 'week') {
                        calendar.changeView('timeGridWeek');
                    } else if (view === 'day') {
                        calendar.changeView('timeGridDay');
                    }
                });
            });

            // Modal functionality
            const addEventModal = document.getElementById('addEventModal');
            const addEventBtn = document.getElementById('addEventBtn');
            const closeModal = document.querySelector('.close-modal');
            const cancelEvent = document.getElementById('cancelEvent');

            addEventBtn.onclick = function() {
                addEventModal.style.display = "block";
                addEventModal.style.visibility = "visible";
                addEventModal.style.opacity = "1";
                
                // Set minimum date to tomorrow (no current day scheduling)
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('eventDate').min = tomorrow.toISOString().split('T')[0];
                document.getElementById('eventDate').value = tomorrow.toISOString().split('T')[0];
                
                // Setup automatic end time and validation
                setupAddScheduleValidation();
            }

            closeModal.onclick = function() {
                addEventModal.style.display = "none";
            }

            cancelEvent.onclick = function() {
                addEventModal.style.display = "none";
            }

            // Close modal when clicking outside - REMOVED to prevent accidental closing
            // window.onclick = function(event) {
            //     if (event.target == addEventModal) {
            //         addEventModal.style.display = "none";
            //     }
            //     if (event.target == document.getElementById('eventInfoModal')) {
            //         document.getElementById('eventInfoModal').style.display = "none";
            //     }
            //     if (event.target == document.getElementById('editEventModal')) {
            //         document.getElementById('editEventModal').style.display = "none";
            //     }
            // }

            // Walk-in Schedule Modal Functionality
            const addWalkinBtn = document.getElementById('addWalkinBtn');
            const addWalkinModal = document.getElementById('addWalkinModal');
            
            if (addWalkinBtn && addWalkinModal) {

                addWalkinBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Walk-in Schedule button clicked!');
                    addWalkinModal.style.display = "block";
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling
                    
                    // Set minimum date to tomorrow (no current day scheduling)
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    const dateField = document.getElementById('walkinDate');
                    if (dateField) {
                        dateField.min = tomorrow.toISOString().split('T')[0];
                        dateField.value = tomorrow.toISOString().split('T')[0];
                    }
                    
                    // Setup automatic end time and validation
                    setupWalkinScheduleValidation();
                    
                    // Set current time
                    const now = new Date();
                    const timeField = document.getElementById('walkinTime');
                    if (timeField) {
                        const hours = now.getHours().toString().padStart(2, '0');
                        const minutes = now.getMinutes().toString().padStart(2, '0');
                        timeField.value = `${hours}:${minutes}`;
                    }
                }

                // Cancel walk-in event
                const cancelWalkinEvent = document.getElementById('cancelWalkinEvent');
                if (cancelWalkinEvent) {
                    cancelWalkinEvent.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Cancel walk-in event clicked');
                        closeAddWalkinModal();
                    }
                }

                // Prevent modal from closing when clicking inside
                addWalkinModal.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
                
                // Prevent modal from closing when clicking on the modal content
                const modalContent = addWalkinModal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }
                
                // Add escape key handler to close modal (guarded)
                document.addEventListener('keydown', function(e) {
                    const m = document.getElementById('addWalkinModal');
                    if (!m) return;
                    if (e.key === 'Escape' && m.style.display === 'block') {
                        e.preventDefault();
                        closeAddWalkinModal();
                    }
                });
                
                // Prevent form submission from closing modal
                const walkinForm = document.getElementById('walkinForm');
                if (walkinForm) {
                    walkinForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        console.log('Form submission prevented!');
                        return false;
                    });
                }
            }

            // Add AJAX for saving event
            const saveEventBtn = document.getElementById('saveEvent');
            if (saveEventBtn) {
                saveEventBtn.onclick = function() {
                    // Enhanced form validation
                    const locationEl = document.getElementById('eventLocation');
                    const descriptionEl = document.getElementById('eventDescription');
                    const eventDateEl = document.getElementById('eventDate');
                    const eventStartTimeEl = document.getElementById('eventStartTime');
                    const eventEndTimeEl = document.getElementById('eventEndTime');
                    const caseSelect = document.getElementById('eventCase');
                    const clientSelect = document.getElementById('eventClient');
                    const attorneySelect = document.getElementById('selectedUserId');
                    
                    // Check if elements exist
                    if (!locationEl || !descriptionEl || !eventDateEl || !eventStartTimeEl || !eventEndTimeEl || !attorneySelect) {
                        console.error('Required form elements not found:');
                        console.error('locationEl:', locationEl);
                        console.error('descriptionEl:', descriptionEl);
                        console.error('eventDateEl:', eventDateEl);
                        console.error('eventStartTimeEl:', eventStartTimeEl);
                        console.error('eventEndTimeEl:', eventEndTimeEl);
                        console.error('attorneySelect:', attorneySelect);
                        showNotification('❌ Form not ready. Please try again.', 'error');
                        return;
                    }
                    
                    const location = locationEl.value.trim();
                    const description = descriptionEl.value.trim();
                    const eventDate = eventDateEl.value;
                    const eventStartTime = eventStartTimeEl.value;
                    const eventEndTime = eventEndTimeEl.value;
                
                    // Validate required fields
                    if (!location) {
                        showNotification('❌ Location is required!', 'error');
                        locationEl.focus();
                        return;
                    }
                    
                    if (!description) {
                        showNotification('❌ Description is required!', 'error');
                        descriptionEl.focus();
                        return;
                    }
                    
                    if (!eventDate) {
                        showNotification('❌ Date is required!', 'error');
                        eventDateEl.focus();
                        return;
                    }
                    
                    if (!eventStartTime) {
                        showNotification('❌ Start time is required!', 'error');
                        eventStartTimeEl.focus();
                        return;
                    }
                    
                    if (!eventEndTime) {
                        showNotification('❌ End time is required!', 'error');
                        eventEndTimeEl.focus();
                        return;
                    }
                    
                    // Validate time range (8AM-6PM) - STRICT VALIDATION
                    const startHour = parseInt(eventStartTime.split(':')[0]);
                    const endHour = parseInt(eventEndTime.split(':')[0]);
                    
                    if (startHour < 8 || startHour >= 18) {
                        showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid start time.', 'error');
                        eventStartTimeEl.focus();
                        return;
                    }
                    
                    if (endHour < 8 || endHour > 18) {
                        showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid end time.', 'error');
                        eventEndTimeEl.focus();
                        return;
                    }
                    
                    if (eventEndTime <= eventStartTime) {
                        showNotification('❌ End time must be after start time!', 'error');
                        eventEndTimeEl.focus();
                        return;
                    }
                    
                    // Validate date restriction (no current day)
                    const today = new Date().toISOString().split('T')[0];
                    if (eventDate <= today) {
                        showNotification('❌ You can\'t create a schedule for today. Cannot save with invalid date.', 'error');
                        eventDateEl.focus();
                        return;
                    }
                    
                    if (!clientSelect || !clientSelect.value) {
                        showNotification('❌ Client selection is required!', 'error');
                        if (clientSelect) clientSelect.focus();
                        return;
                    }
                
                    if (!attorneySelect.value) {
                        showNotification('❌ Attorney selection is required!', 'error');
                        attorneySelect.focus();
                        return;
                    }
                
                    // Check if date is in the past
                    const selectedDateTime = new Date(eventDate + 'T' + eventStartTime);
                    const now = new Date();
                    
                    if (selectedDateTime < now) {
                        showNotification('❌ Cannot schedule events in the past. Please select a future date and time.', 'error');
                        return;
                    }
                    
                    // Show loading state
                    const saveBtn = document.getElementById('saveEvent');
                    const originalText = saveBtn.textContent;
                    saveBtn.textContent = 'Saving...';
                    saveBtn.disabled = true;
                
                    const fd = new FormData(document.getElementById('eventForm'));
                    fd.append('action', 'add_event');
                    fetch('employee_schedule.php', { method: 'POST', body: fd })
                        .then(r => r.text()).then(res => {
                            console.log('Server response:', res); // Debug log
                            if (res === 'success') {
                                showNotification('✅ Schedule successfully created!', 'success');
                                // Close the modal using the proper function
                                closeAddEventModal();
                                // Add the new event to calendar without page reload
                                addNewEventToCalendar();
                                // Auto-refresh the page after a short delay to ensure all data is updated
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                console.log('Error response:', res); // Debug log
                                // Handle specific error messages
                                if (res.startsWith('error:')) {
                                    const errorMsg = res.replace('error:', '').trim();
                                    showNotification('❌ ' + errorMsg, 'error');
                                } else {
                                    showNotification('❌ Error saving schedule. Please try again.', 'error');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('❌ Network error. Please check your connection and try again.', 'error');
                        })
                        .finally(() => {
                            saveBtn.textContent = originalText;
                            saveBtn.disabled = false;
                        });
                };
            }

            // Walk-in Schedule Save Handler
            const saveWalkinBtn = document.getElementById('saveWalkinEvent');
            if (saveWalkinBtn) {
                saveWalkinBtn.onclick = function(e) {
                e.preventDefault(); // Prevent form submission
                e.stopPropagation(); // Stop event bubbling
                
                console.log('Walk-in save button clicked!');
                
                // Validate walk-in form fields
                const walkinDate = document.getElementById('walkinDate').value;
                const walkinStartTime = document.getElementById('walkinStartTime').value;
                const walkinEndTime = document.getElementById('walkinEndTime').value;
                const walkinClientSurname = document.getElementById('walkinClientSurname').value.trim();
                const walkinClientFirstName = document.getElementById('walkinClientFirstName').value.trim();
                const walkinClientContact = document.getElementById('walkinClientContact').value.trim();
                const walkinLocation = document.getElementById('walkinLocation').value.trim();
                const walkinDescription = document.getElementById('walkinDescription').value.trim();
                const walkinAttorney = document.getElementById('walkinAttorney').value;
                
                // Validate required fields
                if (!walkinClientSurname) {
                    showNotification('❌ Client surname is required!', 'error');
                    document.getElementById('walkinClientSurname').focus();
                    return;
                }
                
                if (!walkinClientFirstName) {
                    showNotification('❌ Client first name is required!', 'error');
                    document.getElementById('walkinClientFirstName').focus();
                    return;
                }
                
                if (!walkinClientContact) {
                    showNotification('❌ Client contact number is required!', 'error');
                    document.getElementById('walkinClientContact').focus();
                    return;
                }
                
                if (!walkinDate) {
                    showNotification('❌ Date is required!', 'error');
                    document.getElementById('walkinDate').focus();
                    return;
                }
                
                if (!walkinStartTime) {
                    showNotification('❌ Start time is required!', 'error');
                    document.getElementById('walkinStartTime').focus();
                    return;
                }
                
                if (!walkinEndTime) {
                    showNotification('❌ End time is required!', 'error');
                    document.getElementById('walkinEndTime').focus();
                    return;
                }
                
                if (!walkinLocation) {
                    showNotification('❌ Location is required!', 'error');
                    document.getElementById('walkinLocation').focus();
                    return;
                }
                
                if (!walkinDescription) {
                    showNotification('❌ Description is required!', 'error');
                    document.getElementById('walkinDescription').focus();
                    return;
                }
                
                if (!walkinAttorney) {
                    showNotification('❌ Attorney selection is required!', 'error');
                    document.getElementById('walkinAttorney').focus();
                    return;
                }
                
                // Validate time range (8AM-6PM) - STRICT VALIDATION
                const startHour = parseInt(walkinStartTime.split(':')[0]);
                const endHour = parseInt(walkinEndTime.split(':')[0]);
                
                if (startHour < 8 || startHour >= 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid start time.', 'error');
                    document.getElementById('walkinStartTime').focus();
                    return;
                }
                
                if (endHour < 8 || endHour > 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid end time.', 'error');
                    document.getElementById('walkinEndTime').focus();
                    return;
                }
                
                if (walkinEndTime <= walkinStartTime) {
                    showNotification('❌ End time must be after start time!', 'error');
                    document.getElementById('walkinEndTime').focus();
                    return;
                }
                
                // Validate date restriction (no current day)
                const today = new Date().toISOString().split('T')[0];
                if (walkinDate <= today) {
                    showNotification('❌ You can\'t create a schedule for today. Cannot save with invalid date.', 'error');
                    document.getElementById('walkinDate').focus();
                    return;
                }
                
                const form = document.getElementById('walkinForm');
                const formData = new FormData(form);
                formData.append('action', 'add_walkin');

                // Show loading state
                const saveBtn = document.querySelector('#addWalkinModal .btn-primary');
                const originalText = saveBtn.textContent;
                saveBtn.textContent = 'Saving...';
                saveBtn.disabled = true;

                fetch('employee_schedule.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(result => {
                    console.log('Walk-in server response:', result); // Debug log
                    if (result === 'success') {
                        showNotification('✅ Walk-in schedule created successfully!', 'success');
                        // Close the modal properly
                        closeAddWalkinModal();
                        // Auto-refresh the page after a short delay to ensure all data is updated
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        console.log('Walk-in error response:', result); // Debug log
                        // Handle specific error messages
                        if (result.startsWith('error:')) {
                            const errorMsg = result.replace('error:', '').trim();
                            showNotification('❌ ' + errorMsg, 'error');
                        } else {
                            showNotification('❌ Error creating walk-in schedule. Please try again.', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('❌ Error creating walk-in schedule. Please try again.', 'error');
                })
                .finally(() => {
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                });
                };
            }

            // Function to add new regular event to calendar
            function addNewEventToCalendar() {
                // Get form data
                const form = document.getElementById('eventForm');
                const formData = new FormData(form);
                
                // Create event object from form data
                const newEvent = {
                    title: formData.get('type'),
                    start: formData.get('date') + 'T' + formData.get('time'),
                    allDay: false,
                    backgroundColor: '#8B0000',
                    borderColor: '#8B0000',
                    textColor: 'white',
                    extendedProps: {
                        type: formData.get('type'),
                        location: formData.get('location'),
                        description: formData.get('description'),
                        client: formData.get('client_id'),
                        attorney: formData.get('selected_user_id')
                    }
                };
                
                // Add event to calendar
                calendar.addEvent(newEvent);
            }
            
            // Function to add new walk-in event to calendar
            function addNewWalkinEventToCalendar() {
                // Get form data
                const form = document.getElementById('walkinForm');
                const formData = new FormData(form);
                
                // Combine name fields
                const clientSurname = formData.get('client_surname');
                const clientFirstName = formData.get('client_first_name');
                const clientMiddleName = formData.get('client_middle_name');
                const clientName = clientSurname + ', ' + clientFirstName + (clientMiddleName ? ' ' + clientMiddleName : '');
                
                // Create event object from form data
                const newEvent = {
                    title: formData.get('type'),
                    start: formData.get('date') + 'T' + formData.get('time'),
                    allDay: false,
                    backgroundColor: '#28a745',
                    borderColor: '#28a745',
                    textColor: 'white',
                    extendedProps: {
                        type: formData.get('type'),
                        location: formData.get('location'),
                        description: formData.get('description'),
                        walkinClientName: clientName,
                        walkinClientContact: formData.get('client_contact'),
                        attorney: formData.get('selected_user_id')
                    }
                };
                
                // Add event to calendar
                calendar.addEvent(newEvent);
            }

            // Function to refresh calendar and events without page reload
            function refreshCalendarAndEvents() {
                // Reload the page immediately to show the new schedule
                location.reload();
            }

            // Function to close modal properly
            function closeAddEventModal() {
                const modal = document.getElementById('addEventModal');
                if (modal) {
                    modal.style.display = 'none';
                    // Also remove any backdrop/overlay effects
                    modal.classList.remove('show');
                }
                // Reset form
                const form = document.getElementById('eventForm');
                if (form) {
                    form.reset();
                }
            }

            // Initialize event handlers
            initializeEventHandlers();
            
            // Debug: Check if Save Schedule button exists
            const saveBtn = document.getElementById('saveEvent');
            if (saveBtn) {
                console.log('✅ Save Schedule button found and initialized');
            } else {
                console.error('❌ Save Schedule button not found!');
            }
        }
        
        function showEventDetails(event) {
            const set = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
            set('modalEventType', event.title?.split(':')[0] || 'Event');
            set('modalEventDate', event.start ? event.start.toLocaleDateString() : '-');
            set('modalEventTime', event.start ? event.start.toLocaleTimeString() : '-');
            set('modalType', event.extendedProps?.type || '-');
            set('modalDate', event.start ? event.start.toLocaleDateString() : '-');
            set('modalTime', event.start ? event.start.toLocaleTimeString() : '-');
            set('modalLocation', event.extendedProps?.location || '-');
            set('modalCase', event.extendedProps?.case || '-');
            set('modalAttorney', event.extendedProps?.attorney || '-');
            set('modalClient', event.extendedProps?.client || '-');
            set('modalDescription', event.extendedProps?.description || '-');
            set('modalCreatedBy', event.extendedProps?.createdBy || '-');
            const infoModal = document.getElementById('eventInfoModal');
            if (infoModal) infoModal.style.display = 'block';
        }
        
        // Function to edit event
        function editEvent(button) {
            // Validate that the event is not completed
            if (button.dataset.status && button.dataset.status.toLowerCase() === 'completed') {
                showNotification('❌ Cannot edit completed schedules!', 'error');
                return;
            }

            const eventId = button.dataset.eventId;

            const eventTitle = button.dataset.title;
            const eventType = button.dataset.type;
            const eventDate = button.dataset.date;
            const eventStartTime = button.dataset.startTime;
            const eventEndTime = button.dataset.endTime;
            const eventLocation = button.dataset.location;
            const eventCase = button.dataset.case;
            const eventAttorney = button.dataset.attorney;
            const eventClient = button.dataset.client;
            const eventDescription = button.dataset.description;

            // Populate the edit modal
            document.getElementById('editEventId').value = eventId;
            document.getElementById('editEventType').value = eventType;
            document.getElementById('editEventDate').value = eventDate;
            document.getElementById('editEventStartTime').value = eventStartTime;
            document.getElementById('editEventEndTime').value = eventEndTime;
            document.getElementById('editEventLocation').value = eventLocation;
            document.getElementById('editEventDescription').value = eventDescription;

            // Case association is fixed - no need to load cases for edit

            // Set minimum date to tomorrow (no current day scheduling)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('editEventDate').min = tomorrow.toISOString().split('T')[0];

            // Add event listeners for automatic end time and validation
            setupEditScheduleValidation();

            // Show the edit modal
            document.getElementById('editEventModal').style.display = 'block';
        }

        // Setup validation for walk-in schedule form
        function setupWalkinScheduleValidation() {
            const startTimeInput = document.getElementById('walkinStartTime');
            const endTimeInput = document.getElementById('walkinEndTime');
            const dateInput = document.getElementById('walkinDate');

            // Automatic end time (30-minute interval)
            if (startTimeInput) {
                startTimeInput.addEventListener('change', function() {
                    if (this.value && endTimeInput) {
                        const startTime = new Date('2000-01-01T' + this.value);
                        const endTime = new Date(startTime.getTime() + 30 * 60000); // Add 30 minutes
                        endTimeInput.value = endTime.toTimeString().slice(0, 5);
                    }
                });
            }

            // Time range validation (8AM-6PM)
            if (startTimeInput) startTimeInput.addEventListener('change', validateWalkinTimeRange);
            if (endTimeInput) endTimeInput.addEventListener('change', validateWalkinTimeRange);
            if (dateInput) dateInput.addEventListener('change', validateWalkinDate);
        }

        // Validate time range for walk-in schedule (8AM-6PM)
        function validateWalkinTimeRange() {
            const startTime = document.getElementById('walkinStartTime').value;
            const endTime = document.getElementById('walkinEndTime').value;
            
            if (startTime) {
                const startHour = parseInt(startTime.split(':')[0]);
                if (startHour < 8 || startHour >= 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM.', 'error');
                    return false;
                }
            }
            
            if (endTime) {
                const endHour = parseInt(endTime.split(':')[0]);
                if (endHour < 8 || endHour > 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM.', 'error');
                    return false;
                }
            }
            
            return true;
        }

        // Validate date for walk-in schedule (no current day)
        function validateWalkinDate() {
            const selectedDate = document.getElementById('walkinDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Setup validation for add schedule form
        function setupAddScheduleValidation() {
            const startTimeInput = document.getElementById('eventStartTime');
            const endTimeInput = document.getElementById('eventEndTime');
            const dateInput = document.getElementById('eventDate');

            // Automatic end time (30-minute interval)
            startTimeInput.addEventListener('change', function() {
                if (this.value) {
                    const startTime = new Date('2000-01-01T' + this.value);
                    const endTime = new Date(startTime.getTime() + 30 * 60000); // Add 30 minutes
                    endTimeInput.value = endTime.toTimeString().slice(0, 5);
                }
            });

            // Time range validation (8AM-6PM)
            startTimeInput.addEventListener('change', validateAddTimeRange);
            endTimeInput.addEventListener('change', validateAddTimeRange);

            // Date validation (no current day)
            dateInput.addEventListener('change', validateAddDate);
        }

        // Validate time range for add schedule (8AM-6PM)
        function validateAddTimeRange() {
            const startTime = document.getElementById('eventStartTime').value;
            const endTime = document.getElementById('eventEndTime').value;
            
            if (startTime) {
                const startHour = parseInt(startTime.split(':')[0]);
                if (startHour < 8 || startHour >= 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM.', 'error');
                    return false;
                }
            }
            
            if (endTime) {
                const endHour = parseInt(endTime.split(':')[0]);
                if (endHour < 8 || endHour > 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM.', 'error');
                    return false;
                }
            }
            
            return true;
        }

        // Validate date for add schedule (no current day)
        function validateAddDate() {
            const selectedDate = document.getElementById('eventDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Setup validation for edit schedule form
        function setupEditScheduleValidation() {
            const startTimeInput = document.getElementById('editEventStartTime');
            const endTimeInput = document.getElementById('editEventEndTime');
            const dateInput = document.getElementById('editEventDate');

            // Automatic end time (30-minute interval)
            startTimeInput.addEventListener('change', function() {
                if (this.value) {
                    const startTime = new Date('2000-01-01T' + this.value);
                    const endTime = new Date(startTime.getTime() + 30 * 60000); // Add 30 minutes
                    endTimeInput.value = endTime.toTimeString().slice(0, 5);
                }
            });

            // Time range validation (8AM-6PM)
            startTimeInput.addEventListener('change', validateEditTimeRange);
            endTimeInput.addEventListener('change', validateEditTimeRange);

            // Date validation (no current day)
            dateInput.addEventListener('change', validateEditDate);
        }

        // Validate time range for edit schedule (8AM-6PM)
        function validateEditTimeRange() {
            const startTime = document.getElementById('editEventStartTime').value;
            const endTime = document.getElementById('editEventEndTime').value;
            
            if (startTime) {
                const startHour = parseInt(startTime.split(':')[0]);
                if (startHour < 8 || startHour >= 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM.', 'error');
                    return false;
                }
            }
            
            if (endTime) {
                const endHour = parseInt(endTime.split(':')[0]);
                if (endHour < 8 || endHour > 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM.', 'error');
                    return false;
                }
            }
            
            return true;
        }

        // Validate date for edit schedule (no current day)
        function validateEditDate() {
            const selectedDate = document.getElementById('editEventDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Function to close edit modal
        function closeEditModal() {
            // Show confirmation dialog
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                document.getElementById('editEventModal').style.display = 'none';
            }
        }

        // Function to save event changes
        async function saveEventChanges() {
            // Show enhanced confirmation with typing requirement
            const confirmMessage = `⚠️ WARNING: Save changes to this event?\n\nThis action will:\n• Update the event details\n• Modify the schedule\n• Cannot be easily undone\n\nAre you sure you want to proceed?`;
            
            // Validate form fields
            const startTime = document.getElementById('editEventStartTime').value;
            const endTime = document.getElementById('editEventEndTime').value;
            const eventDate = document.getElementById('editEventDate').value;
            
            if (!startTime || !endTime || !eventDate) {
                showNotification('❌ All fields are required!', 'error');
                return;
            }

            // Validate time range (8AM-6PM)
            const startHour = parseInt(startTime.split(':')[0]);
            const endHour = parseInt(endTime.split(':')[0]);
            
            if (startHour < 8 || startHour >= 18) {
                showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid start time.', 'error');
                document.getElementById('editEventStartTime').focus();
                return;
            }
            
            if (endHour < 8 || endHour > 18) {
                showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid end time.', 'error');
                document.getElementById('editEventEndTime').focus();
                return;
            }

            // Validate that end time is after start time
            if (endTime <= startTime) {
                showNotification('❌ End time must be after start time!', 'error');
                document.getElementById('editEventEndTime').focus();
                return;
            }
            
            // Validate that date is not current day or past
            const today = new Date().toISOString().split('T')[0];
            if (eventDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                document.getElementById('editEventDate').focus();
                return;
            }

            const confirmed = await showEditDoubleConfirmation();
            if (!confirmed) {
                return;
            }
            
            const form = document.getElementById('editEventForm');
            const formData = new FormData(form);
            formData.append('action', 'edit_event');

            // Show loading state
            const saveBtn = document.querySelector('#editEventModal .btn-primary');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            fetch('employee_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                console.log('Server response:', result); // Debug log
                if (result === 'success') {
                    showNotification('✅ Event updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    // Display the actual error message from PHP
                    const errorMessage = result.startsWith('error:') ? result.substring(6) : 'Unknown error occurred';
                    showNotification('❌ ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error updating event. Please try again.', 'error');
            })
            .finally(() => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
        }

        // Initialize all event handlers
        function initializeEventHandlers() {
            // Initialize status selects
            document.querySelectorAll('.status-select').forEach(select => {
                select.dataset.previousStatus = select.value;
            });
            
            // Initialize view details buttons
            document.querySelectorAll('.view-info-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Populate modal with event data
                    const set = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
                    set('modalEventType', this.dataset.type || 'Event');
                    set('modalEventDate', this.dataset.date || 'Date');
                    set('modalEventTime', this.dataset.startTime && this.dataset.endTime ? 
                        new Date('1970-01-01T' + this.dataset.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + 
                        ' - ' + 
                        new Date('1970-01-01T' + this.dataset.endTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Time');
                    set('modalType', this.dataset.type || '-');
                    set('modalDate', this.dataset.date || '-');
                    set('modalTime', this.dataset.startTime && this.dataset.endTime ? 
                        new Date('1970-01-01T' + this.dataset.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + 
                        ' - ' + 
                        new Date('1970-01-01T' + this.dataset.endTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '-');
                    set('modalLocation', this.dataset.location || '-');
                    set('modalCase', this.dataset.case || '-');
                    set('modalAttorney', this.dataset.attorney || '-');
                    
                    // Handle walk-in clients
                    const clientName = this.dataset.client || '-';
                    const walkinClientName = this.dataset.walkinClientName || null;
                    const walkinClientContact = this.dataset.walkinClientContact || null;
                    
                    if (walkinClientName) {
                        // This is a walk-in client - show walk-in details section
                        document.getElementById('caseDetailsSection').style.display = 'none';
                        document.getElementById('walkinDetailsSection').style.display = 'block';
                        
                        // Populate walk-in specific details
                        set('modalWalkinClientName', walkinClientName);
                        set('modalWalkinClientContact', walkinClientContact || '-');
                        set('modalWalkinAttorney', this.dataset.attorney || '-');
                        set('modalWalkinDescription', this.dataset.description || '-');
                        set('modalWalkinCreatedBy', this.dataset.createdBy || '-');
                    } else {
                        // Regular client - show case details section
                        document.getElementById('caseDetailsSection').style.display = 'block';
                        document.getElementById('walkinDetailsSection').style.display = 'none';
                        
                        // Populate regular client details
                        set('modalClient', clientName);
                        set('modalAttorney', this.dataset.attorney || '-');
                        set('modalDescription', this.dataset.description || '-');
                        set('modalCreatedBy', this.dataset.createdBy || '-');
                    }

                    // Show modal
                    document.getElementById('eventInfoModal').style.display = "block";
                });
            });
            
            // Initialize modal close functionality
            document.getElementById('closeEventInfoModal').addEventListener('click', function() {
                document.getElementById('eventInfoModal').style.display = "none";
            });
            
            // Initialize walk-in modal cancel button
            const cancelWalkinBtn = document.getElementById('cancelWalkinEvent');
            if (cancelWalkinBtn) {
                cancelWalkinBtn.addEventListener('click', function() {
                    closeAddWalkinModal();
                });
            }
        }
        
        // Wait for DOM to be ready, then initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            
            console.log('DOM Content Loaded');
            console.log('FullCalendar available:', typeof FullCalendar !== 'undefined');
            
            // Test if calendar element exists
            const calendarEl = document.getElementById('calendar');
            console.log('Calendar element found:', calendarEl);
            
            if (calendarEl) {
                console.log('Calendar element dimensions:', calendarEl.offsetWidth, 'x', calendarEl.offsetHeight);
                console.log('Calendar element styles:', window.getComputedStyle(calendarEl));
            }
            
            // Apply schedule card colors
            if (window.colorManager) {
                console.log('Applying schedule card colors...');
                // Apply colors to all event cards
                document.querySelectorAll('.event-card').forEach(function(card) {
                    const attorneyId = card.getAttribute('data-attorney-id') || card.getAttribute('data-user-id');
                    if (attorneyId) {
                        window.colorManager.applyScheduleCardColors(card, attorneyId);
                    }
                });
            }
            
            // Check if FullCalendar is loaded
            if (typeof FullCalendar !== 'undefined') {
                console.log('Calling waitForFullCalendar...');
                waitForFullCalendar();
            } else {
                console.log('FullCalendar not loaded yet, waiting...');
                // If not loaded yet, wait a bit and try again
                setTimeout(function() {
                    console.log('Checking FullCalendar again...');
                    if (typeof FullCalendar !== 'undefined') {
                        console.log('FullCalendar now available, calling waitForFullCalendar...');
                        waitForFullCalendar();
                    } else {
                        console.error('FullCalendar failed to load');
                    }
                }, 1000);
            }
        });

        // Handle completed event behavior
        function handleCompletedEvent(selectElement, eventId) {
            const eventCard = selectElement.closest('.event-card');
            
            console.log('🔄 Handling completed event:', {
                eventId: eventId,
                eventCard: eventCard,
                calendar: window.calendar
            });
            
            // Show completion toast
            showNotification('✅ Schedule marked as completed and moved to completed section.', 'success');
            
            // Disable the status select and edit buttons
            const statusSelect = eventCard.querySelector('.status-select');
            const editButton = eventCard.querySelector('button[data-action="edit"]');
            const viewButton = eventCard.querySelector('button[data-action="view"]');
            
            if (statusSelect) {
                statusSelect.disabled = true;
                statusSelect.style.opacity = '0.6';
                statusSelect.style.cursor = 'not-allowed';
            }
            
            if (editButton) {
                editButton.disabled = true;
                editButton.style.opacity = '0.6';
                editButton.style.cursor = 'not-allowed';
                editButton.title = 'Cannot edit completed schedules';
            }
            
            // Add completed styling
            eventCard.classList.add('completed-schedule');
            
            // Animate card movement to completed section
            setTimeout(() => {
                eventCard.style.transition = 'all 0.5s ease';
                eventCard.style.opacity = '0';
                eventCard.style.transform = 'translateY(-20px) scale(0.95)';
                
                setTimeout(() => {
                    // Move card to completed section
                    moveCardToCompletedSection(eventCard);
                    
                    // Remove from calendar if FullCalendar is available
                    if (window.calendar) {
                        console.log('🔍 Looking for calendar event with ID:', eventId);
                        console.log('📅 Calendar instance:', window.calendar);
                        
                        // Try to get the event by ID
                        const calendarEvent = window.calendar.getEventById(eventId);
                        
                        if (calendarEvent) {
                            calendarEvent.remove();
                            console.log('✅ Successfully removed event from calendar:', eventId);
                        } else {
                            console.log('⚠️ Event not found in calendar with ID:', eventId);
                            
                            // Debug: List all events in calendar
                            const allEvents = window.calendar.getEvents();
                            console.log('📋 All calendar events:', allEvents.map(e => ({ id: e.id, title: e.title })));
                            
                            // Try to find event by title or other properties
                            const eventByTitle = allEvents.find(e => e.title.includes(eventCard.querySelector('.event-title')?.textContent || ''));
                            if (eventByTitle) {
                                eventByTitle.remove();
                                console.log('✅ Removed event by title match:', eventByTitle.title);
                            }
                        }
                    } else {
                        console.log('❌ Calendar instance not available');
                    }
                    
                    // Update filter counts
                    updateFilterCounts();
                }, 500);
            }, 2000); // Show for 2 seconds before moving
        }

        // Move card to completed section
        function moveCardToCompletedSection(eventCard) {
            const completedGrid = document.querySelector('.completed-events-grid');
            const noCompleted = document.querySelector('.no-completed');
            
            if (completedGrid) {
                // Reset card styling
                eventCard.style.opacity = '1';
                eventCard.style.transform = 'scale(1)';
                eventCard.style.display = 'block';
                
                // Move to completed section
                completedGrid.appendChild(eventCard);
                console.log('✅ Moved card to completed section');
                
                // Hide "no completed" message
                if (noCompleted) {
                    noCompleted.style.display = 'none';
                }
                
                // Show completed section if hidden
                const completedSection = document.querySelector('.completed-schedules-section');
                if (completedSection && completedSection.style.display === 'none') {
                    completedSection.style.display = 'block';
                    console.log('✅ Showed completed section');
                }
                
                // Update the show/hide button states
                const showBtn = document.querySelector('.show-completed-btn');
                const hideBtn = document.querySelector('.toggle-completed-btn');
                if (showBtn) showBtn.style.display = 'none';
                if (hideBtn) hideBtn.style.display = 'flex';
            } else {
                console.error('❌ Completed grid not found');
            }
        }

        // Update filter button counts
        function updateFilterCounts() {
            const filterButtons = document.querySelectorAll('.status-filter-btn');
            const allCards = document.querySelectorAll('.event-card:not([style*="display: none"])');
            
            filterButtons.forEach(button => {
                const status = button.getAttribute('data-status');
                let count = 0;
                
                if (status === 'all') {
                    count = allCards.length;
                } else {
                    allCards.forEach(card => {
                        const cardStatus = getCardStatus(card);
                        if (cardStatus === status) {
                            count++;
                        }
                    });
                }
                
                // Update button text with count
                const originalText = button.textContent.split(' (')[0];
                button.textContent = `${originalText} (${count})`;
            });
        }

        // Get card status from status select or classes
        function getCardStatus(card) {
            const statusSelect = card.querySelector('.status-select');
            if (statusSelect) {
                return statusSelect.value.toLowerCase();
            }
            
            // Fallback to CSS classes
            if (card.classList.contains('status-scheduled')) return 'scheduled';
            if (card.classList.contains('status-completed')) return 'completed';
            if (card.classList.contains('status-rescheduled')) return 'rescheduled';
            if (card.classList.contains('status-cancelled')) return 'cancelled';
            
            return 'scheduled';
        }

        // Global Status Filter Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize global status filter
            initializeGlobalStatusFilter();
            
            // Initialize completed section
            initializeCompletedSection();
        });

        // Initialize completed section
        function initializeCompletedSection() {
            // Check if there are any completed schedules on page load
            const completedCards = document.querySelectorAll('.event-card.status-completed, .event-card.completed-schedule');
            const completedGrid = document.querySelector('.completed-events-grid');
            const noCompleted = document.querySelector('.no-completed');
            
            if (completedCards.length > 0 && completedGrid) {
                completedCards.forEach(card => {
                    // Ensure the card has the completed-schedule class for proper styling
                    if (!card.classList.contains('completed-schedule')) {
                        card.classList.add('completed-schedule');
                    }
                    
                    // Disable status select and edit buttons for completed schedules
                    const statusSelect = card.querySelector('.status-select');
                    const editButton = card.querySelector('button[data-action="edit"]');
                    
                    if (statusSelect) {
                        statusSelect.disabled = true;
                        statusSelect.style.opacity = '0.6';
                        statusSelect.style.cursor = 'not-allowed';
                    }
                    
                    if (editButton) {
                        editButton.disabled = true;
                        editButton.style.opacity = '0.6';
                        editButton.style.cursor = 'not-allowed';
                        editButton.title = 'Cannot edit completed schedules';
                    }
                    
                    // Move existing completed cards to completed section
                    completedGrid.appendChild(card);
                });
                
                // Hide "no completed" message
                if (noCompleted) {
                    noCompleted.style.display = 'none';
                }
                
                console.log(`✅ Moved ${completedCards.length} existing completed cards to completed section`);
            }
        }

        function initializeGlobalStatusFilter() {
            // Find the events filter container
            const eventsFilterContainer = document.querySelector('.events-filter-section');
            
            if (!eventsFilterContainer) {
                return; // Skip if no filter found
            }
            
            const buttons = eventsFilterContainer.querySelectorAll('.status-filter-btn');
            
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    buttons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Get the selected status
                    const selectedStatus = this.getAttribute('data-status');
                    
                    // Filter all event cards across all sections
                    filterAllEventCards(selectedStatus);
                });
            });
        }

        function filterAllEventCards(selectedStatus) {
            // Get all event cards from all sections
            const allEventCards = document.querySelectorAll('.event-card');
            
            allEventCards.forEach(card => {
                // Get the status from the card's data attribute or status select
                const statusSelect = card.querySelector('.status-select');
                let cardStatus = '';
                
                if (statusSelect) {
                    cardStatus = statusSelect.value.toLowerCase();
                } else {
                    // Fallback: check for status classes
                    if (card.classList.contains('status-scheduled')) cardStatus = 'scheduled';
                    else if (card.classList.contains('status-completed')) cardStatus = 'completed';
                    else if (card.classList.contains('status-rescheduled')) cardStatus = 'rescheduled';
                    else if (card.classList.contains('status-cancelled')) cardStatus = 'cancelled';
                    else cardStatus = 'scheduled'; // Default
                }
                
                // Show/hide card based on filter
                if (selectedStatus === 'all' || cardStatus === selectedStatus) {
                    card.classList.remove('filtered-out');
                } else {
                    card.classList.add('filtered-out');
                }
            });
            
            // Smooth transition effect
            setTimeout(() => {
                allEventCards.forEach(card => {
                    if (card.classList.contains('filtered-out')) {
                        card.style.display = 'none';
                    } else {
                        card.style.display = 'block';
                    }
                });
            }, 400); // Match CSS transition duration
        }

        // Initialize pagination for all sections
        document.addEventListener('DOMContentLoaded', function() {
            initAllPagination();
        });



        function initAdminPagination() {
            let currentPage = 1;
            let itemsPerPage = 6;

            const itemsPerPageSelect = document.getElementById('admin-items-per-page');
            const prevBtn = document.getElementById('admin-prev-btn');
            const nextBtn = document.getElementById('admin-next-btn');
            const paginationInfo = document.getElementById('admin-pagination-info');
            const grid = document.querySelector('.admin-priority .events-grid');
            
            if (!grid) return;

            function updateAdminDisplay() {
                const cards = Array.from(grid.querySelectorAll('.event-card'));
                const visibleCards = cards.filter(card => !card.classList.contains('filtered-out'));
                
                const totalPages = Math.ceil(visibleCards.length / itemsPerPage);
                
                // Hide all cards
                cards.forEach(card => card.style.display = 'none');
                
                // Show cards for current page
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                visibleCards.slice(start, end).forEach(card => card.style.display = 'block');
                
                // Update pagination info
                if (paginationInfo) {
                    paginationInfo.textContent = `Page ${currentPage} of ${totalPages || 1}`;
                }
                
                // Update buttons
                if (prevBtn) prevBtn.disabled = currentPage === 1;
                if (nextBtn) nextBtn.disabled = currentPage >= totalPages || totalPages === 0;
            }

            // Event listeners
            if (itemsPerPageSelect) {
                itemsPerPageSelect.addEventListener('change', function() {
                    itemsPerPage = parseInt(this.value);
                    currentPage = 1;
                    updateAdminDisplay();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        updateAdminDisplay();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const totalPages = Math.ceil(Array.from(grid.querySelectorAll('.event-card:not(.filtered-out)')).length / itemsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        updateAdminDisplay();
                    }
                });
            }

            // Initial display
            updateAdminDisplay();
        }

        // Initialize attorney pagination for each attorney group
        function initAttorneyPagination() {
            const attorneyGroups = document.querySelectorAll('.attorney-schedule-group');
            
            attorneyGroups.forEach(function(attorneyGroup, index) {
                let currentPage = 1;
                let itemsPerPage = 6;

                const grid = attorneyGroup.querySelector('.events-grid');
                if (!grid) return;

                // Create pagination controls if they don't exist
                let paginationControls = attorneyGroup.querySelector('.pagination-controls');
                if (!paginationControls) {
                    paginationControls = document.createElement('div');
                    paginationControls.className = 'pagination-controls';
                    paginationControls.innerHTML = `
                        <div class="items-per-page">
                            <label for="itemsPerPageAttorney${index}">Show:</label>
                            <select id="itemsPerPageAttorney${index}">
                                <option value="6">6</option>
                                <option value="12">12</option>
                                <option value="18">18</option>
                                <option value="24">24</option>
                            </select>
                        </div>
                        <span class="pagination-info" id="attorney-pagination-info${index}">Page 1</span>
                        <div class="pagination-buttons">
                            <button class="btn-pagination" id="attorney-prev-btn${index}" disabled>Previous</button>
                            <button class="btn-pagination" id="attorney-next-btn${index}">Next</button>
                        </div>
                    `;
                    attorneyGroup.appendChild(paginationControls);
                }

                const itemsPerPageSelect = attorneyGroup.querySelector(`#itemsPerPageAttorney${index}`);
                const prevBtn = attorneyGroup.querySelector(`#attorney-prev-btn${index}`);
                const nextBtn = attorneyGroup.querySelector(`#attorney-next-btn${index}`);
                const paginationInfo = attorneyGroup.querySelector(`#attorney-pagination-info${index}`);

                function updateAttorneyDisplay() {
                    const cards = Array.from(grid.querySelectorAll('.event-card'));
                    const visibleCards = cards.filter(card => !card.classList.contains('filtered-out'));
                    
                    const totalPages = Math.ceil(visibleCards.length / itemsPerPage);
                    
                    // Hide all cards
                    cards.forEach(card => card.style.display = 'none');
                    
                    // Show cards for current page
                    const start = (currentPage - 1) * itemsPerPage;
                    const end = start + itemsPerPage;
                    visibleCards.slice(start, end).forEach(card => card.style.display = 'block');
                    
                    // Update pagination info
                    if (paginationInfo) {
                        paginationInfo.textContent = `Page ${currentPage} of ${totalPages || 1}`;
                    }
                    
                    // Update buttons
                    if (prevBtn) prevBtn.disabled = currentPage === 1;
                    if (nextBtn) nextBtn.disabled = currentPage >= totalPages || totalPages === 0;
                }

                // Event listeners
                if (itemsPerPageSelect) {
                    itemsPerPageSelect.addEventListener('change', function() {
                        itemsPerPage = parseInt(this.value);
                        currentPage = 1;
                        updateAttorneyDisplay();
                    });
                }

                if (prevBtn) {
                    prevBtn.addEventListener('click', function() {
                        if (currentPage > 1) {
                            currentPage--;
                            updateAttorneyDisplay();
                        }
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', function() {
                        const totalPages = Math.ceil(Array.from(grid.querySelectorAll('.event-card:not(.filtered-out)')).length / itemsPerPage);
                        if (currentPage < totalPages) {
                            currentPage++;
                            updateAttorneyDisplay();
                        }
                    });
                }

                // Initial display
                updateAttorneyDisplay();
            });
        }

        // Initialize completed schedules pagination
        function initCompletedPagination() {
            let currentPage = 1;
            let itemsPerPage = 6;

            const itemsPerPageSelect = document.getElementById('completed-items-per-page');
            const prevBtn = document.getElementById('completed-prev-btn');
            const nextBtn = document.getElementById('completed-next-btn');
            const paginationInfo = document.getElementById('completed-pagination-info');
            const grid = document.querySelector('.completed-events-grid');
            
            if (!grid) return;

            function updateCompletedDisplay() {
                const cards = Array.from(grid.querySelectorAll('.event-card'));
                const visibleCards = cards.filter(card => !card.classList.contains('filtered-out'));
                
                const totalPages = Math.ceil(visibleCards.length / itemsPerPage);
                
                // Hide all cards
                cards.forEach(card => card.style.display = 'none');
                
                // Show cards for current page
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                visibleCards.slice(start, end).forEach(card => card.style.display = 'block');
                
                // Update pagination info
                if (paginationInfo) {
                    paginationInfo.textContent = `Page ${currentPage} of ${totalPages || 1}`;
                }
                
                // Update buttons
                if (prevBtn) prevBtn.disabled = currentPage === 1;
                if (nextBtn) nextBtn.disabled = currentPage >= totalPages || totalPages === 0;
            }

            // Event listeners
            if (itemsPerPageSelect) {
                itemsPerPageSelect.addEventListener('change', function() {
                    itemsPerPage = parseInt(this.value);
                    currentPage = 1;
                    updateCompletedDisplay();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        updateCompletedDisplay();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const totalPages = Math.ceil(Array.from(grid.querySelectorAll('.event-card:not(.filtered-out)')).length / itemsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        updateCompletedDisplay();
                    }
                });
            }

            // Initial display
            updateCompletedDisplay();
        }

        // Update initAllPagination to include attorney and completed pagination
        function initAllPagination() {
            // Initialize admin pagination
            const adminGrid = document.querySelector('.admin-priority .events-grid');
            if (adminGrid) {
                initAdminPagination();
            }
            
            // Initialize attorney pagination
            initAttorneyPagination();
            
            // Initialize completed schedules pagination
            initCompletedPagination();
        }
    </script>
<script src="assets/js/unread-messages.js?v=1761535513"></script></body>
</html> 