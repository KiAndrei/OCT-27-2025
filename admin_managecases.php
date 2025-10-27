<?php
require_once 'session_manager.php';
validateUserAccess('admin');

require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$admin_id = $_SESSION['user_id'];

// Fetch all cases with client and attorney information
$cases = [];
$sql = "SELECT ac.*, 
        c.name as client_name, 
        a.name as attorney_name,
        ac.created_at as date_filed,
        ac.attorney_id
        FROM attorney_cases ac 
        LEFT JOIN user_form c ON ac.client_id = c.id 
        LEFT JOIN user_form a ON ac.attorney_id = a.id 
        ORDER BY ac.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
}

// Debug: Check cases structure
error_log("Admin cases structure: " . json_encode($cases));

// Fetch all attorneys for dropdown
$attorneys = [];
$attorney_sql = "SELECT id, name, user_type FROM user_form WHERE user_type IN ('attorney', 'admin', 'admin_attorney') ORDER BY user_type, name";
$attorney_result = $conn->query($attorney_sql);
if ($attorney_result) {
    while ($row = $attorney_result->fetch_assoc()) {
        $attorneys[] = $row;
    }
}

// Debug: Log all case types from database
$case_types_debug = [];
foreach ($cases as $case) {
    if ($case['case_type'] && !in_array($case['case_type'], $case_types_debug)) {
        $case_types_debug[] = $case['case_type'];
    }
}
error_log("Case types in database: " . implode(', ', $case_types_debug));

