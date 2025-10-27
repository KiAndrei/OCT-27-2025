<?php
session_start();
if (!isset($_SESSION['client_name']) || $_SESSION['user_type'] !== 'client') {
    header('Location: login_form.php');
    exit();
}

// Set cache control headers to prevent back button access after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config.php';
$client_id = $_SESSION['user_id'];

// Check if this is a first-time login AND account was created by attorney/admin
$stmt = $conn->prepare("SELECT profile_image, first_login, created_by FROM user_form WHERE id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$is_first_login = false;
$show_password_modal = false;
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $is_first_login = ($row['first_login'] == 1);
    // Only show password change modal if account was created by attorney/admin (not self-registered)
    $show_password_modal = $is_first_login && !is_null($row['created_by']);
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }
// Total cases
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE client_id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$total_cases = $stmt->get_result()->fetch_row()[0];
// Total documents (from attorney_documents related to client's cases)
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_document_activity ada 
    INNER JOIN attorney_cases ac ON ada.case_id = ac.id 
    WHERE ac.client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$total_documents = $stmt->get_result()->fetch_row()[0];
// Upcoming hearings (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE client_id=? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $client_id, $today, $next_week);
$stmt->execute();
$upcoming_hearings = $stmt->get_result()->fetch_row()[0];

// Recent chat: get the latest message between this client and any attorney
$recent_chat = null;
$stmt = $conn->prepare("SELECT message, sent_at, 'client' as sender, recipient_id as attorney_id FROM client_messages WHERE client_id=?
        UNION ALL
        SELECT message, sent_at, 'attorney' as sender, attorney_id as attorney_id FROM attorney_messages WHERE recipient_id=?
        ORDER BY sent_at DESC LIMIT 1");
$stmt->bind_param("ii", $client_id, $client_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) {
    $recent_chat = $row;
}

// Case status distribution for this client
$status_counts = [];
$stmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM attorney_cases WHERE client_id=? GROUP BY status");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $status_counts[$row['status'] ?? 'Unknown'] = (int)$row['cnt'];
}

// If no cases exist, show sample data
if (empty($status_counts) || array_sum($status_counts) == 0) {
    $status_counts = [
        'Active' => 8,
        'Pending' => 5,
        'Completed' => 12
    ];
}

// Recent activities
$recent_activities = [];
$stmt = $conn->prepare("SELECT 'case' as type, title, created_at FROM attorney_cases WHERE client_id=? ORDER BY created_at DESC LIMIT 3");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recent_activities[] = ['type' => 'Case', 'title' => $row['title'], 'date' => $row['created_at']];
}

