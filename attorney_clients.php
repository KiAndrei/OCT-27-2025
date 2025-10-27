<?php
// AJAX handler for modal content (MUST be before any HTML output)
if (isset($_GET['ajax_client_details']) && isset($_GET['client_id'])) {
    require_once 'config.php';
    session_start();
    $attorney_id = $_SESSION['user_id'];
    $cid = intval($_GET['client_id']);
    
    // Get client info with profile image
    $stmt = $conn->prepare("SELECT id, name, email, phone_number, profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $cinfo = $stmt->get_result()->fetch_assoc();
    
    // Get all cases for this client-attorney pair
    $cases = [];
    $stmt = $conn->prepare("SELECT * FROM attorney_cases WHERE attorney_id=? AND client_id=? ORDER BY created_at DESC");
    $stmt->bind_param("ii", $attorney_id, $cid);
    $stmt->execute();
    $cres = $stmt->get_result();
    while ($row = $cres->fetch_assoc()) $cases[] = $row;
    
    // Get recent messages (last 10)
    $msgs = [];
    $stmt = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?
        UNION ALL
        SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?
        ORDER BY sent_at DESC LIMIT 10");
    $stmt->bind_param("iiii", $cid, $attorney_id, $attorney_id, $cid);
    $stmt->execute();
    $mres = $stmt->get_result();
    while ($row = $mres->fetch_assoc()) $msgs[] = $row;
    
    // Get client statistics
    $total_cases = count($cases);
    $active_cases = count(array_filter($cases, function($c) { return $c['status'] === 'Active'; }));
    $pending_cases = count(array_filter($cases, function($c) { return $c['status'] === 'Pending'; }));
    $closed_cases = count(array_filter($cases, function($c) { return $c['status'] === 'Closed'; }));
    ?>
    
    <div class="client-modal-header" style="z-index: 9999 !important;">
        <div class="client-profile">
            <div class="client-avatar">
                <?php if ($cinfo['profile_image'] && file_exists($cinfo['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($cinfo['profile_image']) ?>" alt="Client Profile">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="client-info">
                <h2><?= htmlspecialchars($cinfo['name']) ?></h2>
                <div class="client-contact">
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($cinfo['email']) ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($cinfo['phone_number']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="client-stats">
            <div class="stat-item">
                <div class="stat-number"><?= $total_cases ?></div>
                <div class="stat-label">Total Cases</div>
            </div>
            <div class="stat-item active">
                <div class="stat-number"><?= $active_cases ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-item pending">
                <div class="stat-number"><?= $pending_cases ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-item closed">
                <div class="stat-number"><?= $closed_cases ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>
    </div>

    <div class="modal-sections" style="z-index: 9999 !important;">
        <div class="section">
            <h3><i class="fas fa-gavel"></i> Case Overview</h3>
        <div class="case-list">
            <?php if (count($cases) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No cases for this client yet.</p>
                        <button class="btn btn-primary" onclick="window.open('attorney_cases.php', '_blank')">
                            <i class="fas fa-plus"></i> Create New Case
                        </button>
                    </div>
            <?php else: foreach ($cases as $case): ?>
                <div class="case-item">
                        <div class="case-header">
                            <div class="case-title">
                                <span class="case-id">#<?= htmlspecialchars($case['id']) ?></span>
                                <h4><?= htmlspecialchars($case['title']) ?></h4>
                            </div>
                            <span class="case-status status-<?= strtolower($case['status']) ?>">
                                <?= htmlspecialchars($case['status']) ?>
                            </span>
                        </div>
                        <div class="case-details">
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($case['case_type']) ?></span>
                            <?php if ($case['next_hearing']): ?>
                                <span><i class="fas fa-calendar"></i> Next Hearing: <?= date('M j, Y', strtotime($case['next_hearing'])) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-clock"></i> Created: <?= date('M j, Y', strtotime($case['created_at'])) ?></span>
                        </div>
                        <div class="case-actions">
                            <button class="btn btn-secondary btn-sm" onclick="viewCaseDetails(<?= $case['id'] ?>)">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

        <div class="section">
            <h3><i class="fas fa-comments"></i> Recent Communication</h3>
    <div class="chat-area">
        <?php if (count($msgs) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p>No messages yet. Start a conversation with your client.</p>
                        <button class="btn btn-primary" onclick="window.location.href='attorney_messages.php?client_id=<?= $cid ?>'">
                            <i class="fas fa-envelope"></i> Send Message
                        </button>
                    </div>
                <?php else: ?>
                    <div class="message-list">
                        <?php foreach (array_reverse($msgs) as $m): ?>
                            <div class="message-bubble <?= $m['sender'] === 'attorney' ? 'sent' : 'received' ?>">
                                <div class="message-header">
                                    <span class="sender"><?= $m['sender'] === 'attorney' ? 'You' : 'Client' ?></span>
                                    <span class="time"><?= date('M j, g:i A', strtotime($m['sent_at'])) ?></span>
                                </div>
                                <div class="message-content"><?= htmlspecialchars($m['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="message-actions">
                        <button class="btn btn-primary" onclick="window.location.href='attorney_messages.php?client_id=<?= $cid ?>'">
                            <i class="fas fa-reply"></i> Continue Conversation
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            </div>
    </div>
    <?php
    exit();
}

session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}

require_once 'config.php';
$attorney_id = $_SESSION['user_id'];

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

// Fetch all clients (shared clients) with enhanced data
$clients = [];
$stmt = $conn->prepare('SELECT uf.id, uf.name, uf.email, uf.phone_number, uf.profile_image FROM user_form uf WHERE uf.user_type=\'client\'');
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Total clients
$total_clients = count($clients);

// Active cases (all cases since all clients are shared)
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE status='Active'");
$stmt->execute();
$active_cases = $stmt->get_result()->fetch_row()[0];

// Unread messages for this attorney (from all clients)
$stmt = $conn->prepare("SELECT COUNT(*) FROM client_messages WHERE recipient_id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$unread_messages = $stmt->get_result()->fetch_row()[0];

// Upcoming appointments (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE attorney_id=? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $attorney_id, $today, $next_week);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_row()[0];

// Get all cases (all attorneys can see all cases)
$stmt = $conn->prepare("
    SELECT ac.id, ac.title, ac.case_type, ac.status, ac.description, ac.created_at,
           ac.attorney_id, c.name as client_name, a.name as attorney_name
    FROM attorney_cases ac
    LEFT JOIN user_form c ON ac.client_id = c.id
    LEFT JOIN user_form a ON ac.attorney_id = a.id
    ORDER BY ac.created_at DESC
");
$stmt->execute();
$cases_result = $stmt->get_result();
$cases = [];
while ($row = $cases_result->fetch_assoc()) {
    $cases[] = $row;
}

// For each client, get their active cases count and last contact
$client_details = [];
foreach ($clients as $c) {
    $cid = $c['id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND client_id=? AND status='Active'");
    $stmt->bind_param("ii", $attorney_id, $cid);
    $stmt->execute();
    $active = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND client_id=? AND status='Pending'");
    $stmt->bind_param("ii", $attorney_id, $cid);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND client_id=? AND status='Closed'");
    $stmt->bind_param("ii", $attorney_id, $cid);
    $stmt->execute();
    $closed = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $conn->prepare("SELECT sent_at FROM (
        SELECT sent_at FROM client_messages WHERE client_id=? AND recipient_id=?
        UNION ALL
        SELECT sent_at FROM attorney_messages WHERE attorney_id=? AND recipient_id=?
        ORDER BY sent_at DESC LIMIT 1
    ) as t ORDER BY sent_at DESC LIMIT 1");
    $stmt->bind_param("iiii", $cid, $attorney_id, $attorney_id, $cid);
    $stmt->execute();
    $last_msg = $stmt->get_result()->fetch_row()[0] ?? '-';
    
    $status = $active > 0 ? 'Active' : ($pending > 0 ? 'Pending' : 'Inactive');
    $client_details[] = [
        'id' => $cid,
        'name' => $c['name'],
        'email' => $c['email'],
        'phone' => $c['phone_number'],
        'profile_image' => $c['profile_image'],
        'active_cases' => $active,
        'pending_cases' => $pending,
        'closed_cases' => $closed,
        'total_cases' => $active + $pending + $closed,
        'last_contact' => $last_msg,
        'status' => $status
    ];
}

// Sort clients by status (Active first, then Pending, then Inactive)
usort($client_details, function($a, $b) {
    $status_order = ['Active' => 1, 'Pending' => 2, 'Inactive' => 3];
    return $status_order[$a['status']] - $status_order[$b['status']];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Clients - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/case-modal-styles.css?v=<?= time() ?>">
    <style>
        .case-overview {
            padding: 0;
        }
        
        .case-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 12px 20px;
            border-radius: 12px 12px 0 0;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            flex-shrink: 0;
        }
        
        .case-title-section h3 {
            margin: 0 0 6px 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .case-meta {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .case-id {
            background: #5D0E26;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-badge.status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.status-closed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .case-details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding: 12px;
            background: #ffffff;
            height: calc(80vh - 180px);
            overflow: hidden;
        }
        
        .detail-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            border: 1px solid rgba(93, 14, 38, 0.08);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .detail-section h3 {
            margin: 0 0 8px 0;
            color: #5D0E26;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid rgba(93, 14, 38, 0.05);
            flex-shrink: 0;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: #666;
            font-size: 0.75rem;
            min-width: 100px;
        }
        
        .detail-label i {
            color: #5D0E26;
            width: 12px;
            text-align: center;
            font-size: 0.7rem;
        }
        
        .detail-value {
            color: #2c3e50;
            font-weight: 500;
            text-align: right;
            max-width: 150px;
            word-wrap: break-word;
            font-size: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .case-details-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                padding: 16px;
            }
            
            .case-header {
                padding: 16px;
            }
            
            .case-title-section h3 {
                font-size: 1.3rem;
            }
            
            .case-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
        .client-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #8b1538 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .client-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .client-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .quick-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .client-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1200px) {
            .client-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .client-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .client-grid {
                grid-template-columns: 1fr;
            }
        }

        .client-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            cursor: pointer;
        }

        .client-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }

        .client-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .client-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .client-info h3 {
            margin: 0 0 0.25rem 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .client-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .client-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .stat-item.active {
            background: rgba(39, 174, 96, 0.1);
            border-color: rgba(39, 174, 96, 0.2);
        }

        .stat-item.pending {
            background: rgba(243, 156, 18, 0.1);
            border-color: rgba(243, 156, 18, 0.2);
        }

        .stat-item.closed {
            background: rgba(108, 117, 125, 0.1);
            border-color: rgba(108, 117, 125, 0.2);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
            font-weight: 700;
        }

        .client-header p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .quick-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .client-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1200px) {
            .client-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .client-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .client-grid {
                grid-template-columns: 1fr;
            }
        }

        .client-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            cursor: pointer;
        }

        .client-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }

        .client-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .client-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .client-info h3 {
            margin: 0 0 0.25rem 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .client-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .client-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .stat-item.active {
            background: rgba(39, 174, 96, 0.1);
            border-color: rgba(39, 174, 96, 0.2);
        }

        .stat-item.pending {
            background: rgba(243, 156, 18, 0.1);
            border-color: rgba(243, 156, 18, 0.2);
        }

        .stat-item.closed {
            background: rgba(108, 117, 125, 0.1);
            border-color: rgba(108, 117, 125, 0.2);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .client-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .client-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .client-status.status-active {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .client-status.status-pending {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .client-status.status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }

        /* Enhanced Modal Styles */
        .modal-bg {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 800px;
            width: 90%;
            margin: 40px auto;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
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
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(124,15,47,0.3);
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
        
        .alert-modal-btn:active {
            transform: translateY(-1px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 25px;
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border: none;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .close-modal:hover {
            background: #e9ecef;
            color: #333;
            transform: rotate(90deg);
        }

        .client-modal-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .client-profile {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .client-profile .client-avatar {
            width: 80px;
            height: 80px;
        }

        .client-profile .client-info h2 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .client-contact {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .client-contact span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.95rem;
        }

        .client-contact i {
            width: 16px;
            color: var(--primary-color);
        }

        .client-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .modal-sections {
            padding: 1rem 2rem 2rem 2rem;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section h3 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0 1rem 0;
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .section h3 i {
            color: var(--primary-color);
        }

        .case-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .case-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .case-item:hover {
            background: #e9ecef;
            border-color: var(--primary-color);
        }

        .case-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .case-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .case-id {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .case-title h4 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .case-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .case-details {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .case-details span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .case-details i {
            color: var(--primary-color);
            width: 14px;
        }

        .case-actions {
            display: flex;
            justify-content: flex-end;
        }

        .chat-area {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
        }

        .message-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .message-bubble {
            margin-bottom: 1rem;
            max-width: 80%;
        }

        .message-bubble.sent {
            margin-left: auto;
        }

        .message-bubble.received {
            margin-right: auto;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }

        .message-bubble.sent .message-header {
            color: var(--primary-color);
        }

        .message-bubble.received .message-header {
            color: #666;
        }

        .message-content {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            word-break: break-word;
        }

        .message-bubble.sent .message-content {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .message-actions {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        /* Dashboard Cards Styling */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-cards .card {
            background: white;
            border-radius: 16px;
            padding: 2rem 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 2rem;
            min-height: 180px;
        }

        .dashboard-cards .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .dashboard-cards .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .dashboard-cards .card-content {
            flex: 1;
            min-width: 0;
        }

        .dashboard-cards .card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #5D0E26;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .dashboard-cards .card-title {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .dashboard-cards .card-description {
            font-size: 0.9rem;
            color: #999;
        }

        /* Responsive Design */
        @media (max-width: 1400px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .client-grid {
                grid-template-columns: 1fr;
            }

            .client-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

        .modal-content {
                width: 95%;
                margin: 20px auto;
            }

            .client-profile {
                flex-direction: column;
                text-align: center;
            }

            .case-details {
                flex-direction: column;
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .client-header {
                padding: 1.5rem;
            }

            .client-header h1 {
                font-size: 2rem;
            }

            .quick-actions {
                flex-direction: column;
            }

            .quick-actions .btn {
                justify-content: center;
            }
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
            <li><a href="attorney_cases.php"><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php" class="active"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'My Clients';
        $page_subtitle = 'Manage your client relationships and case portfolios with professional care';
        include 'components/profile_header.php'; 
        ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <button onclick="openAddClientModal()" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Add New Client
            </button>
            <a href="attorney_cases.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i>
                New Case
            </a>
            <a href="attorney_messages.php" class="btn btn-secondary">
                <i class="fas fa-envelope"></i>
                Send Message
            </a>
            <a href="attorney_schedule.php" class="btn btn-secondary">
                <i class="fas fa-calendar"></i>
                Schedule Meeting
            </a>
            <button class="btn btn-secondary" onclick="refreshClientData()">
                <i class="fas fa-sync-alt"></i>
                Refresh Data
            </button>
        </div>

        <!-- Statistics Overview -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= number_format($total_clients) ?></div>
                    <div class="card-title">Total Clients</div>
                    <div class="card-description">Active relationships</div>
                </div>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= number_format($active_cases) ?></div>
                    <div class="card-title">Active Cases</div>
                    <div class="card-description">Ongoing proceedings</div>
                </div>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= number_format($unread_messages) ?></div>
                    <div class="card-title">Unread Messages</div>
                    <div class="card-description">Need attention</div>
                </div>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= number_format($upcoming_appointments) ?></div>
                    <div class="card-title">Upcoming</div>
                    <div class="card-description">Next 7 days</div>
                </div>
            </div>
        </div>

        <!-- Client Grid -->
        <div class="client-grid">
            <?php if (count($client_details) === 0): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-users" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <h3>No Clients Yet</h3>
                    <p>You haven't been assigned any clients yet. Start by creating your first case.</p>
                    <a href="attorney_cases.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Your First Case
                    </a>
            </div>
            <?php else: ?>
                    <?php foreach ($client_details as $c): ?>
                    <div class="client-card" data-client-id="<?= $c['id'] ?>" data-client-name="<?= htmlspecialchars($c['name']) ?>">
                        <div class="client-card-header">
                            <div class="client-avatar">
                                <?php if ($c['profile_image'] && file_exists($c['profile_image'])): ?>
                                    <img src="<?= htmlspecialchars($c['profile_image']) ?>" alt="Client Profile">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="client-info">
                                <h3><?= htmlspecialchars($c['name']) ?></h3>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($c['email']) ?></p>
                            </div>
                        </div>

                        <div class="client-stats">
                            <div class="stat-item active">
                                <div class="stat-number"><?= $c['active_cases'] ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                            <div class="stat-item pending">
                                <div class="stat-number"><?= $c['pending_cases'] ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-item closed">
                                <div class="stat-number"><?= $c['closed_cases'] ?></div>
                                <div class="stat-label">Closed</div>
                            </div>
                        </div>

                        <div class="client-actions">
                            <button class="btn btn-primary btn-sm" onclick="viewClientDetails(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Client Modal -->
    <div id="addClientModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
        <div style="background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%); border-radius: 24px; width: 90%; max-width: 800px; height: 85vh; position: relative; box-shadow: 0 25px 80px rgba(93, 14, 38, 0.3), 0 0 0 1px rgba(255,255,255,0.1); border: 1px solid rgba(93, 14, 38, 0.1); overflow: hidden; display: flex; flex-direction: column;">
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); padding: 20px 32px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user-plus" style="color: white; font-size: 16px;"></i>
                        </div>
                        <div>
                            <h2 style="color: white; margin: 0; font-size: 1.4em; font-weight: 700;">Add New Client</h2>
                            <p style="color: rgba(255,255,255,0.9); margin: 2px 0 0 0; font-size: 0.85em;">Create client account for walk-in clients</p>
                        </div>
                    </div>
                    <button onclick="closeAddClientModal()" style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border: none; border-radius: 8px; color: white; font-size: 16px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div style="flex: 1; padding: 24px; display: flex; flex-direction: column; overflow-y: auto;">
            
            <form id="addClientForm" style="display: flex; flex-direction: column;">
                <!-- Error Message Display -->
                <div id="errorMessage" style="display: none; background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #dc2626; border: 1px solid #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: 500; font-size: 0.9em;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i>
                    <span id="errorText"></span>
                </div>
                
                <!-- Form Fields Container - 3 Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <!-- Row 1: Name Fields -->
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Surname</label>
                        <input type="text" name="surname" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter surname" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">First Name</label>
                        <input type="text" name="first_name" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter first name" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Middle Name</label>
                        <input type="text" name="middle_name" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter middle name" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <!-- Row 2: Contact Fields -->
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Email Address</label>
                        <input type="email" name="email" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter email address" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Email</label>
                        <input type="email" name="confirm_email" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm email address" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Phone Number</label>
                        <input type="text" name="phone_number" id="phoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter phone number (09xxxxxxxxx)" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); validatePhoneNumber();" onkeypress="return event.charCode >= 48 && event.charCode <= 57" onfocus="this.style.borderColor='#5D0E26'" onblur="validatePhoneNumber();">
                    </div>
                    
                    <!-- Row 3: Password Fields -->
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Phone</label>
                        <input type="text" name="confirm_phone_number" id="confirmPhoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm phone number" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" onkeypress="return event.charCode >= 48 && event.charCode <= 57" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Password</label>
                        <div style="position: relative;">
                            <input type="password" name="password" id="password" required style="width: 100%; padding: 10px 35px 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter password" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, ''); checkPasswordStrength()">
                            <i class="fas fa-eye" id="togglePassword" onclick="togglePasswordVisibility('password', 'togglePassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; cursor: pointer; font-size: 12px; padding: 4px;" onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6b7280'"></i>
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Password</label>
                        <div style="position: relative;">
                            <input type="password" name="confirm_password" id="confirm_password" required style="width: 100%; padding: 10px 35px 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm password" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, '')">
                            <i class="fas fa-eye" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; cursor: pointer; font-size: 12px; padding: 4px;" onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6b7280'"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Password Strength Indicator -->
                <div id="passwordStrengthIndicator" style="display: none; margin-bottom: 20px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
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
                            <span style="margin-left: 6px; color: #64748b;">Uppercase letter</span>
                        </div>
                        <div style="margin-bottom: 4px;">
                            <span id="lowercaseCheck" style="color: #ef4444;">✗</span>
                            <span style="margin-left: 6px; color: #64748b;">Lowercase letter</span>
                        </div>
                        <div style="margin-bottom: 4px;">
                            <span id="numberCheck" style="color: #ef4444;">✗</span>
                            <span style="margin-left: 6px; color: #64748b;">Number</span>
                        </div>
                        <div>
                            <span id="specialCheck" style="color: #ef4444;">✗</span>
                            <span style="margin-left: 6px; color: #64748b;">Special char (!@#$%^&*()-_+={}[]:";'<>.,?/\|~)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: auto; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeAddClientModal()" style="padding: 10px 20px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; cursor: pointer; font-weight: 600; color: #374151; transition: all 0.3s ease; font-size: 13px;" onmouseover="this.style.borderColor='#d1d5db'; this.style.background='#f9fafb'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">Cancel</button>
                    <button type="submit" style="padding: 10px 20px; background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 13px;" onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'" onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'">Add Client</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Enhanced Client Details Modal -->
    <div class="modal-bg" id="clientModalBg" style="z-index: 9999 !important;">
        <div class="modal-content" id="client-modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <button class="close-modal" onclick="closeClientModal()">
                <i class="fas fa-times"></i>
            </button>
            <div id="clientModalBody">
                <!-- AJAX content here -->
            </div>
        </div>
    </div>

    <script>
    // Professional Success Modal Functions
    function showAttorneyClientSuccessModal(title, message) {
        const modal = document.createElement('div');
        modal.className = 'alert-modal';
        modal.id = 'attorneyClientSuccessModal';
        
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
                    <button class="alert-modal-btn" onclick="closeAttorneyClientSuccessModal()">
                        <i class="fas fa-check" style="margin-right: 8px;"></i>
                        Continue
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.style.display = 'block';
    }
    
    function closeAttorneyClientSuccessModal() {
        const modal = document.getElementById('attorneyClientSuccessModal');
        if (modal) {
            modal.remove();
            window.location.reload();
        }
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('attorneyClientSuccessModal');
        if (modal && event.target === modal) {
            closeAttorneyClientSuccessModal();
        }
    });

    // Add Client Modal Functions
    function openAddClientModal() {
        document.getElementById('addClientModal').style.display = 'flex';
    }
    
    function closeAddClientModal() {
        document.getElementById('addClientModal').style.display = 'none';
        hideError(); // Clear any error messages
        
        // Hide password strength indicator
        const indicator = document.getElementById('passwordStrengthIndicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
        
        // Reset form
        document.getElementById('addClientForm').reset();
    }
    
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
    
    function validateForm() {
            const surname = document.querySelector('input[name="surname"]').value.trim();
            const first_name = document.querySelector('input[name="first_name"]').value.trim();
            const middle_name = document.querySelector('input[name="middle_name"]').value.trim();
            const name = surname + ', ' + first_name + (middle_name ? ' ' + middle_name : '');
            const email = document.querySelector('input[name="email"]').value.trim();
            const confirmEmail = document.querySelector('input[name="confirm_email"]').value.trim();
            const phoneNumber = document.querySelector('input[name="phone_number"]').value.trim();
            const confirmPhoneNumber = document.querySelector('input[name="confirm_phone_number"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value.trim();
            
            // Clear previous error
            hideError();
            
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
        document.getElementById('addClientForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form first
            if (!validateForm()) {
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Adding Client...';
            submitBtn.disabled = true;
            
            // Get form data
            const formData = new FormData(this);
            
            // Combine name fields
            const surname = formData.get('surname');
            const first_name = formData.get('first_name');
            const middle_name = formData.get('middle_name');
            const fullName = surname + ', ' + first_name + (middle_name ? ' ' + middle_name : '');
            
            // Add combined name to form data
            formData.set('name', fullName);
            
            // Submit via AJAX
            fetch('add_client.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success - show success message
                    showAttorneyClientSuccessModal('Client Successfully Registered!', 'The client has been successfully registered and an email has been sent to ' + data.email + '. They can now access the system.');
                    // Close modal and reload page
                    closeAddClientModal();
                    // Reload the page to show the new client
                    window.location.reload();
                } else {
                    // Error - show error message
                    showError(data.message || 'Failed to add client. Please try again.');
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
        
        // Prevent modal from closing when clicking outside
        // Modal will only close via Cancel button or X button

        function closeCaseDetailsModal() {
            document.getElementById('caseDetailsModalBg').style.display = 'none';
            document.getElementById('caseDetailsModalBody').innerHTML = '';
        }
        
        function openCaseDetailsModal(caseId, title, clientName, description, status, caseType, createdAt) {
            const formattedDate = new Date(createdAt).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
            const html = `
                <div class="case-overview">
                    <div class="case-header">
                        <div class="case-title-section">
                            <h3>${title || 'Untitled Case'}</h3>
                            <div class="case-meta">
                                <span class="case-id">#${caseId}</span>
                                <span class="status-badge status-${(status || 'active').toLowerCase()}">${status || 'Active'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="case-details-grid">
                        <div class="detail-section">
                            <h3><i class="fas fa-info-circle"></i> Case Information</h3>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-hashtag"></i> Case ID:</span>
                                <span class="detail-value">#${caseId}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-tag"></i> Type:</span>
                                <span class="detail-value">${caseType || 'Not specified'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-calendar"></i> Date Filed:</span>
                                <span class="detail-value">${formattedDate}</span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h3><i class="fas fa-users"></i> People Involved</h3>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-user"></i> Client:</span>
                                <span class="detail-value">${clientName || 'No Client'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-gavel"></i> Attorney:</span>
                                <span class="detail-value">${clientName || 'Not assigned'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-calendar-check"></i> Next Hearing:</span>
                                <span class="detail-value">Not Scheduled</span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h3><i class="fas fa-folder-open"></i> Case Details</h3>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-file-alt"></i> Title:</span>
                                <span class="detail-value">${title || 'Untitled Case'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-info"></i> Status:</span>
                                <span class="detail-value">${status || 'Active'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-align-left"></i> Description:</span>
                                <span class="detail-value">${description || 'No description provided.'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('caseDetailsModalBody').innerHTML = html;
            document.getElementById('caseDetailsModalBg').style.display = 'block';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const caseModal = document.getElementById('caseDetailsModalBg');
            if (event.target === caseModal) {
                closeCaseDetailsModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCaseDetailsModal();
            }
        });

        function viewClientDetails(clientId, clientName) {
            fetch('attorney_clients.php?ajax_client_details=1&client_id=' + clientId)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('clientModalBody').innerHTML = html;
                    document.getElementById('clientModalBg').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
        }

        function closeClientModal() {
            document.getElementById('clientModalBg').style.display = 'none';
            document.getElementById('clientModalBody').innerHTML = '';
            document.body.style.overflow = 'auto';
        }

        function refreshClientData() {
            location.reload();
        }

        // Close modal when clicking outside
        document.getElementById('clientModalBg').addEventListener('click', function(e) {
            if (e.target === this) {
                closeClientModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeClientModal();
            }
    });
    </script>
    
    <!-- Case Details Modal -->
    <div class="modal-bg" id="caseDetailsModalBg" style="z-index: 9999 !important;">
        <div class="modal-content" id="case-details-modal-content" style="z-index: 9999 !important; max-width: 800px; width: 90%; max-height: 80vh; overflow: hidden;">
            <div class="modal-header">
                <div class="header-content">
                    <div class="case-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="header-text">
                        <h2>Case Details</h2>
                        <p>Comprehensive information about case details</p>
                    </div>
                </div>
                <button class="close-modal" onclick="closeCaseDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="caseDetailsModalBody" style="overflow: hidden; max-height: calc(80vh - 120px);">
                <!-- Case details will be populated here -->
            </div>
        </div>
    </div>

    <script src="assets/js/enhanced-case-modal.js?v=<?= time() ?>"></script>
    <script>
        // Set global variables for the enhanced modal
        window.userRole = 'attorney';
        window.userId = <?= $attorney_id ?>;
        
        // Override the existing viewCaseDetails function to use enhanced modal
        function viewCaseDetails(caseId) {
            // Find the case data from the cases array
            const cases = <?= json_encode($cases) ?>;
            const caseData = cases.find(c => c.id == caseId);

            console.log('Attorney viewCaseDetails:', {
                caseId: caseId,
                cases: cases,
                caseData: caseData,
                attorneyId: <?= $attorney_id ?>,
                caseDataAttorneyId: caseData ? caseData.attorney_id : 'undefined'
            });

            if (!caseData) {
                alert('Case not found');
                return;
            }

            // Ensure user data is set before showing modal
            enhancedCaseModal.setUserData('attorney', <?= $attorney_id ?>);

            // Use the enhanced modal
            showEnhancedCaseModal(caseData);
        }
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html> 