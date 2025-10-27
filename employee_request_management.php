<?php
require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$employee_id = $_SESSION['user_id'];

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

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'review_request') {
        $request_id = intval($_POST['request_id']);
        $action = $_POST['review_action']; // 'approve' or 'reject'
        $review_notes = trim($_POST['review_notes']);
        $attorney_id = isset($_POST['attorney_id']) ? intval($_POST['attorney_id']) : null;
        
        // Validate attorney selection for approval
        if ($action === 'approve' && (!$attorney_id || $attorney_id <= 0)) {
            $error_message = "Attorney selection is required when approving a request.";
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update request status
            $status = ($action === 'approve') ? 'Approved' : 'Rejected';
            $stmt = $conn->prepare("UPDATE client_request_form SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_notes = ? WHERE id = ?");
            $stmt->bind_param("sisi", $status, $employee_id, $review_notes, $request_id);
            $stmt->execute();
            
            // Notify client about approval/rejection
            if ($action === 'approve' && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
                // Get client info
                $stmt_client = $conn->prepare("SELECT client_id FROM client_request_form WHERE id = ?");
                $stmt_client->bind_param('i', $request_id);
                $stmt_client->execute();
                $client_id = $stmt_client->get_result()->fetch_assoc()['client_id'];
                
                if ($client_id) {
                    $nTitle = 'Request Approved!';
                    $nMsg = "Your request has been approved! You can now start messaging with our team and your assigned attorney.";
                    $userType = 'client';
                    $notificationType = 'success';
                    
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                    $stmtN->bind_param('issss', $client_id, $userType, $nTitle, $nMsg, $notificationType);
                    $stmtN->execute();
                }
            }
            
            // Insert review record
            $stmt = $conn->prepare("INSERT INTO employee_request_reviews (request_form_id, employee_id, action, review_notes) VALUES (?, ?, ?, ?)");
            $review_action = ($action === 'approve') ? 'Approved' : 'Rejected';
            $stmt->bind_param("iiss", $request_id, $employee_id, $review_action, $review_notes);
            $stmt->execute();
            
            // If approved, create conversation and optionally assign attorney
            if ($action === 'approve') {
                // Get client_id for this request
                $stmt = $conn->prepare("SELECT client_id FROM client_request_form WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $client_id = $res->fetch_assoc()['client_id'];
                
                // Create employee conversation
                $stmt = $conn->prepare("INSERT INTO client_employee_conversations (request_form_id, client_id, employee_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $request_id, $client_id, $employee_id);
                $stmt->execute();
                $conversation_id = $conn->insert_id;
                
                // Create attorney assignment (required for approval)
                $stmt = $conn->prepare("INSERT INTO client_attorney_assignments (conversation_id, client_id, employee_id, attorney_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiii", $conversation_id, $client_id, $employee_id, $attorney_id);
                $stmt->execute();
                $assignment_id = $conn->insert_id;
                
                // Create attorney conversation
                $stmt = $conn->prepare("INSERT INTO client_attorney_conversations (assignment_id, client_id, attorney_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $assignment_id, $client_id, $attorney_id);
                $stmt->execute();

                // Notify attorney about the assignment (approve flow)
                if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
                    // Get client name for notification
                    $stmt_client = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                    $stmt_client->bind_param('i', $client_id);
                    $stmt_client->execute();
                    $client_name = $stmt_client->get_result()->fetch_assoc()['name'];

                    $nTitle = 'New Client Assignment';
                    $nMsg = "You have been assigned to a new client: $client_name. You can now start communicating with them.";
                    $userType = 'attorney';
                    $notificationType = 'success';

                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                    $stmtN->bind_param('issss', $attorney_id, $userType, $nTitle, $nMsg, $notificationType);
                    $stmtN->execute();
                }
            }
            
            $conn->commit();
            
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $employee_id,
                $employee_name,
                'employee',
                'Request Review',
                'Communication',
                "Request ID: $request_id - Action: $review_action" . ($attorney_id ? " with attorney assignment" : ""),
                'success',
                'medium'
            );
            
            $success_message = "Request " . strtolower($review_action) . " successfully!" . ($attorney_id ? " Attorney assigned." : "");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Failed to process request. Please try again.";
        }
    }
    
    if ($_POST['action'] === 'assign_attorney') {
        $conversation_id = intval($_POST['conversation_id']);
        $client_id = intval($_POST['client_id']);
        $attorney_id = intval($_POST['attorney_id']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create attorney assignment
            $stmt = $conn->prepare("INSERT INTO client_attorney_assignments (conversation_id, client_id, employee_id, attorney_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $conversation_id, $client_id, $employee_id, $attorney_id);
            $stmt->execute();
            
            $assignment_id = $conn->insert_id;
            
            // Create attorney conversation
            $stmt = $conn->prepare("INSERT INTO client_attorney_conversations (assignment_id, client_id, attorney_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $assignment_id, $client_id, $attorney_id);
            $stmt->execute();
            
            // Notify attorney about the assignment
            if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
                // Get client name for notification
                $stmt_client = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
                $stmt_client->bind_param('i', $client_id);
                $stmt_client->execute();
                $client_name = $stmt_client->get_result()->fetch_assoc()['name'];
                
                $nTitle = 'New Client Assignment';
                $nMsg = "You have been assigned to a new client: $client_name. You can now start communicating with them.";
                $userType = 'attorney';
                $notificationType = 'success';
                
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                $stmtN->bind_param('issss', $attorney_id, $userType, $nTitle, $nMsg, $notificationType);
                $stmtN->execute();
            }
            
            // Update employee conversation status
            $stmt = $conn->prepare("UPDATE client_employee_conversations SET conversation_status = 'Completed' WHERE id = ?");
            $stmt->bind_param("i", $conversation_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $employee_id,
                $employee_name,
                'employee',
                'Attorney Assignment',
                'Communication',
                "Assigned attorney ID: $attorney_id to client ID: $client_id",
                'success',
                'medium'
            );
            
            $success_message = "Attorney assigned successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Failed to assign attorney. Please try again.";
        }
    }
}

// Fetch pending requests
$stmt = $conn->prepare("
    SELECT crf.*, u.name as client_name, u.email as client_email
    FROM client_request_form crf
    JOIN user_form u ON crf.client_id = u.id
    WHERE crf.status = 'Pending'
    ORDER BY crf.submitted_at ASC
");
$stmt->execute();
$res = $stmt->get_result();
$pending_requests = [];
while ($row = $res->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Fetch approved requests with conversations and attorney assignments
$stmt = $conn->prepare("
    SELECT crf.*, u.name as client_name, u.email as client_email, 
           cec.id as conversation_id, cec.conversation_status, cec.concern_identified,
           caa.id as assignment_id, caa.attorney_id, caa.seen_status,
           att.name as attorney_name
    FROM client_request_form crf
    JOIN user_form u ON crf.client_id = u.id
    LEFT JOIN client_employee_conversations cec ON crf.id = cec.request_form_id
    LEFT JOIN client_attorney_assignments caa ON crf.client_id = caa.client_id
    LEFT JOIN user_form att ON caa.attorney_id = att.id
    WHERE crf.status = 'Approved'
    ORDER BY crf.submitted_at DESC
");
$stmt->execute();
$res = $stmt->get_result();
$approved_requests = [];
while ($row = $res->fetch_assoc()) {
    $approved_requests[] = $row;
}

// Fetch request history (both approved and rejected requests)
$stmt = $conn->prepare("
    SELECT crf.*, u.name as client_name, u.email as client_email, 
           cec.id as conversation_id, cec.conversation_status, cec.concern_identified,
           caa.id as assignment_id, caa.attorney_id, caa.seen_status,
           att.name as attorney_name, err.action as review_action, err.review_notes,
           emp.name as reviewed_by_name
    FROM client_request_form crf
    JOIN user_form u ON crf.client_id = u.id
    LEFT JOIN client_employee_conversations cec ON crf.id = cec.request_form_id
    LEFT JOIN client_attorney_assignments caa ON crf.client_id = caa.client_id
    LEFT JOIN user_form att ON caa.attorney_id = att.id
    LEFT JOIN employee_request_reviews err ON crf.id = err.request_form_id
    LEFT JOIN user_form emp ON err.employee_id = emp.id
    WHERE crf.status IN ('Approved', 'Rejected')
    ORDER BY crf.reviewed_at DESC, crf.submitted_at DESC
");
$stmt->execute();
$res = $stmt->get_result();
$request_history = [];
while ($row = $res->fetch_assoc()) {
    $request_history[] = $row;
}

// Fetch available attorneys and admins for assignment
$stmt = $conn->prepare("SELECT id, name FROM user_form WHERE user_type IN ('attorney', 'admin') ORDER BY name");
$stmt->execute();
$res = $stmt->get_result();
$attorneys = [];
while ($row = $res->fetch_assoc()) {
    $attorneys[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Management - Opiña Law Office</title>
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
            <li><a href="employee_request_management.php" class="active"><i class="fas fa-clipboard-check"></i><span>Request Review</span><?php if (count($pending_requests) > 0): ?><span class="notification-badge"><?= count($pending_requests) ?></span><?php endif; ?></a></li>
            <li><a href="employee_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Request Management';
        $page_subtitle = 'Review and manage client messaging requests';
        include 'components/profile_header.php'; 
        ?>

        <div class="content-container">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Pending Requests Section -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Pending Requests</h2>
                    <span class="badge"><?= count($pending_requests) ?></span>
                </div>
                
                <?php if (empty($pending_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Pending Requests</h3>
                        <p>All client requests have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-grid">
                        <?php foreach ($pending_requests as $request): ?>
                            <div class="request-card pending">
                                <div class="request-header">
                                    <div class="request-info">
                                        <h3><?= htmlspecialchars($request['client_name']) ?></h3>
                                        <p class="client-email">
                                            <i class="fas fa-envelope"></i>
                                            <?= htmlspecialchars($request['client_email']) ?>
                                        </p>
                                        <p class="submitted-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('M d, Y H:i', strtotime($request['submitted_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="request-status pending">
                                        <i class="fas fa-clock"></i>
                                        Pending
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <button class="btn btn-info icon-only" onclick="viewRequestDetails(<?= $request['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-success icon-only" onclick="reviewRequest(<?= $request['id'] ?>, 'approve')" title="Approve Request">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-danger icon-only" onclick="reviewRequest(<?= $request['id'] ?>, 'reject')" title="Reject Request">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Request History Section -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Request History</h2>
                    <span class="badge"><?= count($request_history) ?></span>
                </div>
                
                <?php if (empty($request_history)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Processed Requests</h3>
                        <p>Processed requests (approved or rejected) will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-grid">
                        <?php foreach ($request_history as $request): ?>
                            <div class="request-card <?= $request['status'] === 'Approved' ? 'approved' : 'rejected' ?>">
                                <div class="request-header">
                                    <div class="request-info">
                                        <h3><?= htmlspecialchars($request['client_name']) ?></h3>
                                        <p class="client-email">
                                            <i class="fas fa-envelope"></i>
                                            <?= htmlspecialchars($request['client_email']) ?>
                                        </p>
                                        <p class="submitted-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= ucfirst($request['status']) ?>: <?= date('M d, Y H:i', strtotime($request['reviewed_at'])) ?>
                                        </p>
                                        <?php if ($request['reviewed_by_name']): ?>
                                            <p class="reviewed-by">
                                                <i class="fas fa-user"></i>
                                                Reviewed by: <?= htmlspecialchars($request['reviewed_by_name']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="request-status <?= $request['status'] === 'Approved' ? 'approved' : 'rejected' ?>">
                                        <i class="fas fa-<?= $request['status'] === 'Approved' ? 'check-circle' : 'times-circle' ?>"></i>
                                        <?= $request['status'] ?>
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <button class="btn btn-primary view-details-btn" onclick="viewRequestDetails(<?= $request['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                        <span>View Details</span>
                                    </button>
                                </div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Review Request Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Review Request</h2>
                <button class="close-modal" onclick="closeReviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="reviewForm">
                    <input type="hidden" id="requestId" name="request_id">
                    <input type="hidden" id="reviewAction" name="review_action">
                    
                    <div class="form-group">
                        <label for="reviewNotes">Review Notes</label>
                        <textarea id="reviewNotes" name="review_notes" rows="4" placeholder="Add your review notes here..."></textarea>
                    </div>
                    
                    <!-- Attorney Assignment Section (only shown when approving) -->
                    <div id="attorneyAssignmentSection" style="display: none;">
                        <div class="form-group">
                            <label for="attorneySelectReview">Assign Attorney <span style="color: red;">*</span></label>
                            <select id="attorneySelectReview" name="attorney_id" required>
                                <option value="">Choose an attorney...</option>
                                <?php foreach ($attorneys as $attorney): ?>
                                    <option value="<?= $attorney['id'] ?>"><?= htmlspecialchars($attorney['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>
                        <button type="submit" class="btn" id="submitBtn">
                            <i class="fas fa-check"></i>
                            <span id="submitText">Submit</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Attorney Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Attorney</h2>
                <button class="close-modal" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <input type="hidden" id="conversationId" name="conversation_id">
                    <input type="hidden" id="clientId" name="client_id">
                    
                    <div class="form-group">
                        <label for="attorneySelect">Select Attorney</label>
                        <select id="attorneySelect" name="attorney_id" required>
                            <option value="">Choose an attorney...</option>
                            <?php foreach ($attorneys as $attorney): ?>
                                <option value="<?= $attorney['id'] ?>"><?= htmlspecialchars($attorney['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-tie"></i>
                            Assign Attorney
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .content-container {
            padding: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
        }

        .section-header h2 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            background: var(--primary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            border: 2px dashed rgba(93, 14, 38, 0.2);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin: 0 0 10px 0;
        }

        .empty-state p {
            color: #999;
            margin: 0;
        }

        .requests-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .request-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: visible;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .request-status.rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .rejection-info {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .review-notes {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #6c757d;
        }

        .review-notes h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 0.95rem;
        }

        .review-notes p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .reviewed-by {
            color: #666;
            font-size: 0.85rem;
            margin: 5px 0 0 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 12px;
        }

        .request-info {
            flex: 1;
            min-width: 0;
            width: 0;
        }

        .request-info h3 {
            color: #2c3e50;
            margin: 0 0 4px 0;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
            display: block;
        }

        .client-email {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 0 0 3px 0;
            display: flex;
            align-items: center;
            gap: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .submitted-date {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0 0 3px 0;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .reviewed-by {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .request-status {
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            flex-shrink: 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .request-status.pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .request-status.approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .request-actions {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
        }

        .view-details-btn {
            width: 100%;
            padding: 10px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #5D0E26;
            color: white;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            white-space: nowrap;
            text-align: center;
        }

        .view-details-btn:hover {
            background: #4a0b1e;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .view-details-btn i {
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .view-details-btn span {
            flex-shrink: 0;
        }

        .request-details {
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            margin-bottom: 10px;
        }

        .detail-item label {
            font-weight: 600;
            color: var(--primary-color);
            min-width: 80px;
            margin-right: 10px;
        }

        .detail-item span {
            color: #666;
            flex: 1;
        }

        .file-link {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .file-link:hover {
            text-decoration: underline;
        }

        .conversation-status {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 10px;
        }

        .status-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .status-info:last-child {
            margin-bottom: 0;
        }

        .status-info i {
            color: var(--primary-color);
        }

        .request-actions {
            display: flex;
            justify-content: flex-start;
            gap: 8px;
            align-items: center;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
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
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        /* Icon-only button styles */
        .btn.icon-only {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .btn.icon-only:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .btn.icon-only i {
            margin: 0;
        }

        /* Tooltip styles for icon buttons */
        .btn.icon-only::after {
            content: attr(title);
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 10000;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .btn.icon-only::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.9);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 10000;
            pointer-events: none;
        }

        .btn.icon-only:hover::after,
        .btn.icon-only:hover::before {
            opacity: 1;
            visibility: visible;
        }

        .action-note {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 10px;
            color: #1565c0;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 10px;
        }

        .action-note i {
            color: #1976d2;
        }

        .assignment-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 10px;
            color: #2e7d32;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 10px;
        }

        .assignment-info i {
            color: #4caf50;
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
            margin: 8% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            max-height: 80vh;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(93, 14, 38, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(93, 14, 38, 0.1);
        }

        @media (max-width: 1200px) {
            .requests-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .requests-grid {
                grid-template-columns: 1fr;
            }
            
            .request-header {
                flex-direction: column;
                gap: 12px;
            }
            
            .request-actions {
                gap: 8px;
            }
            
            .btn.icon-only {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
        }

        /* ID Display Styles */
        .id-display {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .id-preview {
            margin-top: 8px;
        }

        .id-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
        }

        .id-image:hover {
            border-color: #1976d2;
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
        }

        .pdf-preview {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            color: #666;
        }

        .pdf-preview i {
            font-size: 1.5rem;
            color: #dc3545;
        }

        .no-file {
            color: #dc3545;
            font-style: italic;
        }

        .privacy-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
        }

        .privacy-status.consented {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .privacy-status.not-consented {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            cursor: pointer;
        }

        .image-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .image-modal-close:hover {
            color: #ccc;
        }

        /* Request Details Toggle */
        .request-details-toggle {
            margin-top: 15px;
            text-align: center;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #1976d2;
            color: #1976d2;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: #1976d2;
            color: white;
        }

        .request-details-collapse {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .request-details-collapse .request-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .request-details-collapse .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .request-details-collapse .detail-item label {
            font-weight: 600;
            color: #1976d2;
            font-size: 0.9rem;
        }

        .request-details-collapse .detail-item span {
            color: #333;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .request-details-collapse .request-details {
                grid-template-columns: 1fr;
            }
        }

        /* Request Details Modal */
        .request-details-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(5px);
        }

        .request-details-modal .modal-content {
            background: #ffffff;
            margin: 4% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
        }

        /* Custom scrollbar for modal */
        .request-details-modal .modal-content::-webkit-scrollbar {
            width: 6px;
        }

        .request-details-modal .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .request-details-modal .modal-content::-webkit-scrollbar-thumb {
            background: #5D0E26;
            border-radius: 3px;
        }

        .request-details-modal .modal-content::-webkit-scrollbar-thumb:hover {
            background: #8B1538;
        }

        .request-details-modal .modal-header {
            background: #5D0E26;
            color: white;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .request-details-modal .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
        }

        .request-details-modal .modal-close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: all 0.2s ease;
            padding: 4px;
            border-radius: 4px;
        }

        .request-details-modal .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .request-details-modal .modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* New Modern Layout Styles */
        .request-details-container {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .client-info-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 18px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .client-info-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #5D0E26;
        }

        .client-info-header i {
            font-size: 1.5rem;
            color: #5D0E26;
        }

        .client-info-header h3 {
            margin: 0;
            color: #5D0E26;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .client-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .client-detail-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .client-detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.15);
        }

        .client-detail-card .detail-label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: #5D0E26;
            margin-bottom: 4px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .client-detail-card .detail-label i {
            font-size: 0.75rem;
        }

        .client-detail-card .detail-value {
            color: #333;
            font-size: 0.75rem;
            line-height: 1.2;
            font-weight: 500;
        }

        .concern-section {
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            border-radius: 12px;
            padding: 18px;
            border: 1px solid #ffc107;
            box-shadow: 0 2px 10px rgba(255, 193, 7, 0.1);
        }

        .concern-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffc107;
        }

        .concern-header i {
            font-size: 1.5rem;
            color: #f57c00;
        }

        .concern-header h3 {
            margin: 0;
            color: #f57c00;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .concern-content {
            background: white;
            border-radius: 10px;
            padding: 12px;
            border-left: 4px solid #ffc107;
            font-size: 0.75rem;
            line-height: 1.3;
            color: #333;
        }

        .status-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-radius: 12px;
            padding: 18px;
            border: 1px solid #4caf50;
            box-shadow: 0 2px 10px rgba(76, 175, 80, 0.1);
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4caf50;
        }

        .status-header i {
            font-size: 1.5rem;
            color: #2e7d32;
        }

        .status-header h3 {
            margin: 0;
            color: #2e7d32;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .status-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #c8e6c9;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-card.unseen {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
            border: 2px solid #f39c12 !important;
            animation: pulseUnseen 2s infinite;
        }

        .status-card i {
            font-size: 1.2rem;
            color: #4caf50;
        }
        
        .status-card.unseen i {
            color: #f39c12;
        }

        .status-card span {
            color: #333;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        @keyframes pulseUnseen {
            0% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(243, 156, 18, 0); }
            100% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0); }
        }

        .request-details-modal .concern-item label {
            color: #856404;
            font-weight: 700;
            font-size: 1rem;
        }

        .request-details-modal .concern-text {
            color: #856404;
            font-size: 1rem;
            line-height: 1.6;
            font-style: italic;
            background: rgba(255, 255, 255, 0.7);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .request-details-modal .id-display {
            margin-top: 10px;
        }

        .request-details-modal .id-image {
            max-width: 100%;
            max-height: 150px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s ease;
            object-fit: cover;
        }

        .request-details-modal .id-image:hover {
            border-color: #5D0E26;
            transform: scale(1.02);
        }

        .request-details-modal .file-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #5D0E26;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 8px;
            padding: 4px 8px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 4px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .request-details-modal .file-link:hover {
            background: rgba(93, 14, 38, 0.1);
        }

        .request-details-modal .file-link i {
            font-size: 1rem;
        }

        .request-details-modal .pdf-preview {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            color: #666;
        }

        .request-details-modal .pdf-preview i {
            font-size: 1.5rem;
            color: #dc3545;
        }

        .request-details-modal .pdf-preview span {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .request-details-modal .pdf-preview:hover {
            background: #e9ecef;
            border-color: #5D0E26;
            transform: translateY(-1px);
        }

        .request-details-modal .no-file {
            color: #dc3545;
            font-style: italic;
            font-weight: 500;
            padding: 8px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 4px;
            border-left: 2px solid #dc3545;
            font-size: 0.9rem;
        }

        .request-details-modal .privacy-status {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .request-details-modal .privacy-status.consented {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .request-details-modal .privacy-status.not-consented {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .request-details-modal .privacy-status i {
            font-size: 1.1rem;
        }

        /* Images Section */
        .images-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .image-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .image-item {
            display: flex;
            flex-direction: column;
        }

        .image-item label {
            font-weight: 600;
            color: #5D0E26;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }

        .image-item .id-image {
            width: 100%;
            height: 100px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s ease;
            object-fit: cover;
        }

        .image-item .id-image:hover {
            border-color: #5D0E26;
            transform: scale(1.02);
        }

        .image-item .pdf-preview {
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            color: #666;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .image-item .pdf-preview:hover {
            background: #e9ecef;
            border-color: #5D0E26;
            transform: translateY(-1px);
        }

        .image-item .pdf-preview i {
            font-size: 1.2rem;
            color: #dc3545;
        }

        .image-item .pdf-preview span {
            font-weight: 500;
            font-size: 0.8rem;
        }

        .btn-info {
            background: #5D0E26;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-info:hover {
            background: #8B1538;
            transform: translateY(-1px);
        }

        .btn-info i {
            margin-right: 6px;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .details-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .request-details-modal .modal-body {
                padding: 20px;
            }
            
            .request-details-modal .modal-content {
                margin: 5% auto;
                width: 95%;
                max-height: 90vh;
            }
            
            .request-details-modal .modal-header {
                padding: 15px 20px;
            }
            
            .request-details-modal .modal-header h2 {
                font-size: 1.2rem;
            }
            
            .client-details-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .client-detail-card {
                padding: 15px;
            }
            
            .client-info-section,
            .concern-section,
            .status-section {
                padding: 20px;
            }
            
            .client-info-header,
            .concern-header,
            .status-header {
                margin-bottom: 15px;
                padding-bottom: 10px;
            }
            
            .client-info-header h3,
            .concern-header h3,
            .status-header h3 {
                font-size: 1.1rem;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .image-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .client-details-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        /* PDF Modal */
        .pdf-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            cursor: pointer;
        }

        .pdf-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
        }

        .pdf-modal-header {
            background: #5D0E26;
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0;
        }

        .pdf-modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
        }

        .pdf-modal-close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .pdf-modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pdf-modal-body {
            padding: 20px;
            background: #f8f9fa;
        }

        /* Modal-specific rejection info and review notes */
        .rejection-info-modal {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.1);
        }

        .rejection-info-modal i {
            font-size: 1.1rem;
            color: #dc3545;
        }

        .review-notes-modal {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border-left: 4px solid #6c757d;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .review-notes-modal h4 {
            margin: 0 0 12px 0;
            color: #495057;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .review-notes-modal h4::before {
            content: '📝';
            font-size: 1.1rem;
        }

        .review-notes-modal p {
            margin: 0;
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.6;
            background: rgba(255, 255, 255, 0.7);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }

        /* Modal conversation status styling */
        .conversation-status-modal {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border-left: 4px solid #28a745;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .status-info-modal {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            border: 1px solid rgba(40, 167, 69, 0.2);
            font-size: 0.9rem;
        }

        .status-info-modal:last-child {
            margin-bottom: 0;
        }

        .status-info-modal i {
            color: #28a745;
            font-size: 1rem;
            width: 16px;
            text-align: center;
        }

        .status-info-modal span {
            color: #2c3e50;
            font-weight: 500;
        }
        
        .request-details-modal .attorney-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid;
        }
        
        .request-details-modal .attorney-status.seen {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
        }
        
        .request-details-modal .attorney-status.unseen {
            color: #ffc107;
            background: rgba(255, 193, 7, 0.1);
            border-color: #ffc107;
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
        function reviewRequest(requestId, action) {
            document.getElementById('requestId').value = requestId;
            document.getElementById('reviewAction').value = action;
            
            const modal = document.getElementById('reviewModal');
            const title = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const attorneySection = document.getElementById('attorneyAssignmentSection');
            const attorneySelect = document.getElementById('attorneySelectReview');
            
            // Get client name from the request card
            const requestCard = document.querySelector(`[onclick*="reviewRequest(${requestId}"]`).closest('.request-card');
            const clientName = requestCard.querySelector('h3').textContent;
            
            if (action === 'approve') {
                title.innerHTML = `<i class="fas fa-check-circle"></i> Approve Request - ${clientName}`;
                submitBtn.className = 'btn btn-success';
                submitText.textContent = 'Approve';
                attorneySection.style.display = 'block';
                attorneySelect.required = true;
            } else {
                title.innerHTML = `<i class="fas fa-times-circle"></i> Reject Request - ${clientName}`;
                submitBtn.className = 'btn btn-danger';
                submitText.textContent = 'Reject';
                attorneySection.style.display = 'none';
                attorneySelect.required = false;
                attorneySelect.value = ''; // Clear the value when hidden
            }
            
            modal.style.display = 'block';
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
            document.getElementById('reviewForm').reset();
            // Ensure attorney select is not required when modal is closed
            document.getElementById('attorneySelectReview').required = false;
        }

        function assignAttorney(conversationId, clientId) {
            document.getElementById('conversationId').value = conversationId;
            document.getElementById('clientId').value = clientId;
            document.getElementById('assignModal').style.display = 'block';
        }

        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('assignForm').reset();
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
            
            // Close modals when clicking outside
            if (event.target == document.getElementById('reviewModal')) {
                closeReviewModal();
            }
            if (event.target == document.getElementById('assignModal')) {
                closeAssignModal();
            }
        }

        // Handle review form submission
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reviewAction = document.getElementById('reviewAction').value;
            const attorneySelect = document.getElementById('attorneySelectReview');
            
            // Validate attorney selection for approval
            if (reviewAction === 'approve' && (!attorneySelect.value || attorneySelect.value === '')) {
                alert('Please select an attorney before approving the request.');
                attorneySelect.focus();
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'review_request');
            
            fetch('employee_request_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                if (result.includes('successfully')) {
                    location.reload();
                } else {
                    alert('Error processing request. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request. Please try again.');
            });
        });

        // Handle assign form submission
        document.getElementById('assignForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'assign_attorney');
            
            fetch('employee_request_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                if (result.includes('successfully')) {
                    location.reload();
                } else {
                    alert('Error assigning attorney. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error assigning attorney. Please try again.');
            });
        });
    </script>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <!-- Request Details Modal -->
    <div id="requestDetailsModal" class="request-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Client Request Details</h2>
                <span class="modal-close" onclick="closeRequestDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- PDF Viewer Modal -->
    <div id="pdfModal" class="pdf-modal" onclick="closePDFModal()">
        <div class="pdf-modal-content" onclick="event.stopPropagation()">
            <div class="pdf-modal-header">
                <h3 id="pdfModalTitle">PDF Document</h3>
                <span class="pdf-modal-close" onclick="closePDFModal()">&times;</span>
            </div>
            <div class="pdf-modal-body">
                <iframe id="pdfViewer" src="" style="width: 100%; height: 80vh; border: none; border-radius: 8px;"></iframe>
            </div>
        </div>
    </div>

    <script>
        // Image Modal Functions
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'block';
            modalImg.src = imageSrc;
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // PDF Modal Functions
        function openPDFModal(pdfPath, filename) {
            const modal = document.getElementById('pdfModal');
            const modalTitle = document.getElementById('pdfModalTitle');
            const pdfViewer = document.getElementById('pdfViewer');
            
            modalTitle.textContent = filename || 'PDF Document';
            pdfViewer.src = pdfPath;
            modal.style.display = 'block';
        }

        function closePDFModal() {
            const modal = document.getElementById('pdfModal');
            const pdfViewer = document.getElementById('pdfViewer');
            
            modal.style.display = 'none';
            // Clear the iframe src to stop loading
            pdfViewer.src = '';
        }

        // Close modal when clicking outside the image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Toggle Request Details
        function toggleRequestDetails(requestId) {
            const detailsDiv = document.getElementById('requestDetails' + requestId);
            const button = event.target.closest('button');
            const icon = button.querySelector('i');
            
            if (detailsDiv.style.display === 'none') {
                detailsDiv.style.display = 'block';
                icon.className = 'fas fa-eye-slash';
                button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Request Details';
            } else {
                detailsDiv.style.display = 'none';
                icon.className = 'fas fa-eye';
                button.innerHTML = '<i class="fas fa-eye"></i> View Request Details';
            }
        }

        // View Request Details Modal
        function viewRequestDetails(requestId) {
            // Fetch request details via AJAX
            fetch('get_request_details.php?id=' + requestId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const request = data.request;
                        const modalContent = document.getElementById('requestDetailsContent');
                        
                        modalContent.innerHTML = `
                            <div class="request-details-container">
                                <!-- Client Information Section -->
                                <div class="client-info-section">
                                    <div class="client-info-header">
                                        <i class="fas fa-user-circle"></i>
                                        <h3>Client Information</h3>
                                    </div>
                                    <div class="client-details-grid">
                                        <div class="client-detail-card">
                                            <div class="detail-label">
                                                <i class="fas fa-user"></i>
                                                Client Name
                                            </div>
                                            <div class="detail-value">${request.client_name}</div>
                                        </div>
                                        <div class="client-detail-card">
                                            <div class="detail-label">
                                                <i class="fas fa-envelope"></i>
                                                Email Address
                                            </div>
                                            <div class="detail-value">${request.client_email}</div>
                                        </div>
                                        <div class="client-detail-card">
                                            <div class="detail-label">
                                                <i class="fas fa-map-marker-alt"></i>
                                                Address
                                            </div>
                                            <div class="detail-value">${request.address}</div>
                                        </div>
                                        <div class="client-detail-card">
                                            <div class="detail-label">
                                                <i class="fas fa-calendar-plus"></i>
                                                Submitted Date
                                            </div>
                                            <div class="detail-value">${new Date(request.submitted_at).toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Legal Concern Section -->
                                <div class="concern-section">
                                    <div class="concern-header">
                                        <i class="fas fa-gavel"></i>
                                        <h3>Legal Concern/Issue</h3>
                                    </div>
                                    <div class="concern-content">
                                        ${request.concern_description || 'No concern provided'}
                                    </div>
                                </div>

                                <!-- Privacy & Status Section -->
                                <div class="status-section">
                                    <div class="status-header">
                                        <i class="fas fa-shield-alt"></i>
                                        <h3>Privacy & Status Information</h3>
                                    </div>
                                    <div class="status-grid">
                                        <div class="status-card">
                                            <i class="fas fa-${request.privacy_consent ? 'check-circle' : 'times-circle'}"></i>
                                            <span>${request.privacy_consent ? 'Agreed to Data Privacy Act' : 'Not agreed to Data Privacy Act'}</span>
                                        </div>
                                    </div>
                                </div>
                            
                            ${request.status === 'Approved' && request.attorney_name ? `
                                <!-- Assignment Information Section -->
                                <div class="status-section">
                                    <div class="status-header">
                                        <i class="fas fa-user-tie"></i>
                                        <h3>Assignment Information</h3>
                                    </div>
                                    <div class="status-grid">
                                        <div class="status-card">
                                            <i class="fas fa-user-tie"></i>
                                            <span>Attorney Assigned: ${request.attorney_name}</span>
                                        </div>
                                        <div class="status-card ${request.seen_status !== 'Seen' ? 'unseen' : ''}">
                                            <i class="fas fa-${request.seen_status === 'Seen' ? 'check-circle' : 'clock'}"></i>
                                            <span>${request.seen_status === 'Seen' ? 'Message Seen by Attorney' : 'Message Not Yet Seen by Attorney'}</span>
                                        </div>
                                        ${request.reviewed_by_name ? `
                                            <div class="status-card">
                                                <i class="fas fa-user-check"></i>
                                                <span>Reviewed by: ${request.reviewed_by_name}</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Documents Section -->
                            <div class="status-section">
                                <div class="status-header">
                                    <i class="fas fa-file-alt"></i>
                                    <h3>Government ID Documents</h3>
                                </div>
                                
                                <div class="image-row">
                                    <div class="image-item">
                                        <label>Government ID Front:</label>
                                        ${request.valid_id_front_path ? `
                                            <div class="id-preview">
                                                ${request.valid_id_front_path.toLowerCase().includes('.pdf') ? `
                                                    <div class="pdf-preview" onclick="openPDFModal('${request.valid_id_front_path}', '${request.valid_id_front_filename}')" style="cursor: pointer;">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <span>PDF Document</span>
                                                    </div>
                                                ` : `
                                                    <img src="${request.valid_id_front_path}" alt="Front ID" class="id-image" onclick="openImageModal('${request.valid_id_front_path}')">
                                                `}
                                            </div>
                                        ` : '<span class="no-file">No front ID uploaded</span>'}
                                    </div>
                                    
                                    <div class="image-item">
                                        <label>Government ID Back:</label>
                                        ${request.valid_id_back_path ? `
                                            <div class="id-preview">
                                                ${request.valid_id_back_path.toLowerCase().includes('.pdf') ? `
                                                    <div class="pdf-preview" onclick="openPDFModal('${request.valid_id_back_path}', '${request.valid_id_back_filename}')" style="cursor: pointer;">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <span>PDF Document</span>
                                                    </div>
                                                ` : `
                                                    <img src="${request.valid_id_back_path}" alt="Back ID" class="id-image" onclick="openImageModal('${request.valid_id_back_path}')">
                                                `}
                                            </div>
                                        ` : '<span class="no-file">No back ID uploaded</span>'}
                                    </div>
                                </div>
                            </div>
                            
                            ${request.status === 'Rejected' ? `
                                <!-- Rejection Info -->
                                <div class="rejection-info-modal">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Request was rejected. Client can re-apply if needed.</span>
                                </div>
                            ` : ''}
                            
                            ${request.review_notes ? `
                                <!-- Review Notes -->
                                <div class="review-notes-modal">
                                    <h4>Review Notes:</h4>
                                    <p>${request.review_notes}</p>
                                </div>
                            ` : ''}
                        `;
                        
                        document.getElementById('requestDetailsModal').style.display = 'block';
                    } else {
                        alert('Error loading request details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading request details. Please try again.');
                });
        }

        // Close Request Details Modal
        function closeRequestDetailsModal() {
            document.getElementById('requestDetailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('requestDetailsModal');
            if (event.target === modal) {
                closeRequestDetailsModal();
            }
        }
    </script>
<script src="assets/js/unread-messages.js?v=1761535513"></script></body>
</html>
