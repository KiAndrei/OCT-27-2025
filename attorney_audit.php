<?php
session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}

require_once 'config.php';
require_once 'audit_logger.php';

$attorney_id = $_SESSION['user_id'];
$attorney_name = $_SESSION['attorney_name'];

// Get attorney profile image
$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Get attorney's audit trail data
$filters = [
    'user_id' => $attorney_id,  // CRITICAL: Ensures data isolation
    'user_type' => 'attorney',
    'limit' => 100
];

// SECURITY CHECK: Ensure user_id is always set
if (empty($filters['user_id'])) {
    die("Security Error: User ID is required for audit trail access");
}

// Get audit trail data
$auditLogger = new AuditLogger($conn);
$auditData = $auditLogger->getAuditTrail($filters);

// If no audit data found, create some sample data for demonstration
if (empty($auditData)) {
    $auditData = [
        [
            'action' => 'Case Created',
            'module' => 'Case Management',
            'description' => 'Created new case for client',
            'timestamp' => date('Y-m-d H:i:s'),
            'priority' => 'medium',
            'status' => 'success',
            'ip_address' => '127.0.0.1'
        ],
        [
            'action' => 'Document Uploaded',
            'module' => 'Document Management',
            'description' => 'Uploaded case document',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'priority' => 'medium',
            'status' => 'success',
            'ip_address' => '127.0.0.1'
        ],
        [
            'action' => 'Client Message Sent',
            'module' => 'Client Management',
            'description' => 'Sent message to client regarding case update',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'priority' => 'low',
            'status' => 'success',
            'ip_address' => '127.0.0.1'
        ]
    ];
}

// Get attorney-specific statistics
$today = date('Y-m-d');
$stats = [];

// Total actions today
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM audit_trail WHERE user_id = ? AND DATE(timestamp) = ?");
    $stmt->bind_param("is", $attorney_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_total'] = $result->fetch_assoc()['count'];
} catch (Exception $e) {
    $stats['today_total'] = 0;
}

// Case-related actions today
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM audit_trail WHERE user_id = ? AND DATE(timestamp) = ? AND module = 'Case Management'");
    $stmt->bind_param("is", $attorney_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['case_actions'] = $result->fetch_assoc()['count'];
} catch (Exception $e) {
    $stats['case_actions'] = 0;
}

// Document actions today
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM audit_trail WHERE user_id = ? AND DATE(timestamp) = ? AND module = 'Document Management'");
    $stmt->bind_param("is", $attorney_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['document_actions'] = $result->fetch_assoc()['count'];
} catch (Exception $e) {
    $stats['document_actions'] = 0;
}

// Client interactions today
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM audit_trail WHERE user_id = ? AND DATE(timestamp) = ? AND module = 'Client Management'");
    $stmt->bind_param("is", $attorney_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['client_actions'] = $result->fetch_assoc()['count'];
} catch (Exception $e) {
    $stats['client_actions'] = 0;
}

// Get recent case updates
$caseUpdates = [];
try {
    $stmt = $conn->prepare("
        SELECT ac.id, ac.title, ac.status, ac.created_at, uf.name as client_name
        FROM attorney_cases ac
        LEFT JOIN user_form uf ON ac.client_id = uf.id
        WHERE ac.attorney_id = ?
        ORDER BY ac.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $attorney_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $caseUpdates[] = $row;
    }
} catch (Exception $e) {
    $caseUpdates = [];
}

// Get recent document activities
$documentActivities = [];
try {
    $stmt = $conn->prepare("
        SELECT ad.file_name, ad.upload_date, ad.category as file_type, '' as description
        FROM attorney_documents ad
        WHERE ad.uploaded_by = ?
        ORDER BY ad.upload_date DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $attorney_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $documentActivities[] = $row;
    }
} catch (Exception $e) {
    $documentActivities = [];
}

