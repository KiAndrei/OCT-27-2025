<?php
session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}

// Set cache control headers to prevent back button access after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config.php';
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
        $profile_image = 'images/default-avatar.jpg';
    }
// Total cases handled
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$total_cases = $stmt->get_result()->fetch_row()[0];
// Total documents uploaded
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_documents WHERE uploaded_by=? AND uploaded_by IS NOT NULL AND uploaded_by > 0");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$total_documents = $stmt->get_result()->fetch_row()[0];
// Total clients
$stmt = $conn->prepare("SELECT uf.id FROM user_form uf WHERE uf.user_type='client' AND uf.id IN (SELECT client_id FROM attorney_cases WHERE attorney_id=?)");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$clients_res = $stmt->get_result();
$total_clients = $clients_res ? $clients_res->num_rows : 0;
// Upcoming hearings (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE attorney_id=? AND date BETWEEN ? AND ? AND type IN ('Hearing','Appointment')");
$stmt->bind_param("iss", $attorney_id, $today, $next_week);
$stmt->execute();
$upcoming_events = $stmt->get_result()->fetch_row()[0];
// Case status distribution for this attorney
$status_counts = [];
$stmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM attorney_cases WHERE attorney_id=? GROUP BY status");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $status_counts[$row['status'] ?? 'Unknown'] = (int)$row['cnt'];
}