// Fetch all clients for dropdown
$clients = [];
$stmt = $conn->prepare("SELECT id, name FROM user_form WHERE user_type='client'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Attorney dropdown removed - admin automatically assigned as attorney

// Handle AJAX add case
if (isset($_POST['action']) && $_POST['action'] === 'add_case') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $client_id = intval($_POST['client_id']);
    $attorney_id = intval($_POST['attorney_id']);
    $case_type = $_POST['case_type'];
    $status = 'Pending'; // Automatically set to Pending
    $next_hearing = null; // No next hearing field anymore
    
    $stmt = $conn->prepare("INSERT INTO attorney_cases (title, description, attorney_id, client_id, case_type, status, next_hearing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiisss', $title, $description, $attorney_id, $client_id, $case_type, $status, $next_hearing);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Log case creation to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Create',
            'Case Management',
            "Created new case: $title (Type: $case_type, Status: Pending)",
            'success',
            'medium'
        );
        
        // Notify client about the new case
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            // Get attorney name for notification (admin acting as attorney)
            $stmt_attorney = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
            $stmt_attorney->bind_param('i', $attorney_id);
            $stmt_attorney->execute();
            $attorney_name = $stmt_attorney->get_result()->fetch_assoc()['name'];
            
            $nTitle = 'New Case Assigned';
            $nMsg = "A new case has been created for you by attorney: $attorney_name - $title";
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

// Handle AJAX update case
if (isset($_POST['action']) && $_POST['action'] === 'edit_case') {
    $case_id = intval($_POST['case_id']);
    $status = $_POST['status'];
    $attorney_id = intval($_POST['attorney_id']);
    
    $stmt = $conn->prepare("UPDATE attorney_cases SET status=?, attorney_id=? WHERE id=?");
    $stmt->bind_param('sii', $status, $attorney_id, $case_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log case update to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Update',
            'Case Management',
            "Updated case ID: $case_id (Status: $status)",
            'warning',
            'medium'
        );
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

// Handle AJAX delete case
if (isset($_POST['action']) && $_POST['action'] === 'delete_case') {
    $case_id = intval($_POST['case_id']);
    
    // Get case details before deletion for audit logging
    $caseStmt = $conn->prepare("SELECT title, case_type FROM attorney_cases WHERE id = ?");
    $caseStmt->bind_param('i', $case_id);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result();
    $caseData = $caseResult->fetch_assoc();
    
    $stmt = $conn->prepare("DELETE FROM attorney_cases WHERE id=?");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log case deletion to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Delete',
            'Case Management',
            "Deleted case: " . ($caseData['title'] ?? "ID: $case_id") . " (Type: " . ($caseData['case_type'] ?? 'Unknown') . ")",
            'success',
            'high' // HIGH priority for deletions
        );
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Management - Admin Dashboard</title>
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
            <li><a href="admin_managecases.php" class="active"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="admin_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="admin_messages.php"><i class="fas fa-comments"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>

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
                    <button class="btn-primary" onclick="showAddCaseModal()">
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

            <!-- Pagination Controls -->
            <div class="pagination-controls" style="margin: 20px 0; padding: 12px 16px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); border: 1px solid #e9ecef; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                <label for="itemsPerPage" style="font-weight: 600; color: #5D0E26; font-size: 0.9rem;">Show per page:</label>
                <select id="itemsPerPage" style="padding: 8px 16px; border: 2px solid #5D0E26; border-radius: 8px; background: white; color: #5D0E26; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="15">15</option>
                    <option value="20">20</option>
                    <option value="25">25</option>
                    <option value="30">30</option>
                    <option value="all">All</option>
                </select>
                <span class="items-info" style="font-weight: 500; color: #6c757d; font-size: 0.85rem;"></span>
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
                        <button class="btn-view" onclick="viewCase(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title']) ?>', '<?= htmlspecialchars($case['client_name'] ?? 'N/A') ?>', '<?= htmlspecialchars($case['attorney_name'] ?? 'N/A') ?>', '<?= htmlspecialchars($case['case_type']) ?>', '<?= htmlspecialchars($case['status']) ?>', '<?= htmlspecialchars($case['description'] ?? '') ?>', '<?= date('M d, Y', strtotime($case['date_filed'])) ?>', '<?= htmlspecialchars($case['next_hearing'] ?? '') ?>')">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-edit" onclick="editCase(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title']) ?>', '<?= htmlspecialchars($case['status']) ?>', <?= $case['attorney_id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Case Modal -->
    <div id="addCaseModal" class="modal" style="z-index: 9999 !important;">
        <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <div class="modal-header">
                <div class="header-content">
                    <div class="case-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="header-text">
                        <h2>Add New Case</h2>
                        <p>Create a new case for client management</p>
                    </div>
                </div>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
            <form id="addCaseForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Case Title</label>
                        <input type="text" name="title" required placeholder="Enter case title">
                    </div>
                    <div class="form-group">
                        <label>Case Type</label>
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
                    <label>Client</label>
                    <select name="client_id" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Attorney field removed - admin will automatically be assigned as attorney -->
                    <input type="hidden" name="attorney_id" value="<?= $admin_id ?>">
                </div>
                <div class="form-group description-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" required placeholder="Enter detailed case description"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Save Case</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Edit Case Modal -->
    <div id="editCaseModal" class="modal" style="z-index: 9999 !important;">
        <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <div class="modal-header">
                <div class="header-content">
                    <div class="case-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="header-text">
                        <h2>Admin Case Management</h2>
                        <p>Update case information and status with admin privileges</p>
                    </div>
                </div>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
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
                <div class="form-group">
                    <label>Attorney</label>
                    <select name="attorney_id" id="editCaseAttorney" required>
                        <option value="">Select Attorney</option>
                        <?php foreach ($attorneys as $attorney): ?>
                        <option value="<?= $attorney['id'] ?>">
                            <?= htmlspecialchars($attorney['name']) ?>
                            <?php if ($attorney['user_type'] === 'admin'): ?> (Admin)
                            <?php elseif ($attorney['user_type'] === 'admin_attorney'): ?> (Admin & Attorney)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Update Case</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- View Case Modal -->
    <div id="viewCaseModal" class="modal" style="z-index: 9999 !important;">
        <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <!-- Content will be dynamically populated -->
        </div>
    </div>

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
            gap: 12px; 
            margin-bottom: 20px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 8px;
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
            min-width: 300px;
            flex: 2;
        }
        
        .case-filters, .filters {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .type-filters, .search-bar {
            display: flex;
            width: 100%;
        }
        
        .search-bar {
            position: relative;
        }
        
        .search-bar input {
            flex: 1;
            padding: 10px 40px 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 350px;
        }
        
        .search-bar button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: #5D0E26;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-bar button:hover {
            background: #8B1538;
        }
        
        .case-filter-btn {
            padding: 8px 16px;
            border: 2px solid #5D0E26;
            border-radius: 20px;
            background: white;
            color: #5D0E26;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .case-filter-btn:hover {
            background: #5D0E26;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .case-filter-btn.active {
            background: #5D0E26;
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #5D0E26;
            border-radius: 20px;
            background: white;
            color: #5D0E26;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-btn:hover {
            background: #5D0E26;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .filter-btn.active {
            background: #5D0E26;
            color: white;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .type-filters select {
            padding: 8px 16px;
            border: 2px solid #5D0E26;
            border-radius: 20px;
            background: white;
            color: #5D0E26;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%235D0E26' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .type-filters select:hover {
            background-color: #f8f9fa;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
        }
        
        .type-filters select:focus {
            outline: none;
            border-color: #8B1538;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }
        .search-bar { display: flex; gap: 5px; }
        .search-bar input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
        .search-bar button { padding: 8px 12px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        /* New Card Layout Styles */
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
            box-shadow: 0 8px 25px rgba(0,0,0,0.08), 0 3px 10px rgba(0,0,0,0.05); 
            padding: 12px; 
            border: 1px solid rgba(229, 231, 235, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            box-sizing: border-box;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        .case-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5D0E26 0%, #8B1538 50%, #8B4A6B 100%);
            border-radius: 16px 16px 0 0;
        }
        
        .case-card:hover { 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 12px 35px rgba(0,0,0,0.15), 0 5px 15px rgba(0,0,0,0.1); 
        }
        
        .case-header { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            margin-bottom: 6px; 
        }
        
        .case-status { 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 0.7em; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .case-status:hover::before {
            left: 100%;
        }
        
        .status-active { 
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); 
            color: #155724; 
            border: 1px solid rgba(21, 87, 36, 0.2);
        }
        .status-pending { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            color: #856404; 
            border: 1px solid rgba(133, 100, 4, 0.2);
        }
        .status-closed { 
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); 
            color: #721c24; 
            border: 1px solid rgba(114, 28, 36, 0.2);
        }
        
        .client-name { 
            font-size: 1em; 
            font-weight: 600; 
            color: #1f2937; 
            margin-bottom: 8px; 
            text-align: center;
            padding: 8px 6px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: linear-gradient(135deg, rgba(93, 14, 38, 0.02) 0%, rgba(139, 21, 56, 0.02) 100%);
            border-radius: 6px;
            margin: 6px 0 8px 0;
        }
        
        .client-name i { 
            color: #5D0E26; 
            font-size: 1.1em; 
            filter: drop-shadow(0 1px 2px rgba(93, 14, 38, 0.3));
        }
        
        .case-actions { 
            display: flex; 
            gap: 8px; 
            align-items: center; 
            justify-content: center;
            margin-top: 8px;
        }
        
        .btn-view { 
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); 
            color: white; 
            border: none; 
            padding: 12px; 
            border-radius: 12px; 
            cursor: pointer; 
            width: 44px; 
            height: 44px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-view::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-view:hover::before {
            left: 100%;
        }
        
        .btn-view:hover { 
            background: linear-gradient(135deg, #4A0B1E 0%, #5D0E26 100%); 
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
        }
        
        .btn-edit { 
            padding: 12px; 
            border: none; 
            border-radius: 12px; 
            cursor: pointer; 
            width: 44px; 
            height: 44px; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%); 
            color: #333; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-edit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-edit:hover::before {
            left: 100%;
        }
        
        .btn-edit:hover { 
            background: linear-gradient(135deg, #ff8c00 0%, #ffc107 100%); 
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }
        
        .no-cases { 
            grid-column: 1 / -1; 
            text-align: center; 
            padding: 60px 20px; 
            color: #6b7280; 
        }
        
        .no-cases i { 
            font-size: 4em; 
            margin-bottom: 20px; 
            color: #d1d5db; 
        }
        
        .no-cases h3 { 
            margin-bottom: 10px; 
            color: #374151; 
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
            background-color: rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        .modal-content { 
            background-color: white; 
            margin: 1% auto; 
            padding: 0; 
            border-radius: 12px; 
            width: 95%; 
            max-width: 900px; 
            height: auto;
            max-height: 95vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 2px solid #5D0E26;
            animation: slideIn 0.4s ease-out;
            transform-origin: center;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        
        /* Professional Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 0;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .case-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .header-text h2 {
            margin: 0 0 0.15rem 0;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .header-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.75rem;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .close {
            color: white;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.2);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
            position: absolute;
            top: 10px;
            right: 15px;
        }
        
        .close:hover,
        .close:focus {
            color: #5D0E26;
            background: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }



        .modal-body {
            padding: 1rem 1.5rem;
            background: #ffffff;
            flex: 1;
            overflow: visible;
        }

        .case-overview {
            text-align: center;
            margin-bottom: 0.5rem;
            padding: 0.4rem;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .status-banner {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 0.3rem;
        }

        .status-banner.status-active {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-banner.status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #f57c00;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .status-banner.status-closed {
            background: rgba(158, 158, 158, 0.1);
            color: #616161;
            border: 1px solid rgba(158, 158, 158, 0.3);
        }

        .status-banner i {
            font-size: 0.6rem;
        }

        .case-title {
            margin: 0 0 0.2rem 0;
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
        }

        .case-description {
            margin: 0;
            color: #6b7280;
            font-size: 0.8rem;
            line-height: 1.2;
        }
        
        /* Case Tracking Modal Styles */
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
        
        /* Schedule Section */
        .schedule-section, .documents-section, .timeline-section {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .btn-add-schedule, .btn-upload-doc {
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
        
        .btn-add-schedule:hover, .btn-upload-doc:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .schedule-list, .documents-list {
            margin-top: 1rem;
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
        
        .schedule-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #5a6c7d;
            font-size: 0.875rem;
        }
        
        .document-icon {
            width: 2rem;
            height: 2rem;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }
        
        .document-meta {
            color: #5a6c7d;
            font-size: 0.75rem;
        }
        
        /* Timeline Styles */
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
        
        /* Modal Actions */
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
        
        /* Schedule Display Styles */
        .schedule-loading, .document-loading {
            text-align: center;
            padding: 2rem;
            color: #5a6c7d;
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
        
        .schedule-title, .schedule-description, .schedule-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            color: #5a6c7d;
        }
        
        .schedule-title i, .schedule-description i, .schedule-location i {
            color: #5D0E26;
            width: 1rem;
        }
        
        /* Document Display Styles */
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

        /* Case Details Grid */
        .case-details-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 0.6rem; 
            margin: 0.6rem 0; 
        }
        
        .detail-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 8px;
            padding: 0.6rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.08);
            transition: all 0.3s ease;
        }
        
        .detail-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(93, 14, 38, 0.12);
        }

        .detail-section h4 {
            margin: 0 0 0.4rem 0;
            font-size: 0.8rem;
            font-weight: 700;
            color: #5D0E26;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding-bottom: 0.3rem;
            border-bottom: 2px solid #5D0E26;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .detail-section h4 i {
            color: #5D0E26;
            font-size: 0.8rem;
        }
        
        .detail-item { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            border-bottom: 1px solid rgba(93, 14, 38, 0.1);
            transition: all 0.2s ease;
        }

        .detail-item:hover {
            background: rgba(93, 14, 38, 0.02);
            padding-left: 0.3rem;
            padding-right: 0.3rem;
            border-radius: 4px;
        }

        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label { 
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 600; 
            color: #5D0E26; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 0.3px; 
        }

        .detail-label i {
            color: #5D0E26;
            font-size: 0.7rem;
        }
        
        .detail-value { 
            color: #1f2937; 
            font-weight: 700; 
            font-size: 0.8rem;
            text-align: right;
            background: rgba(93, 14, 38, 0.05);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .modal-footer {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            padding: 0.6rem 1.5rem;
            border-radius: 0 0 12px 12px;
            border-top: 2px solid #5D0E26;
            margin-top: auto;
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 0.8rem;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }

        .btn-primary {
            background: white;
            color: #5D0E26;
            border: 2px solid white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 0.8rem;
        }

        .btn-primary:hover {
            background: #5D0E26;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .form-group { 
            margin-bottom: 25px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #5D0E26;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid #d6d9de; 
            border-radius: 10px; 
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafbfc;
            box-sizing: border-box;
            box-shadow: inset 0 1px 2px rgba(16,24,40,0.04);
        }
        
        .form-group select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
            appearance: none;
        }
        .form-group input:hover, .form-group select:hover, .form-group textarea:hover {
            border-color: #c3cad5;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            outline: none; 
            border-color: #8B1538; 
            box-shadow: 0 0 0 4px rgba(139, 21, 56, 0.12);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Form layout improvements */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row .form-group {
            margin-bottom: 20px;
        }
        

        
        /* Description takes full width */
        .form-group.description-group {
            grid-column: 1 / -1;
        }
        .form-actions { 
            display: flex; 
            gap: 12px; 
            justify-content: flex-end; 
            margin-top: 24px; 
            padding-top: 16px;
            border-top: 1px solid #edf0f3;
        }
        .btn-cancel, .btn-save { 
            padding: 12px 20px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.2px;
            transition: all 0.2s ease;
            min-width: 120px;
        }
        .btn-cancel { 
            background: #697586; 
            color: white; 
        }
        .btn-cancel:hover {
            background: #5c6778;
        }
        .btn-save { 
            background: #7C0F2F; 
            color: white; 
            box-shadow: 0 1px 2px rgba(16,24,40,0.06);
        }
        .btn-save:hover {
            background: #8B1538;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .cases-grid { 
                grid-template-columns: repeat(4, 1fr); 
            }
            .case-details-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            .modal-content {
                width: 95%;
                max-width: 850px;
                margin: 1% auto;
                max-height: 95vh;
            }
            .modal-body {
                padding: 0.8rem 1.2rem;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .cases-grid { 
                grid-template-columns: repeat(2, 1fr); 
            }
            
            .action-bar {
                padding: 15px;
                gap: 15px;
            }
            
            .filter-controls {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: unset;
                width: 100%;
            }
            
            .search-scope {
                min-width: unset;
                flex: 2;
            }
            
            .case-filters, .filters {
                justify-content: center;
                gap: 6px;
            }
            
            .filter-btn, .case-filter-btn {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
            
            .type-filters select {
                padding: 8px 12px;
                font-size: 0.85rem;
                padding-right: 35px;
            }
            
            .search-bar input {
                padding: 8px 35px 8px 10px;
                font-size: 0.85rem;
                min-width: 280px;
            }
            .case-details-grid {
                grid-template-columns: 1fr;
                gap: 0.4rem;
            }
            .modal-content {
                width: 98%;
                margin: 1% auto;
                max-height: 98vh;
            }
            .modal-body {
                padding: 0.6rem 1rem;
            }
            .modal-header {
                padding: 0.6rem 1rem;
            }
            .modal-footer {
                padding: 0.5rem 1rem;
            }
            .footer-actions {
                flex-direction: column;
                gap: 0.4rem;
            }
            .btn-secondary, .btn-primary {
                width: 100%;
                justify-content: center;
                padding: 0.4rem 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .filter-btn, .case-filter-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .filter-label {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 400px) {
            .cases-grid { 
                grid-template-columns: 1fr; 
            }
        }
    </style>

    <script>
        // Modal functionality
        function showAddCaseModal() {
            document.getElementById('addCaseModal').style.display = 'block';
        }

        function editCase(caseId, title, status, attorneyId) {
            document.getElementById('editCaseId').value = caseId;
            document.getElementById('editCaseStatus').value = status;
            document.getElementById('editCaseAttorney').value = attorneyId;
            
            // Store original values for comparison
            document.getElementById('editCaseStatus').dataset.originalStatus = status;
            document.getElementById('editCaseAttorney').dataset.originalAttorneyId = attorneyId;
            
            document.getElementById('editCaseModal').style.display = 'block';
        }

        function viewCase(caseId, title, clientName, attorneyName, caseType, status, description, dateFiled, nextHearing) {
            // Get attorney_id from the cases array
            const cases = <?= json_encode($cases) ?>;
            const fullCaseData = cases.find(c => c.id == caseId);
            const adminId = <?= $admin_id ?>;
            
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
                                <span class="info-value">${dateFiled}</span>
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
                                <span class="info-value">${attorneyName}</span>
                            </div>
                            ${nextHearing ? `
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar-check"></i> Next Hearing:</span>
                                <span class="info-value">${nextHearing}</span>
                            </div>
                            ` : ''}
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
                            ${fullCaseData.attorney_id == adminId ? `
                            <button class="btn-upload-doc" onclick="openDocumentUpload(${caseId})">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                            ` : ''}
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
                                    <div class="timeline-date">${dateFiled}</div>
                                    <div class="timeline-title">Case Filed</div>
                                    <div class="timeline-description">Case was filed and assigned to ${attorneyName}</div>
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
                        <button type="button" class="btn-secondary" onclick="editCase(${caseId}, '${title}', '${status}', ${fullCaseData ? fullCaseData.attorney_id : 'null'})">
                            <i class="fas fa-edit"></i> Edit Case
                        </button>
                        <button type="button" class="btn-cancel" onclick="closeViewModal()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            `;
            
            // Set modal content and show
            document.getElementById('viewCaseModal').querySelector('.modal-content').innerHTML = modalContent;
            document.getElementById('viewCaseModal').style.display = 'block';
            
            // Load schedule and documents data
            loadCaseSchedule(caseId);
            loadCaseDocuments(caseId);
        }
        
        function closeViewModal() {
            document.getElementById('viewCaseModal').style.display = 'none';
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
                    document.getElementById(`scheduleList${caseId}`).innerHTML = `
                        <div class="schedule-item">
                            <div class="schedule-date">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Error loading schedules</span>
                            </div>
                        </div>
                    `;
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
                    document.getElementById(`documentsList${caseId}`).innerHTML = `
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
                });
        }
        
        function openDocumentUpload(caseId) {
            // Check if admin is assigned to this case
            const cases = <?= json_encode($cases) ?>;
            const caseData = cases.find(c => c.id == caseId);
            const adminId = <?= $admin_id ?>;
            
            if (!caseData || caseData.attorney_id != adminId) {
                alert('You can only upload documents to cases assigned to you.');
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
                        const viewCaseModal = document.getElementById('viewCaseModal');
                        if (viewCaseModal && viewCaseModal.style.display === 'block') {
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
            // Only remove upload modals, preserve viewCaseModal
            const uploadModals = document.querySelectorAll('.modal');
            uploadModals.forEach(modal => {
                if (modal.id !== 'viewCaseModal' && modal.id !== 'addCaseModal' && modal.id !== 'editCaseModal') {
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
            
            // Close any upload modal that might still be open (but preserve viewCaseModal)
            const uploadModals = document.querySelectorAll('.modal:not(.success-modal)');
            uploadModals.forEach(modal => {
                if (modal.id !== 'viewCaseModal' && (modal.style.display === 'block' || modal.style.display === '')) {
                    modal.remove();
                }
            });
            
            // Also try to close by ID if it exists (but not viewCaseModal)
            const uploadModalById = document.getElementById('uploadModal');
            if (uploadModalById) {
                uploadModalById.remove();
            }
            
            // Execute the callback if it exists and the view case modal is still open
            if (window.successModalCallback) {
                const viewCaseModal = document.getElementById('viewCaseModal');
                if (viewCaseModal && viewCaseModal.style.display === 'block') {
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

        // Close modals
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = 'none';
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
                // Close button functionality for view case modal
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-cancel')) {
                e.target.closest('.modal').style.display = 'none';
            }
        });

        // Add case form submission
        document.getElementById('addCaseForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_case');
            
            fetch('admin_managecases.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(result => {
                if (result === 'success') {
                    alert('Case added successfully!');
                    location.reload();
                } else {
                    alert('Error adding case');
                }
            });
        };

        // Enhanced Edit case form submission with multiple security layers
        document.getElementById('editCaseForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const caseId = formData.get('case_id');
            const newStatus = formData.get('status');
            const newAttorneyId = formData.get('attorney_id');
            
            // Get current values for comparison
            const currentStatus = document.getElementById('editCaseStatus').dataset.originalStatus || '';
            const currentAttorneyId = document.getElementById('editCaseAttorney').dataset.originalAttorneyId || '';
            
            // Security Layer 1: Initial Warning
            const warning1 = `⚠️ ADMIN CASE MODIFICATION WARNING ⚠️\n\nYou are about to modify case #${caseId}.\n\nThis action will:\n• Change case status\n• Potentially reassign attorney\n• Update case records\n\nAre you sure you want to proceed?`;
            
            if (!confirm(warning1)) {
                return;
            }
            
            // Validate attorney selection
            if (!newAttorneyId || newAttorneyId === '') {
                alert('❌ Error: Attorney selection is required!\n\nPlease select an attorney before proceeding.');
                return;
            }
            
            // Security Layer 2: Detailed Confirmation
            const warning2 = `🔒 ADMIN PRIVILEGE CONFIRMATION 🔒\n\nYou are using ADMIN privileges to modify:\n\nCase ID: #${caseId}\nCurrent Status: ${currentStatus}\nNew Status: ${newStatus}\n\nAttorney Change: ${currentAttorneyId !== newAttorneyId ? 'YES' : 'NO'}\n\nType "CONFIRM" to proceed with changes:`;
            
            const userInput = prompt(warning2);
            if (userInput !== 'CONFIRM') {
                alert('❌ Operation cancelled. You must type "CONFIRM" to proceed.');
                return;
            }
            
            // Security Layer 3: Final Warning with Case Details
            const warning3 = `🚨 FINAL ADMIN WARNING 🚨\n\nYou are about to make PERMANENT changes to:\n\nCase #${caseId}\nStatus: ${currentStatus} → ${newStatus}\nAttorney: ${currentAttorneyId !== newAttorneyId ? 'CHANGED' : 'UNCHANGED'}\n\nThese changes will be logged in the audit trail.\n\nType "ADMIN CONFIRM" to finalize:`;
            
            const finalInput = prompt(warning3);
            if (finalInput !== 'ADMIN CONFIRM') {
                alert('❌ Operation cancelled. You must type "ADMIN CONFIRM" to proceed.');
                return;
            }
            
            // Proceed with the update
            formData.append('action', 'edit_case');
            
            try {
                const response = await fetch('admin_managecases.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                
                if (result === 'success') {
                    alert('✅ Case updated successfully!\n\nChanges have been logged in the audit trail.');
                    document.getElementById('editCaseModal').style.display = 'none';
                    location.reload();
                } else {
                    alert('❌ Error updating case: ' + result);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error updating case');
            }
        };

        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing filters...');
            
            // Test if elements exist
            const typeFilter = document.getElementById('typeFilter');
            const searchInput = document.getElementById('searchInput');
            const statusButtons = document.querySelectorAll('.filter-btn');
            
            console.log('Type filter exists:', !!typeFilter);
            console.log('Search input exists:', !!searchInput);
            console.log('Status buttons count:', statusButtons.length);
            
            // Case filter buttons (All Cases / My Cases)
            const caseFilterButtons = document.querySelectorAll('.case-filter-btn');
            caseFilterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    console.log('Case filter button clicked:', this.textContent);
                    // Remove active class from all case filter buttons
                    document.querySelectorAll('.case-filter-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    filterCases();
                });
            });
            
            // Status filter buttons
            statusButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    console.log('Status button clicked:', this.textContent);
                    // Remove active class from all status buttons
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    filterCases();
                });
            });
            
            // Type filter dropdown
            if (typeFilter) {
                typeFilter.addEventListener('change', function() {
                    console.log('Type filter changed to:', this.value);
                    filterCases();
                });
                
                // Test dropdown immediately
                console.log('Current dropdown value:', typeFilter.value);
            } else {
                console.error('Type filter element not found!');
            }
            
            // Search input
            if (searchInput) {
                searchInput.addEventListener('input', filterCases);
            }
            
            // Pagination control
            const itemsPerPageSelect = document.getElementById('itemsPerPage');
            if (itemsPerPageSelect) {
                itemsPerPageSelect.addEventListener('change', applyPagination);
                
                // Add hover effects
                itemsPerPageSelect.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#8B1538';
                    this.style.boxShadow = '0 2px 8px rgba(93, 14, 38, 0.2)';
                });
                
                itemsPerPageSelect.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#5D0E26';
                    this.style.boxShadow = 'none';
                });
            }
            
            // Initial filter call
            setTimeout(() => {
                console.log('Running initial filter...');
                filterCases();
            }, 100);
        });

        function filterCases() {
            console.log('Filtering cases...');
            
            const activeStatusBtn = document.querySelector('.filter-btn.active');
            const activeCaseFilterBtn = document.querySelector('.case-filter-btn.active');
            const statusFilter = activeStatusBtn ? activeStatusBtn.getAttribute('data-status') : '';
            const caseFilter = activeCaseFilterBtn ? activeCaseFilterBtn.getAttribute('data-filter') : 'all';
            const typeFilter = document.getElementById('typeFilter') ? document.getElementById('typeFilter').value : '';
            const searchTerm = document.getElementById('searchInput') ? document.getElementById('searchInput').value.toLowerCase() : '';
            const rows = document.querySelectorAll('#casesGrid .case-card');

            console.log('Status filter:', statusFilter);
            console.log('Case filter:', caseFilter);
            console.log('Type filter:', typeFilter);
            console.log('Search term:', searchTerm);
            console.log('Total rows:', rows.length);

            // Debug: Log all case types found
            const caseTypes = new Set();
            rows.forEach(row => {
                const type = row.getAttribute('data-type');
                if (type) caseTypes.add(type);
            });
            console.log('Found case types:', Array.from(caseTypes));

            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const type = row.getAttribute('data-type');
                const attorneyId = row.getAttribute('data-attorney-id');
                const text = row.textContent.toLowerCase();
                
                // Case-insensitive matching for type filter
                const typeMatch = !typeFilter || 
                    (type && type.toLowerCase() === typeFilter.toLowerCase()) ||
                    (type && type.toLowerCase().includes(typeFilter.toLowerCase()));
                
                const statusMatch = !statusFilter || status === statusFilter;
                const searchMatch = !searchTerm || text.includes(searchTerm);
                
                // My Cases filter - only show cases assigned to current admin
                const caseFilterMatch = caseFilter === 'all' || 
                    (caseFilter === 'my' && attorneyId === '<?= $admin_id ?>');
                
                const shouldShow = statusMatch && typeMatch && searchMatch && caseFilterMatch;
                
                // Set a data attribute to track filter visibility
                if (shouldShow) {
                    row.setAttribute('data-visible', 'true');
                    row.classList.remove('filtered-out');
                } else {
                    row.setAttribute('data-visible', 'false');
                    row.classList.add('filtered-out');
                }
            });
            
            // Apply pagination after filtering
            applyPagination();
        }
        
        // Pagination functionality
        function applyPagination() {
            const itemsPerPageSelect = document.getElementById('itemsPerPage');
            const allCards = document.querySelectorAll('#casesGrid .case-card');
            const itemsInfo = document.querySelector('.items-info');
            
            if (!itemsPerPageSelect) return;
            
            const value = itemsPerPageSelect.value;
            
            // First, apply filter visibility from data attribute
            const visibleCards = [];
            allCards.forEach(card => {
                const isVisible = card.getAttribute('data-visible') === 'true';
                if (isVisible) {
                    visibleCards.push(card);
                }
            });
            
            const totalVisible = visibleCards.length;
            let showCount = totalVisible;
            
            if (value !== 'all') {
                showCount = parseInt(value);
            }
            
            // Hide all cards first, then show only the visible ones within pagination limit
            allCards.forEach(card => card.style.display = 'none');
            
            // Show cards based on filter and pagination
            visibleCards.forEach((card, index) => {
                if (value === 'all') {
                    card.style.display = '';
                } else {
                    card.style.display = index < showCount ? '' : 'none';
                }
            });
            
            // Update info text
            const displayCount = value === 'all' ? totalVisible : Math.min(showCount, totalVisible);
            itemsInfo.textContent = `Showing ${displayCount} of ${totalVisible} cases`;
        }
    </script>
    <script>
        // Set global variables for the enhanced modal
        window.userRole = 'admin';
        window.userId = <?= $admin_id ?>;
        
        // Debug: Check if global variables are set
        console.log('Global variables set:', {
            userRole: window.userRole,
            userId: window.userId,
            adminId: <?= $admin_id ?>
        });
        
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html> 
