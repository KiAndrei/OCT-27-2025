<?php

require_once 'session_manager.php';

validateUserAccess('admin');

require_once 'config.php';

require_once 'audit_logger.php';

require_once 'action_logger_helper.php';

require_once 'color_manager.php';

require_once 'color_assignment.php';

$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");

$stmt->bind_param("i", $admin_id);

$stmt->execute();

$res = $stmt->get_result();

$profile_image = '';

if ($res && $row = $res->fetch_assoc()) {

    $profile_image = $row['profile_image'];

}

if (!$profile_image || !file_exists($profile_image)) {

        $profile_image = 'images/default-avatar.jpg';

    }

// Fetch all cases for dropdown

$cases = [];

$stmt = $conn->prepare("SELECT ac.id, ac.title, uf1.name as client_name, uf2.name as attorney_name FROM attorney_cases ac 

    LEFT JOIN user_form uf1 ON ac.client_id = uf1.id 

    LEFT JOIN user_form uf2 ON ac.attorney_id = uf2.id 

    ORDER BY ac.id DESC");

$stmt->execute();

$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) $cases[] = $row;



// Fetch all attorneys and admins for dropdown
$attorneys_and_admins = [];
$stmt = $conn->prepare("SELECT id, name, user_type FROM user_form WHERE user_type IN ('attorney', 'admin') ORDER BY user_type, name");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $attorneys_and_admins[] = $row;



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



    // Update the event

    $stmt = $conn->prepare("UPDATE case_schedules SET type=?, description=?, date=?, start_time=?, end_time=?, location=? WHERE id=?");

    $stmt->bind_param('sssssi', $type, $description, $date, $start_time, $end_time, $location, $event_id);

    $stmt->execute();

    

    if ($stmt->affected_rows > 0) {

        // Get schedule details for notification

        $stmt_info = $conn->prepare("SELECT client_id, walkin_client_name, attorney_id FROM case_schedules WHERE id = ?");

        $stmt_info->bind_param('i', $event_id);

        $stmt_info->execute();

        $schedule_info = $stmt_info->get_result()->fetch_assoc();

        

        // Get admin name for notification

        $stmt_admin = $conn->prepare("SELECT name FROM user_form WHERE id = ?");

        $stmt_admin->bind_param('i', $admin_id);

        $stmt_admin->execute();

        $admin_name = $stmt_admin->get_result()->fetch_assoc()['name'];

        

        // Notify client about schedule update (if client exists)

        if ($schedule_info['client_id'] && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {

            $nTitle = 'Schedule Updated';

            $nMsg = "Your schedule has been updated by admin: $admin_name on $date from $start_time to $end_time at $location";

            $userType = 'client';

            $notificationType = 'info';

            

            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");

            $stmtN->bind_param('issss', $schedule_info['client_id'], $userType, $nTitle, $nMsg, $notificationType);

            $stmtN->execute();

        }

        

        // Notify attorney about schedule update (if attorney exists)

        if ($schedule_info['attorney_id'] && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {

            $nTitle = 'Schedule Updated';

            $nMsg = "Your schedule has been updated by admin: $admin_name on $date from $start_time to $end_time at $location";

            $userType = 'attorney';

            $notificationType = 'info';

            

            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");

            $stmtN->bind_param('issss', $schedule_info['attorney_id'], $userType, $nTitle, $nMsg, $notificationType);

            $stmtN->execute();

        }

    }

    

    echo $stmt->affected_rows > 0 ? 'success' : 'error';

    exit();

}



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

    $stmt = $conn->prepare("SELECT id, title, type, start_time, end_time FROM case_schedules WHERE attorney_id = ? AND date = ? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?)) AND status != 'Cancelled'");
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
    $stmt->bind_param('iiissssssi', $case_id, $selected_user_id, $client_id, $type, $description, $date, $start_time, $end_time, $location, $admin_id);

    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Get admin name for notification
        $stmt_admin = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
        $stmt_admin->bind_param('i', $admin_id);
        $stmt_admin->execute();
        $admin_name = $stmt_admin->get_result()->fetch_assoc()['name'];
        
        // Notify attorney about the new schedule
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'New Schedule Assigned';
            $nMsg = "A new $type has been scheduled for you by admin: $admin_name on $date from $start_time to $end_time at $location";
            $userType = 'attorney';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $selected_user_id, $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }
        
        // Notify client if client_id exists
        if ($client_id && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'New Schedule Created';
            $nMsg = "A new $type has been scheduled for you by admin: $admin_name on $date from $start_time to $end_time at $location";
            $userType = 'client';
            $notificationType = 'info';
            
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmtN->bind_param('issss', $client_id, $userType, $nTitle, $nMsg, $notificationType);
            $stmtN->execute();
        }

        // Log schedule creation to audit trail with creator, attorney, and client details
        try {
            // Fetch attorney name
            $attorney_name = '';
            $stmt_att = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_att->bind_param('i', $selected_user_id);
            $stmt_att->execute();
            $attorney_row = $stmt_att->get_result()->fetch_assoc();
            if ($attorney_row && !empty($attorney_row['name'])) { $attorney_name = $attorney_row['name']; }

            // Fetch client name if available
            $client_name = '';
            if (!empty($client_id)) {
                $stmt_cli = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                $stmt_cli->bind_param('i', $client_id);
                $stmt_cli->execute();
                $client_row = $stmt_cli->get_result()->fetch_assoc();
                if ($client_row && !empty($client_row['name'])) { $client_name = $client_row['name']; }
            }

            $details = "Created by: $admin_name; Attorney: " . ($attorney_name !== '' ? $attorney_name : (string)$selected_user_id) . "; Client: " . ($client_name !== '' ? $client_name : ($client_id ? (string)$client_id : 'N/A')) . "; Type: $type; Date: $date; Time: $start_time-$end_time; Location: $location";

            $auditLogger = new AuditLogger($conn);
            $auditLogger->logAction(
                $admin_id,
                $admin_name,
                'admin',
                'Schedule Created',
                'Schedule Management',
                $details,
                'success',
                'medium'
            );
        } catch (Exception $e) {
            error_log('Audit log (admin schedule create) failed: ' . $e->getMessage());
        }
    }

    echo $stmt->affected_rows > 0 ? 'success' : 'error';

    exit();

}

