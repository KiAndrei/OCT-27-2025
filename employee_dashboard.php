<?php
session_start();
if (!isset($_SESSION['employee_name']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: login_form.php');
    exit();
}

// Set cache control headers to prevent back button access after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config.php';

// Employee-specific statistics
$employee_id = $_SESSION['user_id'];

// Fetch pending requests count for notification badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM client_request_form WHERE status = 'Pending'");
$stmt->execute();
$pending_requests_count = $stmt->get_result()->fetch_row()[0];

// Employee documents
$stmt = $conn->prepare("SELECT COUNT(*) FROM employee_documents WHERE uploaded_by = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$my_documents = $stmt->get_result()->fetch_row()[0];

// Total documents (employee can see total)
$stmt = $conn->prepare("SELECT COUNT(*) FROM employee_documents");
$stmt->execute();
$total_documents = $stmt->get_result()->fetch_row()[0];

// Schedules created by employee
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE created_by_employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$schedules_created = $stmt->get_result()->fetch_row()[0];

// Upcoming schedules (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE date BETWEEN ? AND ?");
$stmt->bind_param("ss", $today, $next_week);
$stmt->execute();
$upcoming_schedules_count = $stmt->get_result()->fetch_row()[0];

// Today's appointments
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE date = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_row()[0];

// This month's schedules created by employee
$this_month = date('Y-m');
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE created_by_employee_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("is", $employee_id, $this_month);
$stmt->execute();
$this_month_created = $stmt->get_result()->fetch_row()[0];

// Total attorneys (including admin)
$stmt = $conn->prepare("SELECT COUNT(*) FROM user_form WHERE user_type IN ('attorney', 'admin')");
$stmt->execute();
$total_attorneys = $stmt->get_result()->fetch_row()[0];

// Total schedules in system
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules");
$stmt->execute();
$total_schedules = $stmt->get_result()->fetch_row()[0];

// Schedule type distribution - Employee can see schedule types
$schedule_type_counts = [];
$stmt = $conn->prepare("SELECT type, COUNT(*) as cnt FROM case_schedules GROUP BY type");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $schedule_type_counts[$row['type'] ?? 'Unknown'] = (int)$row['cnt'];
}

// If no schedules exist, show sample data
if (empty($schedule_type_counts) || array_sum($schedule_type_counts) == 0) {
    $schedule_type_counts = [
        'Appointment' => 12,
        'Hearing' => 8,
        'Free Legal Advice' => 15
    ];
}

// Recent activities - Employee specific
$recent_activities = [];

// Employee's documents
$stmt = $conn->prepare("SELECT 'document' as type, file_name as title, upload_date as date, 'employee' as category FROM employee_documents WHERE uploaded_by = ? ORDER BY upload_date DESC LIMIT 3");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Employee's schedules
$stmt = $conn->prepare("SELECT 'schedule' as type, title, created_at as date, type as category FROM case_schedules WHERE created_by_employee_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Recent schedule activities
$stmt = $conn->prepare("SELECT 'schedule' as type, title, created_at as date, type as category FROM case_schedules ORDER BY created_at DESC LIMIT 2");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Sort by date
usort($recent_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$recent_activities = array_slice($recent_activities, 0, 8);

// Monthly schedule trends
$monthly_schedules = array_fill(1, 12, 0);
$year = date('Y');
$stmt = $conn->prepare("SELECT MONTH(created_at) as month, COUNT(*) as cnt FROM case_schedules WHERE YEAR(created_at) = ? GROUP BY month");
$stmt->bind_param("i", $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $monthly_schedules[(int)$row['month']] = (int)$row['cnt'];
}

// If no monthly data exists, show sample data
if (array_sum($monthly_schedules) == 0) {
    $monthly_schedules = [3, 5, 7, 4, 8, 6, 9, 7, 5, 8, 6, 4]; // Sample data for each month
}

// Fetch recent announcements
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $announcements[] = $row;
}

// Upcoming schedules with attorney info
$upcoming_schedules = [];
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $conn->prepare("SELECT cs.*, uf.name as client_name, 
                    uf_attorney.name as attorney_name FROM case_schedules cs 
                    LEFT JOIN user_form uf ON cs.client_id = uf.id 
                    LEFT JOIN user_form uf_attorney ON cs.attorney_id = uf_attorney.id 
                    WHERE cs.date BETWEEN ? AND ? 
                    ORDER BY cs.date, cs.start_time LIMIT 5");
$stmt->bind_param("ss", $today, $tomorrow);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $upcoming_schedules[] = $row;
}

// Top attorneys by schedules (including admin)
$top_attorneys = [];
$stmt = $conn->prepare("SELECT uf.name, COUNT(cs.id) as schedule_count FROM user_form uf 
                    LEFT JOIN case_schedules cs ON uf.id = cs.attorney_id 
                    WHERE uf.user_type IN ('attorney', 'admin') 
                    GROUP BY uf.id, uf.name 
                    ORDER BY schedule_count DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $top_attorneys[] = $row;
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/unread-messages.js?v=<?= time() ?>"></script>
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
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employee_dashboard.php" class="active"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
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
            <li><a href="employee_messages.php" class="has-badge"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>
    <div class="main-content">
        <?php 
        $page_title = 'Employee Dashboard';
        $page_subtitle = 'Overview of your work activities and system statistics';
        include 'components/profile_header.php'; 
        ?>
        

        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome back, <?= htmlspecialchars($_SESSION['employee_name']) ?>!</h2>
                <p>Here's your daily overview and work summary</p>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="current-time"></span>
                </div>
            </div>
            <div class="welcome-actions">
                <a href="employee_document_generation.php" class="welcome-btn primary">
                    <i class="fas fa-file-plus"></i>
                    Generate Document
                </a>
                <a href="employee_schedule.php" class="welcome-btn secondary">
                    <i class="fas fa-calendar-plus"></i>
                    View Schedule
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $my_documents ?></div>
                    <div class="stat-label">My Documents</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= $total_documents ?> total</span>
                    </div>
                </div>
            </div>

            <div class="stat-card secondary">
                <div class="stat-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $schedules_created ?></div>
                    <div class="stat-label">Schedules Created</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= $upcoming_schedules_count ?> upcoming</span>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $total_schedules ?></div>
                    <div class="stat-label">Total Schedules</div>
                    <div class="stat-details">
                        <span class="stat-detail">All appointments</span>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $today_appointments ?></div>
                    <div class="stat-label">Today's Appointments</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= date('M j, Y') ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $this_month_created ?></div>
                    <div class="stat-label">Created This Month</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= date('F Y') ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $total_attorneys ?></div>
                    <div class="stat-label">Active Attorneys</div>
                    <div class="stat-details">
                        <span class="stat-detail">Available for scheduling</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="dashboard-graph" style="margin-bottom: 32px;">
            <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
            <?php if (count($announcements) > 0): ?>
                <div id="announcementCarousel" style="position: relative;">
                    <?php foreach ($announcements as $index => $announcement): ?>
                        <div class="announcement-slide" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                            <!-- Large Image -->
                            <div style="width: 100%; height: 300px; border-radius: 12px; margin-bottom: 16px; overflow: hidden; cursor: pointer; position: relative;" onclick="openImageModal('<?= htmlspecialchars($announcement['image_path']) ?>')">
                                <?php if ($announcement['image_path'] && file_exists($announcement['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($announcement['image_path']) ?>" alt="Announcement Image" style="width: 100%; height: 100%; object-fit: cover;">
                                    <!-- Zoom Icon Overlay -->
                                    <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 8px; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                        <i class="fas fa-search-plus"></i>
                                    </div>
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Content Below Image -->
                            <div style="text-align: left;">
                                <p style="color: #555; line-height: 1.6; margin: 0;">
                                    <?= htmlspecialchars($announcement['description']) ?>
                                </p>
                                <div style="margin-top: 12px; font-size: 0.85rem; color: #888;">
                                    Uploaded: <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?>
                                </div>
                                <div style="margin-top: 12px;">
                                    <button onclick="deleteAnnouncement(<?= $announcement['id'] ?>)" 
                                        style="background: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; transition: all 0.3s ease;"
                                        onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Navigation Buttons -->
                    <?php if (count($announcements) > 1): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
                            <button onclick="previousAnnouncement()" class="nav-btn" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 8px 16px; cursor: pointer; color: #666; transition: all 0.2s;">
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <div style="display: flex; gap: 8px;">
                                <?php for ($i = 0; $i < count($announcements); $i++): ?>
                                    <button onclick="goToAnnouncement(<?= $i ?>)" class="nav-dot" style="width: 8px; height: 8px; border-radius: 50%; border: none; background: <?= $i === 0 ? 'var(--primary-color)' : '#e9ecef' ?>; cursor: pointer; transition: all 0.2s;"></button>
                                <?php endfor; ?>
                            </div>
                            <button onclick="nextAnnouncement()" class="nav-btn" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 8px 16px; cursor: pointer; color: #666; transition: all 0.2s;">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-bullhorn" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>No announcements available</p>
                </div>
            <?php endif; ?>
            
            <!-- Upload Announcement Button -->
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="openAnnouncementUploadModal()" class="btn btn-primary" style="background: var(--gradient-primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                    <i class="fas fa-plus"></i> Upload New Announcement
                </button>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="analytics-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Analytics & Reports</h2>
                <p>Visual insights into your work performance</p>
            </div>
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Schedule Type Distribution</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('scheduleTypeChart')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="scheduleTypeChart"></canvas>
                        <?php 
                        $pie_total = array_sum($schedule_type_counts);
                        // Show overlay ONLY for sample data (total = 35)
                        if ($pie_total == 35): ?>
                        <div class="sample-data-overlay">
                            <i class="fas fa-info-circle"></i>
                            <span>Sample data shown</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Monthly Schedule Trends (<?= $year ?>)</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('monthlyTrendsChart')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyTrendsChart"></canvas>
                        <?php 
                        $monthly_total = array_sum($monthly_schedules);
                        // Show overlay ONLY for sample data (total = 66)
                        if ($monthly_total == 66): ?>
                        <div class="sample-data-overlay">
                            <i class="fas fa-info-circle"></i>
                            <span>Sample data shown</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity & Schedule Section -->
        <div class="activity-schedule-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-check"></i> Today's Overview</h2>
                <p>Current activities and upcoming schedules</p>
            </div>
            <div class="activity-schedule-grid">
                <!-- Recent Activities -->
                <div class="activities-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activities</h3>
                    </div>
                    <div class="activities-list">
                        <?php if (count($recent_activities) > 0): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?= 
                                            strtolower($activity['type']) === 'case' ? 'gavel' : 
                                            (strtolower($activity['type']) === 'document' ? 'file-alt' : 
                                            (strtolower($activity['type']) === 'schedule' ? 'calendar' : 'info-circle')) 
                                        ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?= htmlspecialchars($activity['title']) ?></div>
                                        <div class="activity-meta">
                                            <span class="activity-type"><?= ucfirst($activity['type']) ?></span>
                                            <?php if (isset($activity['category'])): ?>
                                                <span class="activity-category"><?= $activity['category'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-time"><?= date('M j, Y g:i A', strtotime($activity['date'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Schedules -->
                <div class="schedules-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Schedules</h3>
                        <a href="employee_schedule.php" class="view-all-btn">View All</a>
                    </div>
                    <div class="schedules-list">
                        <?php if (count($upcoming_schedules) > 0): ?>
                            <?php foreach ($upcoming_schedules as $schedule): ?>
                                <div class="schedule-item">
                                    <div class="schedule-date">
                                        <div class="date-day"><?= date('j', strtotime($schedule['date'])) ?></div>
                                        <div class="date-month"><?= date('M', strtotime($schedule['date'])) ?></div>
                                    </div>
                                    <div class="schedule-content">
                                        <div class="schedule-title"><?= htmlspecialchars($schedule['title']) ?></div>
                                        <div class="schedule-meta">
                                            <span class="schedule-time">
                                                <i class="fas fa-clock"></i>
                                                <?= date('g:i A', strtotime($schedule['start_time'])) ?>
                                            </span>
                                            <span class="schedule-type"><?= $schedule['type'] ?></span>
                                        </div>
                                        <?php if ($schedule['attorney_name']): ?>
                                            <div class="schedule-attorney">
                                                <i class="fas fa-user-tie"></i>
                                                <?= htmlspecialchars($schedule['attorney_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming schedules</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Performance Section -->
        <div class="team-performance-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Team Performance</h2>
                <p>Track your team's productivity and achievements</p>
            </div>
            <div class="team-performance-grid">
                <!-- Top Attorneys -->
                <div class="attorneys-card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Top Attorneys</h3>
                        <a href="employee_clients.php" class="view-all-btn">View All</a>
                    </div>
                    <div class="attorneys-list">
                        <?php if (count($top_attorneys) > 0): ?>
                            <?php foreach ($top_attorneys as $index => $attorney): ?>
                                <div class="attorney-item">
                                    <div class="attorney-rank"><?= $index + 1 ?></div>
                                    <div class="attorney-avatar">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="attorney-content">
                                        <div class="attorney-name"><?= htmlspecialchars($attorney['name']) ?></div>
                                        <div class="attorney-cases"><?= $attorney['schedule_count'] ?> schedules</div>
                                    </div>
                                    <div class="attorney-badge">
                                        <i class="fas fa-medal"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-tie"></i>
                                <p>No attorney data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats Summary -->
                <div class="quick-stats-card">
                    <div class="card-header">
                        <h3><i class="fas fa-tachometer-alt"></i> Quick Stats</h3>
                    </div>
                    <div class="quick-stats-list">
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="quick-stat-content">
                                <div class="quick-stat-value"><?= $total_schedules ?></div>
                                <div class="quick-stat-label">Total Schedules</div>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="quick-stat-content">
                                <div class="quick-stat-value"><?= $today_appointments ?></div>
                                <div class="quick-stat-label">Today's Appointments</div>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="quick-stat-content">
                                <div class="quick-stat-value"><?= $this_month_created ?></div>
                                <div class="quick-stat-label">Created This Month</div>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="quick-stat-content">
                                <div class="quick-stat-value"><?= $total_attorneys ?></div>
                                <div class="quick-stat-label">Active Attorneys</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement Upload Modal -->
    <div id="announcementUploadModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 16px; padding: 32px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2 style="margin: 0; color: #5D0E26; font-size: 1.5rem; font-weight: 700;">
                    <i class="fas fa-bullhorn"></i> Upload New Announcement
                </h2>
                <button onclick="closeAnnouncementUploadModal()" style="background: none; border: none; font-size: 24px; color: #666; cursor: pointer; padding: 8px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="announcementUploadForm" enctype="multipart/form-data">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #333; margin-bottom: 8px;">
                        Announcement Description *
                    </label>
                    <textarea id="announcementDescription" name="description" required 
                        style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; resize: vertical; min-height: 100px; font-family: inherit;"
                        placeholder="Enter announcement description..."></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #333; margin-bottom: 8px;">
                        Announcement Image *
                    </label>
                    <div style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; background: #f9fafb;">
                        <input type="file" id="announcementImage" name="image" accept="image/*" required 
                            style="display: none;" onchange="previewImage(this)">
                        <label for="announcementImage" style="cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #5D0E26;"></i>
                            <div>
                                <span style="color: #5D0E26; font-weight: 600;">Click to upload image</span>
                                <p style="margin: 4px 0 0 0; color: #666; font-size: 0.9rem;">PNG, JPG, JPEG up to 5MB</p>
                            </div>
                        </label>
                    </div>
                    <div id="imagePreview" style="display: none; margin-top: 16px; text-align: center;">
                        <img id="previewImg" style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #e5e7eb;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" onclick="closeAnnouncementUploadModal()" 
                        style="padding: 12px 24px; border: 2px solid #e5e7eb; background: white; color: #666; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                        Cancel
                    </button>
                    <button type="submit" 
                        style="padding: 12px 24px; background: var(--gradient-primary); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-upload"></i> Upload Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Zoom Modal -->
    <div id="imageModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); cursor: pointer;" onclick="closeImageModal()">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%;">
            <img id="modalImage" src="" alt="Zoomed Image" style="width: 100%; height: auto; border-radius: 8px;">
        </div>
        <div style="position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; font-weight: bold; cursor: pointer;" onclick="closeImageModal()">
            <i class="fas fa-times"></i>
        </div>
    </div>

    <style>
        /* CSS Variables */
        :root {
            --primary-color: #5D0E26;
            --primary-dark: #4A0B1E;
            --secondary-color: #8B1538;
            --accent-color: #8B4A6B;
            --text-color: #333;
            --light-gray: #f8f9fa;
            --border-color: #dcdde1;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --gradient-primary: linear-gradient(135deg, #5D0E26, #8B1538);
            --gradient-secondary: linear-gradient(135deg, #8B1538, #5D0E26);
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Modern Employee Dashboard Styles */
        .welcome-section {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.3);
        }

        .welcome-content h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            font-family: 'Playfair Display', serif;
        }

        .welcome-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 16px;
        }

        .current-time {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .welcome-actions {
            display: flex;
            gap: 12px;
        }

        .welcome-btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .welcome-btn.primary {
            background: white;
            color: #5D0E26;
        }

        .welcome-btn.secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .welcome-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-card.primary .stat-icon { background: linear-gradient(135deg, #5D0E26, #8B1538); }
        .stat-card.secondary .stat-icon { background: linear-gradient(135deg, #5D0E26, #8B1538); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, #5D0E26, #8B1538); }
        .stat-card.warning .stat-icon { background: linear-gradient(135deg, #5D0E26, #8B1538); }
        .stat-card.info .stat-icon { background: linear-gradient(135deg, #5D0E26, #8B1538); }
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #5D0E26, #8B1538); }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
            line-height: 1;
        }

        .stat-label {
            font-size: 1rem;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-details {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-detail {
            font-size: 0.8rem;
            color: #27ae60;
            font-weight: 500;
        }


        .analytics-section, .activity-schedule-section, .team-performance-section {
            margin-bottom: 32px;
        }

        .section-header {
            margin-bottom: 24px;
            text-align: center;
        }

        .section-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .section-header p {
            color: #666;
            font-size: 1rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .activity-schedule-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .team-performance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .chart-card, .activities-card, .schedules-card, .attorneys-card, .quick-stats-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }

        .chart-header, .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .chart-header h3, .card-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-actions {
            display: flex;
            gap: 8px;
        }

        .chart-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: #f8f9fa;
            color: #666;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-btn:hover {
            background: #e9ecef;
            color: #333;
        }

        .view-all-btn {
            color: #5D0E26;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .view-all-btn:hover {
            color: #8B1538;
            text-decoration: none;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        .sample-data-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #5D0E26;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 13px;
            font-weight: bold;
            color: #5D0E26;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            pointer-events: none;
        }

        .sample-data-overlay i {
            font-size: 11px;
        }

        .activities-list, .schedules-list, .attorneys-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item, .schedule-item, .attorney-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f8f9fa;
            transition: background 0.2s ease;
        }

        .activity-item:last-child, .schedule-item:last-child, .attorney-item:last-child {
            border-bottom: none;
        }

        .activity-item:hover, .schedule-item:hover, .attorney-item:hover {
            background: #f8f9fa;
            margin: 0 -16px;
            padding: 16px;
            border-radius: 8px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .activity-type, .activity-category {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: #f8f9fa;
            color: #666;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #999;
        }

        .schedule-date {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .date-day {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1;
        }

        .date-month {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        .schedule-content {
            flex: 1;
        }

        .schedule-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .schedule-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 4px;
        }

        .schedule-time, .schedule-type {
            font-size: 0.8rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .schedule-attorney, .schedule-case {
            font-size: 0.8rem;
            color: #999;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .schedule-attorney {
            color: #5D0E26;
            font-weight: 600;
        }

        .schedule-attorney i {
            color: #8B1538;
        }

        .attorney-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
        }

        .attorney-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .attorney-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .attorney-content {
            flex: 1;
        }

        .attorney-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .attorney-cases {
            font-size: 0.8rem;
            color: #666;
        }

        .attorney-badge {
            color: #ffc107;
            font-size: 1.2rem;
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

        .quick-stats-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .quick-stat-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .quick-stat-item:hover {
            background: #e9ecef;
            transform: translateX(4px);
        }

        .quick-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .quick-stat-content {
            flex: 1;
        }

        .quick-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .quick-stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .charts-grid, .activity-schedule-grid, .team-performance-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

        }
    </style>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }

        // Update time every minute
        updateTime();
        setInterval(updateTime, 60000);

        // Schedule Type Chart
        const ctx = document.getElementById('scheduleTypeChart').getContext('2d');
        const scheduleTypeData = {
            labels: <?= json_encode(array_keys($schedule_type_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($schedule_type_counts)) ?>,
                backgroundColor: [
                    '#5D0E26', '#27ae60', '#3498db', '#f39c12', '#e74c3c', '#9b59b6', '#8B1538', '#8B4A6B'
                ],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4
            }]
        };
        
        const scheduleTypeChart = new Chart(ctx, {
            type: 'doughnut',
            data: scheduleTypeData,
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 3
                    }
                }
            }
        });

        // Monthly Trends Chart
        const ctx2 = document.getElementById('monthlyTrendsChart').getContext('2d');
        const monthlyData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Schedules Created',
                data: <?= json_encode(array_values($monthly_schedules)) ?>,
                backgroundColor: 'rgba(93, 14, 38, 0.1)',
                borderColor: '#5D0E26',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#5D0E26',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        };
        
        const monthlyTrendsChart = new Chart(ctx2, {
            type: 'line',
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
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });

        // Chart export function
        function exportChart(chartId) {
            const canvas = document.getElementById(chartId);
            const url = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = chartId + '_chart.png';
            link.href = url;
            link.click();
        }

        // Fix chart responsiveness on window resize
        window.addEventListener('resize', function() {
            scheduleTypeChart.resize();
            monthlyTrendsChart.resize();
        });

        // Force chart resize after page load
        setTimeout(function() {
            scheduleTypeChart.resize();
            monthlyTrendsChart.resize();
        }, 100);

        // Announcement Carousel Functions
        let currentAnnouncement = 0;
        const announcements = <?= json_encode($announcements) ?>;
        
        function showAnnouncement(index) {
            const slides = document.querySelectorAll('.announcement-slide');
            const dots = document.querySelectorAll('.nav-dot');
            
            // Hide all slides
            slides.forEach(slide => slide.style.display = 'none');
            
            // Show current slide
            if (slides[index]) {
                slides[index].style.display = 'block';
            }
            
            // Update dots
            dots.forEach((dot, i) => {
                dot.style.background = i === index ? 'var(--primary-color)' : '#e9ecef';
            });
            
            currentAnnouncement = index;
        }
        
        function nextAnnouncement() {
            const nextIndex = (currentAnnouncement + 1) % announcements.length;
            showAnnouncement(nextIndex);
        }
        
        function previousAnnouncement() {
            const prevIndex = currentAnnouncement === 0 ? announcements.length - 1 : currentAnnouncement - 1;
            showAnnouncement(prevIndex);
        }
        
        function goToAnnouncement(index) {
            showAnnouncement(index);
        }

        // Announcement Upload Modal Functions
        function openAnnouncementUploadModal() {
            document.getElementById('announcementUploadModal').style.display = 'block';
        }

        function closeAnnouncementUploadModal() {
            document.getElementById('announcementUploadModal').style.display = 'none';
            // Reset form
            document.getElementById('announcementUploadForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Image Zoom Modal Functions
        function openImageModal(imageSrc) {
            if (!imageSrc) {
                alert('No image to display');
                return;
            }
            
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            if (modal && modalImg) {
                modal.style.display = 'block';
                modalImg.src = imageSrc;
                modalImg.onload = function() {
                    console.log('Image loaded successfully');
                };
                modalImg.onerror = function() {
                    console.error('Failed to load image:', imageSrc);
                    alert('Failed to load image');
                };
                
                // Prevent body scroll when modal is open
                document.body.style.overflow = 'hidden';
            } else {
                console.error('Modal elements not found');
                alert('Modal not found');
            }
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.style.display = 'none';
                // Restore body scroll
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
                closeAnnouncementUploadModal();
            }
        });

        // Handle announcement form submission
        document.getElementById('announcementUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;
            
            fetch('upload_announcement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                console.log('Upload response:', data);
                if (data.includes('success') || data.includes('Announcement uploaded successfully')) {
                    alert('Announcement uploaded successfully!');
                    closeAnnouncementUploadModal();
                    // Reload the page to show the new announcement
                    location.reload();
                } else {
                    alert('Error uploading announcement: ' + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading announcement: ' + error.message);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Delete announcement function
        function deleteAnnouncement(announcementId) {
            if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('announcement_id', announcementId);
                
                fetch('delete_announcement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success') || data.includes('Announcement deleted successfully')) {
                        alert('Announcement deleted successfully!');
                        // Reload the page to show updated announcements
                        location.reload();
                    } else {
                        alert('Error deleting announcement. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting announcement. Please try again.');
                });
            }
        }

        // Add hover effects for navigation buttons
        document.addEventListener('DOMContentLoaded', function() {
            const navBtns = document.querySelectorAll('.nav-btn');
            navBtns.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.background = '#e9ecef';
                    this.style.color = '#333';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.background = '#f8f9fa';
                    this.style.color = '#666';
                });
            });
        });
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