// If no cases exist, show default statuses with sample data
if (empty($status_counts)) {
    $status_counts = [
        'Active' => 3,
        'Pending' => 2,
        'Closed' => 1
    ];
}
// Upcoming hearings table (next 5)
$hearings = [];
$stmt = $conn->prepare("SELECT cs.*, ac.title as case_title, 
                        CASE 
                            WHEN uf.name IS NOT NULL AND uf.name != '' THEN uf.name
                            WHEN cs.walkin_client_name IS NOT NULL AND cs.walkin_client_name != '' THEN cs.walkin_client_name
                            ELSE 'Unknown Client'
                        END as client_name 
                        FROM case_schedules cs 
                        LEFT JOIN attorney_cases ac ON cs.case_id = ac.id 
                        LEFT JOIN user_form uf ON cs.client_id = uf.id 
                        WHERE cs.attorney_id=? AND cs.date >= ? 
                        ORDER BY cs.date, cs.start_time LIMIT 5");
$stmt->bind_param("is", $attorney_id, $today);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $hearings[] = $row;
// Recent activity (last 5): cases, documents, messages, hearings
$recent = [];
// Cases
$stmt = $conn->prepare("SELECT id, title, created_at FROM attorney_cases WHERE attorney_id=? ORDER BY created_at DESC LIMIT 2");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Case','title'=>$row['title'],'date'=>$row['created_at']];
// Documents
$stmt = $conn->prepare("SELECT file_name, upload_date FROM attorney_documents WHERE uploaded_by=? ORDER BY upload_date DESC LIMIT 2");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Document','title'=>$row['file_name'],'date'=>$row['upload_date']];
// Messages
$stmt = $conn->prepare("SELECT message, sent_at FROM attorney_messages WHERE attorney_id=? ORDER BY sent_at DESC LIMIT 1");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Message','title'=>mb_strimwidth($row['message'],0,30,'...'),'date'=>$row['sent_at']];
// Hearings
$stmt = $conn->prepare("SELECT title, date, start_time FROM case_schedules WHERE attorney_id=? ORDER BY date DESC, start_time DESC LIMIT 1");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Hearing','title'=>$row['title'],'date'=>$row['date'].' '.$row['start_time']];
// Sort by date desc
usort($recent, function($a,$b){ return strtotime($b['date'])-strtotime($a['date']); });
$recent = array_slice($recent,0,5);
// Cases per month (bar chart)
$cases_per_month = array_fill(1,12,0);
$year = date('Y');
$stmt = $conn->prepare("SELECT MONTH(created_at) as m, COUNT(*) as cnt FROM attorney_cases WHERE attorney_id=? AND YEAR(created_at)=? GROUP BY m");
$stmt->bind_param("ii", $attorney_id, $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $cases_per_month[(int)$row['m']] = (int)$row['cnt'];

// Additional attorney-specific data
// Today's appointments
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE attorney_id=? AND date = ?");
$stmt->bind_param("is", $attorney_id, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_row()[0];

// This month's cases
$this_month = date('Y-m');
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("is", $attorney_id, $this_month);
$stmt->execute();
$this_month_cases = $stmt->get_result()->fetch_row()[0];

// Active cases (not closed)
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND status NOT IN ('Closed', 'Completed')");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$active_cases = $stmt->get_result()->fetch_row()[0];

// Top clients by case count
$top_clients = [];
$stmt = $conn->prepare("SELECT uf.name, COUNT(ac.id) as case_count FROM user_form uf 
                        LEFT JOIN attorney_cases ac ON uf.id = ac.client_id 
                        WHERE uf.user_type = 'client' AND ac.attorney_id = ?
                        GROUP BY uf.id, uf.name 
                        ORDER BY case_count DESC LIMIT 5");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $top_clients[] = $row;
}

// Case type distribution
$case_type_counts = [];
$stmt = $conn->prepare("SELECT case_type, COUNT(*) as cnt FROM attorney_cases WHERE attorney_id=? GROUP BY case_type");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $case_type_counts[$row['case_type'] ?? 'Unknown'] = (int)$row['cnt'];
}

// If no case types exist, show default types with sample data
if (empty($case_type_counts)) {
    $case_type_counts = [
        'Criminal' => 2,
        'Civil' => 3,
        'Family' => 2,
        'Corporate' => 1
    ];
}

// eFiling Statistics
$total_efilings = 0;
$successful_efilings = 0;
$failed_efilings = 0;
$this_month_efilings = 0;

// Total eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$total_efilings = $stmt->get_result()->fetch_row()[0];

// Successful eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=? AND status='Sent'");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$successful_efilings = $stmt->get_result()->fetch_row()[0];

// Failed eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=? AND status='Failed'");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$failed_efilings = $stmt->get_result()->fetch_row()[0];

// This month's eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("is", $attorney_id, $this_month);
$stmt->execute();
$this_month_efilings = $stmt->get_result()->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attorney Dashboard - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/unread-messages.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="attorney_dashboard.php" class="active"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php"><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php" class="has-badge"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
        </ul>
    </div>
    <div class="main-content">
        <?php 
        $page_title = 'Attorney Dashboard';
        $page_subtitle = 'Overview of your cases, clients, and schedule';
        include 'components/profile_header.php'; 
        ?>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome back, <?= htmlspecialchars($_SESSION['attorney_name']) ?>!</h2>
                <p>Here's your overview for today</p>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="current-time"></span>
                </div>
            </div>
            <div class="welcome-actions">
                <a href="attorney_cases.php" class="welcome-btn primary">
                    <i class="fas fa-plus"></i>
                    New Case
                </a>
                <a href="attorney_schedule.php" class="welcome-btn secondary">
                    <i class="fas fa-calendar-plus"></i>
                    Schedule Meeting
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_cases) ?></div>
                    <div class="stat-label">Total Cases</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= number_format($active_cases) ?> active</span>
                    </div>
                </div>
            </div>

            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_documents) ?></div>
                    <div class="stat-label">My Documents</div>
                    <div class="stat-details">
                        <span class="stat-detail">Uploaded</span>
                    </div>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_clients) ?></div>
                    <div class="stat-label">My Clients</div>
                    <div class="stat-details">
                        <span class="stat-detail">Active</span>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($upcoming_events) ?></div>
                    <div class="stat-label">Upcoming Events</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= number_format($today_appointments) ?> today</span>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($this_month_cases) ?></div>
                    <div class="stat-label">This Month</div>
                    <div class="stat-details">
                        <span class="stat-detail">New cases</span>
                    </div>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($active_cases) ?></div>
                    <div class="stat-label">Active Cases</div>
                    <div class="stat-details">
                        <span class="stat-detail">In progress</span>
                    </div>
                </div>
            </div>

            <div class="stat-card efiling">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_efilings) ?></div>
                    <div class="stat-label">eFiling Submissions</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= number_format($successful_efilings) ?> sent</span>
                        <?php if ($failed_efilings > 0): ?>
                            <span class="stat-detail failed"><?= number_format($failed_efilings) ?> failed</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="analytics-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Analytics & Reports</h2>
                <p>Insights into your caseload and activity</p>
            </div>
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Case Status Distribution</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('caseStatusChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="caseStatusChart"></canvas>
                        <?php if ($total_cases == 0): ?>
                        <div class="chart-sample-notice" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 10px; border-radius: 5px; font-size: 12px; color: #666; text-align: center; pointer-events: none;">
                            <i class="fas fa-info-circle"></i> Sample data shown
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> Cases Per Month (<?= $year ?>)</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('casesPerMonthChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="casesPerMonthChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-doughnut"></i> Case Type Distribution</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('caseTypeChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="caseTypeChart"></canvas>
                        <?php if ($total_cases == 0): ?>
                        <div class="chart-sample-notice" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 10px; border-radius: 5px; font-size: 12px; color: #666; text-align: center; pointer-events: none;">
                            <i class="fas fa-info-circle"></i> Sample data shown
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-area"></i> Case Performance</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('casePerformanceChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="casePerformanceChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- My Schedule -->
        <div class="schedules-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> My Schedule</h3>
            </div>
            <?php if (count($hearings) > 0): ?>
                <table class="upcoming-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Case</th>
                            <th>Client</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hearings as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars($h['date']) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($h['start_time']))) ?></td>
                            <td><?= htmlspecialchars($h['type']) ?></td>
                            <td><?= htmlspecialchars($h['location']) ?></td>
                            <td><?= htmlspecialchars($h['case_title'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($h['client_name']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($h['status'] ?? 'scheduled') ?>"><?= htmlspecialchars($h['status'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No upcoming hearings or appointments</p></div>
            <?php endif; ?>
        </div>

        <!-- Activity & Performance Section -->
        <div class="activity-schedule-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Activity & Performance</h2>
                <p>Recent activity and client insights</p>
            </div>
            <div class="activity-schedule-grid">
                <div class="activities-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                    </div>
                    <?php if (count($recent) > 0): ?>
                        <?php foreach ($recent as $r): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?= strtolower($r['type']) === 'case' ? 'gavel' : (strtolower($r['type']) === 'document' ? 'file-alt' : (strtolower($r['type']) === 'message' ? 'comment' : 'calendar')) ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?= htmlspecialchars($r['title']) ?></div>
                                    <div class="activity-meta">
                                        <span class="activity-type"><?= ucfirst($r['type']) ?></span>
                                    </div>
                                    <div class="activity-time"><?= date('M j, Y g:i A', strtotime($r['date'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-inbox"></i><p>No recent activity</p></div>
                    <?php endif; ?>
                </div>

                <div class="clients-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Top Clients</h3>
                    </div>
                    <?php if (count($top_clients) > 0): ?>
                        <?php foreach ($top_clients as $index => $client): ?>
                            <div class="client-item">
                                <div class="client-rank"><?= $index + 1 ?></div>
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="client-content">
                                    <div class="client-name"><?= htmlspecialchars($client['name']) ?></div>
                                    <div class="client-cases"><?= number_format($client['case_count']) ?> cases</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-users"></i><p>No clients yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats Section -->
        <div class="quick-stats-section">
            <div class="section-header">
                <h2><i class="fas fa-tachometer-alt"></i> Quick Stats</h2>
                <p>Key performance indicators</p>
            </div>
            <div class="quick-stats-grid">
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($total_cases) ?></div>
                        <div class="quick-stat-label">Total Cases</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($today_appointments) ?></div>
                        <div class="quick-stat-label">Today's Appointments</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($this_month_cases) ?></div>
                        <div class="quick-stat-label">This Month</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($active_cases) ?></div>
                        <div class="quick-stat-label">Active Cases</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($this_month_efilings) ?></div>
                        <div class="quick-stat-label">This Month eFilings</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .welcome-section{background:linear-gradient(135deg,#5D0E26 0%,#8B1538 100%);border-radius:16px;padding:32px;margin-bottom:32px;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 8px 32px rgba(93,14,38,.3)}
        .welcome-content h2{font-size:2rem;font-weight:700;margin:0 0 8px 0;font-family:'Playfair Display',serif}
        .welcome-content p{opacity:.9;margin:0 0 12px 0}
        .current-time{display:flex;align-items:center;gap:8px;opacity:.85}
        .welcome-actions{display:flex;gap:12px}
        .welcome-btn{padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;display:flex;gap:8px;align-items:center;transition:all .2s ease}
        .welcome-btn.primary{background:#fff;color:#5D0E26}
        .welcome-btn.secondary{background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3)}
        .welcome-btn:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.2)}

        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-bottom:32px}
        .stat-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.08);border:1px solid #f0f0f0;display:flex;gap:20px;align-items:center;position:relative}
        .stat-card .stat-icon{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#5D0E26,#8B1538);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem}
        .stat-card.success .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.warning .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.purple .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.info .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.danger .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.efiling .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-value{font-size:2.2rem;font-weight:700;color:#333;line-height:1}
        .stat-label{color:#666;margin-top:4px}
        .stat-details{margin-top:8px;color:#27ae60;font-size:.85rem}
        .stat-detail.failed{color:#dc3545;margin-left:8px}

        .analytics-section,.activity-schedule-section,.quick-stats-section{margin-bottom:32px}
        .section-header{text-align:center;margin-bottom:20px}
        .section-header h2{display:flex;gap:10px;align-items:center;justify-content:center;margin:0 0 6px 0;color:#333}
        .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .activity-schedule-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .quick-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
        .chart-card,.schedules-card,.activities-card,.clients-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.08);border:1px solid #f0f0f0}
        .chart-header,.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f0f0f0}
        .chart-btn{width:32px;height:32px;border:0;border-radius:8px;background:linear-gradient(135deg,#8B1538,#A91B47);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s ease}
        .chart-btn:hover{background:linear-gradient(135deg,#A91B47,#C41E3A);transform:translateY(-1px);box-shadow:0 4px 12px rgba(139,21,56,.3)}
        .chart-container{height:300px;position:relative;background:linear-gradient(135deg,#fafafa 0%,#f5f5f5 100%);border-radius:12px;padding:16px;box-shadow:inset 0 2px 8px rgba(0,0,0,.05)}
        .upcoming-table{width:100%;border-collapse:collapse}
        .upcoming-table th,.upcoming-table td{padding:12px;border-bottom:1px solid #f5f5f5;text-align:left;font-size:.95rem}
        .status-badge{padding:4px 10px;border-radius:12px;font-size:.8rem}
        .status-scheduled{background:#f8f9fa;color:#555}
        .activities-list{max-height:none;overflow-y:visible}
        .activity-item{display:flex;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid #f8f9fa}
        .activity-icon{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#5D0E26,#8B1538);display:flex;align-items:center;justify-content:center;color:#fff}
        .activity-title{font-weight:600;color:#333}
        .activity-time{color:#999;font-size:.85rem}
        .empty-state{text-align:center;padding:40px 20px;color:#666}
        .empty-state i{font-size:3rem;margin-bottom:12px;opacity:.3}

        .client-item{display:flex;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid #f8f9fa}
        .client-rank{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#5D0E26,#8B1538);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600}
        .client-avatar{width:40px;height:40px;border-radius:50%;background:#f8f9fa;display:flex;align-items:center;justify-content:center;color:#666}
        .client-name{font-weight:600;color:#333}
        .client-cases{color:#666;font-size:.85rem}

        .quick-stat-item{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid #f0f0f0;display:flex;gap:16px;align-items:center}
        .quick-stat-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#5D0E26,#8B1538);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem}
        .quick-stat-value{font-size:1.8rem;font-weight:700;color:#333;line-height:1}
        .quick-stat-label{color:#666;font-size:.9rem;margin-top:4px}

        @media(max-width:1200px){.charts-grid,.activity-schedule-grid{grid-template-columns:1fr}}
    </style>

    <script>
        // Update current time
        function updateTime(){
            const now=new Date();
            document.getElementById('current-time').textContent=now.toLocaleString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'});
        }
        updateTime();setInterval(updateTime,60000);
        // Case Status Chart
        const ctx = document.getElementById('caseStatusChart').getContext('2d');
        const caseStatusData = {
            labels: <?= json_encode(array_keys($status_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($status_counts)) ?>,
                backgroundColor: [
                    '#8B1538', '#28a745', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#e83e8c', '#20c997'
                ],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4,
                hoverBorderColor: '#fff'
            }]
        };
        
        const caseStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: caseStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 3
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });

        // Cases Per Month Chart
        const ctx2 = document.getElementById('casesPerMonthChart').getContext('2d');
        const monthlyData = {
            labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
            datasets: [{
                label: 'Cases Created',
                data: <?= json_encode(array_values($cases_per_month)) ?>,
                backgroundColor: [
                    'rgba(139, 21, 56, 0.8)', 'rgba(169, 27, 71, 0.8)', 'rgba(196, 30, 58, 0.8)', 
                    'rgba(220, 20, 60, 0.8)', 'rgba(178, 34, 34, 0.8)', 'rgba(139, 0, 0, 0.8)',
                    'rgba(128, 0, 32, 0.8)', 'rgba(114, 47, 55, 0.8)', 'rgba(139, 21, 56, 0.8)', 
                    'rgba(169, 27, 71, 0.8)', 'rgba(196, 30, 58, 0.8)', 'rgba(220, 20, 60, 0.8)'
                ],
                borderColor: '#8B1538',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: [
                    'rgba(139, 21, 56, 1)', 'rgba(169, 27, 71, 1)', 'rgba(196, 30, 58, 1)', 
                    'rgba(220, 20, 60, 1)', 'rgba(178, 34, 34, 1)', 'rgba(139, 0, 0, 1)',
                    'rgba(128, 0, 32, 1)', 'rgba(114, 47, 55, 1)', 'rgba(139, 21, 56, 1)', 
                    'rgba(169, 27, 71, 1)', 'rgba(196, 30, 58, 1)', 'rgba(220, 20, 60, 1)'
                ]
            }]
        };
        
        const casesPerMonthChart = new Chart(ctx2, {
            type: 'bar',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stepSize: 1,
                        max: function(context) {
                            const max = Math.max(...context.chart.data.datasets[0].data);
                            return max > 0 ? Math.ceil(max * 1.2) : 5; // Add 20% padding, minimum 5
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 6
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Chart export
        function exportChart(id){const c=document.getElementById(id);const a=document.createElement('a');a.href=c.toDataURL('image/png');a.download=id+"_chart.png";a.click();}

        // Case Type Chart
        const ctx3 = document.getElementById('caseTypeChart').getContext('2d');
        const caseTypeData = {
            labels: <?= json_encode(array_keys($case_type_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($case_type_counts)) ?>,
                backgroundColor: [
                    '#8B1538', '#28a745', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#e83e8c', '#20c997'
                ],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4,
                hoverBorderColor: '#fff'
            }]
        };
        
        const caseTypeChart = new Chart(ctx3, {
            type: 'doughnut',
            data: caseTypeData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 3
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });

        // Case Performance Chart
        const ctx4 = document.getElementById('casePerformanceChart').getContext('2d');
        const casePerformanceData = {
            labels: ["Active", "Closed", "Pending"],
            datasets: [{
                label: 'Cases',
                data: [
                    <?= $active_cases ?>,
                    <?= max(0, $total_cases - $active_cases) ?>,
                    <?= isset($status_counts['Pending']) ? $status_counts['Pending'] : 0 ?>
                ],
                backgroundColor: [
                    'rgba(139, 21, 56, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderColor: [
                    '#8B1538',
                    '#28a745',
                    '#ffc107'
                ],
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: [
                    'rgba(139, 21, 56, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)'
                ]
            }]
        };
        
        const casePerformanceChart = new Chart(ctx4, {
            type: 'bar',
            data: casePerformanceData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stepSize: 1,
                        max: function(context) {
                            const max = Math.max(...context.chart.data.datasets[0].data);
                            return max > 0 ? Math.ceil(max * 1.2) : 5; // Add 20% padding, minimum 5
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 6
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Fix chart responsiveness on window resize
        window.addEventListener('resize', function() {
            caseStatusChart.resize();
            casesPerMonthChart.resize();
            caseTypeChart.resize();
            casePerformanceChart.resize();
        });

        // Force chart resize after page load
        setTimeout(function() {
            caseStatusChart.resize();
            casesPerMonthChart.resize();
            caseTypeChart.resize();
            casePerformanceChart.resize();
        }, 100);
    </script>

    <!-- First-Time Login Password Change Modal -->
    <div id="firstLoginModal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 10002;">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); padding: 0; max-width: 400px; width: 90%; position: relative; animation: modalSlideIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); overflow: hidden;">
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; text-align: center; position: relative;">
                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-key" style="color: white; font-size: 20px;"></i>
                </div>
                <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; margin: 0 0 5px 0; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);">Change Password Now</h2>
                <p style="font-size: 14px; font-weight: 500; opacity: 0.9; margin: 0;">Security Requirement</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 25px; text-align: center;">
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px; text-align: left;">
                    <h4 style="color: #856404; margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>
                        Security Notice
                    </h4>
                    <p style="color: #856404; margin: 0; font-size: 12px; line-height: 1.4;">
                        For security purposes, you must change your password before accessing the system. 
                        This is a one-time requirement for all new accounts.
                    </p>
                </div>
                
                <p style="color: #5D0E26; font-size: 16px; font-weight: 600; margin: 0 0 10px 0; line-height: 1.4;">
                    Welcome to Opiña Law Office!
                </p>
                <p style="color: #666; font-size: 14px; margin: 0 0 25px 0; line-height: 1.5;">
                    Please change your password to continue using your dashboard.
                </p>
                
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="redirectToProfilePasswordChange();" 
                            style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; padding: 12px 25px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);">
                        <i class="fas fa-key" style="margin-right: 6px;"></i>
                        Change Password
                    </button>
                    <button onclick="logoutUser();" 
                            style="background: #6c757d; color: white; border: none; padding: 12px 25px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.3s ease;">
                        <i class="fas fa-sign-out-alt" style="margin-right: 6px;"></i>
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        // First-Time Login Modal Functions
        function showFirstLoginModal() {
            const modal = document.getElementById('firstLoginModal');
            modal.style.display = 'flex';
        }

        function closeFirstLoginModal() {
            const modal = document.getElementById('firstLoginModal');
            modal.style.display = 'none';
        }

        function redirectToProfilePasswordChange() {
            // Close the first-time login modal
            closeFirstLoginModal();
            
            // Call the existing changePassword function from profile header
            setTimeout(() => {
                if (typeof changePassword === 'function') {
                    changePassword();
                } else {
                    // Fallback: try to find and click the change password link
                    const passwordChangeLink = document.querySelector('a[onclick*="changePassword"]');
                    if (passwordChangeLink) {
                        passwordChangeLink.click();
                    }
                }
            }, 100);
        }

        function logoutUser() {
            if (confirm('Are you sure you want to logout? You will need to change your password on your next login.')) {
                // Log out the user
                fetch('logout.php', {
                    method: 'POST'
                })
                .then(() => {
                    window.location.href = 'login_form.php';
                })
                .catch(error => {
                    console.error('Error:', error);
                    window.location.href = 'login_form.php';
                });
            }
        }

        // Show modal if session flag is set
        <?php if (isset($_SESSION['show_first_login_modal'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showFirstLoginModal();
        });
        <?php unset($_SESSION['show_first_login_modal']); ?>
        <?php endif; ?>
    </script>
</body>
</html> 