// Return attorneys assigned to a specific client (for dynamic filtering)
if (isset($_POST['action']) && $_POST['action'] === 'get_attorneys_for_client') {
    header('Content-Type: application/json');
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




// Fetch all events with joins

$events = [];

$stmt = $conn->prepare("SELECT cs.*, ac.title as case_title, 

    CASE 

        WHEN cs.attorney_id IS NOT NULL THEN cs.attorney_id 

        ELSE ac.attorney_id 

    END as final_attorney_id,

    uf1.name as attorney_name, 
    CASE 
        WHEN cs.client_id IS NOT NULL THEN uf2.name 
        WHEN cs.walkin_client_name IS NOT NULL THEN cs.walkin_client_name
        ELSE 'Walk-in Client'
    END as client_name, 
    cs.created_by_employee_id, uf3.name as created_by_name,
    CASE 
        WHEN cs.walkin_client_name IS NOT NULL THEN cs.walkin_client_name
        ELSE NULL
    END as walkin_client_name,
    CASE 
        WHEN cs.walkin_client_contact IS NOT NULL THEN cs.walkin_client_contact
        ELSE NULL
    END as walkin_client_contact,

    CASE 

        WHEN cs.attorney_id = ? THEN 1  -- Admin's own schedules first (priority 1)

        WHEN cs.attorney_id IS NOT NULL THEN 2  -- Other attorneys (priority 2)

        ELSE 3  -- Events without specific attorney (priority 3)

    END as priority_order

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

    ORDER BY priority_order ASC, uf1.name ASC, cs.date ASC, cs.start_time ASC");

$stmt->bind_param('i', $admin_id);

$stmt->execute();

$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) $events[] = $row;

$js_events = [];
$calendar_events = []; // Separate array for calendar view (excludes completed events)

foreach ($events as $ev) {

    // Debug: Log the attorney information and status
    error_log("Event: " . $ev['type'] . " - Attorney ID: " . ($ev['final_attorney_id'] ?? 'NULL') . " - Attorney Name: " . ($ev['attorney_name'] ?? 'NULL') . " - Status: " . ($ev['status'] ?? 'NULL'));

    // Get colors for the attorney/admin assigned to this event
    $attorneyId = $ev['final_attorney_id'] ?? 0;
    $attorneyUserType = $ev['attorney_user_type'] ?? 'attorney';
    $eventColors = getEventColors($attorneyId, $attorneyUserType);
    
    // Check if this is an admin event
    $isAdminEvent = ($ev['final_attorney_id'] == $admin_id);

    $js_events[] = [

        'id' => $ev['id'], // Add ID for calendar event removal

        'title' => $ev['type'] . ': ' . ($ev['case_title'] ?? ''),

        'start' => $ev['date'] . 'T' . $ev['start_time'],

        'type' => $ev['type'],

        'description' => $ev['description'],

        'location' => $ev['location'],

        'case' => $ev['case_title'],

        'attorney' => $ev['attorney_name'],

        'client' => $ev['client_name'],

        'color' => $eventColors['calendar_event_color'],
        'backgroundColor' => $eventColors['calendar_event_color'],
        'borderColor' => $eventColors['calendar_event_color'],
        'textColor' => $isAdminEvent ? '#ffffff' : '#000000',

        'extendedProps' => [

            'eventType' => $ev['type'],

            'attorneyName' => $ev['attorney_name'] ?? 'Unknown',

            'attorneyId' => $ev['final_attorney_id'] ?? 0,

            'attorneyUserType' => $ev['attorney_user_type'] ?? 'attorney',

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
            'type' => $ev['type'],
            'description' => $ev['description'],
            'location' => $ev['location'],
            'case' => $ev['case_title'],
            'attorney' => $ev['attorney_name'],
            'client' => $ev['client_name'],
            'color' => $eventColors['calendar_event_color'],
            'backgroundColor' => $eventColors['calendar_event_color'],
            'borderColor' => $eventColors['calendar_event_color'],
            'textColor' => $isAdminEvent ? '#ffffff' : '#000000',
            'extendedProps' => [
                'eventType' => $ev['type'],
                'attorneyName' => $ev['attorney_name'] ?? 'Unknown',
                'attorneyId' => $ev['final_attorney_id'] ?? 0,
                'attorneyUserType' => $ev['attorney_user_type'] ?? 'attorney',
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

</head>

<body>

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

            <li><a href="admin_schedule.php" class="active"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>

            <li><a href="admin_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>

            <li><a href="admin_usermanagement.php"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>

            <li><a href="admin_managecases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>

            <li><a href="admin_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>

            <li><a href="admin_messages.php"><i class="fas fa-comments"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>

            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>

        </ul>

    </div>



    <!-- Main Content -->

    <div class="main-content">

        <!-- Header -->
        <?php 
        $page_title = 'Schedule Management';
        $page_subtitle = 'Manage court hearings, meetings, and appointments';
        include 'components/profile_header.php'; 
        ?>





        <!-- Action Buttons -->

        <div class="action-buttons">

            <button class="btn btn-primary" id="addEventBtn">

                <i class="fas fa-plus"></i> Add Schedule

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

                    if ($ev['final_attorney_id'] == $admin_id) {

                        $admin_events[] = $ev;

                    } elseif ($ev['final_attorney_id'] !== null) {

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

                        <h3>My Schedules (Admin)</h3>

                        <span class="priority-badge">Priority 1</span>

                    </div>

                    <!-- Pagination Controls -->
                    <div class="pagination-controls" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div class="pagination-info">
                            <span>Show</span>
                            <select id="adminItemsPerPage" class="pagination-select">
                                <option value="6">6</option>
                                <option value="12">12</option>
                                <option value="18">18</option>
                            </select>
                            <span class="items-count">per page</span>
                        </div>
                        <div class="pagination-info">
                            <span id="adminInfo">Showing 0 - 0 of 0</span>
                        </div>
                    </div>

                    <div class="events-grid" id="adminEventsGrid">

                        <?php foreach ($admin_events as $ev): ?>

                                                 <div class="event-card admin-event status-<?= strtolower($ev['status'] ?? 'scheduled') ?><?= (strtolower($ev['status'] ?? 'scheduled') === 'completed') ? ' completed-schedule' : '' ?>" data-event-id="<?= $ev['id'] ?>" data-event-type="<?= htmlspecialchars($ev['type']) ?>" data-title="<?= htmlspecialchars($ev['title'] ?? '') ?>" data-attorney="<?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>" data-user-id="<?= $admin_id ?>" data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>">

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

                                    <button class="btn btn-warning btn-sm edit-event-btn"  

                                        data-event-id="<?= $ev['id'] ?>"

                                        data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"

                                        data-type="<?= htmlspecialchars($ev['type']) ?>"

                                        data-date="<?= htmlspecialchars($ev['date']) ?>"

                                        data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                        data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"

                                        data-location="<?= htmlspecialchars($ev['location']) ?>"

                                        data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"

                                        data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"

                                        data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"

                                        data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"

                                        data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"

                                        data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                        data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"

                                        data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">

                                        <i class="fas fa-edit"></i>

                                    </button>

                                    <button class="btn btn-info btn-sm view-details-btn" 

                                        data-event-id="<?= $ev['id'] ?>"

                                        data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"

                                        data-type="<?= htmlspecialchars($ev['type']) ?>"

                                        data-date="<?= htmlspecialchars($ev['date']) ?>"

                                        data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                        data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"

                                        data-location="<?= htmlspecialchars($ev['location']) ?>"

                                        data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"

                                        data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"

                                        data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"

                                        data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"

                                        data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"

                                        data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                        data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"

                                        data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">

                                        <i class="fas fa-eye"></i>

                                    </button>

                                </div>

                            </div>

                    </div>

                    <?php endforeach; ?>

                    </div>

                    <!-- Pagination Buttons -->
                    <div class="pagination-buttons" style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                        <button id="adminPrevBtn" class="btn btn-secondary btn-sm">Previous</button>
                        <span id="adminPageInfo" style="display: flex; align-items: center; padding: 0 1rem;">Page 1 of 1</span>
                        <button id="adminNextBtn" class="btn btn-secondary btn-sm">Next</button>
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

                         <!-- Pagination Controls -->
                         <div class="pagination-controls" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                             <div class="pagination-info">
                                 <span>Show</span>
                                 <select class="attorneyItemsPerPage pagination-select" data-attorney="<?= htmlspecialchars($attorney_name) ?>">
                                     <option value="6">6</option>
                                     <option value="12">12</option>
                                     <option value="18">18</option>
                                 </select>
                                 <span class="items-count">per page</span>
                             </div>
                             <div class="pagination-info">
                                 <span class="attorneyInfo" data-attorney="<?= htmlspecialchars($attorney_name) ?>">Showing 0 - 0 of 0</span>
                             </div>
                         </div>

                         

                         <div class="events-grid attorney-events-grid" data-attorney="<?= htmlspecialchars($attorney_name) ?>">

                             <?php foreach ($attorney_schedules as $ev): ?>

                                                           <div class="event-card attorney-event status-<?= strtolower($ev['status'] ?? 'scheduled') ?><?= (strtolower($ev['status'] ?? 'scheduled') === 'completed') ? ' completed-schedule' : '' ?>" data-event-id="<?= $ev['id'] ?>" data-event-type="<?= htmlspecialchars($ev['type']) ?>" data-title="<?= htmlspecialchars($ev['title'] ?? '') ?>" data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? 'Unknown Attorney') ?>" data-user-id="<?= $ev['final_attorney_id'] ?>" data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>">

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

                                         <button class="btn btn-warning btn-sm edit-event-btn"  

                                             data-event-id="<?= $ev['id'] ?>"

                                             data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"

                                             data-type="<?= htmlspecialchars($ev['type']) ?>"

                                             data-date="<?= htmlspecialchars($ev['date']) ?>"

                                             data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                             data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"

                                             data-location="<?= htmlspecialchars($ev['location']) ?>"

                                             data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"

                                             data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"

                                             data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"

                                             data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                        data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"

                                        data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">

                                             <i class="fas fa-edit"></i>

                                         </button>

                                         <button class="btn btn-info btn-sm view-details-btn" 

                                             data-event-id="<?= $ev['id'] ?>"

                                             data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"

                                             data-type="<?= htmlspecialchars($ev['type']) ?>"

                                             data-date="<?= htmlspecialchars($ev['date']) ?>"

                                             data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                             data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"

                                             data-location="<?= htmlspecialchars($ev['location']) ?>"

                                             data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"

                                             data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"

                                             data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"

                                             data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                        data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"

                                        data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">

                                             <i class="fas fa-eye"></i>

                                         </button>

                                     </div>

                                 </div>

                             </div>

                             <?php endforeach; ?>

                         </div>

                         <!-- Pagination Buttons -->
                         <div class="pagination-buttons" style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                             <button class="attorneyPrevBtn btn btn-secondary btn-sm" data-attorney="<?= htmlspecialchars($attorney_name) ?>">Previous</button>
                             <span class="attorneyPageInfo" data-attorney="<?= htmlspecialchars($attorney_name) ?>" style="display: flex; align-items: center; padding: 0 1rem;">Page 1 of 1</span>
                             <button class="attorneyNextBtn btn btn-secondary btn-sm" data-attorney="<?= htmlspecialchars($attorney_name) ?>">Next</button>
                         </div>

                     </div>

                     <?php endforeach; ?>

                 </div>

                 <?php endif; ?>

                

                <!-- Other Events -->

                <?php if (!empty($other_events)): ?>

                <div class="priority-section other-priority">

                    <div class="priority-header">

                        <i class="fas fa-calendar-alt"></i>

                        <h3>Other Events</h3>

                        <span class="priority-badge">Priority 3</span>

                    </div>

                    <!-- Pagination Controls -->
                    <div class="pagination-controls" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div class="pagination-info">
                            <span>Show</span>
                            <select id="otherItemsPerPage" class="pagination-select">
                                <option value="6">6</option>
                                <option value="12">12</option>
                                <option value="18">18</option>
                            </select>
                            <span class="items-count">per page</span>
                        </div>
                        <div class="pagination-info">
                            <span id="otherInfo">Showing 0 - 0 of 0</span>
                        </div>
                    </div>

                    <div class="events-grid" id="otherEventsGrid">

                        <?php foreach ($other_events as $ev): ?>

                                                                                                   <div class="event-card other-event status-<?= strtolower($ev['status'] ?? 'scheduled') ?><?= (strtolower($ev['status'] ?? 'scheduled') === 'completed') ? ' completed-schedule' : '' ?>" data-event-id="<?= $ev['id'] ?>" data-event-type="<?= htmlspecialchars($ev['type']) ?>" data-title="<?= htmlspecialchars($ev['title'] ?? '') ?>" data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? 'Unknown Attorney') ?>" data-user-id="<?= $ev['final_attorney_id'] ?>" data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>">

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

                                    <button class="btn btn-warning btn-sm edit-event-btn"  

                                        data-event-id="<?= $ev['id'] ?>"

                                        data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"

                                        data-type="<?= htmlspecialchars($ev['type']) ?>"

                                        data-date="<?= htmlspecialchars($ev['date']) ?>"

                                        data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                        data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"

                                        data-location="<?= htmlspecialchars($ev['location']) ?>"

                                        data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"

                                        data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"

                                        data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"

                                        data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"

                                        data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"

                                        data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                        data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"

                                        data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">

                                        <i class="fas fa-edit"></i>

                                    </button>

                                    <button class="btn btn-info btn-sm view-details-btn" 

                                        data-event-id="<?= $ev['id'] ?>"

                                        data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"

                                        data-type="<?= htmlspecialchars($ev['type']) ?>"

                                        data-date="<?= htmlspecialchars($ev['date']) ?>"

                                        data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                                        data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"

                                        data-location="<?= htmlspecialchars($ev['location']) ?>"

                                        data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"

                                        data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"

                                        data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"

                                        data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"

                                        data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"

                                        data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                                        data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"

                                        data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>">

                                        <i class="fas fa-eye"></i>

                                    </button>

                                </div>

                            </div>

                        </div>

                        <?php endforeach; ?>

                    </div>

                    <!-- Pagination Buttons -->
                    <div class="pagination-buttons" style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                        <button id="otherPrevBtn" class="btn btn-secondary btn-sm">Previous</button>
                        <span id="otherPageInfo" style="display: flex; align-items: center; padding: 0 1rem;">Page 1 of 1</span>
                        <button id="otherNextBtn" class="btn btn-secondary btn-sm">Next</button>
                    </div>

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

                <!-- Pagination Controls -->
                <div class="pagination-controls" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <div class="pagination-info">
                        <span>Show</span>
                        <select id="completedItemsPerPage" class="pagination-select">
                            <option value="6">6</option>
                            <option value="12">12</option>
                            <option value="18">18</option>
                        </select>
                        <span class="items-count">per page</span>
                    </div>
                    <div class="pagination-info">
                        <span id="completedInfo">Showing 0 - 0 of 0</span>
                    </div>
                </div>

                <div class="completed-events-grid" id="completedEventsGrid">
                    <!-- Completed events will be moved here -->
                </div>

                <!-- Pagination Buttons -->
                <div class="pagination-buttons" style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                    <button id="completedPrevBtn" class="btn btn-secondary btn-sm">Previous</button>
                    <span id="completedPageInfo" style="display: flex; align-items: center; padding: 0 1rem;">Page 1 of 1</span>
                    <button id="completedNextBtn" class="btn btn-secondary btn-sm">Next</button>
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

                        <label for="eventDate">Date <span style="color: red;">*</span></label>

                        <input type="date" id="eventDate" name="date" required min="<?= date('Y-m-d') ?>">

                    </div>

                    <div class="form-group">

                        <label for="eventStartTime">Start Time <span style="color: red;">*</span></label>

                        <input type="time" id="eventStartTime" name="start_time" required>

                    </div>

                    <div class="form-group">

                        <label for="eventEndTime">End Time <span style="color: red;">*</span></label>

                        <input type="time" id="eventEndTime" name="end_time" required>

                    </div>

                    <div class="form-group">
                        <label for="eventLocation">Location <span style="color: red;">*</span></label>
                        <input type="text" id="eventLocation" name="location" required placeholder="Enter specific location">
                    </div>

                    <div class="form-group">
                        <label for="eventClient">Client Selection <span style="color: red;">*</span></label>
                        <select id="eventClient" name="client_id" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="selectedUserId">Assigned Attorney <span style="color: red;">*</span></label>
                        <select id="selectedUserId" name="selected_user_id" required>
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
                        <select id="eventType" name="type" required>
                            <option value="Hearing">Hearing</option>
                            <option value="Appointment">Appointment</option>
                            <option value="Free Legal Advice">Free Legal Advice</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="eventDescription">Description <span style="color: red;">*</span></label>
                        <textarea id="eventDescription" name="description" rows="3" required placeholder="Enter detailed description of this schedule..."></textarea>
                    </div>

                </form>

            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEvent">Cancel</button>
                <button class="btn btn-primary" id="saveEvent">Save Schedule</button>
            </div>

        </div>

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
                fetch('admin_schedule.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_attorneys_for_client&client_id='+encodeURIComponent(cid) })
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



         <!-- Edit Event Modal -->

     <div class="modal" id="editEventModal">

         <div class="modal-content edit-event-modal">

             <div class="modal-header">

                 <h2>Edit Event</h2>

             </div>

            <div class="modal-body">

                <form id="editEventForm" class="event-form-grid">

                    <input type="hidden" id="editEventId" name="event_id">

                    <div class="form-group">

                        <label for="editEventDate">Date</label>

                        <input type="date" id="editEventDate" name="date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">

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

                    <div class="form-group full-width">

                        <label for="editEventDescription">Description</label>

                        <textarea id="editEventDescription" name="description" rows="3"></textarea>

                    </div>

                </form>

            </div>

            <div class="modal-footer">

                <button class="btn btn-secondary" id="cancelEditEvent">Cancel</button>

                <button class="btn btn-primary" id="saveEditEvent">Save Schedule</button>

            </div>

        </div>

    </div>



    <style>

        .calendar-container {

            background: white;

            border-radius: 10px;

            padding: 20px;

            margin-bottom: 30px;

            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.08);

            overflow: hidden;

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

        .fc .fc-toolbar-title {

            font-size: 1.6em;

            font-weight: 600;

            color: #1976d2;

        }

        .fc .fc-daygrid-day.fc-day-today {

            background: #e3f2fd;

        }

        

        /* Calendar sizing and overflow control */

        .fc .fc-daygrid-day {

            min-height: 100px;

        }

        

        .fc .fc-daygrid-day-frame {

            min-height: 100px;

        }

        

        .fc .fc-daygrid-day-events {

            min-height: 0;

            max-height: none;

        }

        

        .fc .fc-daygrid-day-number {

            font-size: 0.9em;

            font-weight: 500;

        }

        .fc .fc-daygrid-event {

            border-radius: 6px;

            font-size: 1em;

            box-shadow: 0 1px 4px rgba(25, 118, 210, 0.08);

            padding: 4px 8px;

            margin: 2px 0;

            border: none;

            font-weight: 500;

        }



        /* Attorney-based Color Coding for Calendar Events */

        .fc .fc-daygrid-event {

            border-radius: 4px;

            font-size: 0.85em;

            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);

            padding: 3px 6px;

            margin: 1px 0;

            border: 1px solid !important;

            font-weight: 500;

            transition: all 0.2s ease;

            max-width: 100%;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }



        /* Hover effects for all calendar events */

        .fc .fc-daygrid-event:hover {

            transform: translateY(-1px);

            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);

        }

        .fc .fc-button {

            background: #5D0E26;

            border: none;

            color: #fff;

            border-radius: 5px;

            padding: 6px 14px;

            font-weight: 500;

            margin: 0 2px;

            transition: background 0.2s;

        }

        .fc .fc-button:hover, .fc .fc-button:focus {

            background: #4A0B1E;

        }

        .fc .fc-button-primary:not(:disabled).fc-button-active, .fc .fc-button-primary:not(:disabled):active {

            background: #8B1538;

        }

        .view-options .btn {

            padding: 8px 15px;

            border-radius: 5px;

            border: none;

            background: #f4f6f8;

            color: #5D0E26;

            font-weight: 500;

            transition: background 0.2s, color 0.2s;

            }

        .view-options .btn.active, .view-options .btn:active {

            background: #5D0E26;

            color: #fff;

            }



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



        .btn-primary {

            background: #5D0E26;

            color: white;

            border: none;

            padding: 10px 20px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 14px;

            font-weight: 500;

            transition: all 0.3s ease;

            display: inline-flex;

            align-items: center;

            gap: 8px;

        }

        

        .btn-primary:hover {

            background: #4A0B1E;

            transform: translateY(-1px);

        }




        .legend {

            font-size: 1em;

        }





        @media (max-width: 900px) {

            .calendar-container { padding: 5px; }

        }







        .btn-secondary {

            background: linear-gradient(135deg, #6c757d, #5a6268);

            color: white;

            border: none;

            padding: 0.5rem 1rem;

            border-radius: 5px;

            font-weight: 600;

            font-size: 0.8rem;

            cursor: pointer;

            transition: all 0.3s ease;

            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

            min-width: 80px;

        }



        .btn-secondary:hover {

            background: linear-gradient(135deg, #5a6268, #4a4a4a);

            transform: translateY(-1px);

        }



        @media (max-width: 700px) {

            .event-form-grid { 

                grid-template-columns: 1fr; 

                gap: 12px; 

            }

            .action-buttons {

                flex-direction: column;

                gap: 1rem;

                align-items: stretch;

            }

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



        .section-header .header-actions {

            display: flex;

            gap: 0.5rem;

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

            background: linear-gradient(135deg, rgba(93, 14, 38, 0.05) 0%, rgba(139, 21, 56, 0.1) 100%);

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

            color: #5d0e26;

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

            background: #5d0e26;

        }







        .no-events {

            text-align: center;

            padding: 3rem;

            color: #666;

        }



        .no-events i {

            font-size: 4rem;

            margin-bottom: 1rem;

            opacity: 0.3;

        }



        .no-events h3 {

            margin: 0 0 1rem 0;

            color: #333;

        }



        .events-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 0.75rem;

            align-items: start;

        }



        .event-card {

            background: white;

            border-radius: 8px;

            padding: 0.6rem;

            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);

            transition: all 0.3s ease;

            border: 1px solid #f0f0f0;

            cursor: pointer;

            min-height: 120px;

        }



        .event-card:hover {

            transform: translateY(-4px);

            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);

            border-color: #5d0e26;

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



        /* Admin Event Cards - Light Maroon Background */
        .event-card.admin-event {

            background: linear-gradient(135deg, rgba(201, 42, 42, 0.08) 0%, rgba(201, 42, 42, 0.15) 100%);
        }



        /* Attorney Event Cards - Default Light Green Background (fallback) */

        .event-card.attorney-event {

            background: linear-gradient(135deg, rgba(81, 207, 102, 0.08) 0%, rgba(81, 207, 102, 0.15) 100%);

        }



        /* Other Event Cards - Light Blue Background */

        .event-card.other-event {

            background: linear-gradient(135deg, rgba(116, 192, 252, 0.08) 0%, rgba(116, 192, 252, 0.15) 100%);

        }



        /* Dynamic User Color Coding - Colors loaded from database */
        /* Specific colors will be applied via JavaScript based on current database users */



        .event-card:hover {

            transform: translateY(-4px);

            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);

            border-color: #5d0e26;

        }



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



        /* Admin avatar - Light Red */

        .event-card.admin-event .avatar-placeholder {

            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);

        }



        /* Attorney avatars - Light Green */

        .event-card.attorney-event .avatar-placeholder {

            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);

        }



        /* Other event avatars - Light Blue */

        .event-card.other-event .avatar-placeholder {

            background: linear-gradient(135deg, #74c0fc 0%, #4dabf7 100%);

        }



        /* Dynamic Color Coding - Generated by Color Manager */
        <?php echo generateColorCSS(); ?>



        .event-card-header {

            display: flex;

            align-items: center;

            gap: 0.5rem;

            margin-bottom: 0.5rem;

        }



        .event-avatar {

            width: 35px;

            height: 35px;

            border-radius: 50%;

            overflow: hidden;

            flex-shrink: 0;

        }



        .avatar-placeholder {

            width: 100%;

            height: 100%;

            background: linear-gradient(135deg, #5d0e26 0%, #8b1538 100%);

            display: flex;

            align-items: center;

            justify-content: center;

            color: white;

            font-size: 1.5rem;

        }



        .event-info h3 {

            margin: 0 0 0.25rem 0;

            color: #5d0e26;

            font-weight: 600;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            line-height: 1.3;

        }



        .event-info p {

            margin: 0 0 0.3rem 0;

            color: #666;

            font-size: 0.8rem;

            display: flex;

            align-items: center;

            gap: 0.4rem;

        }



        .event-info p:last-child {

            margin-bottom: 0;

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



        .attorney-detail {

            color: #43a047 !important;

            font-weight: 500;

        }



        .client-detail {

            color: #9c27b0 !important;

            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;

        }



        /* Attorney Name Display Styling */

        .attorney-name-display {

            background: linear-gradient(135deg, rgba(93, 14, 38, 0.1) 0%, rgba(139, 21, 56, 0.2) 100%);

            border: 1px solid rgba(93, 14, 38, 0.3);

            border-radius: 8px;

            padding: 0.5rem 0.75rem;

            margin: 0.5rem 0;

            display: flex;

            align-items: center;

            gap: 0.5rem;

            color: #5d0e26;

            font-weight: 600;

            font-size: 1rem;

        }



        .attorney-name-display i {

            color: #5d0e26;

            font-size: 1.1rem;

        }



        .attorney-name-display strong {

            color: #5d0e26;

            font-weight: 700;

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



        .event-info i {

            font-size: 1rem;

            width: 16px;

            text-align: center;

        }



        .event-stats {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 0.5rem;

            margin-bottom: 0.75rem;

        }



        .stat-item {

            text-align: center;

            padding: 0.5rem;

            background: #f8f9fa;

            border-radius: 6px;

            border: 1px solid #e9ecef;

            height: 65px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            align-items: center;

            min-height: 65px;

        }



        /* Colored boxes for date, time, and location */

        .stat-item.date-box {

            background: rgba(76, 175, 80, 0.15);

            border-color: rgba(76, 175, 80, 0.3);

            border-left: 4px solid #4caf50;

        }



        .stat-item.time-box {

            background: rgba(255, 193, 7, 0.15);

            border-color: rgba(255, 193, 7, 0.3);

            border-left: 4px solid #ffc107;

        }



        .stat-item.location-box {

            background: rgba(156, 39, 176, 0.15);

            border-color: rgba(156, 39, 176, 0.3);

            border-left: 4px solid #9c27b0;

        }



        .stat-item .stat-number {

            font-size: 1.1rem;

            font-weight: 700;

            color: #333;

            margin-bottom: 0.25rem;

        }



        .stat-item .stat-label {

            font-size: 0.8rem;

            color: #666;

            font-weight: 500;

            text-transform: uppercase;

            letter-spacing: 0.5px;

        }



        .stat-number {

            font-size: 1.5rem;

            font-weight: 700;

            color: #1976d2;

            margin-bottom: 0.25rem;

            line-height: 1;

            min-height: 1.5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            text-align: center;

            width: 100%;

        }



        .stat-label {

            font-size: 0.8rem;

            color: #666;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            line-height: 1;

            min-height: 1rem;

        }



        .client-name {

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

            max-width: 100%;

            cursor: help;

            font-size: 0.9rem;

            line-height: 1.2;

            text-align: center;

            width: 100%;

        }



        .event-actions {

            display: flex;

            gap: 0.4rem;

            flex-wrap: wrap;

            align-items: center;

            margin-top: 0.4rem;

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



        .event-status {

            display: inline-block;

            padding: 0.25rem 0.75rem;

            border-radius: 20px;

            font-size: 0.75rem;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 0.5px;

        }



        .status-scheduled {

            background: rgba(25, 118, 210, 0.1);

            color: #1976d2;

            border: 1px solid rgba(25, 118, 210, 0.3);

        }



        .status-completed {

            background: rgba(76, 175, 80, 0.1);

            color: #4caf50;

            border: 1px solid rgba(76, 175, 80, 0.3);

        }



        .status-cancelled {

            background: rgba(244, 67, 54, 0.1);

            color: #f44336;

            border: 1px solid rgba(244, 67, 54, 0.3);

        }



        .status-rescheduled {

            background: rgba(255, 193, 7, 0.1);

            color: #ff9800;

            border: 1px solid rgba(255, 193, 7, 0.3);

        }



        /* Button Styles */

        .btn {

            border: none;

            border-radius: 8px;

            font-weight: 500;

            cursor: pointer;

            transition: all 0.3s ease;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            gap: 0.5rem;

        }



        .btn-primary {

            background: #5D0E26;

            color: white;

        }



        .btn-primary:hover {

            background: #4A0B1E;

        }



        .btn-secondary {

            background: #6c757d;

            color: white;

        }



        .btn-secondary:hover {

            background: #5a6268;

        }



        .btn-warning {

            background: #ffc107;

            color: #212529;

        }



        .btn-warning:hover {

            background: #e0a800;

        }



        .btn-outline-primary {

            background: transparent;

            color: #5d0e26;

            border: 2px solid #5d0e26;

        }



        .btn-outline-primary:hover {

            background: #5d0e26;

            color: white;

        }



        .btn-info {

            background: #17a2b8;

            color: white;

        }



        .btn-info:hover {

            background: #138496;

        }



        /* Modal Styles */

        .modal {

            display: none;

            position: fixed;

            z-index: 9999;

            left: 0;

            top: 0;

            width: 100%;

            height: 100%;

            background-color: rgba(0, 0, 0, 0.5);

            overflow: hidden;

        }



        .modal-content {

            background-color: #fefefe;

            margin: 1% auto;

            padding: 0;

            border: none;

            border-radius: 12px;

            width: 95%;

            max-width: 900px;

            max-height: 95vh;

            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);

            animation: modalSlideIn 0.3s ease-out;

            position: relative;

            display: flex;

            flex-direction: column;

            overflow: hidden;

        }



        @keyframes modalSlideIn {

            from {

                transform: translateY(-50px);

                opacity: 0;

            }

            to {

                transform: translateY(0);

                opacity: 1;

            }

        }



        .modal-header {

            background: linear-gradient(135deg, #5d0e26 0%, #8b1538 100%);

            color: white;

            padding: 1rem 1.5rem;

            border-radius: 12px 12px 0 0;

            display: flex;

            justify-content: space-between;

            align-items: center;

            flex-shrink: 0;

        }



        .modal-header h2 {

            margin: 0;

            font-size: 1.3rem;

            font-weight: 600;

        }



        .close-modal {

            background: none;

            border: none;

            color: white;

            font-size: 2rem;

            cursor: pointer;

            padding: 0;

            width: 30px;

            height: 30px;

            display: none; /* Hide the X button */

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            transition: background 0.2s;

        }



        .close-modal:hover {

            background: rgba(255, 255, 255, 0.2);

        }



        .modal-body {

            padding: 1rem;

            flex: 1;

            overflow: visible;

        }



        .modal-footer {

            padding: 1rem 1.5rem;

            border-top: 1px solid #e9ecef;

            display: flex;

            justify-content: flex-end;

            gap: 1rem;

            flex-shrink: 0;

        }



        .event-form-grid {

            display: grid;

            grid-template-columns: 1fr 1fr 1fr;

            gap: 1rem;

            height: 100%;

        }



        .form-group.full-width {

            grid-column: 1 / -1;

        }



        .form-group {

            margin-bottom: 0.75rem;

        }



        .form-group label {

            margin-bottom: 0.25rem;

            font-size: 0.9rem;

            color: #e74c3c !important;

        }



        .form-group input,

        .form-group select,

        .form-group textarea {

            padding: 0.5rem;

            font-size: 0.85rem;

        }



        .form-group textarea {

            height: 60px;

            resize: none;

        }



        .form-group label {

            display: block;

            margin-bottom: 0.5rem;

            font-weight: 500;

            color: #e74c3c !important;

        }



        .form-group input,

        .form-group select,

        .form-group textarea {

            width: 100%;

            padding: 0.75rem;

            border: 1px solid #ced4da;

            border-radius: 6px;

            font-size: 0.9rem;

            transition: border-color 0.2s, box-shadow 0.2s;

        }



        .form-group input:focus,

        .form-group select:focus,

        .form-group textarea:focus {

            border-color: #5d0e26;

            outline: none;

            box-shadow: 0 0 0 0.2rem rgba(93, 14, 38, 0.25);

        }



        .form-group small {

            display: block;

            margin-top: 0.25rem;

        }



        .form-group {

            margin-bottom: 1rem;

        }



        /* Event Details Modal Styles - Compact Version */

        .event-overview {

            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);

            border-radius: 8px;

            padding: 1rem;

            margin-bottom: 1rem;

            text-align: center;

        }



        .event-type-display {

            margin-bottom: 0.75rem;

        }



        .type-badge {

            background: linear-gradient(135deg, #5d0e26 0%, #8b1538 100%);

            color: white;

            padding: 0.4rem 1rem;

            border-radius: 20px;

            font-weight: 600;

            font-size: 0.9rem;

            text-transform: uppercase;

            letter-spacing: 0.3px;

        }



        .event-datetime {

            display: flex;

            justify-content: center;

            gap: 1rem;

        }



        .date-display,

        .time-display {

            padding: 0.6rem 1rem;

            border-radius: 6px;

            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);

            font-weight: 600;

            color: #333;

            font-size: 0.85rem;

        }



        /* Date Display - Light Green Background */

        .date-display {

            background: linear-gradient(135deg, rgba(76, 175, 80, 0.15) 0%, rgba(76, 175, 80, 0.25) 100%);

            border: 1px solid rgba(76, 175, 80, 0.3);

            border-left: 4px solid #4caf50;

        }



        /* Time Display - Light Peach/Orange Background */

        .time-display {

            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 193, 7, 0.25) 100%);

            border: 1px solid rgba(255, 193, 7, 0.3);

            border-left: 4px solid #ffc107;

        }



        .event-details-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 1.5rem;

        }



        .detail-section h3 {

            color: #5d0e26;

            font-size: 1rem;

            font-weight: 600;

            margin-bottom: 0.75rem;

            display: flex;

            align-items: center;

            gap: 0.4rem;

        }



        .detail-item {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 0.5rem 0;

            border-bottom: 1px solid #f0f0f0;

        }



        .detail-item:last-child {

            border-bottom: none;

        }



        .detail-label {

            font-weight: 500;

            color: #666;

            display: flex;

            align-items: center;

            gap: 0.4rem;

            font-size: 0.85rem;

        }



        .detail-value {

            font-weight: 600;

            color: #333;

            text-align: right;

            max-width: 180px;

            word-wrap: break-word;

            font-size: 0.85rem;

        }



        .btn-close-modal {

            background: #6c757d;

            color: white;

            border: none;

            padding: 0.75rem 1.5rem;

            border-radius: 6px;

            font-weight: 500;

            cursor: pointer;

            transition: background 0.2s;

        }



        .btn-close-modal:hover {

            background: #5a6268;

        }



        .btn-sm {

            padding: 0.5rem 1rem;

            font-size: 0.85rem;

        }



        /* Responsive Modal */

        @media (max-width: 1200px) {

            .event-form-grid {

                grid-template-columns: 1fr 1fr;

            }

            

            .add-event-modal .event-form-grid {

                grid-template-columns: 1fr 1fr;

            }

            

            .modal-content {

                max-width: 600px;

                width: 85%;

            }

        }

        

        @media (max-width: 768px) {

            .event-form-grid {

                grid-template-columns: 1fr;

            }

            

            .event-details-grid {

                grid-template-columns: 1fr;

                gap: 1rem;

            }

            

            .event-datetime {

                flex-direction: column;

                gap: 1rem;

            }

            

            .modal-content {

                width: 90%;

                margin: 3% auto;

                max-width: 90vw;

            }

        }



        /* Ensure modal is above header */

        .header {

            z-index: 100;

        }



        .sidebar {

            z-index: 100;

        }



        /* Force modal to be on top */

        .modal {

            z-index: 99999 !important;

        }



        .modal-content {

            z-index: 100000 !important;

        }



        /* Add Schedule Modal Specific Styles */

        .add-event-modal {

            max-height: 90vh;

            overflow-y: auto;

        }



        .add-event-modal .modal-body {

            padding: 1.5rem;

            flex: 1;

            overflow-y: auto;

        }



        .add-event-modal .event-form-grid {

            display: grid;

            grid-template-columns: 1fr 1fr 1fr;

            gap: 1rem;

            height: auto;

        }



        .add-event-modal .form-group {

            margin-bottom: 1rem;

        }



        .add-event-modal .form-group.full-width {

            grid-column: 1 / -1;

        }



        .add-event-modal .form-group label {

            display: block;

            margin-bottom: 0.5rem;

            font-weight: 500;

            color: #e74c3c !important;

        }



        .add-event-modal .form-group input,

        .add-event-modal .form-group select,

        .add-event-modal .form-group textarea {

            width: 100%;

            padding: 0.75rem;

            border: 1px solid #ced4da;

            border-radius: 6px;

            font-size: 0.9rem;

            transition: border-color 0.2s, box-shadow 0.2s;

        }



        .add-event-modal .form-group input:focus,

        .add-event-modal .form-group select:focus,

        .add-event-modal .form-group textarea:focus {

            border-color: #5d0e26;

            outline: none;

            box-shadow: 0 0 0 0.2rem rgba(93, 14, 38, 0.25);

        }



        .add-event-modal .form-group textarea {

            height: 80px;

            resize: vertical;

            min-height: 60px;

        }



        .add-event-modal .form-group small {

            display: block;

            margin-top: 0.25rem;

            color: #666;

            font-style: italic;

        }



        .add-event-modal .modal-footer {

            padding: 1rem 1.5rem;

            border-top: 1px solid #e9ecef;

            display: flex;

            justify-content: flex-end;

            gap: 1rem;

            flex-shrink: 0;

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

            background: #5d0e26;

            color: white;

        }



        .add-event-modal .btn:hover {

            transform: translateY(-1px);

            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);

        }



        .add-event-modal .btn:active {

            transform: translateY(0);

        }



        /* Notification animation */

        @keyframes slideIn {

            from {

                transform: translateX(100%);

                opacity: 0;

            }

            to {

                transform: translateX(0);

                opacity: 1;

            }

        }



        /* Calendar Event Color Overrides - More Specific */
        .fc-event {
            border: none !important;
            background: none !important;
            background-color: transparent !important;
        }
        
        .fc-event .fc-event-main {
            border: none !important;
            background: none !important;
            background-color: transparent !important;
        }
        
        .fc-event .fc-event-main-frame {
            border: none !important;
            background: none !important;
            background-color: transparent !important;
        }
        
        .fc-event-title {
            color: inherit;
        }
        
        /* Force our custom colors to take precedence */
        .fc-event[data-attorney-name] {
            background-color: inherit !important;
            border-color: inherit !important;
        }
        
        /* Notification Styles */

        .notification {

            position: fixed;

            top: 20px;

            right: 20px;

            z-index: 100001 !important;

            animation: slideIn 0.3s ease-out;

        }



        .notification-content {

            display: flex;

            align-items: center;

            gap: 1rem;

            padding: 1rem 1.5rem;

            border-radius: 8px;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);

            min-width: 300px;

        }



        .notification-success {

            background: #d4edda;

            border: 1px solid #c3e6cb;

            color: #155724;

        }



        .notification-error {

            background: #f8d7da;

            border: 1px solid #f5c6cb;

            color: #721c24;

        }



        .notification-info {

            background: #d1ecf1;

            border: 1px solid #bee5eb;

            color: #0c5460;

        }



        .notification-warning {

            background: #fff3cd;

            border: 1px solid #ffeaa7;

            color: #856404;

        }



        .notification-message {

            flex: 1;

            font-weight: 500;

        }



        .notification-close {

            background: none;

            border: none;

            font-size: 1.5rem;

            cursor: pointer;

            color: inherit;

            opacity: 0.7;

            transition: opacity 0.2s;

        }



        .notification-close:hover {

            opacity: 1;

        }











        /* Responsive Design */

        @media (max-width: 1200px) {

            .events-grid {

                grid-template-columns: repeat(3, 1fr);

            }

        }



        @media (max-width: 900px) {

            .events-grid {

                grid-template-columns: repeat(2, 1fr);

            }

        }



        @media (max-width: 768px) {

            .events-grid {

                grid-template-columns: 1fr;

            }

            

            .upcoming-events-section {

                padding: 1rem;

            }

            

            .section-header h2 {

                font-size: 1.5rem;

            }

        }

        /* Global Status Filter Styles */
        .global-status-filter-container {
            margin: 2rem 0;
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
        }

        /* Pagination Styles */
        .pagination-controls {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-select {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .items-count {
            color: #666;
        }

        .pagination-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .pagination-buttons .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Maroon pagination buttons */
        .pagination-buttons .btn-secondary {
            background: #5D0E26;
            border: none;
            color: white;
            transition: all 0.3s ease;
        }

        .pagination-buttons .btn-secondary:hover:not(:disabled) {
            background: #4A0B1E;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(93, 14, 38, 0.3);
        }

        .pagination-buttons .btn-secondary:disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            opacity: 0.5;
        }

    </style>



    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="assets/js/color-manager.js"></script>

    <script>

        console.log('Script loaded - waiting for DOM...');

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

        

        // Global calendar variable

        let calendar;

        

        document.addEventListener('DOMContentLoaded', function() {

            var calendarEl = document.getElementById('calendar');

            var events = <?php echo json_encode($calendar_events); ?>;

            calendar = new FullCalendar.Calendar(calendarEl, {

                initialView: 'dayGridMonth',

                                 height: 650,

                headerToolbar: {

                    left: 'prev,next today',

                    center: 'title',

                    right: 'dayGridMonth,timeGridWeek,timeGridDay'

                },

                events: events,

                eventDidMount: function(info) {

                    // Add data attributes for event type and user identification

                    if (info.event.extendedProps.eventType) {

                        info.el.setAttribute('data-event-type', info.event.extendedProps.eventType);

                    }

                    // Add user ID for color coding

                    if (info.event.extendedProps.attorneyId) {

                        info.el.setAttribute('data-user-id', info.event.extendedProps.attorneyId);

                    }

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

                },

                eventClick: function(info) {

                    // Trigger the same modal as View Details button

                    showEventDetailsModal(info.event);

                }

            });

            calendar.render();
            
            // Store calendar instance globally for access
            window.calendar = calendar;











        });

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

        // Update event status
        async function updateEventStatus(selectElement) {

            const eventId = selectElement.dataset.eventId;

            const newStatus = selectElement.value;

            const currentStatus = selectElement.dataset.previousValue || 'Scheduled';

            

            console.log('🔄 Status Update Request:', {

                eventId: eventId,

                currentStatus: currentStatus,

                newStatus: newStatus,

                userType: '<?= $_SESSION['user_type'] ?? 'unknown' ?>',

                userId: '<?= $_SESSION['user_id'] ?? 'unknown' ?>'

            });

            

            // Show double confirmation modal
            const confirmed = await showDoubleConfirmation(newStatus, currentStatus);
            if (!confirmed) {
                // Revert dropdown to previous value
                selectElement.value = currentStatus;
                return;
            }

            

            // Store current value for future reference

            selectElement.dataset.previousValue = newStatus;

            

            // Proceed with status update

            const formData = new FormData();

            formData.append('event_id', eventId);

            formData.append('new_status', newStatus);

            formData.append('action', 'update_status');

            

            console.log('📤 Sending status update request...');

            

            fetch('update_event_status.php', {

                method: 'POST',

                body: formData

            })

            .then(response => {

                console.log('📥 Response received:', response);

                return response.json();

            })

            .then(data => {

                console.log('📊 Response data:', data);

                if (data.success) {

                    // Special handling for completed status
                    if (newStatus.toLowerCase() === 'completed') {
                        handleCompletedEvent(selectElement, eventId);
                    } else {
                        showNotification(`✅ Event status updated to ${newStatus} successfully!`, 'success');
                        
                        // Update the select styling
                        updateStatusSelectStyling(selectElement, newStatus);
                        
                        // Force refresh after successful update
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }

                } else {

                    showNotification(`❌ Failed to update status: ${data.message}`, 'error');

                    // Reset to previous value on error

                    selectElement.value = currentStatus;

                }

            })

            .catch(error => {

                console.error('❌ Error updating event status:', error);

                showNotification('❌ Error occurred while updating status', 'error');

                // Reset to previous value on error

                selectElement.value = currentStatus;

            });

        }







        // Update status select styling

        function updateStatusSelectStyling(selectElement, status) {

            // Remove all status-specific classes

            selectElement.classList.remove('status-scheduled', 'status-completed', 'status-cancelled', 'status-rescheduled');

            

            // Add appropriate class

            selectElement.classList.add(`status-${status.toLowerCase()}`);

        }













        

        // Initialize calendar view options

        function initializeViewOptions() {

            const viewButtons = document.querySelectorAll('.view-options .btn');

            

            viewButtons.forEach(button => {

                button.addEventListener('click', function() {

                    const view = this.dataset.view;

                    

                    // Remove active class from all buttons

                    viewButtons.forEach(btn => btn.classList.remove('active'));

                    

                    // Add active class to clicked button

                    this.classList.add('active');

                    

                    // Change calendar view

                    if (calendar) {

                        switch(view) {

                            case 'month':

                                calendar.changeView('dayGridMonth');

                                break;

                            case 'week':

                                calendar.changeView('timeGridWeek');

                                break;

                            case 'day':

                                calendar.changeView('timeGridDay');

                                break;

                        }

                    }

                });

            });

        }

        

        // Initialize status select previous values

        function initializeStatusSelects() {

            const statusSelects = document.querySelectorAll('.status-select');

            statusSelects.forEach(select => {

                // Set previous value for change detection

                select.dataset.previousValue = select.value;

            });

        }



        // Add Schedule Modal Functionality

        document.addEventListener('DOMContentLoaded', function() {

            console.log('DOM Content Loaded - Initializing modals...');

            

            // Initialize calendar view options

            initializeViewOptions();

            

            const addEventBtn = document.getElementById('addEventBtn');

            const addEventModal = document.getElementById('addEventModal');

            const addWalkinBtn = document.getElementById('addWalkinBtn');

            const addWalkinModal = document.getElementById('addWalkinModal');

            const closeModal = addEventModal.querySelector('.close-modal');

            const cancelEvent = document.getElementById('cancelEvent');



            console.log('Add Schedule Button:', addEventBtn);

            console.log('Add Schedule Modal:', addEventModal);



            if (addEventBtn && addEventModal) {

                addEventBtn.onclick = function() {

                    console.log('Add Schedule button clicked!');

                    addEventModal.style.display = "block";

                    document.body.style.overflow = 'hidden'; // Prevent background scrolling

                    

                    // Set minimum date to tomorrow (no current day scheduling)

                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);

                    const dateField = document.getElementById('eventDate');

                    if (dateField) {

                        dateField.min = tomorrow.toISOString().split('T')[0];

                        dateField.value = tomorrow.toISOString().split('T')[0];

                    }

                    


                }



                // Setup automatic end time and validation
                setupAdminAddScheduleValidation();

                // X button functionality removed - only Cancel button can close modal



                if (cancelEvent) {

                    cancelEvent.onclick = function() {

                        console.log('Cancel event clicked');

                        addEventModal.style.display = "none";

                        document.body.style.overflow = 'auto';

                    }

                }

            } else {

                console.error('Modal elements not found!');

            }




            // Close modal when clicking outside - REMOVED to prevent accidental closing
            // window.onclick = function(event) {
            //     if (event.target == addEventModal) {
            //         addEventModal.style.display = "none";
            //         document.body.style.overflow = 'auto';
            //     }
            //     const eventInfoModal = document.getElementById('eventInfoModal');
            //     if (event.target == eventInfoModal) {
            //         eventInfoModal.style.display = "none";
            //         document.body.style.overflow = 'auto';
            //     }
            //     const editEventModal = document.getElementById('editEventModal');
            //     if (event.target == editEventModal) {
            //         editEventModal.style.display = "none";
            //         document.body.style.overflow = 'auto';
            //     }
            // }


            // Add AJAX for saving event
            const saveEventBtn = document.getElementById('saveEvent');
            if (saveEventBtn) {
                saveEventBtn.onclick = function() {
                // Enhanced form validation
                const location = document.getElementById('eventLocation').value.trim();
                const description = document.getElementById('eventDescription').value.trim();
                const eventDate = document.getElementById('eventDate').value;
                const eventStartTime = document.getElementById('eventStartTime').value;
                const eventEndTime = document.getElementById('eventEndTime').value;
                const caseSelect = document.getElementById('eventCase');
                const clientSelect = document.getElementById('eventClient');
                const attorneySelect = document.getElementById('selectedUserId');
                
                // Validate required fields
                if (!location) {
                    showNotification('❌ Location is required!', 'error');
                    document.getElementById('eventLocation').focus();
                    return;
                }
                
                if (!description) {
                    showNotification('❌ Description is required!', 'error');
                    document.getElementById('eventDescription').focus();
                    return;
                }
                
                if (!eventDate) {
                    showNotification('❌ Date is required!', 'error');
                    document.getElementById('eventDate').focus();
                    return;
                }
                
                if (!eventStartTime) {
                    showNotification('❌ Start time is required!', 'error');
                    document.getElementById('eventStartTime').focus();
                    return;
                }
                
                if (!eventEndTime) {
                    showNotification('❌ End time is required!', 'error');
                    document.getElementById('eventEndTime').focus();
                    return;
                }
                
                // Validate time range (8AM-6PM) - STRICT VALIDATION
                const startHour = parseInt(eventStartTime.split(':')[0]);
                const endHour = parseInt(eventEndTime.split(':')[0]);
                
                if (startHour < 8 || startHour >= 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid start time.', 'error');
                    document.getElementById('eventStartTime').focus();
                    return;
                }
                
                if (endHour < 8 || endHour > 18) {
                    showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid end time.', 'error');
                    document.getElementById('eventEndTime').focus();
                    return;
                }
                
                // Validate that end time is after start time
                if (eventStartTime >= eventEndTime) {
                    showNotification('❌ End time must be after start time!', 'error');
                    document.getElementById('eventEndTime').focus();
                    return;
                }
                
                // Validate date restriction (no current day)
                const today = new Date().toISOString().split('T')[0];
                if (eventDate <= today) {
                    showNotification('❌ You can\'t create a schedule for today. Cannot save with invalid date.', 'error');
                    document.getElementById('eventDate').focus();
                    return;
                }
                
                if (!clientSelect.value) {
                    showNotification('❌ Client selection is required!', 'error');
                    document.getElementById('eventClient').focus();
                    return;
                }
                
                if (!attorneySelect.value) {
                    showNotification('❌ Attorney selection is required!', 'error');
                    document.getElementById('selectedUserId').focus();
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
                
                fetch('admin_schedule.php', { method: 'POST', body: fd })
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
                    start: formData.get('date') + 'T' + formData.get('start_time'),
                    end: formData.get('date') + 'T' + formData.get('end_time'),
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
                // Reload the page after showing success notification
                setTimeout(() => {
                    location.reload();
                }, 3000);
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
            } else {
                console.error('❌ Save Event button not found!');
            }

            // Initialize edit event functionality

            initializeEditEventHandlers();

            

            // Initialize view details functionality

            initializeViewDetailsHandlers();

            

            // Initialize status selects

            initializeStatusSelects();

            

            // Debug: Check if Save Schedule button exists

            const saveBtn = document.getElementById('saveEvent');

            if (saveBtn) {

                console.log('✅ Save Schedule button found and initialized');

            } else {

                console.error('❌ Save Schedule button not found!');

                    }
                    
                    // Setup automatic end time and validation
                    setupAdminAddScheduleValidation();
                });



        // Global editEvent function for onclick handlers
        window.editEvent = function(button) {
            // Validate that the event is not completed
            if (button.dataset.status && button.dataset.status.toLowerCase() === 'completed') {
                showNotification('❌ Cannot edit completed schedules!', 'error');
                return;
            }

            console.log('Edit button clicked for event:', button.dataset.eventId);
            
            const eventId = button.dataset.eventId;
            const eventType = button.dataset.type;
            const eventDate = button.dataset.date;
            const eventStartTime = button.dataset.startTime;
            const eventEndTime = button.dataset.endTime;
            const eventLocation = button.dataset.location;
            const eventDescription = button.dataset.description;
            const eventClient = button.dataset.client;
            const eventAttorney = button.dataset.attorney;
            const eventCase = button.dataset.case;
            
            // Populate edit modal
            document.getElementById('editEventId').value = eventId;
            document.getElementById('editEventDate').value = eventDate;
            document.getElementById('editEventStartTime').value = eventStartTime;
            document.getElementById('editEventEndTime').value = eventEndTime;
            document.getElementById('editEventLocation').value = eventLocation;
            document.getElementById('editEventType').value = eventType;
            document.getElementById('editEventDescription').value = eventDescription;
            
            // Case association is fixed - no need to load cases for edit
            
            // Set minimum date to tomorrow (no current day scheduling)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('editEventDate').min = tomorrow.toISOString().split('T')[0];

            // Add event listeners for automatic end time and validation
            console.log('🚀 About to call setupAdminEditScheduleValidation');
            setupAdminEditScheduleValidation();
            console.log('✅ setupAdminEditScheduleValidation completed');
            
            // Initialize edit form case dropdown functionality
            initializeAdminEditCaseDropdown();

            // Show edit modal
            document.getElementById('editEventModal').style.display = "block";
            console.log('📱 Edit modal displayed');
        };

        // Setup validation for admin add schedule form
        function setupAdminAddScheduleValidation() {
            const startTimeInput = document.getElementById('eventStartTime');
            const endTimeInput = document.getElementById('eventEndTime');
            const dateInput = document.getElementById('eventDate');

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
            if (startTimeInput) startTimeInput.addEventListener('change', validateAdminAddTimeRange);
            if (endTimeInput) endTimeInput.addEventListener('change', validateAdminAddTimeRange);
            if (dateInput) dateInput.addEventListener('change', validateAdminAddDate);
        }

        // Validate time range for admin add schedule (8AM-6PM)
        function validateAdminAddTimeRange() {
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

        // Validate date for admin add schedule (no current day)
        function validateAdminAddDate() {
            const selectedDate = document.getElementById('eventDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Setup validation for admin edit schedule form
        function setupAdminEditScheduleValidation() {
            console.log('🔧 Admin Edit Validation Setup Called');
            const startTimeInput = document.getElementById('editEventStartTime');
            const endTimeInput = document.getElementById('editEventEndTime');
            const dateInput = document.getElementById('editEventDate');

            console.log('📋 Elements found:', {
                startTime: startTimeInput,
                endTime: endTimeInput,
                date: dateInput
            });

            console.log('🔍 Start Time Input Details:', {
                element: startTimeInput,
                value: startTimeInput ? startTimeInput.value : 'null',
                id: startTimeInput ? startTimeInput.id : 'null'
            });

            console.log('🔍 End Time Input Details:', {
                element: endTimeInput,
                value: endTimeInput ? endTimeInput.value : 'null',
                id: endTimeInput ? endTimeInput.id : 'null'
            });

            // Remove existing listeners first to prevent duplicates
            if (startTimeInput) {
                console.log('🗑️ Removing existing listeners...');
                startTimeInput.removeEventListener('change', handleAdminEditStartTimeChange);
                console.log('➕ Adding new start time listener...');
                startTimeInput.addEventListener('change', handleAdminEditStartTimeChange);
                console.log('✅ Added admin start time listener');
            } else {
                console.log('❌ Start Time Input NOT FOUND!');
            }

            // Time range validation (8AM-6PM)
            if (startTimeInput) {
                startTimeInput.addEventListener('change', validateAdminTimeRange);
                console.log('✅ Added time range validation to start time');
            }
            if (endTimeInput) {
                endTimeInput.addEventListener('change', validateAdminTimeRange);
                console.log('✅ Added time range validation to end time');
            }
            if (dateInput) {
                dateInput.addEventListener('change', validateAdminDate);
                console.log('✅ Added date validation');
            }
            
            console.log('🏁 Admin Edit Validation Setup Complete');
        }

        // Handle admin edit start time change (auto end time)
        function handleAdminEditStartTimeChange() {
            console.log('⏰ Admin Start Time Changed!');
            console.log('🔍 Handler function called at:', new Date().toLocaleTimeString());
            
            const startTimeInput = document.getElementById('editEventStartTime');
            const endTimeInput = document.getElementById('editEventEndTime');
            
            console.log('📊 Current values:', {
                startTimeValue: startTimeInput ? startTimeInput.value : 'null',
                endTimeValue: endTimeInput ? endTimeInput.value : 'null'
            });
            
            if (startTimeInput && startTimeInput.value && endTimeInput) {
                console.log('🔄 Calculating new end time...');
                const startTime = new Date('2000-01-01T' + startTimeInput.value);
                const endTime = new Date(startTime.getTime() + 30 * 60000); // Add 30 minutes
                const newEndTime = endTime.toTimeString().slice(0, 5);
                
                console.log('📅 Time calculation:', {
                    originalStart: startTimeInput.value,
                    calculatedEnd: newEndTime
                });
                
                endTimeInput.value = newEndTime;
                console.log('🕒 Auto-set End Time to:', newEndTime);
                console.log('✅ End time updated successfully!');
            } else {
                console.log('❌ Cannot update end time - missing elements or values');
                console.log('🔍 Debug info:', {
                    startTimeInputExists: !!startTimeInput,
                    startTimeValue: startTimeInput ? startTimeInput.value : 'null',
                    endTimeInputExists: !!endTimeInput
                });
            }
            
            // Also validate time range
            console.log('🔍 Running time range validation...');
            validateAdminTimeRange();
        }

        // Validate time range for admin (8AM-6PM)
        function validateAdminTimeRange() {
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

        // Validate date for admin (no current day)
        function validateAdminDate() {
            const selectedDate = document.getElementById('editEventDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Initialize edit event handlers

        function initializeEditEventHandlers() {

            console.log('Initializing edit event handlers...');

            

            document.querySelectorAll('.edit-event-btn').forEach(function(btn) {

                btn.addEventListener('click', function(e) {

                    e.preventDefault();

                    e.stopPropagation();

                    

                    // Validate that the event is not completed
                    if (this.dataset.status && this.dataset.status.toLowerCase() === 'completed') {
                        showNotification('❌ Cannot edit completed schedules!', 'error');
                        return;
                    }

                    console.log('Edit button clicked for event:', this.dataset.eventId);

                    

                    const eventId = this.dataset.eventId;

                    const eventType = this.dataset.type;

                    const eventDate = this.dataset.date;

                    const eventStartTime = this.dataset.startTime;

                    const eventEndTime = this.dataset.endTime;

                    const eventLocation = this.dataset.location;

                    const eventDescription = this.dataset.description;

                    

                    // Populate edit modal

                    document.getElementById('editEventId').value = eventId;

                    document.getElementById('editEventDate').value = eventDate;

                    document.getElementById('editEventStartTime').value = eventStartTime;

                    document.getElementById('editEventEndTime').value = eventEndTime;

                    document.getElementById('editEventLocation').value = eventLocation;

                    document.getElementById('editEventType').value = eventType;

                    document.getElementById('editEventDescription').value = eventDescription;

                    

                    // Set minimum date to tomorrow (no current day scheduling)
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    document.getElementById('editEventDate').min = tomorrow.toISOString().split('T')[0];

                    // Add event listeners for automatic end time and validation
                    console.log('🚀 About to call setupAdminEditScheduleValidation from initializeEditEventHandlers');
                    setupAdminEditScheduleValidation();
                    console.log('✅ setupAdminEditScheduleValidation completed from initializeEditEventHandlers');

                    // Show edit modal
                    document.getElementById('editEventModal').style.display = "block";
                    console.log('📱 Edit modal displayed from initializeEditEventHandlers');

                });

            });



            // Edit modal close functionality

            const cancelEditEvent = document.getElementById('cancelEditEvent');

            if (cancelEditEvent) {

                cancelEditEvent.addEventListener('click', function() {

                    console.log('Cancel edit clicked');

                    // Show confirmation dialog
                    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                        document.getElementById('editEventModal').style.display = "none";
                    }

                });

            }



            // Save edit functionality

            const saveEditEvent = document.getElementById('saveEditEvent');

            if (saveEditEvent) {

                saveEditEvent.addEventListener('click', async function() {

                    console.log('Save edit clicked');

                    // Show enhanced confirmation with typing requirement
                    const confirmMessage = `⚠️ WARNING: Save changes to this schedule?\n\nThis action will:\n• Update the schedule details\n• Modify the schedule\n• Cannot be easily undone\n\nAre you sure you want to proceed?`;
                    
                    // Validate form fields
                    const startTime = document.getElementById('editEventStartTime').value;
                    const endTime = document.getElementById('editEventEndTime').value;
                    const eventDate = document.getElementById('editEventDate').value;
                    
                    if (!startTime || !endTime || !eventDate) {
                        showNotification('❌ All fields are required!', 'error');
                        return;
                    }
                    
                    // Validate time range (8AM-6PM) - STRICT VALIDATION
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
                    
                    // Validate date restriction (no current day)
                    const today = new Date().toISOString().split('T')[0];
                    if (eventDate <= today) {
                        showNotification('❌ You can\'t create a schedule for today. Cannot save with invalid date.', 'error');
                        document.getElementById('editEventDate').focus();
                        return;
                    }

                    const confirmed = await showEditDoubleConfirmation();
                    if (!confirmed) {
                        return;
                    }

                    const formData = new FormData(document.getElementById('editEventForm'));

                    formData.append('action', 'edit_event');

                    // Debug: Log the form data being sent
                    console.log('Form data being sent:');
                    for (let [key, value] of formData.entries()) {
                        console.log(key + ': ' + value);
                    }

                    

                    fetch('update_event_details.php', { method: 'POST', body: formData })

                        .then(r => r.json()).then(data => {

                            if (data.success) {

                                showNotification('✅ Event updated successfully!', 'success');

                                setTimeout(() => location.reload(), 1500);

                            } else {

                                showNotification('❌ Error updating event: ' + data.message, 'error');

                            }

                        })

                        .catch(error => {

                            console.error('Error:', error);

                            showNotification('❌ Error updating event. Please try again.', 'error');

                        });

                });

            }

        }



        // Function to show event details modal (used by both calendar clicks and View Details buttons)
        function showEventDetailsModal(event) {
            console.log('Showing event details modal for event:', event.title);
            
            // Helper setters to avoid null reference errors
            const setText = (id, value) => { const el = document.getElementById(id); if (el) el.innerText = value; };
            const setDisplay = (id, value) => { const el = document.getElementById(id); if (el) el.style.display = value; };

            // Format date and time
            const eventDate = event.start ? new Date(event.start).toLocaleDateString() : 'N/A';
            const eventTime = event.start && event.end ? 
                new Date(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' - ' + 
                new Date(event.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'N/A';

            // Populate modal with event data
            setText('modalEventType', event.extendedProps.eventType || 'Event');
            setText('modalEventDate', eventDate);
            setText('modalEventTime', eventTime);
            setText('modalType', event.extendedProps.eventType || '-');
            setText('modalDate', eventDate);
            setText('modalTime', eventTime);
            setText('modalLocation', event.extendedProps.location || '-');
            setText('modalAttorney', event.extendedProps.attorney || '-');

            // Handle walk-in clients
            const clientName = event.extendedProps.client || '-';
            const walkinClientName = event.extendedProps.walkinClientName || null;
            const walkinClientContact = event.extendedProps.walkinClientContact || null;
            
            if (walkinClientName) {
                // This is a walk-in client - show walk-in details section
                setDisplay('caseDetailsSection', 'none');
                setDisplay('walkinDetailsSection', 'block');
                
                // Populate walk-in specific details
                setText('modalWalkinClientName', walkinClientName);
                setText('modalWalkinClientContact', walkinClientContact || '-');
                setText('modalWalkinAttorney', event.extendedProps.attorney || '-');
                setText('modalWalkinDescription', event.extendedProps.description || '-');
                setText('modalWalkinCreatedBy', event.extendedProps.createdBy || '-');
            } else {
                // This is a regular client - show case details section
                setDisplay('caseDetailsSection', 'block');
                setDisplay('walkinDetailsSection', 'none');
                
                // Populate regular client details
                setText('modalClient', clientName);
                setText('modalDescription', event.extendedProps.description || '-');
                setText('modalCreatedBy', event.extendedProps.createdBy || '-');
            }

            // Show the modal
            document.getElementById('eventInfoModal').style.display = "block";
        }

        // Initialize view details handlers

        function initializeViewDetailsHandlers() {

            console.log('Initializing view details handlers...');

            

            document.querySelectorAll('.view-details-btn').forEach(function(btn) {

                btn.addEventListener('click', function(e) {

                    e.preventDefault();

                    e.stopPropagation();

                    

                    console.log('View details button clicked for event:', this.dataset.eventId);

                    

                    // Create a mock event object from button dataset attributes
                    const mockEvent = {
                        title: this.dataset.title || 'Event',
                        start: this.dataset.date ? new Date(this.dataset.date + 'T' + (this.dataset.startTime || '00:00')) : null,
                        end: this.dataset.date ? new Date(this.dataset.date + 'T' + (this.dataset.endTime || '23:59')) : null,
                        extendedProps: {
                            eventType: this.dataset.type || 'Event',
                            location: this.dataset.location || '-',
                            attorney: this.dataset.attorney || '-',
                            client: this.dataset.client || '-',
                            description: this.dataset.description || '-',
                            createdBy: this.dataset.createdBy || '-',
                            walkinClientName: this.dataset.walkinClientName || null,
                            walkinClientContact: this.dataset.walkinClientContact || null
                        }
                    };

                    // Use the shared modal function
                    showEventDetailsModal(mockEvent);

                });

            });

            

            // Initialize modal close functionality

            const closeEventInfoModal = document.getElementById('closeEventInfoModal');

            if (closeEventInfoModal) {

                closeEventInfoModal.addEventListener('click', function() {

                    console.log('Close event info modal clicked');

                    document.getElementById('eventInfoModal').style.display = "none";

                });

            }

        }



        // Update user dropdown based on selected user type - REMOVED (using simplified attorney selection)

        // function updateUserDropdown() {
        //     // This function is no longer needed since we use simplified attorney selection
        // }

        

















        // Show notification function

        function showNotification(message, type = 'info') {

            console.log('Showing notification:', message, type);

            

            // Remove existing notifications

            const existingNotifications = document.querySelectorAll('.notification');

            existingNotifications.forEach(notification => notification.remove());

            

            // Create notification element

            const notification = document.createElement('div');

            notification.className = `notification notification-${type}`;

            notification.innerHTML = `

                <div class="notification-content">

                    <span class="notification-message">${message}</span>

                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>

                </div>

            `;

            

            // Add to page

            document.body.appendChild(notification);

            

            // Auto-remove after 5 seconds

            setTimeout(() => {

                if (notification.parentElement) {

                    notification.remove();

                }

            }, 5000);

        }





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


        // Test function to manually complete a schedule (for debugging)
        function testCompleteSchedule() {
            const firstCard = document.querySelector('.event-card');
            const firstSelect = firstCard?.querySelector('.status-select');
            
            if (firstSelect) {
                firstSelect.value = 'Completed';
                handleCompletedEvent(firstSelect, firstCard.dataset.eventId || 'test-1');
            } else {
                console.log('❌ No schedule card found to test');
            }
        }

        // Debug function to inspect calendar events
        function debugCalendarEvents() {
            if (window.calendar) {
                const allEvents = window.calendar.getEvents();
                console.log('📅 Calendar Debug Info:');
                console.log('Total events:', allEvents.length);
                allEvents.forEach((event, index) => {
                    console.log(`Event ${index + 1}:`, {
                        id: event.id,
                        title: event.title,
                        start: event.start,
                        extendedProps: event.extendedProps
                    });
                });
            } else {
                console.log('❌ Calendar not available');
            }
        }

        // Make debug function globally accessible
        window.debugCalendarEvents = debugCalendarEvents;

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
            // Find the global filter container
            const globalFilterContainer = document.querySelector('.events-filter-section');
            
            if (!globalFilterContainer) {
                return; // Skip if no global filter found
            }
            
            const buttons = globalFilterContainer.querySelectorAll('.status-filter-btn');
            
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

        // Pagination functionality for all sections
        let adminCurrentPage = 1;
        let adminItemsPerPage = 6;
        const attorneyPages = {};
        const attorneyItemsPerPage = {};
        let otherCurrentPage = 1;
        let otherItemsPerPage = 6;
        let completedCurrentPage = 1;
        let completedItemsPerPage = 6;

        function initAllPagination() {
            initAdminPagination();
            initAttorneyPagination();
            initOtherPagination();
            initCompletedPagination();
        }

        // Admin Pagination
        function initAdminPagination() {
            const itemsPerPageSelect = document.getElementById('adminItemsPerPage');
            const prevBtn = document.getElementById('adminPrevBtn');
            const nextBtn = document.getElementById('adminNextBtn');

            if (!itemsPerPageSelect) return;

            itemsPerPageSelect.addEventListener('change', function() {
                adminItemsPerPage = parseInt(this.value);
                adminCurrentPage = 1;
                updateAdminDisplay();
            });

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (adminCurrentPage > 1) {
                        adminCurrentPage--;
                        updateAdminDisplay();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const cards = document.querySelectorAll('#adminEventsGrid .event-card');
                    const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
                    const totalPages = Math.ceil(visibleCards.length / adminItemsPerPage);
                    if (adminCurrentPage < totalPages) {
                        adminCurrentPage++;
                        updateAdminDisplay();
                    }
                });
            }

            updateAdminDisplay();
        }

        function updateAdminDisplay() {
            const cards = document.querySelectorAll('#adminEventsGrid .event-card');
            // Filter out cards that are filtered-out or already hidden
            const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
            const totalCards = visibleCards.length;
            const totalPages = Math.ceil(totalCards / adminItemsPerPage);
            const startIndex = (adminCurrentPage - 1) * adminItemsPerPage;
            const endIndex = startIndex + adminItemsPerPage;

            visibleCards.forEach((card, index) => {
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            const pageInfo = document.getElementById('adminPageInfo');
            const adminInfo = document.getElementById('adminInfo');
            
            if (pageInfo) {
                pageInfo.textContent = `Page ${adminCurrentPage} of ${totalPages}`;
            }

            if (adminInfo && totalCards > 0) {
                const start = startIndex + 1;
                const end = Math.min(endIndex, totalCards);
                adminInfo.textContent = `Showing ${start} - ${end} of ${totalCards}`;
            }

            const prevBtn = document.getElementById('adminPrevBtn');
            const nextBtn = document.getElementById('adminNextBtn');

            if (prevBtn) prevBtn.disabled = adminCurrentPage === 1;
            if (nextBtn) nextBtn.disabled = adminCurrentPage === totalPages || totalPages === 0;
        }

        // Attorney Pagination
        function initAttorneyPagination() {
            const itemsPerPageSelects = document.querySelectorAll('.attorneyItemsPerPage');
            const prevBtns = document.querySelectorAll('.attorneyPrevBtn');
            const nextBtns = document.querySelectorAll('.attorneyNextBtn');

            itemsPerPageSelects.forEach(select => {
                const attorneyName = select.getAttribute('data-attorney');
                attorneyPages[attorneyName] = 1;
                attorneyItemsPerPage[attorneyName] = 6;

                select.addEventListener('change', function() {
                    attorneyItemsPerPage[attorneyName] = parseInt(this.value);
                    attorneyPages[attorneyName] = 1;
                    updateAttorneyDisplay(attorneyName);
                });
            });

            prevBtns.forEach(btn => {
                const attorneyName = btn.getAttribute('data-attorney');
                btn.addEventListener('click', function() {
                    if (attorneyPages[attorneyName] > 1) {
                        attorneyPages[attorneyName]--;
                        updateAttorneyDisplay(attorneyName);
                    }
                });
            });

            nextBtns.forEach(btn => {
                const attorneyName = btn.getAttribute('data-attorney');
                btn.addEventListener('click', function() {
                    const cards = document.querySelectorAll(`.attorney-events-grid[data-attorney="${attorneyName}"] .event-card`);
                    const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
                    const totalPages = Math.ceil(visibleCards.length / attorneyItemsPerPage[attorneyName]);
                    if (attorneyPages[attorneyName] < totalPages) {
                        attorneyPages[attorneyName]++;
                        updateAttorneyDisplay(attorneyName);
                    }
                });
            });

            // Initialize all attorney sections
            document.querySelectorAll('.attorney-events-grid').forEach(grid => {
                const attorneyName = grid.getAttribute('data-attorney');
                updateAttorneyDisplay(attorneyName);
            });
        }

        function updateAttorneyDisplay(attorneyName) {
            const cards = document.querySelectorAll(`.attorney-events-grid[data-attorney="${attorneyName}"] .event-card`);
            // Filter out cards that are filtered-out or already hidden
            const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
            const totalCards = visibleCards.length;
            const totalPages = Math.ceil(totalCards / attorneyItemsPerPage[attorneyName]);
            const startIndex = (attorneyPages[attorneyName] - 1) * attorneyItemsPerPage[attorneyName];
            const endIndex = startIndex + attorneyItemsPerPage[attorneyName];

            visibleCards.forEach((card, index) => {
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            const pageInfo = document.querySelector(`.attorneyPageInfo[data-attorney="${attorneyName}"]`);
            const attorneyInfo = document.querySelector(`.attorneyInfo[data-attorney="${attorneyName}"]`);
            
            if (pageInfo) {
                pageInfo.textContent = `Page ${attorneyPages[attorneyName]} of ${totalPages}`;
            }

            if (attorneyInfo && totalCards > 0) {
                const start = startIndex + 1;
                const end = Math.min(endIndex, totalCards);
                attorneyInfo.textContent = `Showing ${start} - ${end} of ${totalCards}`;
            }

            const prevBtn = document.querySelector(`.attorneyPrevBtn[data-attorney="${attorneyName}"]`);
            const nextBtn = document.querySelector(`.attorneyNextBtn[data-attorney="${attorneyName}"]`);

            if (prevBtn) prevBtn.disabled = attorneyPages[attorneyName] === 1;
            if (nextBtn) nextBtn.disabled = attorneyPages[attorneyName] === totalPages || totalPages === 0;
        }

        // Other Events Pagination
        function initOtherPagination() {
            const itemsPerPageSelect = document.getElementById('otherItemsPerPage');
            const prevBtn = document.getElementById('otherPrevBtn');
            const nextBtn = document.getElementById('otherNextBtn');

            if (!itemsPerPageSelect) return;

            itemsPerPageSelect.addEventListener('change', function() {
                otherItemsPerPage = parseInt(this.value);
                otherCurrentPage = 1;
                updateOtherDisplay();
            });

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (otherCurrentPage > 1) {
                        otherCurrentPage--;
                        updateOtherDisplay();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const cards = document.querySelectorAll('#otherEventsGrid .event-card');
                    const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
                    const totalPages = Math.ceil(visibleCards.length / otherItemsPerPage);
                    if (otherCurrentPage < totalPages) {
                        otherCurrentPage++;
                        updateOtherDisplay();
                    }
                });
            }

            updateOtherDisplay();
        }

        function updateOtherDisplay() {
            const cards = document.querySelectorAll('#otherEventsGrid .event-card');
            // Filter out cards that are filtered-out or already hidden
            const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
            const totalCards = visibleCards.length;
            const totalPages = Math.ceil(totalCards / otherItemsPerPage);
            const startIndex = (otherCurrentPage - 1) * otherItemsPerPage;
            const endIndex = startIndex + otherItemsPerPage;

            visibleCards.forEach((card, index) => {
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            const pageInfo = document.getElementById('otherPageInfo');
            const otherInfo = document.getElementById('otherInfo');
            
            if (pageInfo) {
                pageInfo.textContent = `Page ${otherCurrentPage} of ${totalPages}`;
            }

            if (otherInfo && totalCards > 0) {
                const start = startIndex + 1;
                const end = Math.min(endIndex, totalCards);
                otherInfo.textContent = `Showing ${start} - ${end} of ${totalCards}`;
            }

            const prevBtn = document.getElementById('otherPrevBtn');
            const nextBtn = document.getElementById('otherNextBtn');

            if (prevBtn) prevBtn.disabled = otherCurrentPage === 1;
            if (nextBtn) nextBtn.disabled = otherCurrentPage === totalPages || totalPages === 0;
        }

        // Completed Schedules Pagination
        function initCompletedPagination() {
            const itemsPerPageSelect = document.getElementById('completedItemsPerPage');
            const prevBtn = document.getElementById('completedPrevBtn');
            const nextBtn = document.getElementById('completedNextBtn');

            if (!itemsPerPageSelect) return;

            itemsPerPageSelect.addEventListener('change', function() {
                completedItemsPerPage = parseInt(this.value);
                completedCurrentPage = 1;
                updateCompletedDisplay();
            });

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (completedCurrentPage > 1) {
                        completedCurrentPage--;
                        updateCompletedDisplay();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const cards = document.querySelectorAll('#completedEventsGrid .event-card');
                    const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
                    const totalPages = Math.ceil(visibleCards.length / completedItemsPerPage);
                    if (completedCurrentPage < totalPages) {
                        completedCurrentPage++;
                        updateCompletedDisplay();
                    }
                });
            }

            updateCompletedDisplay();
        }

        function updateCompletedDisplay() {
            const cards = document.querySelectorAll('#completedEventsGrid .event-card');
            // Filter out cards that are filtered-out or already hidden
            const visibleCards = Array.from(cards).filter(card => !card.classList.contains('filtered-out'));
            const totalCards = visibleCards.length;
            const totalPages = Math.ceil(totalCards / completedItemsPerPage);
            const startIndex = (completedCurrentPage - 1) * completedItemsPerPage;
            const endIndex = startIndex + completedItemsPerPage;

            visibleCards.forEach((card, index) => {
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            const pageInfo = document.getElementById('completedPageInfo');
            const completedInfo = document.getElementById('completedInfo');
            
            if (pageInfo) {
                pageInfo.textContent = `Page ${completedCurrentPage} of ${totalPages}`;
            }

            if (completedInfo && totalCards > 0) {
                const start = startIndex + 1;
                const end = Math.min(endIndex, totalCards);
                completedInfo.textContent = `Showing ${start} - ${end} of ${totalCards}`;
            }

            const prevBtn = document.getElementById('completedPrevBtn');
            const nextBtn = document.getElementById('completedNextBtn');

            if (prevBtn) prevBtn.disabled = completedCurrentPage === 1;
            if (nextBtn) nextBtn.disabled = completedCurrentPage === totalPages || totalPages === 0;
        }

        // Initialize all pagination on page load
        document.addEventListener('DOMContentLoaded', function() {
            initAllPagination();
        });

    </script>

<script src="assets/js/unread-messages.js?v=1761540569"></script>
</body>

</html> 

