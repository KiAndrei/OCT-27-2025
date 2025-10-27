<?php
require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

// Fetch users by type
$admins = [];
$attorneys = [];
$employees = [];
$clients = [];

$res = $conn->query("SELECT id, name, email, phone_number, user_type, last_login, account_locked FROM user_form ORDER BY user_type, name");
while ($row = $res->fetch_assoc()) {
    if ($row['user_type'] === 'admin') $admins[] = $row;
    elseif ($row['user_type'] === 'attorney') $attorneys[] = $row;
    elseif ($row['user_type'] === 'employee') $employees[] = $row;
    elseif ($row['user_type'] === 'client') $clients[] = $row;
}

if (isset($_POST['delete_user_btn']) && isset($_POST['delete_user_id'])) {
    $delete_id = intval($_POST['delete_user_id']);
    // Prevent admin from deleting themselves
    if ($delete_id !== $_SESSION['user_id']) {
        // Get user details before deletion for audit logging
        $userStmt = $conn->prepare("SELECT name, email, user_type FROM user_form WHERE id = ?");
        $userStmt->bind_param('i', $delete_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM user_form WHERE id = ?");
        $stmt->bind_param('i', $delete_id);
        $stmt->execute();
        
        // Log user deletion to audit trail
        if ($userData) {
            global $auditLogger;
            $auditLogger->logAction(
                $_SESSION['user_id'],
                $_SESSION['admin_name'] ?? 'Admin',
                'admin',
                'User Delete',
                'User Management',
                "Deleted user: {$userData['name']} ({$userData['email']}) - Type: {$userData['user_type']}",
                'success',
                'high' // HIGH priority for user deletions
            );
        }
    }
    // Refresh to update the list
    header('Location: admin_usermanagement.php');
    exit();
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <style>
        /* Enhanced User Management Styles */
        .user-section {
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            padding: 28px 24px;
            border: 1px solid rgba(255,255,255,0.8);
            position: relative;
            overflow: hidden;
        }
        
        .user-section::before {
            display: none;
        }
        
        .user-section h2 {
            margin-bottom: 20px;
            color: #1a202c;
            font-size: 1.5em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-section h2::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, #8B0000, #A52A2A);
            border-radius: 2px;
        }
        
        .user-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 16px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
            table-layout: fixed;
            border: 1px solid #e5e7eb;
        }
        
        .user-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-weight: 700;
            color: #374151;
            padding: 16px 12px;
            text-align: left;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }
        
        .user-table td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        /* Specific column alignments */
        .user-table td:nth-child(1) { text-align: center; font-weight: 600; } /* ID - centered */
        .user-table td:nth-child(5) { font-size: 0.9em; color: #6b7280; } /* Last Login - smaller text */
        .user-table td:nth-child(6) { text-align: center; } /* Status - centered */
        .user-table td:nth-child(7) { text-align: center; } /* Actions - centered */
        
        /* Column width specifications for consistent alignment */
        .user-table th:nth-child(1), .user-table td:nth-child(1) { width: 8%; }  /* ID */
        .user-table th:nth-child(2), .user-table td:nth-child(2) { width: 20%; } /* Name */
        .user-table th:nth-child(3), .user-table td:nth-child(3) { width: 25%; } /* Email */
        .user-table th:nth-child(4), .user-table td:nth-child(4) { width: 15%; } /* Phone */
        .user-table th:nth-child(5), .user-table td:nth-child(5) { width: 18%; } /* Last Login */
        .user-table th:nth-child(6), .user-table td:nth-child(6) { width: 10%; } /* Status */
        .user-table th:nth-child(7), .user-table td:nth-child(7) { width: 14%; } /* Actions */
        
        /* Text truncation for long content */
        .user-table td:nth-child(2), .user-table td:nth-child(3) {
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .user-table td:nth-child(2):hover, .user-table td:nth-child(3):hover {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            position: relative;
            z-index: 10;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 4px;
            padding: 8px;
        }
        
        .user-table tr:hover {
            background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
            transform: translateY(-1px);
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(139, 0, 0, 0.1);
            border-left: 3px solid #8B0000;
        }
        
        /* Alternating row colors for better readability */
        .user-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        .user-table tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
        }
        
        .user-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        
        .status-active {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #dc2626;
            border: 1px solid #fca5a5;
            min-width: 80px;
            justify-content: center;
        }
        
        .btn-delete:hover {
            background: linear-gradient(135deg, #fee2e2 0%, #fca5a5 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
            color: #8B0000;
            border: 1px solid #fca5a5;
            margin-right: 8px;
        }
        
        .btn-edit:hover {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.2);
            color: #A52A2A;
        }
        
        /* Add User Button Enhancement - Maroon Theme */
        .add-user-btn {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 50%, #5D0E26 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(93, 14, 38, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .add-user-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .add-user-btn:hover::before {
            left: 100%;
        }
        
        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(93, 14, 38, 0.6);
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 50%, #8B1538 100%);
        }
        
        /* User Count Badges */
        .user-count-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .badge-admin { background: linear-gradient(135deg, #8B0000, #A52A2A); color: white; }
        .badge-attorney { background: linear-gradient(135deg, #8B0000, #A52A2A); color: white; }
        .badge-employee { background: linear-gradient(135deg, #8B0000, #A52A2A); color: white; }
        .badge-client { background: linear-gradient(135deg, #8B0000, #A52A2A); color: white; }
        
        /* Success/Error Messages Enhancement */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .message-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border-color: #86efac;
        }
        
        .message-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #dc2626;
            border-color: #fca5a5;
        }
        
        @media (max-width: 768px) {
            .user-section { padding: 20px 16px; }
            .user-table th, .user-table td { padding: 12px 16px; }
            .add-user-btn { padding: 12px 24px; font-size: 14px; }
            
            /* Mobile responsive adjustments */
            .user-table {
                font-size: 12px;
                table-layout: auto;
            }
            
            .user-table th, .user-table td {
                padding: 8px 12px;
            }
            
            /* Adjust column widths for mobile */
            .user-table th:nth-child(1), .user-table td:nth-child(1) { width: auto; }
            .user-table th:nth-child(2), .user-table td:nth-child(2) { width: auto; }
            .user-table th:nth-child(3), .user-table td:nth-child(3) { width: auto; }
            .user-table th:nth-child(4), .user-table td:nth-child(4) { width: auto; }
            .user-table th:nth-child(5), .user-table td:nth-child(5) { width: auto; }
            .user-table th:nth-child(6), .user-table td:nth-child(6) { width: auto; }
            .user-table th:nth-child(7), .user-table td:nth-child(7) { width: auto; }
            
            /* Stack search and filter on mobile */
            .search-filter-container {
                flex-direction: column;
                gap: 12px;
            }
            
            .search-filter-container > div {
                width: 100%;
            }
        }
        
        /* Enhanced table responsiveness */
        .user-table {
            min-width: 800px;
        }
        
        /* Pagination button styles */
        .pagination-btn {
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: #f3f4f6 !important;
            transform: translateY(-1px);
        }
        
        .pagination-btn:disabled {
            cursor: not-allowed;
        }
        
        /* Modal Input Focus Effects */
        #addUserModal input:focus,
        #addUserModal select:focus {
            outline: none;
            border-color: #8B0000;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }
        
        /* Modal Button Hover Effects */
        #addUserModal button:hover {
            transform: translateY(-1px);
        }
        
        #addUserModal button[type="submit"]:hover {
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.4);
        }
        
        /* Professional Alert Modal Styles */
        .alert-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease;
        }
        
        .alert-modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            margin: auto !important;
            padding: 0 !important;
            border-radius: 16px !important;
            width: 85% !important;
            max-width: 400px !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 8px 25px rgba(0,0,0,0.2) !important;
            animation: slideIn 0.4s ease !important;
            overflow: hidden !important;
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 2001 !important;
        }
        
        .alert-modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #7C0F2F 0%, #8B1538 50%, #7C0F2F 100%);
        }
        
        .alert-modal-header {
            padding: 25px 30px 15px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            position: relative;
        }
        
        .alert-modal-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(16,185,129,0.3);
            animation: bounceIn 0.6s ease;
        }
        
        .alert-modal-icon i {
            font-size: 28px;
            color: white;
        }
        
        .alert-modal-title {
            margin: 0 0 8px 0;
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .alert-modal-message {
            margin: 0;
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
            line-height: 1.4;
        }
        
        .alert-modal-footer {
            padding: 15px 30px 25px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .alert-modal-btn {
            background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(124,15,47,0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .alert-modal-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .alert-modal-btn:hover {
            background: linear-gradient(135deg, #8B1538 0%, #7C0F2F 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(124,15,47,0.4);
        }
        
        .alert-modal-btn:hover::before {
            left: 100%;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
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
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="admin_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="admin_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generations</span></a></li>
            <li><a href="admin_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="admin_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="admin_usermanagement.php" class="active"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
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
        $page_title = 'User Management';
        $page_subtitle = 'Total Users: ' . (count($admins) + count($attorneys) + count($employees) + count($clients)) . ' (' . count($admins) . ' Admin, ' . count($attorneys) . ' Attorney, ' . count($employees) . ' Employee, ' . count($clients) . ' Client)';
        include 'components/profile_header.php'; 
        ?>
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Quick Stats Section -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 4px 16px rgba(93, 14, 38, 0.4);">
                <i class="fas fa-users" style="font-size: 24px; margin-bottom: 8px;"></i>
                <h3 style="margin: 0; font-size: 1.5em; font-weight: 700;"><?= count($admins) + count($attorneys) + count($employees) + count($clients) ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9em;">Total Users</p>
            </div>
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 4px 16px rgba(93, 14, 38, 0.4);">
                <i class="fas fa-user-check" style="font-size: 24px; margin-bottom: 8px;"></i>
                <h3 style="margin: 0; font-size: 1.5em; font-weight: 700;"><?= count($attorneys) ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9em;">Attorneys</p>
            </div>
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 4px 16px rgba(93, 14, 38, 0.4);">
                <i class="fas fa-user-tie" style="font-size: 24px; margin-bottom: 8px;"></i>
                <h3 style="margin: 0; font-size: 1.5em; font-weight: 700;"><?= count($employees) ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9em;">Employees</p>
            </div>
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 4px 16px rgba(93, 14, 38, 0.4);">
                <i class="fas fa-user" style="font-size: 24px; margin-bottom: 8px;"></i>
                <h3 style="margin: 0; font-size: 1.5em; font-weight: 700;"><?= count($clients) ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9em;">Clients</p>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div style="background: linear-gradient(135deg, #fff 0%, #f8fafc 100%); border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); margin-bottom: 24px; padding: 24px; border: 1px solid rgba(255,255,255,0.8);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1; min-width: 300px;">
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 16px;"></i>
                        <input type="text" id="searchInput" placeholder="Search users by name, email, or phone..." style="width: 100%; padding: 14px 16px 14px 48px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; background: white; transition: border-color 0.2s ease;" onkeyup="filterUsers()">
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <select id="statusFilter" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; background: white; cursor: pointer;" onchange="filterUsers()">
                        <option value="">All Status</option>
                        <option value="Active">Active Only</option>
                        <option value="Inactive">Inactive Only</option>
                    </select>
                    <button onclick="openAddUserModal()" class="add-user-btn">
                        <i class="fas fa-plus"></i> Add New User
                    </button>
                </div>
            </div>
        </div>

        <div class="user-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Admin Users <span class="user-count-badge badge-admin"><?= count($admins) ?></span></h2>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="color: #64748b; font-size: 14px;">Show:</span>
                    <select id="adminPageSize" style="padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px;" onchange="changePageSize('admin')">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="user-table" id="adminTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($admins) === 0): ?>
                        <tr><td colspan="7">No admin users found.</td></tr>
                    <?php else: foreach ($admins as $user): ?>
                        <tr data-id="<?= $user['id'] ?>">
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone_number']) ?></td>
                            <td><?= $user['last_login'] ? htmlspecialchars($user['last_login']) : 'Never' ?></td>
                            <td>
                                <span class="status-badge <?= $user['account_locked'] ? 'status-inactive' : 'status-active' ?>">
                                    <?= $user['account_locked'] ? 'Inactive' : 'Active' ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn btn-view" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: #2563eb; border: 1px solid #93c5fd; min-width: 80px; justify-content: center;" onclick="viewAdminDetails(<?= $user['id'] ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div id="adminPagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px;">
                <button onclick="changePage('admin', 'prev')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Previous</button>
                <span id="adminPageInfo" style="color: #64748b; font-size: 14px;">Page 1</span>
                <button onclick="changePage('admin', 'next')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Next</button>
            </div>
        </div>
        <div class="user-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Attorney Users <span class="user-count-badge badge-attorney"><?= count($attorneys) ?></span></h2>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="color: #64748b; font-size: 14px;">Show:</span>
                    <select id="attorneyPageSize" style="padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px;" onchange="changePageSize('attorney')">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="user-table" id="attorneyTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($attorneys) === 0): ?>
                        <tr><td colspan="7">No attorney users found.</td></tr>
                    <?php else: foreach ($attorneys as $user): ?>
                        <tr data-id="<?= $user['id'] ?>">
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone_number']) ?></td>
                            <td><?= $user['last_login'] ? htmlspecialchars($user['last_login']) : 'Never' ?></td>
                            <td>
                                <span class="status-badge <?= $user['account_locked'] ? 'status-inactive' : 'status-active' ?>">
                                <?= $user['account_locked'] ? 'Inactive' : 'Active' ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" onclick="confirmDeleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', 'Admin')" class="action-btn btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div id="attorneyPagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px;">
                <button onclick="changePage('attorney', 'prev')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Previous</button>
                <span id="attorneyPageInfo" style="color: #64748b; font-size: 14px;">Page 1</span>
                <button onclick="changePage('attorney', 'next')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Next</button>
            </div>
        </div>
        <div class="user-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Employee Users <span class="user-count-badge badge-employee"><?= count($employees) ?></span></h2>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="color: #64748b; font-size: 14px;">Show:</span>
                    <select id="employeePageSize" style="padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px;" onchange="changePageSize('employee')">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="user-table" id="employeeTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) === 0): ?>
                        <tr><td colspan="7">No employee users found.</td></tr>
                    <?php else: foreach ($employees as $user): ?>
                        <tr data-id="<?= $user['id'] ?>">
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone_number']) ?></td>
                            <td><?= $user['last_login'] ? htmlspecialchars($user['last_login']) : 'Never' ?></td>
                            <td>
                                <span class="status-badge <?= $user['account_locked'] ? 'status-inactive' : 'status-active' ?>">
                                <?= $user['account_locked'] ? 'Inactive' : 'Active' ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" onclick="confirmDeleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', 'Employee')" class="action-btn btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div id="employeePagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px;">
                <button onclick="changePage('employee', 'prev')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Previous</button>
                <span id="employeePageInfo" style="color: #64748b; font-size: 14px;">Page 1</span>
                <button onclick="changePage('employee', 'next')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Next</button>
            </div>
        </div>
        <div class="user-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Client Users <span class="user-count-badge badge-client"><?= count($clients) ?></span></h2>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="color: #64748b; font-size: 14px;">Show:</span>
                    <select id="clientPageSize" style="padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px;" onchange="changePageSize('client')">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="user-table" id="clientTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients) === 0): ?>
                        <tr><td colspan="7">No client users found.</td></tr>
                    <?php else: foreach ($clients as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone_number']) ?></td>
                            <td><?= $user['last_login'] ? htmlspecialchars($user['last_login']) : 'Never' ?></td>
                            <td>
                                <span class="status-badge <?= $user['account_locked'] ? 'status-inactive' : 'status-active' ?>">
                                <?= $user['account_locked'] ? 'Inactive' : 'Active' ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" onclick="confirmDeleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', 'Client')" class="action-btn btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div id="clientPagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px;">
                <button onclick="changePage('client', 'prev')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Previous</button>
                <span id="clientPageInfo" style="color: #64748b; font-size: 14px;">Page 1</span>
                <button onclick="changePage('client', 'next')" class="pagination-btn" style="padding: 8px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-size: 12px;">Next</button>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
        <div style="background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%); border-radius: 24px; width: 90%; max-width: 800px; height: 85vh; position: relative; box-shadow: 0 25px 80px rgba(93, 14, 38, 0.3), 0 0 0 1px rgba(255,255,255,0.1); border: 1px solid rgba(93, 14, 38, 0.1); overflow: hidden; display: flex; flex-direction: column;">
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); padding: 20px 32px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user-plus" style="color: white; font-size: 16px;"></i>
                        </div>
                        <div>
                            <h2 style="color: white; margin: 0; font-size: 1.4em; font-weight: 700;">Add New User</h2>
                            <p style="color: rgba(255,255,255,0.9); margin: 2px 0 0 0; font-size: 0.85em;">Create employee or attorney accounts</p>
                        </div>
                    </div>
                    <button onclick="closeAddUserModal()" style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border: none; border-radius: 8px; color: white; font-size: 16px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div style="flex: 1; padding: 24px; display: flex; flex-direction: column; overflow-y: auto;">
            
            <form id="addUserForm" style="display: flex; flex-direction: column;">
                <!-- Error Message Display -->
                <div id="errorMessage" style="display: none; background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #dc2626; border: 1px solid #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: 500; font-size: 0.9em;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i>
                    <span id="errorText"></span>
                </div>
                
                <!-- Name Fields Container - Horizontal Layout -->
                <div style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Surname</label>
                            <input type="text" name="surname" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter surname" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">First Name</label>
                            <input type="text" name="first_name" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter first name" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Middle Name</label>
                            <input type="text" name="middle_name" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter middle name" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                    </div>
                </div>
                
                <!-- Email Row - 3 Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Email Address</label>
                        <input type="email" name="email" id="email" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter email address" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'; checkEmailExists();" oninput="this.value = this.value.replace(/\s/g, ''); clearEmailValidation();">
                        <div id="emailValidation" style="margin-top: 5px; font-size: 0.8rem;"></div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Email</label>
                        <input type="email" name="confirm_email" id="confirm_email" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm email address" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, '')">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Phone Number</label>
                        <input type="text" name="phone_number" id="phoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter phone number (09xxxxxxxxx)" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); validatePhoneNumber();" onkeypress="return event.charCode >= 48 && event.charCode <= 57" onfocus="this.style.borderColor='#8B0000'" onblur="validatePhoneNumber();">
                    </div>
                </div>
                
                <!-- Password Row - 3 Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Phone</label>
                        <input type="text" name="confirm_phone_number" id="confirmPhoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm phone number" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" onkeypress="return event.charCode >= 48 && event.charCode <= 57" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Password</label>
                        <div style="position: relative;">
                            <input type="password" name="password" id="password" required style="width: 100%; padding: 10px 35px 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter password" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, ''); checkPasswordStrength()">
                            <i class="fas fa-eye" id="togglePassword" onclick="togglePasswordVisibility('password', 'togglePassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; cursor: pointer; font-size: 12px; padding: 4px;" onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6b7280'"></i>
                        </div>
                        
                        <!-- Password Strength Indicator -->
                        <div id="passwordStrengthIndicator" style="display: none; margin-top: 8px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <!-- Password Strength Bar -->
                            <div style="margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <span style="font-size: 11px; font-weight: 600; color: #64748b;">Password Strength:</span>
                                    <span id="strengthText" style="font-size: 11px; font-weight: 600; color: #64748b;">Weak</span>
                                </div>
                                <div style="width: 100%; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                                    <div id="strengthBar" style="width: 0%; height: 100%; background: #ef4444; transition: all 0.3s ease; border-radius: 2px;"></div>
                                </div>
                            </div>
                            
                            <!-- Password Requirements Checklist -->
                            <div style="font-size: 11px;">
                                <div style="margin-bottom: 4px;">
                                    <span id="lengthCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">At least 8 characters</span>
                                </div>
                                <div style="margin-bottom: 4px;">
                                    <span id="uppercaseCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">One uppercase letter</span>
                                </div>
                                <div style="margin-bottom: 4px;">
                                    <span id="lowercaseCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">One lowercase letter</span>
                                </div>
                                <div style="margin-bottom: 4px;">
                                    <span id="numberCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">One number</span>
                                </div>
                                <div>
                                    <span id="specialCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">Special char (!@#$%^&*()-_+={}[]:";'<>.,?/\|~)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Password</label>
                        <div style="position: relative;">
                            <input type="password" name="confirm_password" id="confirm_password" required style="width: 100%; padding: 10px 35px 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm password" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, '')">
                            <i class="fas fa-eye" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; cursor: pointer; font-size: 12px; padding: 4px;" onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6b7280'"></i>
                        </div>
                    </div>
                </div>
                
                <!-- User Type Row - 3 Column Layout (User Type takes 1 column) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">User Type</label>
                        <select name="user_type" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white; cursor: pointer;" onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e5e7eb'">
                            <option value="">Select User Type</option>
                            <option value="employee">👨‍💼 Employee</option>
                            <option value="attorney">⚖️ Attorney</option>
                        </select>
                    </div>
                    
                    <!-- Empty columns for alignment -->
                    <div></div>
                    <div></div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: auto; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeAddUserModal()" style="padding: 10px 20px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; cursor: pointer; font-weight: 600; color: #374151; transition: all 0.3s ease; font-size: 13px;" onmouseover="this.style.borderColor='#d1d5db'; this.style.background='#f9fafb'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">Cancel</button>
                    <button type="submit" style="padding: 10px 20px; background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 13px;" onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'" onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'">Add User</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); overflow-y: auto;">
        <div style="background: linear-gradient(135deg, #fff 0%, #f8fafc 100%); margin: 2% auto; padding: 30px; border-radius: 20px; width: 95%; max-width: 600px; max-height: 90vh; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.8); overflow-y: auto;">
            <span onclick="closeEditUserModal()" style="position: absolute; right: 25px; top: 20px; font-size: 32px; font-weight: bold; cursor: pointer; color: #666; transition: color 0.2s ease;">&times;</span>
            
                <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #8B0000, #A52A2A); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-user-edit" style="color: white; font-size: 24px;"></i>
                </div>
                <h2 style="color: #1a202c; margin-bottom: 8px; font-size: 1.8em; font-weight: 700;">Edit User</h2>
                <p style="color: #64748b; font-size: 0.95em;">Modify user details</p>
            </div>
            
            <form method="POST" action="edit_user.php" onsubmit="return validateEditForm()">
                <input type="hidden" name="user_id" id="editUserId">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 0.9em;">Full Name:</label>
                        <input type="text" name="name" id="editName" required style="width: 100%; padding: 14px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; transition: border-color 0.2s ease; background: white;" placeholder="Enter full name">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 0.9em;">Email:</label>
                        <input type="email" name="email" id="editEmail" required style="width: 100%; padding: 14px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; transition: border-color 0.2s ease; background: white;" placeholder="Enter email address">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 0.9em;">Phone Number:</label>
                        <input type="text" name="phone_number" id="editPhoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 14px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; transition: border-color 0.2s ease; background: white;" placeholder="Enter 11-digit phone number" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 0.9em;">User Type:</label>
                        <select name="user_type" id="editUserType" required style="width: 100%; padding: 14px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; transition: border-color 0.2s ease; background: white;">
                            <option value="">Select User Type</option>
                            <option value="employee">👨‍💼 Employee</option>
                            <option value="attorney">⚖️ Attorney</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditUserModal()" style="padding: 14px 28px; border: 2px solid #e5e7eb; background: white; border-radius: 10px; cursor: pointer; font-weight: 600; color: #374151; transition: all 0.2s ease;">Cancel</button>
                    <button type="submit" style="padding: 14px 28px; background: linear-gradient(135deg, #8B0000, #A52A2A); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s ease; box-shadow: 0 4px 16px rgba(139, 0, 0, 0.3);">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            hideError(); // Clear any error messages
            
            // Hide password strength indicator
            const indicator = document.getElementById('passwordStrengthIndicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
            
            // Clear form fields
            const form = document.getElementById('addUserForm');
            if (form) {
                form.reset();
            }
        }

        // Professional Success Modal Function
        function showSuccessModal(title, message) {
            const modal = document.createElement('div');
            modal.className = 'alert-modal';
            modal.id = 'successModal';
            
            modal.innerHTML = `
                <div class="alert-modal-content">
                    <div class="alert-modal-header">
                        <div class="alert-modal-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3 class="alert-modal-title">${title}</h3>
                        <p class="alert-modal-message">${message}</p>
                    </div>
                    <div class="alert-modal-footer">
                        <button class="alert-modal-btn" onclick="closeSuccessModal()">
                            <i class="fas fa-check" style="margin-right: 8px;"></i>
                            Continue
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
        
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.remove();
                // Reload the page to show the new user
                window.location.reload();
            }
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('successModal');
            if (modal && event.target === modal) {
                closeSuccessModal();
            }
        });
        
        function validatePhoneNumber() {
            const phoneNumber = document.getElementById('phoneNumber').value;
            const phoneInput = document.getElementById('phoneNumber');
            
            if (phoneNumber.length > 0 && phoneNumber.length < 11) {
                if (!phoneNumber.startsWith('09')) {
                    phoneInput.style.borderColor = '#dc2626';
                    phoneInput.style.backgroundColor = '#fef2f2';
                } else {
                    phoneInput.style.borderColor = '#10b981';
                    phoneInput.style.backgroundColor = '#f0fdf4';
                }
            } else if (phoneNumber.length === 11) {
                if (phoneNumber.startsWith('09')) {
                    phoneInput.style.borderColor = '#10b981';
                    phoneInput.style.backgroundColor = '#f0fdf4';
                } else {
                    phoneInput.style.borderColor = '#dc2626';
                    phoneInput.style.backgroundColor = '#fef2f2';
                }
            } else {
                phoneInput.style.borderColor = '#e5e7eb';
                phoneInput.style.backgroundColor = 'white';
            }
        }
        
        function checkEmailExists() {
            const email = document.getElementById('email').value;
            const validationDiv = document.getElementById('emailValidation');
            
            if (!email || !email.includes('@')) {
                validationDiv.innerHTML = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('email', email);
            
            fetch('check_email_exists.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    validationDiv.innerHTML = `<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Email already exists</span>`;
                    document.getElementById('email').style.borderColor = '#ef4444';
                } else {
                    validationDiv.innerHTML = `<span style="color: #10b981;"><i class="fas fa-check-circle"></i> Email is available</span>`;
                    document.getElementById('email').style.borderColor = '#10b981';
                }
            })
            .catch(error => {
                console.error('Error checking email:', error);
            });
        }
        
        function clearEmailValidation() {
            const validationDiv = document.getElementById('emailValidation');
            validationDiv.innerHTML = '';
            document.getElementById('email').style.borderColor = '#e5e7eb';
        }
        
        function validateForm() {
            const name = document.querySelector('input[name="name"]').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phoneNumber = document.getElementById('phoneNumber').value;
            
            // Validate phone number - must start with 09 and be 11 digits
            if (phoneNumber && (!phoneNumber.startsWith('09') || phoneNumber.length !== 11)) {
                showError('Phone number must start with 09 and be exactly 11 digits (e.g., 09123456789)');
                return false;
            }
            const confirmPhoneNumber = document.getElementById('confirmPhoneNumber').value;
            const email = document.getElementById('email').value;
            const confirmEmail = document.getElementById('confirm_email').value;
            
            // Clear previous error
            hideError();
            
            // Check if email already exists
            const emailValidationDiv = document.getElementById('emailValidation');
            if (emailValidationDiv.innerHTML.includes('Email already exists')) {
                showError('Email already exists. Please use a different email address.');
                return false;
            }
            
            // Name validation - allow spaces for full names
            
            if (email.includes(' ')) {
                showError('Email cannot contain spaces!');
                return false;
            }
            
            if (confirmEmail.includes(' ')) {
                showError('Confirm email cannot contain spaces!');
                return false;
            }
            
            if (password.includes(' ')) {
                showError('Password cannot contain spaces!');
                return false;
            }
            
            if (confirmPassword.includes(' ')) {
                showError('Confirm password cannot contain spaces!');
                return false;
            }
            
            // Email validation
            if (email !== confirmEmail) {
                showError('Email addresses do not match!');
                return false;
            }
            
            // Phone validation
            if (phoneNumber !== confirmPhoneNumber) {
                showError('Phone numbers do not match!');
                return false;
            }
            
            // Password validation
            if (password !== confirmPassword) {
                showError('Passwords do not match!');
                return false;
            }
            
            // Enhanced password validation to match server-side requirements
            if (password.length < 8) {
                showError('Password must be at least 8 characters long!');
                return false;
            }
            
            // Check for uppercase, lowercase, number, and special character
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChar = /[!@#$%^&*()\-_+={}[\]:";'<>.,?/\\|~]/.test(password);
            
            if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
                showError('Password must include:\n• Uppercase and lowercase letters\n• At least one number\n• At least one special character (!@#$%^&*()...etc)');
                return false;
            }
            
            // Phone number validation
            if (phoneNumber.length !== 11) {
                showError('Phone number must be exactly 11 digits!');
                return false;
            }
            
            if (!/^[0-9]{11}$/.test(phoneNumber)) {
                showError('Phone number must contain only digits!');
                return false;
            }
            
            return true;
        }
        
        // Handle form submission with AJAX
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form first
            if (!validateForm()) {
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Adding User...';
            submitBtn.disabled = true;
            
            // Get form data
            const formData = new FormData(this);
            
            // Submit via AJAX
            fetch('add_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Check if response contains success indicators
                if (data.includes('successfully') || data.includes('created') || data.includes('registered')) {
                    // Extract user type from form data to show specific message
                    const formData = new FormData(document.getElementById('addUserForm'));
                    const userType = formData.get('user_type');
                    
                    let successTitle = 'User Successfully Registered!';
                    let successMessage = 'The user has been successfully registered and added to the system.';
                    
                    if (userType === 'employee') {
                        successTitle = 'Employee Successfully Registered!';
                        successMessage = 'The employee has been successfully registered and can now access the system.';
                    } else if (userType === 'attorney') {
                        successTitle = 'Attorney Successfully Registered!';
                        successMessage = 'The attorney has been successfully registered and can now manage cases.';
                    }
                    
                    showSuccessModal(successTitle, successMessage);
                    // Close modal and reload page
                    closeAddUserModal();
                } else {
                    // Error - show error message
                    showError('Failed to add user. Please try again.');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred. Please try again.');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
        
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorDiv.style.display = 'block';
            
            // Scroll to error message
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function hideError() {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.style.display = 'none';
        }
        
        // Password Strength Checker Function
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const indicator = document.getElementById('passwordStrengthIndicator');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            // Show/hide indicator based on password input
            if (password.length > 0) {
                indicator.style.display = 'block';
            } else {
                indicator.style.display = 'none';
                return;
            }
            
            // Check individual requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*()\-_+={}[\]:";'<>.,?/\\|~]/.test(password);
            
            // Update checkmarks
            updateCheckmark('lengthCheck', hasLength);
            updateCheckmark('uppercaseCheck', hasUppercase);
            updateCheckmark('lowercaseCheck', hasLowercase);
            updateCheckmark('numberCheck', hasNumber);
            updateCheckmark('specialCheck', hasSpecial);
            
            // Calculate strength score
            let score = 0;
            if (hasLength) score += 1;
            if (hasUppercase) score += 1;
            if (hasLowercase) score += 1;
            if (hasNumber) score += 1;
            if (hasSpecial) score += 1;
            
            // Update strength bar and text
            let strength = 'Weak';
            let color = '#ef4444';
            let width = '20%';
            
            if (score >= 5) {
                strength = 'Strong';
                color = '#10b981';
                width = '100%';
            } else if (score >= 4) {
                strength = 'Good';
                color = '#3b82f6';
                width = '80%';
            } else if (score >= 3) {
                strength = 'Fair';
                color = '#f59e0b';
                width = '60%';
            } else if (score >= 2) {
                strength = 'Weak';
                color = '#ef4444';
                width = '40%';
            } else {
                strength = 'Very Weak';
                color = '#dc2626';
                width = '20%';
            }
            
            strengthBar.style.width = width;
            strengthBar.style.background = color;
            strengthText.textContent = strength;
            strengthText.style.color = color;
        }
        
        function updateCheckmark(elementId, isValid) {
            const element = document.getElementById(elementId);
            if (isValid) {
                element.textContent = '✓';
                element.style.color = '#10b981';
            } else {
                element.textContent = '✗';
                element.style.color = '#ef4444';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addUserModal');
            const editModal = document.getElementById('editUserModal');
            if (event.target === addModal) {
                closeAddUserModal();
            }
            if (event.target === editModal) {
                closeEditUserModal();
            }
        }

        function openEditUserModal(id, name, email, phone, userType) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editEmail').value = email;
            document.getElementById('editPhoneNumber').value = phone;
            document.getElementById('editUserType').value = userType;
            document.getElementById('editUserModal').style.display = 'block';
        }

        function editUser(id, name, email, phone, userType) {
            openEditUserModal(id, name, email, phone, userType);
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Function to toggle password visibility
        function togglePasswordVisibility(inputId, toggleIconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(toggleIconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function validateEditForm() {
            const name = document.getElementById('editName').value;
            const email = document.getElementById('editEmail').value;
            const phone = document.getElementById('editPhoneNumber').value;
            
            if (!name.trim()) {
                alert('Name is required!');
                return false;
            }
            
            if (!email.trim()) {
                alert('Email is required!');
                return false;
            }
            
            if (!phone.trim()) {
                alert('Phone number is required!');
                return false;
            }
            
            // Email validation - accept any valid email format
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address!');
                return false;
            }
            
            // Phone validation - must be exactly 11 digits
            if (phone.length !== 11) {
                alert('Phone number must be exactly 11 digits!');
                return false;
            }
            
            if (!/^[0-9]{11}$/.test(phone)) {
                alert('Phone number must contain only digits!');
                return false;
            }
            
            return true;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editUserModal');
            if (event.target === modal) {
                closeEditUserModal();
            }
        }
        
        // Pagination and Search Variables
        let currentPages = {
            admin: 1,
            attorney: 1,
            employee: 1,
            client: 1
        };
        
        let pageSizes = {
            admin: 10,
            attorney: 10,
            employee: 10,
            client: 10
        };
        
        // Search and Filter Function
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            
            const tables = ['admin', 'attorney', 'employee', 'client'];
            
            tables.forEach(type => {
                const table = document.getElementById(type + 'Table');
                const rows = table.getElementsByTagName('tr');
                
                for (let i = 1; i < rows.length; i++) { // Skip header row
                    const row = rows[i];
                    const cells = row.getElementsByTagName('td');
                    
                    if (cells.length === 0) continue;
                    
                    const name = cells[1].textContent.toLowerCase();
                    const email = cells[2].textContent.toLowerCase();
                    const phone = cells[3].textContent.toLowerCase();
                    const status = cells[5].textContent.trim();
                    
                    const matchesSearch = name.includes(searchTerm) || 
                                        email.includes(searchTerm) || 
                                        phone.includes(searchTerm);
                    const matchesStatus = statusFilter === '' || status === statusFilter;
                    
                    if (matchesSearch && matchesStatus) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
        
        // Pagination Functions
        function changePageSize(type) {
            pageSizes[type] = parseInt(document.getElementById(type + 'PageSize').value);
            currentPages[type] = 1;
            updatePagination(type);
        }
        
        function changePage(type, direction) {
            const table = document.getElementById(type + 'Table');
            const rows = Array.from(table.getElementsByTagName('tr')).slice(1); // Skip header
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            const totalPages = Math.ceil(visibleRows.length / pageSizes[type]);
            
            if (direction === 'prev' && currentPages[type] > 1) {
                currentPages[type]--;
            } else if (direction === 'next' && currentPages[type] < totalPages) {
                currentPages[type]++;
            }
            
            updatePagination(type);
        }
        
        function updatePagination(type) {
            const table = document.getElementById(type + 'Table');
            const rows = Array.from(table.getElementsByTagName('tr')).slice(1); // Skip header
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            const totalPages = Math.ceil(visibleRows.length / pageSizes[type]);
            const startIndex = (currentPages[type] - 1) * pageSizes[type];
            const endIndex = startIndex + pageSizes[type];
            
            // Hide all rows first
            rows.forEach(row => row.style.display = 'none');
            
            // Show only current page rows
            visibleRows.slice(startIndex, endIndex).forEach(row => row.style.display = '');
            
            // Update page info
            const pageInfo = document.getElementById(type + 'PageInfo');
            pageInfo.textContent = `Page ${currentPages[type]} of ${totalPages || 1}`;
            
            // Update button states
            const prevBtn = pageInfo.previousElementSibling;
            const nextBtn = pageInfo.nextElementSibling;
            
            prevBtn.disabled = currentPages[type] <= 1;
            nextBtn.disabled = currentPages[type] >= totalPages;
            
            prevBtn.style.opacity = currentPages[type] <= 1 ? '0.5' : '1';
            nextBtn.style.opacity = currentPages[type] >= totalPages ? '0.5' : '1';
        }
        
        // Initialize pagination on page load
        document.addEventListener('DOMContentLoaded', function() {
            ['admin', 'attorney', 'employee', 'client'].forEach(type => {
                updatePagination(type);
            });
        });
        
        // View Admin Details Function - Enhanced with Maroon Theme
        function viewAdminDetails(adminId) {
            const adminRow = document.querySelector(`#adminTable tr[data-id="${adminId}"]`);
            if (adminRow) {
                const cells = adminRow.getElementsByTagName('td');
                const name = cells[1].textContent;
                const email = cells[2].textContent;
                const phone = cells[3].textContent;
                const lastLogin = cells[4].textContent;
                const status = cells[5].textContent.trim();
                
                // Create enhanced modal with maroon theme
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99990;
                    animation: fadeIn 0.3s ease;
                `;
                
                const content = document.createElement('div');
                content.style.cssText = `
                    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
                    padding: 0;
                    border-radius: 20px;
                    max-width: 600px;
                    width: 95%;
                    max-height: 70vh;
                    box-shadow: 0 25px 80px rgba(139, 0, 0, 0.3), 0 0 0 1px rgba(255,255,255,0.1);
                    border: 1px solid rgba(139, 0, 0, 0.1);
                    overflow: hidden;
                    animation: slideIn 0.3s ease;
                `;
                
                content.innerHTML = `
                    <!-- Modal Header -->
                    <div style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); padding: 25px 30px; position: relative;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-shield" style="color: white; font-size: 20px;"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; color: white; font-size: 1.4em; font-weight: 700;">Admin Details</h3>
                                <p style="margin: 2px 0 0 0; color: rgba(255,255,255,0.9); font-size: 0.85em;">Administrator Information</p>
                            </div>
                        </div>
                        <button onclick="closeAdminModal(this)" style="position: absolute; right: 20px; top: 20px; width: 36px; height: 36px; background: rgba(255,255,255,0.2); border: none; border-radius: 8px; color: white; font-size: 16px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Modal Content -->
                    <div style="padding: 20px; overflow-y: auto; max-height: calc(70vh - 100px);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Full Name</label>
                                <div style="padding: 10px 14px; background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border: 2px solid #fecaca; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139, 0, 0, 0.1)'" onmouseout="this.style.borderColor='#fecaca'; this.style.boxShadow='none'">
                                    <p style="margin: 0; color: #8B0000; font-weight: 500; font-size: 1em;">${name}</p>
                                </div>
                            </div>
                            
                            <div>
                                <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Email Address</label>
                                <div style="padding: 10px 14px; background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border: 2px solid #fecaca; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139, 0, 0, 0.1)'" onmouseout="this.style.borderColor='#fecaca'; this.style.boxShadow='none'">
                                    <p style="margin: 0; color: #8B0000; font-weight: 500; font-size: 1em;">${email}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Phone Number</label>
                                <div style="padding: 10px 14px; background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border: 2px solid #fecaca; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139, 0, 0, 0.1)'" onmouseout="this.style.borderColor='#fecaca'; this.style.boxShadow='none'">
                                    <p style="margin: 0; color: #8B0000; font-weight: 500; font-size: 1em;">${phone}</p>
                                </div>
                            </div>
                            
                            <div>
                                <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Last Login</label>
                                <div style="padding: 10px 14px; background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border: 2px solid #fecaca; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139, 0, 0, 0.1)'" onmouseout="this.style.borderColor='#fecaca'; this.style.boxShadow='none'">
                                    <p style="margin: 0; color: #8B0000; font-weight: 500; font-size: 1em;">${lastLogin}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Account Status</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="padding: 8px 16px; border-radius: 20px; font-size: 0.85em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; ${status === 'Active' ? 'background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534; border: 1px solid #86efac;' : 'background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #dc2626; border: 1px solid #fca5a5;'}">
                                    ${status}
                                </span>
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: ${status === 'Active' ? '#10b981' : '#ef4444'}; animation: pulse 2s infinite;"></div>
                            </div>
                        </div>
                        
                        <div style="text-align: center;">
                            <button onclick="closeAdminModal(this)" style="padding: 10px 20px; background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; box-shadow: 0 4px 16px rgba(139, 0, 0, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(139, 0, 0, 0.4)'; this.style.background='linear-gradient(135deg, #A52A2A 0%, #8B0000 100%)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 16px rgba(139, 0, 0, 0.3)'; this.style.background='linear-gradient(135deg, #8B0000 0%, #A52A2A 100%)'">
                                <i class="fas fa-times" style="margin-right: 6px;"></i>Close
                            </button>
                        </div>
                    </div>
                `;
                
                modal.appendChild(content);
                document.body.appendChild(modal);
                
                // Add CSS animations
                const style = document.createElement('style');
                style.setAttribute('data-modal-style', 'true');
                style.textContent = `
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideIn {
                        from { transform: translateY(-20px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    @keyframes pulse {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.5; }
                    }
                `;
                document.head.appendChild(style);
                
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            modal.remove();
                            // Remove any added styles
                            const addedStyles = document.querySelectorAll('style[data-modal-style]');
                            addedStyles.forEach(style => style.remove());
                        }, 300);
                    }
                });
                
                // Add fadeOut animation
                const fadeOutStyle = document.createElement('style');
                fadeOutStyle.setAttribute('data-modal-style', 'true');
                fadeOutStyle.textContent = `
                    @keyframes fadeOut {
                        from { opacity: 1; }
                        to { opacity: 0; }
                    }
                `;
                document.head.appendChild(fadeOutStyle);
            }
        }

        // Close Admin Modal Function
        function closeAdminModal(button) {
            const modal = button.closest('[style*="position: fixed"]');
            if (modal) {
                modal.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    modal.remove();
                    // Remove any added styles
                    const addedStyles = document.querySelectorAll('style[data-modal-style]');
                    addedStyles.forEach(style => style.remove());
                }, 300);
            }
        }

        // Triple Confirmation Delete User Function
        function confirmDeleteUser(userId, userName, userType) {
            // First: Warning message
            if (confirm('⚠️ WARNING: You are about to delete user "' + userName + '" (' + userType + ')!\n\nThis action will:\n• Permanently remove the user account\n• Delete all associated data\n• Cannot be undone\n\nAre you sure you want to continue?')) {
                
                // Second: Final confirmation
                if (confirm('🚨 FINAL CONFIRMATION:\n\nYou are about to permanently delete:\n\n👤 User: ' + userName + '\n📧 Type: ' + userType + '\n\nThis action cannot be undone!\n\nClick OK to proceed to final step.')) {
                    
                    // Third: Type "DELETE" to confirm
                    const userInput = prompt('🚨 FINAL STEP:\n\nType "DELETE" exactly to confirm deletion of user "' + userName + '":');
                    
                    if (userInput === 'DELETE') {
                        // Proceed with deletion
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
                        const userIdInput = document.createElement('input');
                        userIdInput.type = 'hidden';
                        userIdInput.name = 'delete_user_id';
                        userIdInput.value = userId;
                        
                        const deleteBtnInput = document.createElement('input');
                        deleteBtnInput.type = 'hidden';
                        deleteBtnInput.name = 'delete_user_btn';
                        deleteBtnInput.value = '1';
                        
                        form.appendChild(userIdInput);
                        form.appendChild(deleteBtnInput);
                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        alert('❌ Deletion cancelled. User "' + userName + '" is safe.');
                    }
                } else {
                    alert('❌ Deletion cancelled. User "' + userName + '" is safe.');
                }
            } else {
                alert('❌ Deletion cancelled. User "' + userName + '" is safe.');
            }
        }
    </script>
<script src="assets/js/unread-messages.js?v=1761535511"></script></body>
</html> 