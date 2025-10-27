<?php
require_once 'session_manager.php';
validateUserAccess('attorney');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'color_manager.php';
require_once 'color_assignment.php';
$attorney_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
    // Fallback to an existing bundled image to avoid 404
    $profile_image = 'images/default-avatar.jpg';
}

// Fetch all cases for this attorney
$cases = [];
$stmt = $conn->prepare("SELECT ac.id, ac.title, uf.name as client_name FROM attorney_cases ac LEFT JOIN user_form uf ON ac.client_id = uf.id WHERE ac.attorney_id=? ORDER BY ac.id DESC");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $cases[] = $row;

// Fetch all clients for this attorney (from both cases and assignments)
$clients = [];
$stmt = $conn->prepare("
    SELECT DISTINCT uf.id, uf.name, uf.email 
    FROM user_form uf 
    WHERE uf.user_type = 'client' 
    AND (uf.id IN (SELECT client_id FROM attorney_cases WHERE attorney_id = ?)
         OR uf.id IN (SELECT client_id FROM client_attorney_assignments WHERE attorney_id = ?))
    ORDER BY uf.name
");
$stmt->bind_param("ii", $attorney_id, $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Fetch all registered clients (for free legal advice sessions)
$all_clients = [];
$stmt = $conn->prepare("SELECT id, name, email FROM user_form WHERE user_type = 'client' ORDER BY name");
$stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $all_clients[] = $row;

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
      
      // Check if event is completed and belongs to this attorney
      $stmt = $conn->prepare("SELECT cs.status FROM case_schedules cs 
          LEFT JOIN attorney_cases ac ON cs.case_id = ac.id 
          WHERE cs.id = ? AND (
              cs.attorney_id = ? OR 
              (cs.attorney_id IS NULL AND ac.attorney_id = ?)
          )");
      $stmt->bind_param("iii", $event_id, $attorney_id, $attorney_id);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result && $row = $result->fetch_assoc()) {
          if ($row['status'] && strtolower($row['status']) === 'completed') {
              echo 'error: Cannot edit completed schedules';
              exit;
          }
      } else {
          echo 'error: Event not found or you do not have permission to edit it';
          exit;
      }

      // Check if event belongs to this attorney first
      $check_stmt = $conn->prepare("SELECT cs.id FROM case_schedules cs 
          LEFT JOIN attorney_cases ac ON cs.case_id = ac.id 
          WHERE cs.id = ? AND (
              cs.attorney_id = ? OR 
              (cs.attorney_id IS NULL AND ac.attorney_id = ?)
          )");
      $check_stmt->bind_param("iii", $event_id, $attorney_id, $attorney_id);
      $check_stmt->execute();
      $check_result = $check_stmt->get_result();
      
      if ($check_result->num_rows === 0) {
          echo 'error: You can only edit your own events';
          exit;
      }

      // Update the event (simple approach like admin)
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
      
      echo 'success';
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
    
    // Client is always required
    if (!$client_id) {
        echo 'error: Client selection is required';
        exit;
    }
    
    // Check for time conflicts - prevent double booking
    $stmt = $conn->prepare("SELECT id, title, type, start_time, end_time FROM case_schedules WHERE attorney_id = ? AND date = ? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?)) AND status != 'Cancelled'");
    $stmt->bind_param("isssssss", $attorney_id, $date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $conflict_result = $stmt->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $conflict_event = $conflict_result->fetch_assoc();
        $conflict_start = date('g:i A', strtotime($conflict_event['start_time']));
        $conflict_end = date('g:i A', strtotime($conflict_event['end_time']));
        echo 'error: You already have a ' . $conflict_event['type'] . ' scheduled from ' . $conflict_start . ' to ' . $conflict_end . ' on ' . $date . ' that conflicts with your time slot. Please choose a different time.';
        exit;
    }
    
    // If case_id is provided, get client_id from case
    if ($case_id) {
        $stmt = $conn->prepare("SELECT client_id FROM attorney_cases WHERE id=? AND attorney_id=?");
        $stmt->bind_param("ii", $case_id, $attorney_id);
        $stmt->execute();
        $q = $stmt->get_result();
        if ($r = $q->fetch_assoc()) {
            $client_id = $r['client_id'];
        }
    }
    
    // For free legal advice, allow any client (client is already required above)
    if ($type === 'Free Legal Advice') {
        // Client can be any registered client - validation already done above
    } else {
        // For other types, verify client belongs to this attorney if no case
        if (!$case_id) {
            $stmt = $conn->prepare("
                SELECT 1 FROM (
                    SELECT client_id FROM attorney_cases WHERE client_id=? AND attorney_id=?
                    UNION
                    SELECT client_id FROM client_attorney_assignments WHERE client_id=? AND attorney_id=?
                ) as client_check LIMIT 1
            ");
            $stmt->bind_param("iiii", $client_id, $attorney_id, $client_id, $attorney_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                echo 'error: Unauthorized client access - client must be assigned to you';
                exit;
            }
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO case_schedules (case_id, attorney_id, client_id, type, description, date, start_time, end_time, location, created_by_employee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiissssssi', $case_id, $attorney_id, $client_id, $type, $description, $date, $start_time, $end_time, $location, $attorney_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log to audit trail with full context (creator, attorney, client)
        try {
            $auditLogger = new AuditLogger($conn);

            // Fetch client name if available
            $client_name = '';
            if (!empty($client_id)) {
                $stmt_cli = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                $stmt_cli->bind_param('i', $client_id);
                $stmt_cli->execute();
                $client_row = $stmt_cli->get_result()->fetch_assoc();
                if ($client_row && !empty($client_row['name'])) { $client_name = $client_row['name']; }
            }

            $creator_name = $_SESSION['attorney_name'] ?? 'Attorney';
            $details = "Created by: $creator_name; Attorney: $creator_name; Client: " . ($client_name !== '' ? $client_name : ($client_id ? (string)$client_id : 'N/A')) . "; Type: $type; Date: $date; Time: $start_time-$end_time; Location: $location";

            $auditLogger->logAction(
                $attorney_id,
                $creator_name,
                'attorney',
                'Schedule Created',
                'Schedule Management',
                $details,
                'success',
                'medium'
            );
        } catch (Exception $auditError) {
            error_log("Audit logging failed: " . $auditError->getMessage());
        }
        
        // Notify client about the new schedule
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'New Schedule Created';
            $nMsg = "A new $type has been scheduled for you on $date from $start_time to $end_time at $location";
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

// Fetch all events for this attorney
$events = [];
$stmt = $conn->prepare("SELECT cs.*, ac.title as case_title, 
    CASE 
        WHEN cs.client_id IS NOT NULL THEN uf.name 
        WHEN cs.walkin_client_name IS NOT NULL THEN cs.walkin_client_name
        ELSE 'Walk-in Client'
    END as client_name, 
    uf_attorney.name as attorney_name, uf_creator.name as created_by_name,
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
    LEFT JOIN user_form uf ON cs.client_id = uf.id 
    LEFT JOIN user_form uf_attorney ON cs.attorney_id = uf_attorney.id 
    LEFT JOIN user_form uf_creator ON cs.created_by_employee_id = uf_creator.id 
    WHERE (
        cs.attorney_id = ? OR 
        (cs.attorney_id IS NULL AND ac.attorney_id = ?)
    )
    ORDER BY cs.date, cs.start_time");
$stmt->bind_param("ii", $attorney_id, $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $events[] = $row;

// Debug: Log session information
error_log("Session attorney_name: " . ($_SESSION['attorney_name'] ?? 'NULL'));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NULL'));

// Generate JavaScript events for FullCalendar
$js_events = [];
$calendar_events = []; // Separate array for calendar view (excludes completed events)
foreach ($events as $ev) {
    // Debug: Log attorney information
    error_log("Attorney Schedule Event: " . $ev['type'] . " - Attorney ID: " . ($ev['attorney_id'] ?? 'NULL') . " - Attorney Name: " . ($ev['attorney_name'] ?? 'NULL') . " - Status: " . ($ev['status'] ?? 'NULL'));
    
    $js_events[] = [
        'id' => $ev['id'], // Add ID for calendar event removal
        'title' => $ev['type'] . ': ' . ($ev['case_title'] ?? ''),
        'start' => $ev['date'] . 'T' . $ev['start_time'],
        'end' => $ev['date'] . 'T' . $ev['end_time'],
        'type' => $ev['type'],
        'description' => $ev['description'],
        'location' => $ev['location'],
        'case' => $ev['case_title'],
        'attorney' => $ev['attorney_name'],
        'client' => $ev['client_name'],
        'extendedProps' => [
            'eventType' => $ev['type'],
            'attorneyName' => $ev['attorney_name'] ?? $_SESSION['attorney_name'] ?? 'Attorney',
            'attorneyId' => $ev['attorney_id'] ?? 0,
            'case' => $ev['case_title'],
            'client' => $ev['client_name'],
            'description' => $ev['description'],
            'createdBy' => $ev['created_by_name'] ?? 'Unknown'
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
            'case' => $ev['case_title'],
            'attorney' => $ev['attorney_name'],
            'client' => $ev['client_name'],
            'extendedProps' => [
                'eventType' => $ev['type'],
                'attorneyName' => $ev['attorney_name'] ?? $_SESSION['attorney_name'] ?? 'Attorney',
                'attorneyId' => $ev['attorney_id'] ?? 0,
                'case' => $ev['case_title'],
                'client' => $ev['client_name'],
                'description' => $ev['description'],
                'createdBy' => $ev['created_by_name'] ?? 'Unknown'
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
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="assets/js/color-manager.js"></script>
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
        }
        
        /* Fix spacing between FullCalendar toolbar buttons */
        .fc-toolbar-chunk {
            display: flex;
            gap: 8px !important;
        }
        
        .fc-toolbar-chunk .fc-button {
            margin: 0 4px !important;
        }
        
        /* Action Buttons Layout - Left/Right Positioning */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            border: 1px solid #f0f0f0;
        }
        
        .view-options {
            display: flex;
            gap: 0;
            align-items: center;
            background: white;
            border-radius: 8px;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: #5D0E26;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Add Schedule button - Smaller Size */
        #addEventBtn {
            background: linear-gradient(135deg, #5D0E26, #8B1538) !important;
            border: 2px solid #5D0E26 !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            padding: 8px 16px !important;
            min-width: 120px !important;
            height: 35px !important;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3) !important;
            position: relative !important;
            z-index: 1000 !important;
            opacity: 1 !important;
            visibility: visible !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            border-radius: 6px !important;
        }

        #addEventBtn:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A) !important;
            border-color: #4A0B1E !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.5) !important;
            scale: 1.02 !important;
        }

        #addEventBtn i {
            font-size: 14px !important;
            margin-right: 6px !important;
            color: white !important;
        }
        
        .btn-primary:hover {
            background: #4A0B1E;
            transform: translateY(-1px);
        }
        
        
        .btn-secondary {
            background: #f8f9fa;
            color: #5D0E26;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            border-radius: 6px;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            color: #5D0E26;
        }
        
        .btn-secondary.active {
            background: #5D0E26;
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .fc-button {
            background: var(--primary-color, #5D0E26);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .fc-button:hover {
            background: var(--primary-dark, #4A0B1E);
            transform: translateY(-2px);
        }
        
        .fc-button.active {
            background: var(--secondary-color, #8B1538);
        }
        
        /* FullCalendar Button Overrides for Maroon Theme */
        .fc-button-primary {
            background: #5D0E26 !important;
            border-color: #5D0E26 !important;
            color: white !important;
        }
        
        .fc-button-primary:hover {
            background: #8B1538 !important;
            border-color: #8B1538 !important;
        }
        
        .fc-button-primary:focus {
            background: #5D0E26 !important;
            border-color: #5D0E26 !important;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.3) !important;
        }
        
        .fc-button-primary:disabled {
            background: #6c757d !important;
            border-color: #6c757d !important;
            opacity: 0.6 !important;
        }
        
        .fc-button-primary:not(:disabled):active {
            background: #4A0B1E !important;
            border-color: #4A0B1E !important;
        }
        
        /* FullCalendar Toolbar Title (Month/Week/Day text) */
        .fc-toolbar-title {
            color: #5D0E26 !important;
            font-weight: 700 !important;
        }
        
        /* FullCalendar Navigation Arrows */
        .fc-prev-button,
        .fc-next-button {
            background: #5D0E26 !important;
            border-color: #5D0E26 !important;
            color: white !important;
        }
        
        .fc-prev-button:hover,
        .fc-next-button:hover {
            background: #8B1538 !important;
            border-color: #8B1538 !important;
        }
        
        .fc-today-button {
            background: #5D0E26 !important;
            border-color: #5D0E26 !important;
            color: white !important;
        }
        
        .fc-today-button:hover {
            background: #8B1538 !important;
            border-color: #8B1538 !important;
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
            transition: all 0.3s ease;
        }
        
        .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .attorney-legend {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }
        
        .attorney-legend h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 10px;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
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
            <li><a href="attorney_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php" ><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php" class="active"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Schedule Management';
        $page_subtitle = 'Manage your court hearings and appointments';
        include 'components/profile_header.php'; 
        ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary" id="addEventBtn">
                <i class="fas fa-plus"></i> Add Schedule
            </button>

            <div class="view-options">
                <button class="btn btn-secondary" id="viewMonthBtn">
                    <i class="fas fa-calendar"></i> Month
                </button>
                <button class="btn btn-secondary" id="viewWeekBtn">
                    <i class="fas fa-calendar-week"></i> Week
                </button>
                <button class="btn btn-secondary" id="viewDayBtn">
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

        <!-- Upcoming Events -->
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
                <p>You have no scheduled events at the moment.</p>
            </div>
            <?php else: ?>
            <div class="events-grid">
                <?php foreach ($events as $ev): ?>
                <?php
                // Debug: Show what attorney name is being used
                $debug_attorney_name = $ev['attorney_name'] ?? $_SESSION['attorney_name'] ?? 'Attorney';
                error_log("Schedule Card Attorney Name: " . $debug_attorney_name);
                ?>
                <div class="event-card status-<?= strtolower($ev['status']) ?><?= (strtolower($ev['status']) === 'completed') ? ' completed-schedule' : '' ?>" data-event-id="<?= $ev['id'] ?>" data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>" data-attorney-id="<?= $_SESSION['user_id'] ?>" data-user-id="<?= $_SESSION['user_id'] ?>" data-attorney-name="<?= htmlspecialchars($debug_attorney_name) ?>" data-type="<?= htmlspecialchars($ev['type']) ?>" data-date="<?= htmlspecialchars($ev['date']) ?>" data-time="<?= htmlspecialchars($ev['start_time']) ?>" data-location="<?= htmlspecialchars($ev['location'] ?? '') ?>" data-case="<?= htmlspecialchars($ev['case_title'] ?? 'No Case') ?>" data-client="<?= htmlspecialchars($ev['client_name'] ?? 'N/A') ?>" data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>" data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>" data-description="<?= htmlspecialchars($ev['description'] ?? '') ?>" data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>">
                    <div class="event-card-header">
                        <div class="event-avatar">
                            <i class="fas fa-<?= $ev['type'] == 'Hearing' ? 'gavel' : 'calendar-check' ?>"></i>
                        </div>
                        <div class="event-info">
                            <h3><?= htmlspecialchars($ev['type']) ?></h3>
                            <p class="event-title">
                                <i class="fas fa-clock"></i>
                                <?= date('g:i A', strtotime($ev['start_time'] ?? '00:00:00')) ?> - <?= date('g:i A', strtotime($ev['end_time'] ?? '00:00:00')) ?>
                            </p>
                            <p class="case-detail">
                                <i class="fas fa-calendar"></i>
                                <?= date('M d, Y', strtotime($ev['date'] ?? date('Y-m-d'))) ?>
                            </p>
                            <p class="client-detail">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($ev['client_name'] ?? 'N/A') ?>
                            </p>
                        </div>
                    </div>
                    

                    
                    <div class="event-actions">
                        <div class="status-management">
                            <select class="status-select" onchange="updateEventStatus(this)" data-previous-status="<?= htmlspecialchars($ev['status']) ?>">
                                <option value="Scheduled" <?= $ev['status'] == 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="Completed" <?= $ev['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Rescheduled" <?= $ev['status'] == 'Rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                                <option value="Cancelled" <?= $ev['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <button class="btn btn-warning btn-sm edit-event-btn" onclick="editEvent(this)" 
                            data-event-id="<?= $ev['id'] ?>"

                            data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"
                            data-type="<?= htmlspecialchars($ev['type']) ?>"
                            data-date="<?= htmlspecialchars($ev['date']) ?>"
                            data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                            data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"
                            data-location="<?= htmlspecialchars($ev['location']) ?>"
                            data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                            data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"
                            data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"
                            data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"
                            data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"

                            data-status="<?= htmlspecialchars($ev['status'] ?? 'Scheduled') ?>"
                            title="Edit Event">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-primary view-info-btn" 
                            data-title="<?= htmlspecialchars($ev['title'] ?: $ev['type'] ?: 'Event') ?>"
                            data-type="<?= htmlspecialchars($ev['type']) ?>"
                            data-date="<?= htmlspecialchars($ev['date']) ?>"
                            data-start-time="<?= htmlspecialchars($ev['start_time']) ?>"

                            data-end-time="<?= htmlspecialchars($ev['end_time']) ?>"
                            data-location="<?= htmlspecialchars($ev['location']) ?>"
                            data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                            data-client="<?= htmlspecialchars($ev['client_name'] ?? '-') ?>"
                            data-walkin-client-name="<?= htmlspecialchars($ev['walkin_client_name'] ?? '') ?>"
                            data-walkin-client-contact="<?= htmlspecialchars($ev['walkin_client_contact'] ?? '') ?>"
                            data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"
                            data-created-by="<?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?>"
                            title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Completed Schedules Section -->
        <div class="completed-schedules-section" style="display: none;">
            <div class="section-header">
                <div class="header-content">
                    <div class="header-text">
                        <h2><i class="fas fa-check-circle"></i> Completed Schedules</h2>
                        <p>View previously completed schedules and appointments</p>
                    </div>
                    
                    <button class="toggle-completed-btn" onclick="toggleCompletedSection()">
                        <i class="fas fa-eye-slash"></i> Hide Completed
                    </button>
                </div>
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
                        <input type="text" id="editEventLocation" name="location" value="Cabuyao" required>
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
                <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveEventChanges()">Save Schedule</button>
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
                        <h2>Scheduled Details</h2>
                        <p>Complete scheduled information and case details</p>
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
                            <span class="detail-label"><i class="fas fa-user-tie"></i> Attorney:</span>
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
                            <span class="detail-label"><i class="fas fa-user-tie"></i> Assigned Attorney:</span>
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
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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

        /* Enhanced Upcoming Events Styles */
        .upcoming-events-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-top: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }

        .section-header {
            margin-bottom: 2rem;
        }

        .section-header .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 1.5rem;
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

        .events-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            max-width: 100%;
        }

        .event-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            border-left: none !important;
            border-top: 1px solid #e9ecef;
            border-right: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            min-height: 160px;
            display: flex;
            flex-direction: column;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .event-card-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .event-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
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
            color: #5D0E26 !important;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .case-detail {
            color: #5D0E26 !important;
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

        .attorney-indicator {
            color: #5D0E26 !important;
            font-weight: 500;
        }

        .event-info i {
            font-size: 0.8rem;
            width: 16px;
            text-align: center;
        }



        .event-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            /* gap: 0.02rem; */
            margin-top: auto;
        }

        .status-management {
            flex: 0 0 auto;
        }

        /* Make edit and view buttons closer together */
        .edit-event-btn + .view-info-btn {
            margin-left: 0.01rem;
        }

        .status-select {
            width: 120px;
            padding: 0.4rem 0.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            background: white;
            color: #333;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }

        .status-select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }

        .status-select option[value="Scheduled"] {
            color: #5D0E26;
            font-weight: 600;
        }

        .status-select option[value="Completed"] {
            color: #2e7d32;
            font-weight: 600;
        }



        .edit-event-btn {
            background: #ffc107 !important;
            border: 1px solid #ffc107 !important;
            color: #212529 !important;
            font-weight: 600 !important;
            border-radius: 4px !important;
            /* padding: 0.1rem !important; */
            font-size: 0.7rem !important;
            width: 68px !important;
            height: 28px !important;
            display: flex !important;
            align-items: left !important;
            justify-content: center !important;
            min-width: 28px !important;
        }

        .edit-event-btn:hover {
            background: #e0a800 !important;
            border-color: #d39e00 !important;
            color: #212529 !important;
        }

        .view-info-btn {
            background: #17a2b8 !important;
            border: 1px solid #17a2b8 !important;
            color: white !important;
            font-weight: 600 !important;
            border-radius: 4px !important;
            /* padding: 0.2rem !important; */
            font-size: 0.7rem !important;
            width: 68px !important;
            height: 28px !important;
            display: left !important;
            align-items: left !important;
            justify-content: center !important;
            min-width: 28px !important;
        }

        .view-info-btn:hover {
            background: #138496 !important;
            border-color: #138496 !important;
            color: white !important;
        }

        .status-select option[value="Rescheduled"] {
            color: #f57c00;
            font-weight: 600;
        }

        .status-select option[value="Cancelled"] {
            color: #6c757d;
            font-weight: 600;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Status-based Card Styling - Left border colors removed */
        .event-card.status-scheduled {
            /* Left border color removed */
        }

        .event-card.status-completed {
            /* Left border color removed */
        }

        .event-card.status-rescheduled {
            /* Left border color removed */
        }

        /* Dynamic Color Coding - Generated by Color Manager */
        <?php echo generateColorCSS(); ?>



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
            max-width: 900px;
            width: 90%;
            margin: 2% auto;
            overflow: hidden;
            max-height: 90vh;
        }

        /* Three Column Modal Styles */
        .three-column-modal {
            max-width: 1200px !important;
            width: 95% !important;
            max-height: 85vh !important;
        }

        .three-column-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
            padding: 1.5rem;
            height: auto;
            overflow: visible;
        }

        .form-column {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: #e74c3c !important;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fff;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            color: #666;
            font-size: 0.75rem;
            font-style: italic;
            margin-top: 0.25rem;
        }

        .modal-header {
            background: #5D0E26;
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
            background: #5D0E26;
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
            color: #5D0E26;
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
            color: #5D0E26;
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
            color: #5D0E26;
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
            color: #5D0E26;
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
            .add-event-modal {
                max-width: 900px !important;
                max-height: 90vh !important;
                margin: 1.5% auto !important;
                width: 95% !important;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .add-event-modal .modal-body {
                padding: 1.5rem;
                flex: 1;
                overflow-y: auto;
            }

                    .add-event-modal .event-form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 0.8rem;
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
                color: #e74c3c !important;
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
            border-color: #5D0E26;
            background: white;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            transform: translateY(-1px);
        }

        .add-event-modal .form-group input:hover,
        .add-event-modal .form-group select:hover,
        .add-event-modal .form-group textarea:hover {
            border-color: #5D0E26;
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
            color: #5D0E26;
        }
        .event-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 24px;
        }
        .event-form-grid .form-group {
            margin-bottom: 0;
        }
        .event-form-grid .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-size: 1rem;
            color: #e74c3c !important;
            margin-bottom: 4px;
            display: block;
            font-weight: 500;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background: #fafbfc;
            margin-top: 2px;
            transition: border 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #5D0E26;
            outline: none;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
        }
        .btn-primary {
            background: #5D0E26;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #4A0B1E;
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        @media (max-width: 1200px) {
            .add-event-modal .event-form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 700px) {
            .add-event-modal .event-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .three-column-form {
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
        }

        /* Filters Container Styles */
        .filters-container {
            display: flex;
            gap: 2rem;
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }

        .filter-group h3 {
            color: #5D0E26;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group h3 i {
            color: #8B1538;
            font-size: 1rem;
        }

        /* Status Filter Styles */
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
            text-align: center;
        }

        .status-filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: flex-start;
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
            border-color: #5D0E26;
            color: #5D0E26;
            background: rgba(93, 14, 38, 0.1);
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
            border-color: #5D0E26;
            color: #5D0E26;
            background: rgba(93, 14, 38, 0.1);
        }

        .status-filter-btn.active {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-color: #5D0E26;
            color: white;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }

        .status-filter-btn.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
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
            /* Left border color removed */
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

        /* Improved spacing for completed event cards - compact but not cramped */
        .event-card.completed-schedule {
            padding: 1rem;
            min-height: 160px;
        }

        .event-card.completed-schedule .event-card-header {
            margin-bottom: 1rem;
        }

        .event-card.completed-schedule .event-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.6rem;
            line-height: 1.3;
        }

        .event-card.completed-schedule .event-info p {
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
            line-height: 1.4;
        }

        .event-card.completed-schedule .event-actions {
            margin-top: 0.8rem;
        }

        /* Better spacing for completed card elements */
        .event-card.completed-schedule .event-avatar {
            width: 45px;
            height: 45px;
            font-size: 1.1rem;
        }

        .event-card.completed-schedule .status-management {
            margin-bottom: 0.5rem;
        }

        /* Completed Schedules Section */
        .completed-schedules-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            width: 100%;
            clear: both;
            position: relative;
        }

        .show-completed-btn, .toggle-completed-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
            white-space: nowrap;
        }

        .show-completed-btn:hover, .toggle-completed-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
        }

        /* Extra Small Screens */
        @media (max-width: 480px) {
            .edit-event-btn, .view-info-btn {
                width: 20px !important;
                height: 20px !important;
                padding: 0.1rem !important;
                font-size: 0.5rem !important;
                min-width: 20px !important;
            }

            .event-actions {
                gap: 0.005rem;
            }

            .edit-event-btn + .view-info-btn {
                margin-left: 0.002rem;
            }
        }

        .completed-events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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

            /* Responsive Action Buttons */
            .event-actions {
                gap: 0.01rem;
                flex-wrap: wrap;
            }

            .edit-event-btn, .view-info-btn {
                width: 24px !important;
                height: 24px !important;
                padding: 0.15rem !important;
                font-size: 0.6rem !important;
                min-width: 24px !important;
            }

            .edit-event-btn + .view-info-btn {
                margin-left: 0.005rem;
            }

            /* Responsive completed event cards */
            .completed-events-grid {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }

            .event-card.completed-schedule {
                padding: 0.8rem;
                min-height: 140px;
            }

            .event-card.completed-schedule .event-card-header {
                margin-bottom: 0.8rem;
            }

            .event-card.completed-schedule .event-info h3 {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }

            .event-card.completed-schedule .event-info p {
                font-size: 0.85rem;
                margin-bottom: 0.25rem;
            }

            .event-card.completed-schedule .event-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .completed-schedules-section {
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding: 1rem;
            }
        }
    </style>

    <script>
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
        
        // Use json_encode to safely pass PHP events to JS with color information
        var events = <?php echo json_encode(array_map(function($ev) {
            // Get colors for this attorney
            $attorneyId = $_SESSION['user_id'] ?? 0;
            $attorneyUserType = $_SESSION['user_type'] ?? 'attorney';
            $eventColors = getEventColors($attorneyId, $attorneyUserType);
            
            return [
                "title" => ($ev['type'] ?? '') . ': ' . ($ev['title'] ?? ''),
                "start" => ($ev['date'] ?? '') . 'T' . ($ev['start_time'] ?? ''),
                "description" => $ev['description'] ?? '',
                "location" => $ev['location'] ?? '',
                "case" => $ev['case_title'] ?? '',
                "client" => $ev['client_name'] ?? '',
                "type" => $ev['type'] ?? '',
                "attorneyName" => $_SESSION['attorney_name'] ?? 'Attorney',
                "attorneyId" => $_SESSION['user_id'] ?? 0,
                "color" => $eventColors['calendar_event_color'],
                "backgroundColor" => $eventColors['calendar_event_color'],
                "borderColor" => $eventColors['calendar_event_color'],
                "textColor" => '#ffffff',
                "extendedProps" => [
                    "eventType" => $ev['type'] ?? '',
                    "attorneyName" => $_SESSION['attorney_name'] ?? 'Attorney',
                    "attorneyId" => $_SESSION['user_id'] ?? 0,
                    "attorneyUserType" => $_SESSION['user_type'] ?? 'attorney',
                    "scheduleCardColor" => $eventColors['schedule_card_color'],
                    "calendarEventColor" => $eventColors['calendar_event_color'],
                    "colorName" => $eventColors['color_name']
                ]
            ];
        }, $events), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

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
            
            // Get event ID from the card (you might need to add this data attribute)
            const eventId = eventCard.dataset.eventId || '1'; // Default fallback
            
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

        // Function to edit event
        window.editEvent = function(button) {
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
            const eventClient = button.dataset.client;
            const eventDescription = button.dataset.description;

            // Populate the edit modal
            const editEventIdEl = document.getElementById('editEventId');
            const editEventTypeEl = document.getElementById('editEventType');
            const editEventDateEl = document.getElementById('editEventDate');
            const editEventStartTimeEl = document.getElementById('editEventStartTime');
            const editEventEndTimeEl = document.getElementById('editEventEndTime');
            const editEventLocationEl = document.getElementById('editEventLocation');
            const editEventDescriptionEl = document.getElementById('editEventDescription');
            const editEventCaseEl = document.getElementById('editEventCase');
            const editEventModalEl = document.getElementById('editEventModal');
            
            if (editEventIdEl) editEventIdEl.value = eventId;
            if (editEventTypeEl) editEventTypeEl.value = eventType;
            if (editEventDateEl) editEventDateEl.value = eventDate;
            if (editEventStartTimeEl) editEventStartTimeEl.value = eventStartTime;
            if (editEventEndTimeEl) editEventEndTimeEl.value = eventEndTime;
            if (editEventLocationEl) editEventLocationEl.value = eventLocation;
            if (editEventDescriptionEl) editEventDescriptionEl.value = eventDescription;
            
            // Case association is fixed - no need to load cases for edit
            
            // Set minimum date to tomorrow (no current day scheduling)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            if (editEventDateEl) editEventDateEl.min = tomorrow.toISOString().split('T')[0];

            // Add event listeners for automatic end time and validation
            setupAttorneyEditScheduleValidation();
            
            // Initialize edit form case dropdown functionality
            initializeEditCaseDropdown();

            // Show the edit modal
            if (editEventModalEl) editEventModalEl.style.display = 'block';
        }

        // Setup validation for attorney add schedule form
        function setupAttorneyAddScheduleValidation() {
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
            if (startTimeInput) startTimeInput.addEventListener('change', validateAttorneyAddTimeRange);
            if (endTimeInput) endTimeInput.addEventListener('change', validateAttorneyAddTimeRange);
            if (dateInput) dateInput.addEventListener('change', validateAttorneyAddDate);
        }

        // Validate time range for attorney add schedule (8AM-6PM)
        function validateAttorneyAddTimeRange() {
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

        // Validate date for attorney add schedule (no current day)
        function validateAttorneyAddDate() {
            const selectedDate = document.getElementById('eventDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Setup validation for attorney edit schedule form
        function setupAttorneyEditScheduleValidation() {
            const startTimeInput = document.getElementById('editEventStartTime');
            const endTimeInput = document.getElementById('editEventEndTime');
            const dateInput = document.getElementById('editEventDate');

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
            if (startTimeInput) startTimeInput.addEventListener('change', validateAttorneyTimeRange);
            if (endTimeInput) endTimeInput.addEventListener('change', validateAttorneyTimeRange);
            if (dateInput) dateInput.addEventListener('change', validateAttorneyDate);
        }

        // Validate time range for attorney (8AM-6PM)
        function validateAttorneyTimeRange() {
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

        // Validate date for attorney (no current day)
        function validateAttorneyDate() {
            const selectedDate = document.getElementById('editEventDate').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (selectedDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                return false;
            }
            
            return true;
        }

        // Function to close edit modal
        window.closeEditModal = function() {
            // Show confirmation dialog
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                const editEventModalEl = document.getElementById('editEventModal');
                if (editEventModalEl) editEventModalEl.style.display = 'none';
            }
        }

        // Function to save event changes
        window.saveEventChanges = async function() {
            // Show enhanced confirmation with typing requirement
            const confirmMessage = `⚠️ WARNING: Save changes to this event?\n\nThis action will:\n• Update the event details\n• Modify the schedule\n• Cannot be easily undone\n\nAre you sure you want to proceed?`;
            
            // Validate form fields
            const editEventStartTimeEl = document.getElementById('editEventStartTime');
            const editEventEndTimeEl = document.getElementById('editEventEndTime');
            const editEventDateEl = document.getElementById('editEventDate');
            
            if (!editEventStartTimeEl || !editEventEndTimeEl || !editEventDateEl) {
                showNotification('❌ Form elements not found!', 'error');
                return;
            }
            
            const startTime = editEventStartTimeEl.value;
            const endTime = editEventEndTimeEl.value;
            const eventDate = editEventDateEl.value;
            
            if (!startTime || !endTime || !eventDate) {
                showNotification('❌ All fields are required!', 'error');
                return;
            }

            // Validate time range (8AM-6PM)
            const startHour = parseInt(startTime.split(':')[0]);
            const endHour = parseInt(endTime.split(':')[0]);
            
            if (startHour < 8 || startHour >= 18) {
                showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid start time.', 'error');
                editEventStartTimeEl.focus();
                return;
            }
            
            if (endHour < 8 || endHour > 18) {
                showNotification('❌ You can only schedule between 8:00 AM and 6:00 PM. Cannot save with invalid end time.', 'error');
                editEventEndTimeEl.focus();
                return;
            }

            // Validate that end time is after start time
            if (endTime <= startTime) {
                showNotification('❌ End time must be after start time!', 'error');
                editEventEndTimeEl.focus();
                return;
            }
            
            // Validate that date is not current day or past
            const today = new Date().toISOString().split('T')[0];
            if (eventDate <= today) {
                showNotification('❌ You can\'t create a schedule for today.', 'error');
                editEventDateEl.focus();
                return;
            }

            const confirmed = await showEditDoubleConfirmation();
            if (!confirmed) {
                return;
            }
            
            const form = document.getElementById('editEventForm');
            if (!form) {
                showNotification('❌ Form not found!', 'error');
                return;
            }
            
            const formData = new FormData(form);
            formData.append('action', 'edit_event');

            // Show loading state
            const saveBtn = document.querySelector('#editEventModal .btn-primary');
            if (!saveBtn) {
                showNotification('❌ Save button not found!', 'error');
                return;
            }
            
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            fetch('attorney_schedule.php', {
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

        // Function to update event card UI based on status
        function updateEventCardUI(selectElement, newStatus) {
            const eventCard = selectElement.closest('.event-card');
            
            // Remove previous status classes
            eventCard.classList.remove('status-scheduled', 'status-completed', 'status-rescheduled', 'status-cancelled');
            
            // Add new status class
            eventCard.classList.add(`status-${newStatus.toLowerCase()}`);
        }

        // Initialize case dropdown functionality
        function initializeCaseDropdown() {
            const clientSelect = document.getElementById('eventClient');
            const caseSelect = document.getElementById('eventCase');
            
            if (!clientSelect || !caseSelect) return;
            
            function resetCases() {
                caseSelect.innerHTML = '<option value="">Select Case</option>';
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
                            caseSelect.appendChild(option); 
                        });
                    }
                })
                .catch(console.error);
            }
            
            clientSelect.addEventListener('change', function() {
                loadCasesForClient(this.value);
            });
        }

        // Initialize edit form case dropdown functionality
        function initializeEditCaseDropdown() {
            const editCaseSelect = document.getElementById('editEventCase');
            
            if (!editCaseSelect) return;
            
            function resetEditCases() {
                editCaseSelect.innerHTML = '<option value="">Select Case</option>';
            }
        }

        // Load cases for edit schedule (get client_id from schedule)
        function loadCasesForEditSchedule(scheduleId, currentCase = null) {
            const editCaseSelect = document.getElementById('editEventCase');
            if (!editCaseSelect) return;
            
            function resetEditCases() {
                editCaseSelect.innerHTML = '<option value="">Select Case</option>';
            }
            
            // Get schedule details to find client_id
            fetch('attorney_schedule.php', { 
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

        // Load cases for edit client (used in editEvent function)
        function loadCasesForEditClient(clientId, currentCase = null) {
            const editCaseSelect = document.getElementById('editEventCase');
            if (!editCaseSelect) return;
            
            function resetEditCases() {
                editCaseSelect.innerHTML = '<option value="">Select Case</option>';
            }
            
            if (!clientId) {
                resetEditCases();
                return;
            }
            
            fetch('get_cases_by_client.php', { 
                method:'POST', 
                headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                body:'client_id='+encodeURIComponent(clientId) 
            })
            .then(r=>r.json())
            .then(data=>{
                resetEditCases();
                if(data && data.success && data.cases){
                    data.cases.forEach(caseItem=>{ 
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

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            
            // Debug: Log the events data
            console.log('Calendar events:', <?= json_encode($calendar_events) ?>);
            
            // Initialize case dropdown functionality
            initializeCaseDropdown();
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?= json_encode($calendar_events) ?>,
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
                },
                eventClick: function(info) {
                    // Trigger the same modal as View Details button
                    showEventDetailsModal(info.event);
                }
            });
            calendar.render();
            
            // Store calendar instance globally for access
            window.calendar = calendar;

            // Modal functionality
            const modal = document.getElementById('addEventModal');
            const addEventBtn = document.getElementById('addEventBtn');
            const closeModal = document.querySelector('.close-modal');
            const cancelEvent = document.getElementById('cancelEvent');

            addEventBtn.onclick = function() {
                modal.style.display = "block";
                
                // Set minimum date to tomorrow (no current day scheduling)
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('eventDate').min = tomorrow.toISOString().split('T')[0];
                document.getElementById('eventDate').value = tomorrow.toISOString().split('T')[0];
                
                // Setup automatic end time and validation
                setupAttorneyAddScheduleValidation();
            }

            closeModal.onclick = function() {
                closeAddEventModal();
            }

            cancelEvent.onclick = function() {
                closeAddEventModal();
            }


            // Close modal when clicking outside - REMOVED to prevent accidental closing
            // window.onclick = function(event) {
            //     if (event.target == modal) {
            //         modal.style.display = "none";
            //     }
            //     if (event.target == document.getElementById('eventInfoModal')) {
            //         document.getElementById('eventInfoModal').style.display = "none";
            //     }
            //     if (event.target == document.getElementById('editEventModal')) {
            //         document.getElementById('editEventModal').style.display = "none";
            //     }
            // }

            // View buttons functionality
            const viewDayBtn = document.getElementById('viewDayBtn');
            if (viewDayBtn) {
                viewDayBtn.onclick = function() {
                    // Remove active class from all view buttons
                    document.querySelectorAll('.view-options .btn').forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    calendar.changeView('timeGridDay');
                }
            }

            const viewWeekBtn = document.getElementById('viewWeekBtn');
            if (viewWeekBtn) {
                viewWeekBtn.onclick = function() {
                    // Remove active class from all view buttons
                    document.querySelectorAll('.view-options .btn').forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    calendar.changeView('timeGridWeek');
                }
            }

            // Set Month button as active by default
            const viewMonthBtn = document.getElementById('viewMonthBtn');
            if (viewMonthBtn) {
                viewMonthBtn.classList.add('active');
                
                viewMonthBtn.onclick = function() {
                    // Remove active class from all view buttons
                    document.querySelectorAll('.view-options .btn').forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    calendar.changeView('dayGridMonth');
                }
            }

            // Initialize event handlers after calendar is ready
            initializeEventHandlers();

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
                    
                    // Check if elements exist
                    if (!locationEl || !descriptionEl || !eventDateEl || !eventStartTimeEl || !eventEndTimeEl || !clientSelect) {
                        console.error('Required form elements not found:');
                        console.error('locationEl:', locationEl);
                        console.error('descriptionEl:', descriptionEl);
                        console.error('eventDateEl:', eventDateEl);
                        console.error('eventStartTimeEl:', eventStartTimeEl);
                        console.error('eventEndTimeEl:', eventEndTimeEl);
                        console.error('clientSelect:', clientSelect);
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
                    
                    // Client is always required
                    if (!clientSelect.value) {
                        showNotification('❌ Client selection is required!', 'error');
                        clientSelect.focus();
                        return;
                    }
                    
                    // Show loading state
                    const saveBtn = document.getElementById('saveEvent');
                    const originalText = saveBtn.textContent;
                    saveBtn.textContent = 'Saving...';
                    saveBtn.disabled = true;
                
                    const fd = new FormData(document.getElementById('eventForm'));
                    fd.append('action', 'add_event');
                    fetch('attorney_schedule.php', { method: 'POST', body: fd })
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


            // Event status management functions
            

            
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
                setText('modalAttorney', '<?= htmlspecialchars($_SESSION['attorney_name'] ?? 'N/A') ?>');
                
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
                    setText('modalWalkinAttorney', '<?= htmlspecialchars($_SESSION['attorney_name'] ?? 'N/A') ?>');
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
                setDisplay('eventInfoModal', 'block');
                
                // Add close button functionality
                setupModalCloseHandlers();
            }

            // Setup modal close handlers (simplified approach)
            function setupModalCloseHandlers() {
                // Close button (X) in header
                const closeModal = document.querySelector('#eventInfoModal .close-modal');
                if (closeModal) {
                    closeModal.onclick = function() {
                        document.getElementById('eventInfoModal').style.display = "none";
                    };
                }
                
                // Close button in footer
                const closeEventInfoModal = document.getElementById('closeEventInfoModal');
                if (closeEventInfoModal) {
                    closeEventInfoModal.onclick = function() {
                        document.getElementById('eventInfoModal').style.display = "none";
                    };
                }
                
                // Close modal when clicking outside
                const modal = document.getElementById('eventInfoModal');
                if (modal) {
                    modal.onclick = function(e) {
                        if (e.target === modal) {
                            modal.style.display = "none";
                        }
                    };
                }
            }

            // Handle View Details button clicks
            function handleViewDetailsClick() {
                document.querySelectorAll('.view-info-btn').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Debug: Log the data being used
                        console.log('Event data:', {
                            title: this.dataset.title,
                            type: this.dataset.type,
                            date: this.dataset.date,
                            time: this.dataset.time
                        });
                        
                        // Create a mock event object from button dataset attributes
                        const mockEvent = {
                            title: this.dataset.title || 'Event',
                            start: this.dataset.date ? new Date(this.dataset.date + 'T' + (this.dataset.startTime || '00:00')) : null,
                            end: this.dataset.date ? new Date(this.dataset.date + 'T' + (this.dataset.endTime || '23:59')) : null,
                            extendedProps: {
                                eventType: this.dataset.type || 'Event',
                                location: this.dataset.location || '-',
                                attorney: '<?= htmlspecialchars($_SESSION['attorney_name'] ?? 'N/A') ?>',
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
            }
            
            // Add event listeners for modal close buttons
            function addModalCloseListeners() {
                // Close button (X) in header for Event Details modal
                const closeModal = document.querySelector('#eventInfoModal .close-modal');
                if (closeModal) {
                    // Remove existing listeners first
                    closeModal.replaceWith(closeModal.cloneNode(true));
                    const newCloseModal = document.querySelector('#eventInfoModal .close-modal');
                    newCloseModal.addEventListener('click', function() {
                        document.getElementById('eventInfoModal').style.display = "none";
                    });
                }
                
                // Close button in footer for Event Details modal
                const closeEventInfoModal = document.getElementById('closeEventInfoModal');
                if (closeEventInfoModal) {
                    // Remove existing listeners first
                    closeEventInfoModal.replaceWith(closeEventInfoModal.cloneNode(true));
                    const newCloseEventInfoModal = document.getElementById('closeEventInfoModal');
                    newCloseEventInfoModal.addEventListener('click', function() {
                        document.getElementById('eventInfoModal').style.display = "none";
                    });
                }
                
                // Close button (X) in header for Add Schedule modal
                const addEventCloseModal = document.querySelector('#addEventModal .close-modal');
                if (addEventCloseModal) {
                    // Remove existing listeners first
                    addEventCloseModal.replaceWith(addEventCloseModal.cloneNode(true));
                    const newAddEventCloseModal = document.querySelector('#addEventModal .close-modal');
                    newAddEventCloseModal.addEventListener('click', function() {
                        document.getElementById('addEventModal').style.display = "none";
                    });
                }
            }

            // Initialize calendar view options
            function initializeViewOptions() {
                const viewButtons = document.querySelectorAll('.view-options .btn');
                
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const buttonId = this.id;
                        
                        // Remove active class from all buttons
                        viewButtons.forEach(btn => btn.classList.remove('active'));
                        
                        // Add active class to clicked button
                        this.classList.add('active');
                        
                        // Change calendar view
                        if (calendar) {
                            switch(buttonId) {
                                case 'viewMonthBtn':
                                    calendar.changeView('dayGridMonth');
                                    break;
                                case 'viewWeekBtn':
                                    calendar.changeView('timeGridWeek');
                                    break;
                                case 'viewDayBtn':
                                    calendar.changeView('timeGridDay');
                                    break;
                            }
                        }
                    });
                });
                
                // Set Month as default active
                const monthBtn = document.getElementById('viewMonthBtn');
                if (monthBtn) {
                    monthBtn.classList.add('active');
                }
            }
            
            // Initialize all event handlers
            function initializeEventHandlers() {
                // Initialize status selects
                document.querySelectorAll('.status-select').forEach(select => {
                    select.dataset.previousStatus = select.value;
                });
                
                // Initialize view details buttons
                handleViewDetailsClick();
                
                // Initialize modal close functionality
                initializeModalHandlers();
                
                // Initialize calendar view options
                initializeViewOptions();
            }
            
            // Initialize modal handlers
            function initializeModalHandlers() {
                // Close modal when clicking outside - REMOVED to prevent accidental closing
                // window.onclick = function(event) {
                //     if (event.target == document.getElementById('eventInfoModal')) {
                //         document.getElementById('eventInfoModal').style.display = "none";
                //     }
                //     if (event.target == document.getElementById('editEventModal')) {
                //         document.getElementById('editEventModal').style.display = "none";
                //     }
                // }
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
                    backgroundColor: '#5D0E26',
                    borderColor: '#5D0E26',
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
                // Hide any time conflict warnings
                hideTimeConflictWarning();
            }

            // Real-time time conflict checker
            function checkTimeConflict() {
                const eventDate = document.getElementById('eventDate').value;
                const eventStartTime = document.getElementById('eventStartTime').value;
                const eventEndTime = document.getElementById('eventEndTime').value;
                
                if (!eventDate || !eventStartTime || !eventEndTime) {
                    hideTimeConflictWarning();
                    return;
                }
                
                // Check for conflicts via AJAX
                fetch('check_time_conflict.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `date=${eventDate}&start_time=${eventStartTime}&end_time=${eventEndTime}&attorney_id=<?= $attorney_id ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.hasConflict) {
                        showTimeConflictWarning(data.conflictEvent);
                    } else {
                        hideTimeConflictWarning();
                    }
                })
                .catch(error => {
                    console.error('Error checking time conflict:', error);
                });
            }
            
            // Show time conflict warning
            function showTimeConflictWarning(conflictEvent) {
                let warningDiv = document.getElementById('timeConflictWarning');
                if (!warningDiv) {
                    warningDiv = document.createElement('div');
                    warningDiv.id = 'timeConflictWarning';
                    warningDiv.style.cssText = `
                        background: #fff3cd;
                        border: 1px solid #ffeaa7;
                        color: #856404;
                        padding: 10px;
                        border-radius: 5px;
                        margin-top: 10px;
                        font-size: 14px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    `;
                    
                    const timeInput = document.getElementById('eventStartTime');
                    timeInput.parentNode.appendChild(warningDiv);
                }
                
                warningDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i>
                    <span><strong>Time Conflict Detected!</strong> You already have a <strong>${conflictEvent.type}</strong> scheduled at <strong>${conflictEvent.time}</strong> on <strong>${conflictEvent.date}</strong>. Please choose a different time.</span>
                `;
            }
            
            // Hide time conflict warning
            function hideTimeConflictWarning() {
                const warningDiv = document.getElementById('timeConflictWarning');
                if (warningDiv) {
                    warningDiv.remove();
                }
            }
            
            // Add event listeners for real-time conflict checking
            const eventDateEl = document.getElementById('eventDate');
            const eventStartTimeEl = document.getElementById('eventStartTime');
            const eventEndTimeEl = document.getElementById('eventEndTime');
            
            if (eventDateEl) {
                eventDateEl.addEventListener('change', checkTimeConflict);
            }
            if (eventStartTimeEl) {
                eventStartTimeEl.addEventListener('change', checkTimeConflict);
                eventStartTimeEl.addEventListener('input', checkTimeConflict);
            }
            if (eventEndTimeEl) {
                eventEndTimeEl.addEventListener('change', checkTimeConflict);
                eventEndTimeEl.addEventListener('input', checkTimeConflict);
            }
            
            // Initialize when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                initializeEventHandlers();
            });
            
            // Modal close functionality is now handled in initializeModalHandlers()
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
            // Find the global filter container
            const globalFilterContainer = document.querySelector('.status-filter-container');
            
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
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html> 