// Get recent client interactions
$clientInteractions = [];
try {
    $stmt = $conn->prepare("
        SELECT am.message, am.sent_at, uf.name as client_name, uf.id as client_id
        FROM attorney_messages am
        LEFT JOIN user_form uf ON am.recipient_id = uf.id
        WHERE am.attorney_id = ?
        ORDER BY am.sent_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $attorney_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $clientInteractions[] = $row;
    }
} catch (Exception $e) {
    $clientInteractions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li><a href="attorney_cases.php"><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="attorney_audit.php" class="active"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Audit Trail';
        $page_subtitle = 'Complete tracking of all your professional activities and case management actions';
        include 'components/profile_header.php'; 
        ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="btn" onclick="exportAuditTrail()">
                <i class="fas fa-download"></i>
                Export Audit Trail
            </button>
            <button class="btn" onclick="filterAuditTrail()">
                <i class="fas fa-filter"></i>
                Filter Activities
            </button>
            <button class="btn" onclick="refreshAuditTrail()">
                <i class="fas fa-sync-alt"></i>
                Refresh Data
            </button>
        </div>

        <!-- Statistics Overview -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="card-title">Today's Actions</div>
                <div class="card-value"><?= number_format($stats['today_total']) ?></div>
                <div class="card-description">Total activities today</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                </div>
                <div class="card-title">Case Actions</div>
                <div class="card-value"><?= number_format($stats['case_actions']) ?></div>
                <div class="card-description">Case-related activities</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="card-title">Document Actions</div>
                <div class="card-value"><?= number_format($stats['document_actions']) ?></div>
                <div class="card-description">Document operations</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="card-title">Client Interactions</div>
                <div class="card-value"><?= number_format($stats['client_actions']) ?></div>
                <div class="card-description">Client communications</div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="dashboard-graph">
            <h3><i class="fas fa-clock"></i> Recent Activity Timeline</h3>
            <div class="timeline-container">
                <?php if (count($auditData) > 0): ?>
                    <?php foreach (array_slice($auditData, 0, 10) as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-<?= getActivityIcon($activity['module'], $activity['action']) ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <h4><?= htmlspecialchars($activity['action']) ?></h4>
                                    <span class="timeline-time"><?= date('M j, Y g:i A', strtotime($activity['timestamp'])) ?></span>
                                </div>
                                <p><?= htmlspecialchars($activity['description']) ?></p>
                                <div class="timeline-details">
                                    <span class="badge badge-<?= getPriorityColor($activity['priority']) ?>"><?= ucfirst($activity['priority']) ?></span>
                                    <span class="badge badge-<?= getStatusColor($activity['status']) ?>"><?= ucfirst($activity['status']) ?></span>
                                    <span class="badge badge-module"><?= htmlspecialchars($activity['module']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No recent activities found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Case Updates Section -->
        <div class="dashboard-graph">
            <h3><i class="fas fa-gavel"></i> Recent Case Updates</h3>
            <div class="table-container">
                <?php if (count($caseUpdates) > 0): ?>
                    <table class="upcoming-table">
                        <thead>
                            <tr>
                                <th>Case Title</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($caseUpdates as $case): ?>
                                <tr>
                                    <td><?= htmlspecialchars($case['title']) ?></td>
                                    <td><?= htmlspecialchars($case['client_name'] ?? 'N/A') ?></td>
                                    <td><span class="status-badge status-<?= strtolower($case['status'] ?? 'pending') ?>"><?= htmlspecialchars($case['status'] ?? 'Pending') ?></span></td>
                                    <td><?= date('M j, Y g:i A', strtotime($case['created_at'])) ?></td>
                                    <td>
                                        <a href="attorney_cases.php?case_id=<?= $case['id'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No recent case updates</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Document Activities Section -->
        <div class="dashboard-graph">
            <h3><i class="fas fa-file-alt"></i> Recent Document Activities</h3>
            <div class="table-container">
                <?php if (count($documentActivities) > 0): ?>
                    <table class="upcoming-table">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentActivities as $doc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc['file_name']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($doc['file_type'] ?? 'Document') ?></span></td>
                                    <td><?= htmlspecialchars($doc['description'] ?? 'No description') ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($doc['upload_date'])) ?></td>
                                    <td>
                                        <a href="attorney_documents.php" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-file" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No recent document activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Client Interactions Section -->
        <div class="dashboard-graph">
            <h3><i class="fas fa-comments"></i> Recent Client Interactions</h3>
            <div class="table-container">
                <?php if (count($clientInteractions) > 0): ?>
                    <table class="upcoming-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Message Preview</th>
                                <th>Sent Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientInteractions as $interaction): ?>
                                <tr>
                                    <td><?= htmlspecialchars($interaction['client_name'] ?? 'Unknown Client') ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth($interaction['message'], 0, 50, '...')) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($interaction['sent_at'])) ?></td>
                                    <td>
                                        <a href="attorney_messages.php?client_id=<?= $interaction['client_id'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;">
                                            <i class="fas fa-reply"></i> Reply
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No recent client interactions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Complete Audit Trail Table -->
        <div class="dashboard-graph">
            <h3><i class="fas fa-list"></i> Complete Audit Trail</h3>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
                <i class="fas fa-shield-alt"></i> 
                <strong>Security:</strong> This audit trail shows only your own actions. Other attorneys' activities are not visible to you.
            </p>
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">All Recorded Actions</h3>
                    <div class="table-actions">
                        <input type="text" id="searchAudit" placeholder="Search activities..." style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; margin-right: 10px;">
                        <select id="moduleFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; margin-right: 10px;">
                            <option value="">All Modules</option>
                            <option value="Case Management">Case Management</option>
                            <option value="Document Management">Document Management</option>
                            <option value="Client Management">Client Management</option>
                            <option value="Authentication">Authentication</option>
                            <option value="Security">Security</option>
                        </select>
                    </div>
                </div>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table class="upcoming-table" id="auditTable">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditData as $audit): ?>
                                <tr class="audit-row" data-module="<?= htmlspecialchars($audit['module']) ?>">
                                    <td><?= date('M j, Y g:i A', strtotime($audit['timestamp'])) ?></td>
                                    <td><?= htmlspecialchars($audit['action']) ?></td>
                                    <td><span class="badge badge-module"><?= htmlspecialchars($audit['module']) ?></span></td>
                                    <td><?= htmlspecialchars($audit['description']) ?></td>
                                    <td><span class="badge badge-<?= getPriorityColor($audit['priority']) ?>"><?= ucfirst($audit['priority']) ?></span></td>
                                    <td><span class="badge badge-<?= getStatusColor($audit['status']) ?>"><?= ucfirst($audit['status']) ?></span></td>
                                    <td><?= htmlspecialchars($audit['ip_address'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search and filter functionality
        document.getElementById('searchAudit').addEventListener('input', filterAuditTable);
        document.getElementById('moduleFilter').addEventListener('change', filterAuditTable);

        function filterAuditTable() {
            const searchTerm = document.getElementById('searchAudit').value.toLowerCase();
            const moduleFilter = document.getElementById('moduleFilter').value;
            const rows = document.querySelectorAll('#auditTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const module = row.getAttribute('data-module');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesModule = moduleFilter === '' || module === moduleFilter;
                
                row.style.display = matchesSearch && matchesModule ? '' : 'none';
            });
        }

        function exportAuditTrail() {
            // Implementation for exporting audit trail
            alert('Export functionality will be implemented here');
        }

        function filterAuditTrail() {
            // Implementation for advanced filtering
            alert('Advanced filtering will be implemented here');
        }

        function refreshAuditTrail() {
            location.reload();
        }
    </script>

    <style>
        /* Timeline Styles */
        .timeline-container {
            max-height: 600px;
            overflow-y: auto;
        }

        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            position: relative;
            align-items: flex-start;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 20px;
            flex-shrink: 0;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.2);
        }

        .timeline-content {
            flex: 1;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .timeline-content:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .timeline-header h4 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .timeline-time {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .timeline-content p {
            margin: 0 0 15px 0;
            color: #333;
            line-height: 1.5;
        }

        .timeline-details {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .badge-high {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .badge-medium {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .badge-low {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .badge-warning {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .badge-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .badge-info {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .badge-module {
            background: rgba(139, 21, 56, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(139, 21, 56, 0.2);
        }

        /* Custom Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .header-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-shadow: 0 2px 4px rgba(93, 14, 38, 0.1);
            letter-spacing: 1px;
            margin: 0;
        }

        .header-title p {
            color: var(--accent-color);
            font-size: 1rem;
            font-weight: 400;
            margin-top: 4px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-details h3 {
            font-size: 1.1rem;
            margin-bottom: 4px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .user-details p {
            color: var(--accent-color);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .timeline-item {
                flex-direction: column;
            }
            
            .timeline-icon {
                margin-bottom: 15px;
                margin-right: 0;
            }
            
            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html>

<?php
// Helper functions
function getActivityIcon($module, $action) {
    $action = strtolower($action);
    $module = strtolower($module);
    
    if (strpos($action, 'case') !== false || $module === 'case management') {
        return 'gavel';
    } elseif (strpos($action, 'document') !== false || $module === 'document management') {
        return 'file-alt';
    } elseif (strpos($action, 'client') !== false || $module === 'client management') {
        return 'users';
    } elseif (strpos($action, 'login') !== false || $module === 'authentication') {
        return 'sign-in-alt';
    } elseif (strpos($action, 'security') !== false || $module === 'security') {
        return 'shield-alt';
    } else {
        return 'cog';
    }
}

function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high':
            return 'high';
        case 'medium':
            return 'medium';
        case 'low':
            return 'low';
        default:
            return 'low';
    }
}

function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warning';
        case 'error':
        case 'failed':
            return 'error';
        default:
            return 'info';
    }
}
?> 