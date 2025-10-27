<?php
// AJAX handler for modal content (MUST be before any HTML output)
if (isset($_GET['ajax_client_details']) && isset($_GET['client_id'])) {
    require_once 'config.php';
    session_start();
    $admin_id = $_SESSION['user_id'];
    $cid = intval($_GET['client_id']);
    
    // Get client info
    $stmt = $conn->prepare("SELECT id, name, email, phone_number FROM user_form WHERE id=?");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $cinfo = $stmt->get_result()->fetch_assoc();
    
    // Get all cases for this client (either assigned to admin or any attorney)
    $cases = [];
    $stmt = $conn->prepare("SELECT ac.*, a.name as attorney_name 
                          FROM attorney_cases ac 
                          LEFT JOIN user_form a ON ac.attorney_id = a.id 
                          WHERE ac.client_id=? 
                          ORDER BY ac.created_at DESC");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $cres = $stmt->get_result();
    while ($row = $cres->fetch_assoc()) $cases[] = $row;
    
    // Get recent messages (last 10) - check admin_messages table for messages to/from admin
    $msgs = [];
    $stmt = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM admin_messages WHERE recipient_id=? AND admin_id=?
        UNION ALL
        SELECT message, sent_at, 'admin' as sender FROM admin_messages WHERE admin_id=? AND recipient_id=?
        ORDER BY sent_at DESC LIMIT 10");
    $stmt->bind_param("iiii", $admin_id, $cid, $admin_id, $cid);
    $stmt->execute();
    $mres = $stmt->get_result();
    while ($row = $mres->fetch_assoc()) $msgs[] = $row;
    ?>
    <div style="background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); padding: 20px; color: white; position: relative; overflow: hidden; margin-bottom: 0;">
        <div style="position: absolute; top: -30px; right: -30px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -20px; left: -20px; width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
        <h2 style="margin: 0 0 8px 0; font-size: 1.4rem; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= htmlspecialchars($cinfo['name']) ?></h2>
        <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.9rem; opacity: 0.9;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-envelope" style="width: 14px;"></i>
                <span><?= htmlspecialchars($cinfo['email']) ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-phone" style="width: 14px;"></i>
                <span><?= htmlspecialchars($cinfo['phone_number']) ?></span>
            </div>
        </div>
    </div>
    <div style="padding: 20px;">
        <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 16px; color: #1f2937; position: relative; padding-left: 12px;">
            <span style="position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 20px; background: linear-gradient(135deg, #5D0E26, #8B1538); border-radius: 2px;"></span>
            Cases
        </h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Left Column: Case Statistics -->
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div class="case-stats" style="padding: 12px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 8px; border: 1px solid rgba(229, 231, 235, 0.8); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);">
                    <h4 style="margin: 0 0 8px 0; font-size: 0.9rem; font-weight: 600; color: #1f2937; text-align: center;">Case Statistics</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="stat-item" style="text-align: center; padding: 8px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2);">
                            <div style="font-size: 1.4rem; font-weight: 700; color: #10b981; margin-bottom: 2px;"><?= $active_count ?? 0 ?></div>
                            <div style="font-size: 0.7rem; color: #059669; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">Active</div>
                        </div>
                        <div class="stat-item" style="text-align: center; padding: 8px; background: rgba(245, 158, 11, 0.1); border-radius: 6px; border: 1px solid rgba(245, 158, 11, 0.2);">
                            <div style="font-size: 1.4rem; font-weight: 700; color: #f59e0b; margin-bottom: 2px;"><?= $pending_count ?? 0 ?></div>
                            <div style="font-size: 0.7rem; color: #d97706; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">Pending</div>
                        </div>
                        <div class="stat-item" style="text-align: center; padding: 8px; background: rgba(107, 114, 128, 0.1); border-radius: 6px; border: 1px solid rgba(107, 114, 128, 0.2);">
                            <div style="font-size: 1.4rem; font-weight: 700; color: #6b7280; margin-bottom: 2px;"><?= $closed_count ?? 0 ?></div>
                            <div style="font-size: 0.7rem; color: #4b5563; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">Closed</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Case Details -->
            <div style="display: flex; flex-direction: column; gap: 12px; height: 100%;">
                <h4 style="margin: 0; font-size: 0.9rem; font-weight: 600; color: #1f2937;">Case Details</h4>
                <div style="max-height: 400px; overflow-y: auto; padding-right: 8px;">
                    <style>
                        /* Custom scrollbar styling */
                        div::-webkit-scrollbar {
                            width: 6px;
                        }
                        div::-webkit-scrollbar-track {
                            background: rgba(0, 0, 0, 0.05);
                            border-radius: 3px;
                        }
                        div::-webkit-scrollbar-thumb {
                            background: linear-gradient(135deg, #5D0E26, #8B1538);
                            border-radius: 3px;
                        }
                        div::-webkit-scrollbar-thumb:hover {
                            background: linear-gradient(135deg, #4a0b1f, #6b0f2a);
                        }
                    </style>
                    <?php if (count($cases) === 0): ?>
                        <div style="color:#888; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">No cases for this client.</div>
                    <?php else: 
                        $active_count = 0;
                        $pending_count = 0;
                        $closed_count = 0;
                        foreach ($cases as $case) {
                            if ($case['status'] === 'Active') $active_count++;
                            elseif ($case['status'] === 'Pending') $pending_count++;
                            elseif ($case['status'] === 'Closed') $closed_count++;
                        }
                        foreach ($cases as $case): ?>
                        <div class="case-item" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 8px; padding: 12px; border: 1px solid rgba(229, 231, 235, 0.8); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1); transition: all 0.3s ease; position: relative; overflow: hidden; margin-bottom: 12px;">
                            <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #5D0E26, #8B1538); transform: scaleX(0); transition: transform 0.3s ease;"></div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 600; box-shadow: 0 2px 4px rgba(93, 14, 38, 0.3);">
                                        #<?= htmlspecialchars($case['id']) ?>
                                    </div>
                                    <h4 style="margin: 0; font-size: 0.9rem; font-weight: 600; color: #1f2937;"><?= htmlspecialchars($case['title']) ?></h4>
                                </div>
                                <span class="status-badge status-<?= strtolower($case['status']) ?>" style="padding: 4px 8px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                    <?= htmlspecialchars($case['status']) ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.75rem; color: #6b7280;">
                                <div style="display: flex; align-items: center; gap: 4px; padding: 6px; background: rgba(107, 114, 128, 0.05); border-radius: 4px; border: 1px solid rgba(107, 114, 128, 0.1);">
                                    <i class="fas fa-gavel" style="color: #5D0E26; font-size: 0.7rem;"></i>
                                    <span><strong>Type:</strong> <?= htmlspecialchars($case['case_type']) ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 4px; padding: 6px; background: rgba(107, 114, 128, 0.05); border-radius: 4px; border: 1px solid rgba(107, 114, 128, 0.1);">
                                    <i class="fas fa-user-tie" style="color: #5D0E26; font-size: 0.7rem;"></i>
                                    <span><strong>Attorney:</strong> <?= htmlspecialchars($case['attorney_name'] ?? 'Unassigned') ?></span>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 4px; padding: 6px; background: rgba(107, 114, 128, 0.05); border-radius: 4px; border: 1px solid rgba(107, 114, 128, 0.1); grid-column: 1 / -1;">
                                    <i class="fas fa-file-alt" style="color: #5D0E26; font-size: 0.7rem; margin-top: 2px;"></i>
                                    <span style="flex-grow: 1;"><strong>Description:</strong> <?= htmlspecialchars($case['description'] ?? 'No description available') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    exit();
}

require_once 'session_manager.php';
validateUserAccess('admin');

require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
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

// Fetch only clients assigned to this admin
$clients = [];
$stmt = $conn->prepare("SELECT DISTINCT uf.id, uf.name, uf.email, uf.phone_number 
                        FROM user_form uf 
                        INNER JOIN attorney_cases ac ON uf.id = ac.client_id 
                        WHERE uf.user_type='client' AND ac.attorney_id = ? 
                        ORDER BY uf.name");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Total clients
$total_clients = count($clients);

// Total active cases in the system
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE status='Active'");
$stmt->execute();
$active_cases = $stmt->get_result()->fetch_row()[0];

// Unread messages for admin (from all clients)
$stmt = $conn->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$unread_messages = $stmt->get_result()->fetch_row()[0];

// Total cases in the system
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases");
$stmt->execute();
$total_cases = $stmt->get_result()->fetch_row()[0];

// For each client, get their active cases count and last contact
$client_details = [];
foreach ($clients as $c) {
    $cid = $c['id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE client_id=? AND status='Active'");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $active = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE client_id=?");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $total_client_cases = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $conn->prepare("SELECT sent_at FROM (
        SELECT sent_at FROM admin_messages WHERE recipient_id=? AND admin_id=?
        UNION ALL
        SELECT sent_at FROM admin_messages WHERE admin_id=? AND recipient_id=?
        ORDER BY sent_at DESC LIMIT 1
    ) as t ORDER BY sent_at DESC LIMIT 1");
    $stmt->bind_param("iiii", $admin_id, $cid, $admin_id, $cid);
    $stmt->execute();
    $last_msg = $stmt->get_result()->fetch_row()[0] ?? '-';
    
    $status = $active > 0 ? 'Active' : 'Inactive';
    $client_details[] = [
        'id' => $cid,
        'name' => $c['name'],
        'email' => $c['email'],
        'phone' => $c['phone_number'],
        'active_cases' => $active,
        'total_cases' => $total_client_cases,
        'last_contact' => $last_msg,
        'status' => $status
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <style>
        /* Enhanced Client Management Styles - Maroon Theme */
        .client-header {
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 100%);
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
            border-radius: 12px;
            padding: 0.75rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            cursor: pointer;
        }

        .client-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: #1976d2;
        }

        .client-card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .client-info h3 {
            margin: 0 0 0.125rem 0;
            color: #8B1538;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .client-info p {
            margin: 0;
            color: #666;
            font-size: 0.8rem;
        }

        .client-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.25rem;
            background: #f8f9fa;
            border-radius: 6px;
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
            font-size: 1.1rem;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 0.125rem;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .client-actions {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 0.25rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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

        .status-active {
            background: rgba(39, 174, 96, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(39, 174, 96, 0.3);
        }

        .status-inactive {
            background: rgba(244, 67, 54, 0.1);
            color: #c62828;
            border: 1px solid rgba(244, 67, 54, 0.3);
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
            background: #8B1538;
            color: white;
        }

        .btn-primary:hover {
            background: #5D0E26;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }
        .modal-bg { display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index: 9999; }
         .modal-content {
             background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%) !important;
             border-radius: 16px !important;
             max-width: 700px !important;
             margin: 40px auto !important;
             padding: 0 !important;
             position: relative !important;
             max-height: 85vh !important;
             overflow: hidden !important;
             word-wrap: break-word !important;
             border: none !important;
             box-shadow: 0 20px 40px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.05) !important;
             width: auto !important;
             backdrop-filter: blur(15px) !important;
         }
         .close-modal { 
             position: absolute !important; 
             top: 16px !important; 
             right: 16px !important; 
             font-size: 1.4em !important; 
             cursor: pointer !important; 
             color: #6b7280 !important; 
             background: rgba(255,255,255,0.9) !important;
             border-radius: 50% !important;
             width: 32px !important;
             height: 32px !important;
             display: flex !important;
             align-items: center !important;
             justify-content: center !important;
             transition: all 0.3s ease !important;
             z-index: 10 !important;
         }
         .close-modal:hover {
             background: rgba(239, 68, 68, 0.1) !important;
             color: #ef4444 !important;
             transform: scale(1.1) !important;
         }
         .case-list { margin-top: 0 !important; }
         .case-item { 
             background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important; 
             border-radius: 12px !important; 
             padding: 16px !important; 
             margin-bottom: 0 !important; 
             border: 1px solid rgba(229, 231, 235, 0.8) !important; 
             box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1) !important;
             transition: all 0.3s ease !important;
             position: relative !important;
             overflow: hidden !important;
         }
         .case-item::before {
             content: '' !important;
             position: absolute !important;
             top: 0 !important;
             left: 0 !important;
             right: 0 !important;
             height: 3px !important;
             background: linear-gradient(90deg, #5D0E26, #8B1538) !important;
             transform: scaleX(0) !important;
             transition: transform 0.3s ease !important;
         }
         .case-item:hover::before {
             transform: scaleX(1) !important;
         }
         .case-item:hover {
             transform: translateY(-2px) !important;
             box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.05) !important;
             border-color: rgba(93, 14, 38, 0.2) !important;
         }
         .section-divider { border-bottom:1px solid #e0e0e0; margin:24px 0 16px 0; }
         .chat-area { margin-top: 0 !important; }
         .chat-bubble { 
             margin-bottom: 12px !important; 
             padding: 12px 16px !important; 
             border-radius: 16px !important; 
             background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important; 
             display: inline-block !important; 
             word-break: break-word !important; 
             max-width: 80% !important; 
             box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
             border: 1px solid rgba(226, 232, 240, 0.8) !important;
             position: relative !important;
             transition: all 0.3s ease !important;
         }
         .chat-bubble::before {
             content: '' !important;
             position: absolute !important;
             bottom: -8px !important;
             left: 20px !important;
             width: 0 !important;
             height: 0 !important;
             border-left: 8px solid transparent !important;
             border-right: 8px solid transparent !important;
             border-top: 8px solid #e2e8f0 !important;
         }
         .chat-bubble.sent { 
             background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%) !important; 
             color: white !important;
             margin-left: auto !important; 
             display: block !important; 
             border-color: rgba(93, 14, 38, 0.3) !important;
         }
         .chat-bubble.sent::before {
             border-top-color: #8B1538 !important;
             right: 20px !important;
             left: auto !important;
         }
         .chat-bubble.received { 
             background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%) !important; 
             color: #334155 !important;
             margin-right: auto !important; 
             display: block !important; 
             border-color: rgba(148, 163, 184, 0.3) !important;
         }
         .chat-bubble:hover {
             transform: translateY(-1px) !important;
             box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
         }
         .chat-meta { 
             font-size: 0.75em !important; 
             color: rgba(107, 114, 128, 0.8) !important; 
             margin-top: 6px !important; 
             font-weight: 500 !important;
             display: flex !important;
             align-items: center !important;
             gap: 4px !important;
         }
         .section-title { 
             font-size: 1.2rem !important; 
             font-weight: 600 !important; 
             margin-bottom: 16px !important; 
             margin-top: 20px !important; 
             color: #1f2937 !important;
             position: relative !important;
             padding-left: 12px !important;
         }
         .section-title::before {
             content: '' !important;
             position: absolute !important;
             left: 0 !important;
             top: 50% !important;
             transform: translateY(-50%) !important;
             width: 3px !important;
             height: 20px !important;
             background: linear-gradient(135deg, #5D0E26, #8B1538) !important;
             border-radius: 2px !important;
         }
         .status-badge {
             padding: 6px 12px !important;
             border-radius: 16px !important;
             font-size: 0.7rem !important;
             font-weight: 600 !important;
             text-transform: uppercase !important;
             letter-spacing: 0.5px !important;
             display: inline-flex !important;
             align-items: center !important;
             gap: 4px !important;
             box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
             border: 1px solid rgba(255, 255, 255, 0.2) !important;
             position: relative !important;
             overflow: hidden !important;
         }
         .status-badge::before {
             content: '' !important;
             position: absolute !important;
             top: 0 !important;
             left: -100% !important;
             width: 100% !important;
             height: 100% !important;
             background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent) !important;
             transition: left 0.5s ease !important;
         }
         .status-badge:hover::before {
             left: 100% !important;
         }
         .status-active { 
             background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important; 
             color: white !important; 
             box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
         }
         .status-pending { 
             background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important; 
             color: white !important; 
             box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3) !important;
         }
         .status-closed { 
             background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%) !important; 
             color: white !important; 
             box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3) !important;
         }
         .status-inactive { background: #f8d7da; color: #721c24; }
         
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
            <li><a href="admin_usermanagement.php"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
            <li><a href="admin_managecases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="admin_clients.php" class="active"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="admin_messages.php"><i class="fas fa-comments"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Client Management';
        $page_subtitle = 'Manage all clients in the system and view their cases';
        include 'components/profile_header.php'; 
        ?>
        <div class="dashboard-cards" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="card" style="height: 100%;">
                <div class="card-icon"><i class="fas fa-users"></i></div>
                <div class="card-info"><h3>Total Clients</h3><p><?= $total_clients ?></p></div>
            </div>
            <div class="card" style="height: 100%;">
                <div class="card-icon"><i class="fas fa-gavel"></i></div>
                <div class="card-info"><h3>Active Cases</h3><p><?= $active_cases ?></p></div>
            </div>
            <div class="card" style="height: 100%;">
                <div class="card-icon"><i class="fas fa-envelope"></i></div>
                <div class="card-info"><h3>Unread Messages</h3><p><?= $unread_messages ?></p></div>
            </div>
            <div class="card" style="height: 100%;">
                <div class="card-icon"><i class="fas fa-folder"></i></div>
                <div class="card-info"><h3>Total Cases</h3><p><?= $total_cases ?></p></div>
            </div>
        </div>


        <!-- Quick Actions -->
        <div class="quick-actions">
            <button onclick="openAddClientModal()" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Add New Client
            </button>
            <a href="admin_managecases.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i>
                New Case
            </a>
            <a href="admin_messages.php" class="btn btn-secondary">
                <i class="fas fa-envelope"></i>
                Send Message
            </a>
            <a href="admin_schedule.php" class="btn btn-secondary">
                <i class="fas fa-calendar"></i>
                Schedule Meeting
            </a>
            <button class="btn btn-secondary" onclick="refreshClientData()">
                <i class="fas fa-sync-alt"></i>
                Refresh Data
            </button>
        </div>

        <!-- Client Grid -->
        <div class="client-grid">
            <?php if (count($client_details) === 0): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-users" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <h3>No Clients Yet</h3>
                    <p>No clients found in the system. Start by adding new clients.</p>
                </div>
            <?php else: ?>
                <?php foreach ($client_details as $c): ?>
                <div class="client-card" data-client-id="<?= $c['id'] ?>" data-client-name="<?= htmlspecialchars($c['name']) ?>" data-attorney-id="<?= $c['attorney_id'] ?? '' ?>">
                    <div class="client-card-header">
                        <div class="client-avatar">
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
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
                            <div class="stat-number"><?= $c['pending_cases'] ?? 0 ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item closed">
                            <div class="stat-number"><?= $c['closed_cases'] ?? ($c['total_cases'] - $c['active_cases']) ?></div>
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
    <div id="addClientModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(93, 14, 38, 0.8), rgba(139, 21, 56, 0.6)); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
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
                
                <!-- Name Fields Container - Horizontal Layout -->
                <div style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
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
                    </div>
                </div>
                
                <!-- Email Row - 3 Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Email Address</label>
                        <input type="email" name="email" id="email" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter email address" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, '')">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Email</label>
                        <input type="email" name="confirm_email" id="confirm_email" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm email address" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, '')">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Phone Number</label>
                        <input type="text" name="phone_number" id="phoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter phone number (09xxxxxxxxx)" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); validatePhoneNumber();" onkeypress="return event.charCode >= 48 && event.charCode <= 57" onfocus="this.style.borderColor='#5D0E26'" onblur="validatePhoneNumber();">
                    </div>
                </div>
                
                <!-- Password Row - 3 Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Confirm Phone</label>
                        <input type="text" name="confirm_phone_number" id="confirmPhoneNumber" maxlength="11" pattern="[0-9]{11}" required style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm phone number" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" onkeypress="return event.charCode >= 48 && event.charCode <= 57" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;">Password</label>
                        <div style="position: relative;">
                            <input type="password" name="password" id="password" required style="width: 100%; padding: 10px 35px 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Enter password" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, ''); checkPasswordStrength()">
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
                            <input type="password" name="confirm_password" id="confirm_password" required style="width: 100%; padding: 10px 35px 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 13px; transition: all 0.3s ease; background: white;" placeholder="Confirm password" onfocus="this.style.borderColor='#5D0E26'" onblur="this.style.borderColor='#e5e7eb'" oninput="this.value = this.value.replace(/\s/g, '')">
                            <i class="fas fa-eye" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; cursor: pointer; font-size: 12px; padding: 4px;" onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6b7280'"></i>
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

    <!-- Client Details Modal -->
    <div class="modal-bg" id="clientModalBg" style="z-index: 9999 !important;">
        <div class="modal-content" id="client-modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <span class="close-modal" onclick="closeClientModal()">&times;</span>
            <div id="clientModalBody">
                <!-- AJAX content here -->
            </div>
        </div>
    </div>
    <script>
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
            // Reload the page to show the new client
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
    
    function validateForm() {
            const surname = document.querySelector('input[name="surname"]').value.trim();
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const middleName = document.querySelector('input[name="middle_name"]').value.trim();
            
            // Combine names into full name
            const name = surname + ', ' + firstName + (middleName ? ' ' + middleName : '');
            
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
            
            // Submit via AJAX
            fetch('add_client.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success - show success message
                    showSuccessModal('Client Successfully Registered!', 'The client has been successfully registered and an email has been sent to ' + data.email + '. They can now access the system.');
                    // Close modal and reload page
                    closeAddClientModal();
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

    function closeClientModal() {
        document.getElementById('clientModalBg').style.display = 'none';
        document.getElementById('clientModalBody').innerHTML = '';
    }
    
    function viewClientDetails(clientId, clientName) {
        fetch('admin_clients.php?ajax_client_details=1&client_id=' + clientId)
            .then(r => r.text())
            .then(html => {
                document.getElementById('clientModalBody').innerHTML = html;
                document.getElementById('clientModalBg').style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
    }

    function refreshClientData() {
        location.reload();
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-bg')) {
            closeClientModal();
        }
    }
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html>