// Sort by date
usort($recent_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$recent_activities = array_slice($recent_activities, 0, 5);

// Monthly case trends for this client
$monthly_cases = array_fill(1, 12, 0);
$year = date('Y');
$stmt = $conn->prepare("SELECT MONTH(created_at) as month, COUNT(*) as cnt FROM attorney_cases WHERE client_id=? AND YEAR(created_at) = ? GROUP BY month");
$stmt->bind_param("ii", $client_id, $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $monthly_cases[(int)$row['month']] = (int)$row['cnt'];
}

// If no monthly data exists, show sample data
if (array_sum($monthly_cases) == 0) {
    $monthly_cases = [2, 3, 5, 2, 6, 4, 7, 5, 3, 6, 4, 2]; // Sample data for each month
}

// Fetch recent announcements
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 2");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $announcements[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/data-privacy-modal.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/unread-messages.js?v=<?= time() ?>"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
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

    </style>
</head>
<body>
    <div class="sidebar client-sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="client_dashboard.php" class="active" title="View your case overview, statistics, and recent activities">
                    <div class="button-content">
                        <i class="fas fa-home"></i>
                        <div class="text-content">
                            <span>Dashboard</span>
                            <small>Overview & Statistics</small>
                        </div>
                    </div>
                    
                </a>
            </li>
            <li>
                <a href="client_documents.php" title="Generate legal documents like affidavits and sworn statements">
                    <div class="button-content">
                        <i class="fas fa-file-alt"></i>
                        <div class="text-content">
                            <span>Document Generation</span>
                            <small>Create Legal Documents</small>
                        </div>
                    </div>
                    
                </a>
            </li>
            <li>
                <a href="client_cases.php" title="Track your legal cases, view case details, and upload documents">
                    <div class="button-content">
                        <i class="fas fa-gavel"></i>
                        <div class="text-content">
                            <span>My Cases</span>
                            <small>Track Legal Cases</small>
                        </div>
                    </div>
                    
                </a>
            </li>
            <li>
                <a href="client_schedule.php" title="View your upcoming appointments, hearings, and court schedules">
                    <div class="button-content">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="text-content">
                            <span>My Schedule</span>
                            <small>Appointments & Hearings</small>
                        </div>
                    </div>
                    
                </a>
            </li>
            <li>
                <a href="client_messages.php" class="has-badge" title="Communicate with your attorney and legal team">
                    <div class="button-content">
                        <i class="fas fa-envelope"></i>
                        <div class="text-content">
                            <span>Messages</span>
                            <small>Chat with Attorney</small>
                        </div>
                    </div>
                    <span class="unread-message-badge hidden" id="unreadMessageBadge">0</span>
                </a>
            </li>
            <li>
                <a href="client_about.php" title="Learn more about Opiña Law Office and our team">
                    <div class="button-content">
                        <i class="fas fa-info-circle"></i>
                        <div class="text-content">
                            <span>About Us</span>
                            <small>Our Story & Team</small>
                        </div>
                    </div>
                    
                </a>
            </li>
        </ul>
    </div>
    <div class="main-content">
        <?php 
        $page_title = 'Client Dashboard';
        $page_subtitle = 'Overview of your cases and legal matters';
        include 'components/profile_header.php'; 
        ?>
        

        <!-- Statistics Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                </div>
                <div class="card-title">Total Cases</div>
                <div class="card-value"><?= number_format($total_cases) ?></div>
                <div class="card-description">Your cases</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="card-title">Upcoming Schedules</div>
                <div class="card-value"><?= number_format($upcoming_hearings) ?></div>
                <div class="card-description">Next 7 days</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-comments"></i>
                    </div>

                </div>
                <div class="card-title">Recent Chat</div>
                <?php if ($recent_chat): ?>
                    <div style="font-size: 1rem; margin-bottom: 4px; color: var(--primary-color); font-weight: 600;">
                        <?= $recent_chat['sender'] === 'client' ? 'You' : 'Attorney' ?>
                    </div>
                    <div style="color: #666; font-size: 0.9rem; line-height: 1.4;">
                        <?= htmlspecialchars(mb_strimwidth($recent_chat['message'], 0, 50, '...')) ?>
                    </div>
                <?php else: ?>
                    <div style="color: #888; font-size: 0.9rem;">No recent chat yet</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements and Weather Container -->
        <div style="display: grid; grid-template-columns: 1fr calc(33.333% - 16px); gap: 24px; margin-bottom: 32px; align-items: stretch;">
            <!-- Announcements Container -->
            <div class="dashboard-graph" style="grid-column: 1;">
                <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
                <?php if (count($announcements) > 0): ?>
                    <div id="announcementCarousel" style="position: relative;">
                        <?php foreach ($announcements as $index => $announcement): ?>
                            <div class="announcement-slide" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                                <!-- Large Image -->
                                <div style="width: 100%; height: 400px; border-radius: 12px; margin-bottom: 16px; overflow: hidden; cursor: pointer; position: relative;" onclick="openImageModal('<?= htmlspecialchars($announcement['image_path']) ?>')">
                                    <?php if ($announcement['image_path'] && file_exists($announcement['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($announcement['image_path']) ?>" alt="Announcement Image" style="width: 100%; height: 100%; object-fit: contain;">
                                        <!-- Zoom Icon Overlay -->
                                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 8px; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                            <i class="fas fa-search-plus"></i>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 4rem;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Content Below Image -->
                                <div style="text-align: left;">
                                    <p style="color: #555; line-height: 1.6; margin: 0;">
                                        <?= htmlspecialchars($announcement['description']) ?>
                                    </p>
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
            </div>

            <!-- Weather Container -->
            <div class="dashboard-graph" style="grid-column: 2;">
                <h3><i class="fas fa-cloud-sun"></i> Weather</h3>
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; height: 100%; border: 1px solid #e5e7eb; display: flex; flex-direction: column;">
                    <!-- Weather Icon -->
                    <div style="font-size: 2.5rem; color: #8B1538; margin-bottom: 16px;">
                        <i class="fas fa-cloud-sun"></i>
                    </div>
                    
                    <!-- Temperature -->
                    <div style="font-size: 2rem; font-weight: 600; color: #333; margin-bottom: 8px;">
                        28°C
                    </div>
                    
                    <!-- Weather Description -->
                    <div style="color: #666; margin-bottom: 20px; font-weight: 400; font-size: 0.95rem;">
                        Partly Cloudy
                    </div>
                    
                    <!-- Location -->
                    <div style="color: #888; margin-bottom: auto; font-size: 0.85rem;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 5px;"></i>
                        Cabuyao, Laguna
                    </div>
                    
                    <!-- Weather Details -->
                    <div style="border-top: 1px solid #f0f0f0; padding: 16px 0 24px 0; margin-top: auto;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666; font-size: 0.85rem;">
                                Feels like
                            </span>
                            <span style="font-weight: 500; font-size: 0.85rem; color: #333;">30°C</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666; font-size: 0.85rem;">
                                Humidity
                            </span>
                            <span style="font-weight: 500; font-size: 0.85rem; color: #333;">65%</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666; font-size: 0.85rem;">
                                Wind Speed
                            </span>
                            <span style="font-weight: 500; font-size: 0.85rem; color: #333;">12 km/h</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666; font-size: 0.85rem;">
                                Visibility
                            </span>
                            <span style="font-weight: 500; font-size: 0.85rem; color: #333;">10 km</span>
                        </div>
                        
                        <!-- Update Time -->
                        <div style="margin-top: 16px; color: #999; font-size: 0.75rem;">
                            Last updated: 2 minutes ago
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 32px;">
            <div class="dashboard-graph">
                <h3><i class="fas fa-chart-pie"></i> Case Status Overview</h3>
                <div class="chart-container">
                    <canvas id="caseStatusChart"></canvas>
                    <?php 
                    $pie_total = array_sum($status_counts);
                    // Show overlay ONLY for sample data (total = 25)
                    if ($pie_total == 25): ?>
                    <div class="sample-data-overlay">
                        <i class="fas fa-info-circle"></i>
                        <span>Sample data shown</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-graph">
            <h3><i class="fas fa-clock"></i> Recent Activities</h3>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div style="display: flex; align-items: center; padding: 16px; border-bottom: 1px solid #f0f0f0; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                            <div style="width: 40px; height: 40px; background: var(--gradient-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin-right: 16px;">
                                <i class="fas fa-gavel"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--primary-color); margin-bottom: 4px;">
                                    <?= htmlspecialchars($activity['title']) ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #666;">
                                    Case • <?= date('M j, Y g:i A', strtotime($activity['date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Data Privacy Waiver Modal -->
    <div id="dataPrivacyModal">
        <div id="dataPrivacyModalContent">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-shield-alt"></i> Data Privacy Notice
                </h2>
            </div>
            <div class="modal-body">
                <div class="modal-content-inner">
                    <div class="office-header">
                        <div class="office-logo">
                            <img src="images/logo.jpg" alt="Opiña Law Office Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                        </div>
                        <h3 class="office-title">OPIÑA LAW OFFICE</h3>
                        <p class="office-subtitle">Data Privacy Notice & Consent</p>
                    </div>

                    <div class="important-notice">
                        <h4>
                            <i class="fas fa-exclamation-triangle"></i> IMPORTANT NOTICE
                        </h4>
                        <p>
                            By accessing this system, you acknowledge that you have read, understood, and agree to the terms outlined in this Data Privacy Notice.
                        </p>
                    </div>

                    <div class="section">
                        <h4>I. Collection of Personal Information</h4>
                        <p>
                            We collect personal information necessary for providing legal services:
                        </p>
                        <ul>
                            <li>Personal identification details</li>
                            <li>Legal case information and documents</li>
                            <li>Communication records and messages</li>
                            <li>Schedule and appointment information</li>
                            <li>Government-issued identification documents</li>
                        </ul>
                    </div>

                    <div class="section">
                        <h4>II. Purpose of Data Processing</h4>
                        <p>
                            Your personal information is processed for:
                        </p>
                        <ul>
                            <li>Providing legal consultation and representation</li>
                            <li>Managing your legal cases and documents</li>
                            <li>Communicating case updates and appointments</li>
                            <li>Complying with legal and regulatory requirements</li>
                            <li>Maintaining attorney-client privilege</li>
                        </ul>
                    </div>

                    <div class="section">
                        <h4>III. Data Security & Confidentiality</h4>
                        <p>
                            We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. All data is handled with strict confidentiality in accordance with attorney-client privilege.
                        </p>
                    </div>

                    <div class="section">
                        <h4>IV. Your Rights</h4>
                        <p>
                            Under the Data Privacy Act of 2012, you have the right to:
                        </p>
                        <ul>
                            <li>Access and request copies of your personal information</li>
                            <li>Correct or update inaccurate information</li>
                            <li>Withdraw consent (subject to legal obligations)</li>
                            <li>File complaints with the National Privacy Commission</li>
                        </ul>
                    </div>

                    <div class="consent-section">
                        <h4>
                            <i class="fas fa-exclamation-triangle"></i> CONSENT DECLARATION
                        </h4>
                        <p>
                            I acknowledge that I have read and understood this Data Privacy Notice and consent to the collection, processing, and use of my personal information as described herein.
                        </p>
                        <div class="checkbox-container">
                            <input type="checkbox" id="consentCheckbox">
                            <label for="consentCheckbox">
                                I agree to the terms and conditions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="acceptButton" class="accept-button" onclick="acceptDataPrivacy()" disabled>
                        <i class="fas fa-check"></i> Accept
                    </button>
            </div>
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

    <script>
        // Case Status Chart
        const ctx = document.getElementById('caseStatusChart').getContext('2d');
        const caseStatusData = {
            labels: <?= json_encode(array_keys($status_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($status_counts)) ?>,
                backgroundColor: [
                    '#5D0E26', '#27ae60', '#3498db', '#f39c12', '#e74c3c', '#9b59b6', '#8B1538', '#8B4A6B'
                ],
                borderWidth: 2,
                borderColor: '#fff'
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
                }
            }
        });

        // Fix chart responsiveness on window resize
        window.addEventListener('resize', function() {
            caseStatusChart.resize();
        });

        // Force chart resize after page load
        setTimeout(function() {
            caseStatusChart.resize();
        }, 100);

        // Announcement Carousel
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
                closeDataPrivacyModal();
            }
        });

        // Data Privacy Modal Functions
        function showDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            const modalContent = document.getElementById('dataPrivacyModalContent');
            
            if (modal && modalContent) {
                modal.style.display = 'block';
                
                // Reset animation
                modalContent.style.animation = 'none';
                modalContent.offsetHeight; // Trigger reflow
                modalContent.style.animation = 'slideDownFromTop 0.5s ease-out';
                
                // Prevent body scroll when modal is open
                document.body.style.overflow = 'hidden';
            }
        }

        function closeDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            if (modal) {
                modal.style.display = 'none';
                // Restore body scroll
                document.body.style.overflow = 'auto';
            }
        }

        function acceptDataPrivacy() {
            const checkbox = document.getElementById('consentCheckbox');
            if (!checkbox.checked) {
                alert('Please check the consent checkbox to proceed.');
                return;
            }
            
            // Store acceptance in sessionStorage (only for current session)
            sessionStorage.setItem('dataPrivacyAccepted', 'true');
            sessionStorage.setItem('dataPrivacyAcceptedDate', new Date().toISOString());
            
            // Close the modal
            closeDataPrivacyModal();
        }

        // Handle checkbox change to enable/disable accept button
        function toggleAcceptButton() {
            const checkbox = document.getElementById('consentCheckbox');
            const acceptButton = document.getElementById('acceptButton');
            
            if (checkbox.checked) {
                acceptButton.style.background = '#5D0E26';
                acceptButton.style.color = 'white';
                acceptButton.style.cursor = 'pointer';
                acceptButton.disabled = false;
            } else {
                acceptButton.style.background = '#ccc';
                acceptButton.style.color = '#666';
                acceptButton.style.cursor = 'not-allowed';
                acceptButton.disabled = true;
            }
        }

        // Check if data privacy has been accepted on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if this is a fresh login from PHP session
            const isFreshLogin = <?= isset($_SESSION['fresh_login']) && $_SESSION['fresh_login'] ? 'true' : 'false' ?>;
            
            // If this is a fresh login, clear any previous acceptance
            if (isFreshLogin) {
                sessionStorage.removeItem('dataPrivacyAccepted');
                // Clear the PHP session flag
                <?php unset($_SESSION['fresh_login']); ?>
            }
            
            // Show modal if not accepted in current login session
            if (!sessionStorage.getItem('dataPrivacyAccepted')) {
            setTimeout(function() {
                showDataPrivacyModal();
                
                // Add event listener to checkbox
                const checkbox = document.getElementById('consentCheckbox');
                if (checkbox) {
                    checkbox.addEventListener('change', toggleAcceptButton);
                }
            }, 500);
            }
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('dataPrivacyModal');
            if (event.target === modal) {
                // Don't allow closing by clicking outside - user must explicitly accept or cancel
                // closeDataPrivacyModal();
            }
        });

    </script>

    <!-- First-Time Login Password Change Modal -->
    <div id="firstLoginModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
        <div style="background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%); border-radius: 24px; width: 90%; max-width: 500px; height: auto; position: relative; box-shadow: 0 25px 80px rgba(93, 14, 38, 0.3), 0 0 0 1px rgba(255,255,255,0.1); border: 1px solid rgba(93, 14, 38, 0.1); overflow: hidden; display: flex; flex-direction: column;">
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); padding: 20px 32px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-key" style="color: white; font-size: 16px;"></i>
                        </div>
                        <div>
                            <h2 style="color: white; margin: 0; font-size: 1.4em; font-weight: 700;">Change Password</h2>
                            <p style="color: rgba(255,255,255,0.9); margin: 2px 0 0 0; font-size: 0.85em;">Security requirement for new accounts</p>
                        </div>
                    </div>
                    <button onclick="logoutUser();" style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border: none; border-radius: 8px; color: white; font-size: 16px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div style="flex: 1; padding: 24px; display: flex; flex-direction: column;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-shield-alt" style="color: #5D0E26; font-size: 24px;"></i>
                    </div>
                    <h3 style="color: #2c3e50; margin: 0 0 10px 0; font-size: 18px; font-weight: 600;">Security First</h3>
                    <p style="color: #6c757d; margin: 0 0 15px 0; font-size: 14px; line-height: 1.5;">
                        For your security, you must change your temporary password before accessing the system.
                    </p>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                        <p style="margin: 0; font-size: 13px; color: #495057; font-weight: 500;">
                            <i class="fas fa-info-circle" style="color: #5D0E26; margin-right: 6px;"></i>
                            This is a one-time security requirement
                        </p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: auto; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <button onclick="logoutUser();" style="padding: 10px 20px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; cursor: pointer; font-weight: 600; color: #374151; transition: all 0.3s ease; font-size: 13px;" onmouseover="this.style.borderColor='#d1d5db'; this.style.background='#f9fafb'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">Cancel</button>
                    <button onclick="redirectToProfilePasswordChange();" style="padding: 10px 20px; background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 13px;" onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'" onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'">Change Password</button>
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

        // Show modal if this is a first-time login AND account was created by attorney/admin
        <?php if ($show_password_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Show waiver first, then password change modal after waiver is accepted
            const isFreshLogin = <?= isset($_SESSION['fresh_login']) && $_SESSION['fresh_login'] ? 'true' : 'false' ?>;
            
            if (isFreshLogin) {
                // Show waiver first
                setTimeout(function() {
                    showDataPrivacyModal();
                }, 500);
                
                // Override the accept button to show password change modal after waiver
                const originalAcceptFunction = window.acceptDataPrivacy;
                window.acceptDataPrivacy = function() {
                    // Call original function
                    originalAcceptFunction();
                    
                    // Show password change modal after waiver is accepted
                    setTimeout(function() {
                        showFirstLoginModal();
                    }, 300);
                };
            } else {
                // If not fresh login but first login, show password change modal directly
                showFirstLoginModal();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html> 
