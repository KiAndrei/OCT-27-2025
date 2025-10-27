<?php
require_once 'session_manager.php';
validateUserAccess('client');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$client_id = $_SESSION['user_id'];

// Check if client has an approved request
$stmt = $conn->prepare("SELECT id, status, review_notes, reviewed_at FROM client_request_form WHERE client_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$request_status = $res->fetch_assoc();

// Set flag to show request access page instead of redirecting
$show_request_access = (!$request_status || $request_status['status'] !== 'Approved');

// Determine if client can submit a new request
$can_submit_request = true;
if ($request_status) {
    // Client can only submit a new request if:
    // 1. No existing request, OR
    // 2. Previous request was rejected
    $can_submit_request = ($request_status['status'] === 'Rejected');
}

// Check if this is a new approval (approved today)
$is_new_approval = false;
if ($request_status && isset($request_status['reviewed_at']) && $request_status['reviewed_at']) {
    $is_new_approval = (date('Y-m-d', strtotime($request_status['reviewed_at'])) === date('Y-m-d'));
}

$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $client_id);
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
    <title>Document Generation - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/document-styles.css?v=<?= time() ?>">
    <style>
        /* Data Preview Styles */
        .data-preview {
            display: none;
        }
        
        .data-preview-header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .data-preview-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .data-preview-content {
            display: grid;
            gap: 15px;
        }
        
        .data-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .data-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .data-value {
            color: #333;
            font-size: 0.95rem;
            line-height: 1.4;
            word-wrap: break-word;
            background: white;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }
        
        .data-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Document Status Section Styles */
        .section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.1);
        }
        
        .section-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .section-header h2 {
            margin: 0 0 3px 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .section-header p {
            margin: 0;
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        /* Table Styles */
        .document-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .document-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .document-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.8rem;
            vertical-align: middle;
        }
        
        .document-table tr:hover {
            background: #f8f9fa;
        }
        
        .document-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-indicator {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-approved .status-indicator { 
            background: #10b981;
        }
        .status-rejected .status-indicator { 
            background: #ef4444;
        }
        .status-pending .status-indicator { 
            background: #f59e0b;
        }
        
        .document-type {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.8rem;
        }
        
        .request-id {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            color: #6b7280;
            background: #f3f4f6;
            padding: 1px 4px;
            border-radius: 3px;
        }
        
        .date-cell {
            color: #6b7280;
            font-size: 0.75rem;
        }
        
        .rejection-reason-cell {
            max-width: 200px;
            min-width: 150px;
        }
        
        .rejection-reason-text {
            color: #dc3545;
            font-size: 0.75rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: inline-block;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            word-wrap: break-word;
            line-height: 1.3;
        }
        
        .rejection-reason-text:hover {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.4);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }
        
        .no-reason {
            color: #6c757d;
            font-size: 0.75rem;
            font-style: italic;
        }
        
        .empty-state {
            text-align: center;
            padding: 25px 15px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin: 0 0 8px 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        /* Responsive Table Styles */
        @media (max-width: 768px) {
            .document-table {
                font-size: 0.7rem;
            }
            
            .document-table th,
            .document-table td {
                padding: 6px 8px;
            }
            
            .document-table th:nth-child(5),
            .document-table td:nth-child(5) {
                display: none; /* Hide Reviewed column on tablet */
            }
        }
        
        @media (max-width: 480px) {
            .document-table {
                font-size: 0.65rem;
            }
            
            .document-table th,
            .document-table td {
                padding: 4px 6px;
            }
            
            .document-table th:nth-child(4),
            .document-table td:nth-child(4),
            .document-table th:nth-child(5),
            .document-table td:nth-child(5) {
                display: none; /* Hide Submitted and Reviewed columns on mobile */
            }
            
            .status-badge {
                font-size: 0.6rem;
                padding: 2px 6px;
            }
            
            .request-id {
                font-size: 0.6rem;
            }
        }
        
        /* Request Access Page Styles - Matching System Theme */
        .request-access-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
            padding: 40px 20px;
        }
        
        .request-access-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.15);
            border: 2px solid rgba(93, 14, 38, 0.1);
            max-width: 500px;
            width: 100%;
        }
        
        .request-icon {
            font-size: 4rem;
            color: #5D0E26;
            margin-bottom: 20px;
        }
        
        .request-access-content p {
            color: #666;
            margin: 0 0 30px 0;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .status-info {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid;
        }
        
        .status-info.pending {
            border-color: #8B1538;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
        }
        
        .status-info.rejected {
            border-color: #e74c3c;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        
        .status-info i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .status-info.pending i {
            color: #8B1538;
        }
        
        .status-info.rejected i {
            color: #e74c3c;
        }
        
        .status-info h3 {
            color: #5D0E26;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            font-family: "Playfair Display", serif;
        }
        
        .status-info p {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0;
        }
        
        .review-notes {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(93, 14, 38, 0.2);
        }
        
        .review-notes strong {
            color: #5D0E26;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .review-notes p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }
        
        .request-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }
        
        .request-actions .btn {
            min-width: 200px;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .request-actions .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }
        
        .request-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.4);
        }
        
        .request-actions .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #5D0E26;
            border: 2px solid rgba(93, 14, 38, 0.2);
        }
        
        .request-actions .btn-secondary:hover {
            background: rgba(93, 14, 38, 0.05);
            border-color: rgba(93, 14, 38, 0.3);
            color: #4A0B1E;
        }
        
        .info-message {
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
            border: 2px solid rgba(93, 14, 38, 0.2);
            border-radius: 12px;
            padding: 15px 20px;
            color: #5D0E26;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 200px;
        }
        
        .info-message i {
            font-size: 1.1rem;
            color: #8B1538;
        }
        
        .request-actions .btn-success {
            background: var(--success-color);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .request-actions .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.4);
        }
        
        .request-actions .btn-warning {
            background: var(--warning-color);
            color: white;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }
        
        .request-actions .btn-warning:hover {
            background: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        }
        
        .request-actions .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .request-status-info {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 15px;
            border: 1px solid rgba(93, 14, 38, 0.1);
            text-align: left;
        }
        
        .request-status-info small {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .request-status-info strong {
            color: #5D0E26;
            font-weight: 600;
        }
        
        .request-status-info em {
            color: #666;
            font-style: italic;
        }
        
        .rejection-details {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(93, 14, 38, 0.2);
            text-align: left;
        }
        
        .rejection-details strong {
            color: #5D0E26;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .rejection-notes {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }
        
        /* Request Form Styles */
        .request-form-content {
            max-height: 70vh;
            overflow-y: auto;
            padding: 20px 0;
        }
        
        .form-section {
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
            color: #5D0E26;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: "Playfair Display", serif;
        }
        
        .form-section-title i {
            color: #8B1538;
            font-size: 1.2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        
        .form-group.full-width-field {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #5D0E26;
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group label::before {
            content: '';
            width: 3px;
            height: 14px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 2px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.95);
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            background: white;
            transform: translateY(-1px);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            line-height: 1.6;
        }
        
        .field-help {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 8px;
            padding: 10px 12px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 8px;
            border-left: 3px solid #8B1538;
        }
        
        .field-help i {
            color: #8B1538;
            font-size: 0.9rem;
            margin-top: 2px;
        }
        
        .field-help span {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .file-upload-container {
            position: relative;
        }
        
        .file-upload-container input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: rgba(93, 14, 38, 0.05);
            border: 2px dashed rgba(93, 14, 38, 0.2);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            color: #5D0E26;
            font-weight: 500;
        }
        
        .file-upload-label:hover {
            background: rgba(93, 14, 38, 0.1);
            border-color: rgba(93, 14, 38, 0.3);
        }
        
        .file-upload-label i {
            color: #8B1538;
        }
        
        .file-info {
            margin-top: 8px;
        }
        
        .file-info small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .privacy-checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 20px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .privacy-checkbox-container input[type="checkbox"] {
            margin: 0;
            width: 18px;
            height: 18px;
            accent-color: #5D0E26;
        }
        
        .privacy-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #5D0E26;
            font-weight: 500;
            cursor: pointer;
            margin: 0;
        }
        
        .privacy-label::before {
            display: none;
        }
        
        .privacy-label i {
            color: #8B1538;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .form-actions .btn {
            min-width: 120px;
        }
        
        /* Child Entry Styles */
        .child-entry {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .child-entry input {
            flex: 1;
        }

        #childrenContainer {
            margin-bottom: 10px;
        }
        
        /* Radio Group Styles */
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 16px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            cursor: pointer;
            color: var(--text-dark);
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .radio-group label:hover {
            background: rgba(93, 14, 38, 0.1);
        }

        .radio-group input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            margin: 0;
        }
        
        /* Button Enhancements */
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.4);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar client-sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="client_dashboard.php" title="View your case overview, statistics, and recent activities">
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
                <a href="client_messages.php" title="Communicate with your attorney and legal team">
                    <div class="button-content">
                        <i class="fas fa-envelope"></i>
                        <div class="text-content">
                        <span>Messages</span><small>Chat with Attorney</small></div></div><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a>
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

    <!-- Solo Parent Modal -->
    <div id="soloParentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> Sworn Affidavit of Solo Parent</h2>
                <span class="close" onclick="closeSoloParentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="soloParentForm" class="modal-form">
                    <div class="form-group">
                        <label for="soloFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="soloFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="soloCompleteAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="soloCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="childrenNames">Children Names <span class="required">*</span></label>
                        <div id="childrenContainer">
                            <div class="child-entry">
                                <input type="text" name="childrenNames[]" placeholder="Child's Name" required>
                                <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
                                <button type="button" onclick="removeChild(this)" class="btn btn-danger btn-sm">Remove</button>
                        </div>
                    </div>
                        <button type="button" onclick="addChild()" class="btn btn-secondary btn-sm">Add Child</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="yearsUnderCase">Years Under Case <span class="required">*</span></label>
                        <input type="number" id="yearsUnderCase" name="yearsUnderCase" required placeholder="Number of years" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Reason Section <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="reasonSection" value="Left the family home and abandoned us" onchange="toggleOtherReason()" required> Left the family home and abandoned us</label>
                            <label><input type="radio" name="reasonSection" value="Died last" onchange="toggleOtherReason()" required> Died last</label>
                            <label><input type="radio" name="reasonSection" value="Other reason, please state" onchange="toggleOtherReason()" required> Other reason, please state</label>
                        </div>
                        <div id="otherReasonContainer" style="display: none; margin-top: 10px;">
                            <input type="text" id="otherReason" name="otherReason" placeholder="Please specify other reason" style="padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: white; width: 100%;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Employment Status <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="employmentStatus" value="Employee and earning" onchange="toggleEmploymentFields()" required> Employee and earning</label>
                            <label><input type="radio" name="employmentStatus" value="Self-employed and earning" onchange="toggleEmploymentFields()" required> Self-employed and earning</label>
                            <label><input type="radio" name="employmentStatus" value="Un-employed and dependent upon" onchange="toggleEmploymentFields()" required> Un-employed and dependent upon</label>
                            </div>
                        <div id="employeeAmountContainer" style="display: none; margin-top: 10px;">
                            <input type="text" id="employeeAmount" name="employeeAmount" placeholder="Monthly amount" style="padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: white; width: 100%;">
                            </div>
                        <div id="selfEmployedAmountContainer" style="display: none; margin-top: 10px;">
                            <input type="text" id="selfEmployedAmount" name="selfEmployedAmount" placeholder="Monthly amount" style="padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: white; width: 100%;">
                            </div>
                        <div id="unemployedDependentContainer" style="display: none; margin-top: 10px;">
                            <input type="text" id="unemployedDependent" name="unemployedDependent" placeholder="Dependent upon" style="padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: white; width: 100%;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="soloDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="soloDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeSoloParentModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveSoloParent()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewSoloParentData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="soloParentDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-user"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Full Name</label>
                            <div class="data-value" id="previewSoloFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewSoloCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Children</label>
                            <div class="data-value" id="previewChildren">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Years Under Case</label>
                            <div class="data-value" id="previewYearsUnderCase">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Reason</label>
                            <div class="data-value" id="previewReason">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Employment Status</label>
                            <div class="data-value" id="previewEmployment">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewSoloDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="saHideData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="saSend()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Senior ID Loss Modal -->
    <div id="seniorIDLossModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-id-card"></i> Affidavit of Loss (Senior ID)</h2>
                <span class="close" onclick="closeSeniorIDLossModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="seniorIDLossForm" class="modal-form">
                    <div class="form-group">
                        <label for="seniorFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="seniorFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorCompleteAddress">Complete Address <span class="required">*</span></label>
                        <textarea id="seniorCompleteAddress" name="completeAddress" required placeholder="Enter your complete address including street, barangay, city, province" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorRelationship">Relationship to Senior Citizen <span class="required">*</span></label>
                        <input type="text" id="seniorRelationship" name="relationship" required placeholder="e.g., Son, Daughter, Spouse, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorCitizenName">Senior Citizen's Full Name <span class="required">*</span></label>
                        <input type="text" id="seniorCitizenName" name="seniorCitizenName" required placeholder="Enter the senior citizen's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorDetailsOfLoss">Details of Loss <span class="required">*</span></label>
                        <textarea id="seniorDetailsOfLoss" name="detailsOfLoss" required placeholder="Describe the circumstances of how the Senior ID was lost" style="resize: vertical; min-height: 80px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                        <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 4px;">Please provide detailed information about when, where, and how the Senior ID was lost</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="seniorDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeSeniorIDLossModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveSeniorIDLoss()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewSeniorIDLossData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="seniorIDLossDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-id-card"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Full Name</label>
                            <div class="data-value" id="previewSeniorFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewSeniorCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Relationship to Senior Citizen</label>
                            <div class="data-value" id="previewSeniorRelationship">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Senior Citizen's Full Name</label>
                            <div class="data-value" id="previewSeniorCitizenName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Details of Loss</label>
                            <div class="data-value" id="previewSeniorDetailsOfLoss">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewSeniorDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hideSeniorIDLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendSeniorIDLoss()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Document Generation';
        $page_subtitle = 'Generate and manage your document storage';
        include 'components/profile_header.php'; 
        ?>

        <?php if ($show_request_access): ?>
            <!-- Request Access Page -->
            <div class="request-access-container">
                <div class="request-access-card">
                    <div class="request-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2>Access Required</h2>
                    <p>To generate legal documents, you need to request access first. This helps us verify your identity and provide better service.</p>
                    
                    <?php if ($request_status): ?>
                        <?php if ($request_status['status'] === 'Pending'): ?>
                            <div class="status-info pending">
                                <i class="fas fa-clock"></i>
                                <h3>Request Under Review</h3>
                                <p>Your request is currently being reviewed by our team. You will be notified once it's approved.</p>
                            </div>
                        <?php elseif ($request_status['status'] === 'Rejected'): ?>
                            <div class="status-info rejected">
                                <i class="fas fa-times-circle"></i>
                                <h3>Previous Request Rejected</h3>
                                <p>Your previous request was rejected. Please submit a new request with updated information.</p>
                                <?php if ($request_status['review_notes']): ?>
                                    <div class="rejection-details">
                                        <strong>Rejection Reason:</strong><br>
                                        <div class="rejection-notes">
                                            <?= nl2br(htmlspecialchars($request_status['review_notes'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="request-actions">
                        <?php if ($can_submit_request): ?>
                            <button onclick="openDocumentRequestModal()" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Request Access
                            </button>
                        <?php else: ?>
                            <?php if ($request_status['status'] === 'Pending'): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-clock"></i>
                                    Request Pending Review
                                </button>
                                <div class="request-status-info">
                                    <small>Your request is currently being reviewed. You will be notified once a decision is made.</small>
                                </div>
                            <?php elseif ($request_status['status'] === 'Approved'): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    Request Approved
                                </button>
                                <div class="request-status-info">
                                    <small>Your request has been approved. You can now access the document generation system.</small>
                                </div>
                            <?php elseif ($request_status['status'] === 'Rejected'): ?>
                                <button onclick="openDocumentRequestModal()" class="btn btn-warning">
                                    <i class="fas fa-redo"></i>
                                    Submit New Request
                                </button>
                                <div class="request-status-info">
                                    <small>Your previous request was rejected. You can submit a new request with updated information.</small>
                                    <?php if (!empty($request_status['review_notes'])): ?>
                                        <br><br>
                                        <strong>Review Notes:</strong><br>
                                        <em><?= nl2br(htmlspecialchars($request_status['review_notes'])) ?></em>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-info-circle"></i>
                                    Request Status: <?= htmlspecialchars($request_status['status']) ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Document Generation Grid -->
            <div class="document-grid">
            <!-- Row 1 -->
            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Affidavit of Loss</h3>
                </div>
                <div class="document-right">
                <p>Generate affidavit of loss document</p>
                <button onclick="openAffidavitLossModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <h3>Affidavit of Loss<br><span style="font-size: 0.9em; font-weight: 500;">(Senior ID)</span></h3>
                </div>
                <div class="document-right">
                <p>Generate affidavit of loss for senior ID</p>
                <button onclick="openSeniorIDLossModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3>Sworn Affidavit of Solo Parent</h3>
                </div>
                <div class="document-right">
                <p>Generate sworn affidavit of solo parent</p>
                <button onclick="openSoloParentModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <!-- Row 2 -->
            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-female"></i>
                </div>
                <h3>Sworn Affidavit of Mother</h3>
                </div>
                <div class="document-right">
                <p>Generate sworn affidavit of mother</p>
                <button onclick="openSwornAffidavitMotherModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-wheelchair"></i>
                </div>
                <h3>Affidavit of Loss<br><span style="font-size: 0.9em; font-weight: 500;">(PWD ID)</span></h3>
                </div>
                <div class="document-right">
                <p>Generate affidavit of loss for PWD ID</p>
                <button onclick="openPWDLossModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Affidavit of Loss (Boticab Booklet/ID)</h3>
                </div>
                <div class="document-right">
                <p>Generate affidavit of loss for Boticab booklet/ID</p>
                <button onclick="openBoticabLossModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <!-- Row 3 -->
            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Joint Affidavit (Two Disinterested Person)</h3>
                </div>
                <div class="document-right">
                <p>Generate joint affidavit of two disinterested person</p>
                <button onclick="openJointAffidavitModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                </button>
                </div>
            </div>

            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>Joint Affidavit of Two Disinterested Person (Solo Parent)</h3>
                </div>
                <div class="document-right">
                <p>Generate joint affidavit of two disinterested person (solo parent)</p>
                    <button onclick="openJointAffidavitSoloParentModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                    </button>
                </div>
            </div>

            <div class="document-box">
                <div class="document-left">
                <div class="document-icon">
                    <i class="fas fa-file-signature"></i>
                </div>
                <h3>Sworn Affidavit (Solo Parent)</h3>
                </div>
                <div class="document-right">
                <p>Generate sworn affidavit for solo parent</p>
                    <button onclick="openSoloParentModal()" class="btn btn-primary generate-btn">
                    <i class="fas fa-edit"></i> Fill Up
                    </button>
                </div>
            </div>
        </div>

        <!-- Document Status Section -->
        <div class="section" style="margin-top: 30px;">
            <div class="section-header">
                <h2><i class="fas fa-file-check"></i> My Document Submissions</h2>
                <p>Track the status of your submitted documents</p>
            </div>
            
            <div id="documentStatusContainer">
                <div class="loading-state" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading your document submissions...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Affidavit of Loss Modal -->
    <div id="affidavitLossModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Affidavit of Loss</h2>
                <span class="close" onclick="closeAffidavitLossModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="affidavitLossForm" class="modal-form">
                    <div class="form-group">
                        <label for="fullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="fullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="completeAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="completeAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="specifyItemLost">Specify Item Lost <span class="required">*</span></label>
                        <input type="text" id="specifyItemLost" name="specifyItemLost" required placeholder="e.g., Driver's License, Passport, ID Card">
                    </div>
                    
                    <div class="form-group">
                        <label for="itemLost">Item Lost <span class="required">*</span></label>
                        <input type="text" id="itemLost" name="itemLost" required placeholder="Describe the specific item that was lost">
                    </div>
                    
                    <div class="form-group">
                        <label for="itemDetails">Details <span class="required">*</span></label>
                        <input type="text" id="itemDetails" name="itemDetails" required placeholder="Provide detailed description of the lost item">
                    </div>
                    
                    <div class="form-group">
                        <label for="dateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="dateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeAffidavitLossModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveAffidavitLoss()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewAffidavitLossData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="affidavitLossDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Full Name</label>
                            <div class="data-value" id="previewFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Specify Item Lost</label>
                            <div class="data-value" id="previewSpecifyItemLost">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Item Lost</label>
                            <div class="data-value" id="previewItemLost">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Item Details</label>
                            <div class="data-value" id="previewItemDetails">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hideAffidavitLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendAffidavitLoss()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- PWD ID Loss Modal -->
    <div id="pwdLossModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-wheelchair"></i> Affidavit of Loss (PWD ID)</h2>
                <span class="close" onclick="closePWDLossModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="pwdLossForm" class="modal-form">
                    <div class="form-group">
                        <label for="pwdFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="pwdFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdFullAddress">Full Address <span class="required">*</span></label>
                        <textarea id="pwdFullAddress" name="fullAddress" required placeholder="Enter your complete address including street, barangay, city, province" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdDetailsOfLoss">Details of Loss <span class="required">*</span></label>
                        <textarea id="pwdDetailsOfLoss" name="detailsOfLoss" required placeholder="Describe the circumstances of how the PWD ID was lost" style="resize: vertical; min-height: 80px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                        <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 4px;">Please provide detailed information about when, where, and how the PWD ID was lost</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="pwdDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closePWDLossModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="savePWDLoss()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewPWDLossData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="pwdLossDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-wheelchair"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Full Name</label>
                            <div class="data-value" id="previewPwdFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Full Address</label>
                            <div class="data-value" id="previewPwdFullAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Details of Loss</label>
                            <div class="data-value" id="previewPwdDetailsOfLoss">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewPwdDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hidePWDLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendPWDLoss()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boticab Booklet/ID Loss Modal -->
    <div id="boticabLossModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-book"></i> Affidavit of Loss (Boticab Booklet/ID)</h2>
                <span class="close" onclick="closeBoticabLossModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="boticabLossForm" class="modal-form">
                    <div class="form-group">
                        <label for="boticabFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="boticabFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="boticabFullAddress">Full Address <span class="required">*</span></label>
                        <textarea id="boticabFullAddress" name="fullAddress" required placeholder="Enter your complete address including street, barangay, city, province" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="boticabDetailsOfLoss">Details of Loss <span class="required">*</span></label>
                        <textarea id="boticabDetailsOfLoss" name="detailsOfLoss" required placeholder="Describe the circumstances of how the Boticab booklet/ID was lost" style="resize: vertical; min-height: 80px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                        <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 4px;">Please provide detailed information about when, where, and how the Boticab booklet/ID was lost</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="boticabDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="boticabDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeBoticabLossModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveBoticabLoss()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewBoticabLossData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="boticabLossDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-book"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Full Name</label>
                            <div class="data-value" id="previewBoticabFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Full Address</label>
                            <div class="data-value" id="previewBoticabFullAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Details of Loss</label>
                            <div class="data-value" id="previewBoticabDetailsOfLoss">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewBoticabDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hideBoticabLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendBoticabLoss()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Joint Affidavit (Two Disinterested Person) Modal -->
    <div id="jointAffidavitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-users"></i> Joint Affidavit (Two Disinterested Person)</h2>
                <span class="close" onclick="closeJointAffidavitModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="jointAffidavitForm" class="modal-form">
                    <div class="form-group">
                        <label for="firstPersonName">First Person Full Name <span class="required">*</span></label>
                        <input type="text" id="firstPersonName" name="firstPersonName" required placeholder="Enter first person's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="secondPersonName">Second Person Full Name <span class="required">*</span></label>
                        <input type="text" id="secondPersonName" name="secondPersonName" required placeholder="Enter second person's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="firstPersonAddress">First Person Complete Address <span class="required">*</span></label>
                        <textarea id="firstPersonAddress" name="firstPersonAddress" required placeholder="Enter first person's complete address including street, barangay, city, province" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="secondPersonAddress">Second Person Complete Address <span class="required">*</span></label>
                        <textarea id="secondPersonAddress" name="secondPersonAddress" required placeholder="Enter second person's complete address including street, barangay, city, province" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="childName">Name of Child <span class="required">*</span></label>
                        <input type="text" id="childName" name="childName" required placeholder="Enter child's full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="dateOfBirth">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="dateOfBirth" name="dateOfBirth" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="placeOfBirth">Place of Birth <span class="required">*</span></label>
                        <input type="text" id="placeOfBirth" name="placeOfBirth" required placeholder="Enter place of birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="fatherName">Father's Full Name <span class="required">*</span></label>
                        <input type="text" id="fatherName" name="fatherName" required placeholder="Enter father's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="motherName">Mother's Full Name <span class="required">*</span></label>
                        <input type="text" id="motherName" name="motherName" required placeholder="Enter mother's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="childNameNumber4">Name of the Person for Late Registration <span class="required">*</span></label>
                        <input type="text" id="childNameNumber4" name="childNameNumber4" required placeholder="Enter person's name for late registration">
                    </div>
                    
                    <div class="form-group">
                        <label for="jointDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="jointDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeJointAffidavitModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveJointAffidavit()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewJointAffidavitData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="jointAffidavitDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-users"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">First Person Full Name</label>
                            <div class="data-value" id="previewFirstPersonName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Second Person Full Name</label>
                            <div class="data-value" id="previewSecondPersonName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">First Person Complete Address</label>
                            <div class="data-value" id="previewFirstPersonAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Second Person Complete Address</label>
                            <div class="data-value" id="previewSecondPersonAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Name of Child</label>
                            <div class="data-value" id="previewChildName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Birth</label>
                            <div class="data-value" id="previewDateOfBirth">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Place of Birth</label>
                            <div class="data-value" id="previewPlaceOfBirth">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Father's Full Name</label>
                            <div class="data-value" id="previewFatherName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Mother's Full Name</label>
                            <div class="data-value" id="previewMotherName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Name of the Person for Late Registration</label>
                            <div class="data-value" id="previewChildNameNumber4">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewJointDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hideJointAffidavitData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendJointAffidavit()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sworn Affidavit of Mother Modal -->
    <div id="swornAffidavitMotherModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-female"></i> Sworn Affidavit of Mother</h2>
                <span class="close" onclick="closeSwornAffidavitMotherModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="swornAffidavitMotherForm" class="modal-form">
                    <div class="form-group">
                        <label for="swornMotherFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="swornMotherFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="swornMotherCompleteAddress">Complete Address <span class="required">*</span></label>
                        <textarea id="swornMotherCompleteAddress" name="completeAddress" required placeholder="Enter your complete address including street, barangay, city, province" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="swornMotherChildName">Name of Child <span class="required">*</span></label>
                        <input type="text" id="swornMotherChildName" name="childName" required placeholder="Enter child's full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="swornMotherBirthDate">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="swornMotherBirthDate" name="birthDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="swornMotherBirthPlace">Place of Birth <span class="required">*</span></label>
                        <input type="text" id="swornMotherBirthPlace" name="birthPlace" required placeholder="Enter place of birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="swornMotherDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="swornMotherDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeSwornAffidavitMotherModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveSwornAffidavitMother()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewSwornAffidavitMotherData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="swornAffidavitMotherDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-female"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Full Name</label>
                            <div class="data-value" id="previewSwornMotherFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewSwornMotherCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Name of Child</label>
                            <div class="data-value" id="previewSwornMotherChildName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Birth</label>
                            <div class="data-value" id="previewSwornMotherBirthDate">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Place of Birth</label>
                            <div class="data-value" id="previewSwornMotherBirthPlace">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewSwornMotherDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hideSwornAffidavitMotherData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendSwornAffidavitMother()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Joint Affidavit of Two Disinterested Person (Solo Parent) Modal -->
    <div id="jointAffidavitSoloParentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-handshake"></i> Joint Affidavit of Two Disinterested Person (Solo Parent)</h2>
                <span class="close" onclick="closeJointAffidavitSoloParentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="jointAffidavitSoloParentForm" class="modal-form">
                    <div class="form-group">
                        <label for="affiant1Name">Full Name of Affiant 1 <span class="required">*</span></label>
                        <input type="text" id="affiant1Name" name="affiant1Name" required placeholder="Enter first affiant's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="affiant2Name">Full Name of Affiant 2 <span class="required">*</span></label>
                        <input type="text" id="affiant2Name" name="affiant2Name" required placeholder="Enter second affiant's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="affiantsAddress">Address of Both Affiants <span class="required">*</span></label>
                        <textarea id="affiantsAddress" name="affiantsAddress" required placeholder="Enter address of both affiants" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="soloParentName">Name of Solo Parent <span class="required">*</span></label>
                        <input type="text" id="soloParentName" name="soloParentName" required placeholder="Enter solo parent's complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="soloParentAddress">Address of Solo Parent <span class="required">*</span></label>
                        <textarea id="soloParentAddress" name="soloParentAddress" required placeholder="Enter solo parent's complete address" style="resize: vertical; min-height: 60px; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="childrenNames">Children's Information <span class="required">*</span></label>
                        <div id="childrenContainerSoloParent">
                            <div class="child-entry">
                                <input type="text" name="childrenNames[]" placeholder="Child's Full Name" required>
                                <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
                                <button type="button" onclick="removeChildSoloParent(this)" class="btn btn-danger btn-sm">Remove</button>
                            </div>
                        </div>
                        <button type="button" onclick="addChildSoloParent()" class="btn btn-secondary btn-sm">Add Child</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="affiant1ValidId">Affiant 1 - Valid ID Number <span class="required">*</span></label>
                        <input type="text" id="affiant1ValidId" name="affiant1ValidId" required placeholder="Enter valid ID number">
                    </div>
                    
                    <div class="form-group">
                        <label for="affiant2ValidId">Affiant 2 - Valid ID Number <span class="required">*</span></label>
                        <input type="text" id="affiant2ValidId" name="affiant2ValidId" required placeholder="Enter valid ID number">
                    </div>
                    
                    <div class="form-group">
                        <label for="jointSoloParentDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="jointSoloParentDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeJointAffidavitSoloParentModal()" class="btn btn-secondary">Cancel</button>
                        <button type="button" onclick="saveJointAffidavitSoloParent()" class="btn btn-primary">Save</button>
                        <button type="button" onclick="viewJointAffidavitSoloParentData()" class="btn btn-primary" style="background: #28a745;">View Data</button>
                    </div>
                </form>
                
                <!-- Data Preview Section -->
                <div id="jointAffidavitSoloParentDataPreview" class="data-preview">
                    <div class="data-preview-header">
                        <h3><i class="fas fa-handshake"></i> Data Preview</h3>
                    </div>
                    <div class="data-preview-content">
                        <div class="data-item">
                            <label class="data-label">Affiant 1 Full Name</label>
                            <div class="data-value" id="previewAffiant1Name">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Affiant 2 Full Name</label>
                            <div class="data-value" id="previewAffiant2Name">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Address of Both Affiants</label>
                            <div class="data-value" id="previewAffiantsAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Solo Parent Name</label>
                            <div class="data-value" id="previewSoloParentName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Solo Parent Address</label>
                            <div class="data-value" id="previewSoloParentAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Children</label>
                            <div class="data-value" id="previewChildrenSoloParent">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Affiant 1 Valid ID Number</label>
                            <div class="data-value" id="previewAffiant1ValidId">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Affiant 2 Valid ID Number</label>
                            <div class="data-value" id="previewAffiant2ValidId">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewJointSoloParentDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <button type="button" onclick="hideJointAffidavitSoloParentData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="sendJointAffidavitSoloParent()" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Access Modal -->
    <div id="requestAccessModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-lock"></i> Access Required</h2>
                <span class="close" onclick="closeRequestAccessModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Request Status View -->
                <div id="requestStatusView" class="request-access-content" style="display: <?= $can_submit_request ? 'none' : 'block' ?>;">
                    <div class="request-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <p>To generate legal documents, you need to request access first. This helps us verify your identity and provide better service.</p>
                    
                    <?php if ($request_status): ?>
                        <?php if ($request_status['status'] === 'Pending'): ?>
                            <div class="status-info pending">
                                <i class="fas fa-clock"></i>
                                <h3>Request Under Review</h3>
                                <p>Your request is currently being reviewed by our team. You will be notified once it's approved.</p>
                            </div>
                        <?php elseif ($request_status['status'] === 'Rejected'): ?>
                            <div class="status-info rejected">
                                <i class="fas fa-times-circle"></i>
                                <h3>Request Rejected</h3>
                                <p>Your request was not approved. You can submit a new request with updated information.</p>
                                <?php if ($request_status['review_notes']): ?>
                                    <div class="review-notes">
                                        <strong>Review Notes:</strong>
                                        <p><?= htmlspecialchars($request_status['review_notes']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="request-actions">
                        <?php if ($can_submit_request): ?>
                            <button onclick="showRequestForm()" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Request Access
                            </button>
                        <?php else: ?>
                            <?php if ($request_status['status'] === 'Pending'): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-clock"></i>
                                    Request Pending Review
                                </button>
                                <div class="request-status-info">
                                    <small>Your request is currently being reviewed. You will be notified once a decision is made.</small>
                                </div>
                            <?php elseif ($request_status['status'] === 'Approved'): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    Request Approved
                                </button>
                                <div class="request-status-info">
                                    <small>Your request has been approved. You can now access the document generation system.</small>
                                </div>
                            <?php elseif ($request_status['status'] === 'Rejected'): ?>
                                <button onclick="showRequestForm()" class="btn btn-warning">
                                    <i class="fas fa-redo"></i>
                                    Submit New Request
                                </button>
                                <div class="request-status-info">
                                    <small>Your previous request was rejected. You can submit a new request with updated information.</small>
                                    <?php if (!empty($request_status['review_notes'])): ?>
                                        <br><br>
                                        <strong>Review Notes:</strong><br>
                                        <em><?= nl2br(htmlspecialchars($request_status['review_notes'])) ?></em>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-info-circle"></i>
                                    Request Status: <?= htmlspecialchars($request_status['status']) ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <button onclick="closeRequestAccessModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Close
                        </button>
                    </div>
                </div>

                <!-- Request Form View -->
                <div id="requestFormView" class="request-form-content" style="display: <?= $can_submit_request ? 'block' : 'none' ?>;">
                    <form id="requestAccessForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="submit_request">
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-user"></i>
                                Personal Information
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_SESSION['client_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sex">Sex *</label>
                                    <select id="sex" name="sex" required>
                                        <option value="">Select Sex</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Contact Information Section -->
                            <div class="form-group">
                                <label for="street_address">Street Address *</label>
                                <input type="text" id="street_address" name="street_address" placeholder="House/Building No., Street Name" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="barangay">Barangay *</label>
                                    <input type="text" id="barangay" name="barangay" placeholder="Barangay" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="city">City/Municipality *</label>
                                    <input type="text" id="city" name="city" placeholder="City/Municipality" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="province">Province *</label>
                                    <input type="text" id="province" name="province" placeholder="Province" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="zip_code">ZIP Code *</label>
                                    <input type="text" id="zip_code" name="zip_code" placeholder="ZIP Code" maxlength="4" pattern="[0-9]{4}" title="Please enter a 4-digit ZIP code" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Legal Concern Section -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-gavel"></i>
                                Legal Concern
                            </div>
                            <div class="form-group">
                                <label for="concern_description">Legal Concern/Issue *</label>
                                <textarea id="concern_description" name="concern_description" rows="6" placeholder="Please describe your legal concern or issue in detail. Include relevant facts, dates, and any specific questions you have. The more information you provide, the better we can assist you." required></textarea>
                                <div class="field-help">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Include key details such as: What happened? When did it occur? Who is involved? What outcome are you seeking?</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Document Upload Section -->
                        <div class="form-group full-width-field">
                            <div class="form-section-title">
                                <i class="fas fa-file-upload"></i>
                                Valid ID
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_id_front">Valid ID Front *</label>
                            <div class="file-upload-container">
                                <input type="file" id="valid_id_front" name="valid_id_front" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" onchange="showFrontPreview(this)" required>
                                <label for="valid_id_front" class="file-upload-label">
                                    <i class="fas fa-upload"></i>
                                    <span>Choose Front Image</span>
                                </label>
                                <div class="file-info">
                                    <small>Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                                </div>
                                
                                <!-- Image Preview Container -->
                                <div id="front-image-container" style="margin-top: 15px; text-align: center; display: none;">
                                    <div style="background: #d4edda; padding: 20px; border-radius: 10px; border: 2px solid #28a745; position: relative;">
                                        <button onclick="removeFrontImage()" style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 14px;" title="Remove Image">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <h4 style="color: #155724; margin-bottom: 15px;">
                                            <i class="fas fa-check-circle"></i> Front ID Uploaded Successfully!
                                        </h4>
                                        <img id="front-image" src="" alt="Front ID" style="max-width: 100%; max-height: 120px; border-radius: 8px; border: 2px solid #28a745;">
                                        <div style="margin-top: 10px; color: #155724; font-weight: bold;">
                                            <span id="front-filename"></span>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <button onclick="removeFrontImage()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-size: 14px;">
                                                <i class="fas fa-trash"></i> Remove & Upload Different Image
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_id_back">Valid ID Back *</label>
                            <div class="file-upload-container">
                                <input type="file" id="valid_id_back" name="valid_id_back" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" onchange="showBackPreview(this)" required>
                                <label for="valid_id_back" class="file-upload-label">
                                    <i class="fas fa-upload"></i>
                                    <span>Choose Back Image</span>
                                </label>
                                <div class="file-info">
                                    <small>Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                                </div>
                                
                                <!-- Image Preview Container -->
                                <div id="back-image-container" style="margin-top: 15px; text-align: center; display: none;">
                                    <div style="background: #d4edda; padding: 20px; border-radius: 10px; border: 2px solid #28a745; position: relative;">
                                        <button onclick="removeBackImage()" style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 14px;" title="Remove Image">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <h4 style="color: #155724; margin-bottom: 15px;">
                                            <i class="fas fa-check-circle"></i> Back ID Uploaded Successfully!
                                        </h4>
                                        <img id="back-image" src="" alt="Back ID" style="max-width: 100%; max-height: 120px; border-radius: 8px; border: 2px solid #28a745;">
                                        <div style="margin-top: 10px; color: #155724; font-weight: bold;">
                                            <span id="back-filename"></span>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <button onclick="removeBackImage()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-size: 14px;">
                                                <i class="fas fa-trash"></i> Remove & Upload Different Image
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Privacy Consent -->
                        <div class="form-group full-width-field">
                            <div class="form-section-title">
                                <i class="fas fa-shield-alt"></i>
                                Privacy Consent
                            </div>
                            
                            <div class="privacy-checkbox-container">
                                <input type="checkbox" id="privacy_consent" name="privacy_consent" required>
                                <label for="privacy_consent" class="privacy-label">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>I agree to the Data Privacy Act (Philippines - RA 10173)</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" onclick="showRequestStatus()" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Back
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <script src="assets/js/modal-functions.js?v=<?= time() ?>"></script>
    <script src="assets/js/form-handlers.js?v=<?= time() ?>"></script>
    <script src="assets/js/document-actions.js?v=<?= time() ?>"></script>
    <script src="assets/js/document-viewer.js?v=<?= time() ?>"></script>
    
    <script>
        // Make modal functions globally accessible
        window.openSoloParentModal = function() {
            document.getElementById('soloParentModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closeSoloParentModal = function() {
            document.getElementById('soloParentModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openAffidavitLossModal = function() {
            document.getElementById('affidavitLossModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closeAffidavitLossModal = function() {
            document.getElementById('affidavitLossModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openSeniorIDLossModal = function() {
            document.getElementById('seniorIDLossModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closeSeniorIDLossModal = function() {
            document.getElementById('seniorIDLossModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openSwornAffidavitMotherModal = function() {
            document.getElementById('swornAffidavitMotherModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closeSwornAffidavitMotherModal = function() {
            document.getElementById('swornAffidavitMotherModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openPWDLossModal = function() {
            document.getElementById('pwdLossModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closePWDLossModal = function() {
            document.getElementById('pwdLossModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openBoticabLossModal = function() {
            document.getElementById('boticabLossModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closeBoticabLossModal = function() {
            document.getElementById('boticabLossModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openJointAffidavitModal = function() {
            document.getElementById('jointAffidavitModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.closeJointAffidavitModal = function() {
            document.getElementById('jointAffidavitModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        window.openDocumentRequestModal = function() {
            const modal = document.getElementById('requestAccessModal');
            if (modal) {
                modal.style.display = 'block';
            }
        };
        
        window.closeRequestAccessModal = function() {
            document.getElementById('requestAccessModal').style.display = 'none';
        };
        
        window.showRequestForm = function() {
            document.getElementById('requestStatusView').style.display = 'none';
            document.getElementById('requestFormView').style.display = 'block';
        };
        
        window.showRequestStatus = function() {
            document.getElementById('requestFormView').style.display = 'none';
            document.getElementById('requestStatusView').style.display = 'block';
        };

        // Data Preview Functions
        function viewAffidavitLossData() {
            const form = document.getElementById('affidavitLossForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewCompleteAddress').textContent = formData.get('completeAddress') || '-';
            document.getElementById('previewSpecifyItemLost').textContent = formData.get('specifyItemLost') || '-';
            document.getElementById('previewItemLost').textContent = formData.get('itemLost') || '-';
            document.getElementById('previewItemDetails').textContent = formData.get('itemDetails') || '-';
            document.getElementById('previewDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('affidavitLossDataPreview').style.display = 'block';
        }

        function hideAffidavitLossData() {
            document.getElementById('affidavitLossForm').style.display = 'block';
            document.getElementById('affidavitLossDataPreview').style.display = 'none';
        }

        // Sworn Affidavit (Solo Parent) Modal Functions
        function openSoloParentModal() {
            document.getElementById('soloParentModal').style.display = 'block';
        }
        function closeSoloParentModal() {
            document.getElementById('soloParentModal').style.display = 'none';
        }
        
        function addChild() {
            const container = document.getElementById('childrenContainer');
            const childEntry = document.createElement('div');
            childEntry.className = 'child-entry';
            childEntry.innerHTML = `
                <input type="text" name="childrenNames[]" placeholder="Child's Name" required>
                <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
                <button type="button" onclick="removeChild(this)" class="btn btn-danger btn-sm">Remove</button>
            `;
            container.appendChild(childEntry);
        }
        
        function removeChild(button) {
            button.parentElement.remove();
        }
        
        function saveSoloParent() {
            showDataSavedModal();
        }
        
        function viewSoloParentData() {
            const form = document.getElementById('soloParentForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewSoloFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewSoloCompleteAddress').textContent = formData.get('completeAddress') || '-';
            
            // Handle children
            const names = formData.getAll('childrenNames[]');
            const ages = formData.getAll('childrenAges[]');
            const children = names.map((name, index) => {
                const age = ages[index] || '';
                return name ? (age ? `${name} (${age})` : name) : null;
            }).filter(Boolean);
            document.getElementById('previewChildren').textContent = children.length ? children.join(', ') : '-';
            
            document.getElementById('previewYearsUnderCase').textContent = formData.get('yearsUnderCase') || '-';
            
            // Handle reason with conditional field
            let reasonText = formData.get('reasonSection') || '-';
            if (reasonText === 'Other reason, please state' && formData.get('otherReason')) {
                reasonText = `${reasonText}: ${formData.get('otherReason')}`;
            }
            document.getElementById('previewReason').textContent = reasonText;
            
            // Handle employment status with conditional fields
            let employmentText = formData.get('employmentStatus') || '-';
            if (employmentText === 'Employee and earning' && formData.get('employeeAmount')) {
                employmentText = `${employmentText} Php ${formData.get('employeeAmount')}`;
            } else if (employmentText === 'Self-employed and earning' && formData.get('selfEmployedAmount')) {
                employmentText = `${employmentText} Php ${formData.get('selfEmployedAmount')}`;
            } else if (employmentText === 'Un-employed and dependent upon' && formData.get('unemployedDependent')) {
                employmentText = `${employmentText} ${formData.get('unemployedDependent')}`;
            }
            document.getElementById('previewEmployment').textContent = employmentText;
            
            document.getElementById('previewSoloDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('soloParentDataPreview').style.display = 'block';
        }
        
        function saHideData() {
            document.getElementById('soloParentForm').style.display = 'block';
            document.getElementById('soloParentDataPreview').style.display = 'none';
        }
        
        function saSend() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('soloParentForm');
                const formData = new FormData(form);
                    
                const data = {
                    fullName: formData.get('fullName'),
                    completeAddress: formData.get('completeAddress'),
                    childrenNames: formData.getAll('childrenNames[]'),
                    childrenAges: formData.getAll('childrenAges[]'),
                    yearsUnderCase: formData.get('yearsUnderCase'),
                    reasonSection: formData.get('reasonSection'),
                    otherReason: formData.get('otherReason'),
                    employmentStatus: formData.get('employmentStatus'),
                    employeeAmount: formData.get('employeeAmount'),
                    selfEmployedAmount: formData.get('selfEmployedAmount'),
                    unemployedDependent: formData.get('unemployedDependent'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('soloParent', data);
            });
        }
        
        function toggleOtherReason() {
            const otherReasonContainer = document.getElementById('otherReasonContainer');
            const selectedReason = document.querySelector('input[name="reasonSection"]:checked');
            
            if (selectedReason && selectedReason.value === 'Other reason, please state') {
                otherReasonContainer.style.display = 'block';
            } else {
                otherReasonContainer.style.display = 'none';
            }
        }
        
        function toggleEmploymentFields() {
            const employeeContainer = document.getElementById('employeeAmountContainer');
            const selfEmployedContainer = document.getElementById('selfEmployedAmountContainer');
            const unemployedContainer = document.getElementById('unemployedDependentContainer');
            const selectedEmployment = document.querySelector('input[name="employmentStatus"]:checked');
            
            // Hide all containers first
            employeeContainer.style.display = 'none';
            selfEmployedContainer.style.display = 'none';
            unemployedContainer.style.display = 'none';
            
            // Show relevant container based on selection
            if (selectedEmployment) {
                if (selectedEmployment.value === 'Employee and earning') {
                    employeeContainer.style.display = 'block';
                } else if (selectedEmployment.value === 'Self-employed and earning') {
                    selfEmployedContainer.style.display = 'block';
                } else if (selectedEmployment.value === 'Un-employed and dependent upon') {
                    unemployedContainer.style.display = 'block';
                }
            }
        }

        function viewPWDLossData() {
            const form = document.getElementById('pwdLossForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewPwdFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewPwdFullAddress').textContent = formData.get('fullAddress') || '-';
            document.getElementById('previewPwdDetailsOfLoss').textContent = formData.get('detailsOfLoss') || '-';
            document.getElementById('previewPwdDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('pwdLossDataPreview').style.display = 'block';
        }

        function hidePWDLossData() {
            document.getElementById('pwdLossForm').style.display = 'block';
            document.getElementById('pwdLossDataPreview').style.display = 'none';
        }

        function viewBoticabLossData() {
            const form = document.getElementById('boticabLossForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewBoticabFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewBoticabFullAddress').textContent = formData.get('fullAddress') || '-';
            document.getElementById('previewBoticabDetailsOfLoss').textContent = formData.get('detailsOfLoss') || '-';
            document.getElementById('previewBoticabDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('boticabLossDataPreview').style.display = 'block';
        }

        function hideBoticabLossData() {
            document.getElementById('boticabLossForm').style.display = 'block';
            document.getElementById('boticabLossDataPreview').style.display = 'none';
        }

        // Joint Affidavit Modal Functions
        function openJointAffidavitModal() {
            document.getElementById('jointAffidavitModal').style.display = 'block';
        }

        function closeJointAffidavitModal() {
            document.getElementById('jointAffidavitModal').style.display = 'none';
        }

        function saveJointAffidavit() {
            // Save functionality - can be implemented later if needed
            showDataSavedModal();
        }

        function viewJointAffidavitData() {
            const form = document.getElementById('jointAffidavitForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewFirstPersonName').textContent = formData.get('firstPersonName') || '-';
            document.getElementById('previewSecondPersonName').textContent = formData.get('secondPersonName') || '-';
            document.getElementById('previewFirstPersonAddress').textContent = formData.get('firstPersonAddress') || '-';
            document.getElementById('previewSecondPersonAddress').textContent = formData.get('secondPersonAddress') || '-';
            document.getElementById('previewChildName').textContent = formData.get('childName') || '-';
            document.getElementById('previewDateOfBirth').textContent = formData.get('dateOfBirth') || '-';
            document.getElementById('previewPlaceOfBirth').textContent = formData.get('placeOfBirth') || '-';
            document.getElementById('previewFatherName').textContent = formData.get('fatherName') || '-';
            document.getElementById('previewMotherName').textContent = formData.get('motherName') || '-';
            document.getElementById('previewChildNameNumber4').textContent = formData.get('childNameNumber4') || '-';
            document.getElementById('previewJointDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('jointAffidavitDataPreview').style.display = 'block';
        }

        function hideJointAffidavitData() {
            document.getElementById('jointAffidavitForm').style.display = 'block';
            document.getElementById('jointAffidavitDataPreview').style.display = 'none';
        }

        function sendJointAffidavit() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('jointAffidavitForm');
                const formData = new FormData(form);
                
                const data = {
                    firstPersonName: formData.get('firstPersonName'),
                    secondPersonName: formData.get('secondPersonName'),
                    firstPersonAddress: formData.get('firstPersonAddress'),
                    secondPersonAddress: formData.get('secondPersonAddress'),
                    childName: formData.get('childName'),
                    dateOfBirth: formData.get('dateOfBirth'),
                    placeOfBirth: formData.get('placeOfBirth'),
                    fatherName: formData.get('fatherName'),
                    motherName: formData.get('motherName'),
                    childNameNumber4: formData.get('childNameNumber4'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('jointAffidavit', data);
            });
        }

        // Sworn Affidavit of Mother Modal Functions
        function openSwornAffidavitMotherModal() {
            document.getElementById('swornAffidavitMotherModal').style.display = 'block';
        }

        function closeSwornAffidavitMotherModal() {
            document.getElementById('swornAffidavitMotherModal').style.display = 'none';
        }

        function saveSwornAffidavitMother() {
            // Save functionality - can be implemented later if needed
            showDataSavedModal();
        }

        function viewSwornAffidavitMotherData() {
            const form = document.getElementById('swornAffidavitMotherForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewSwornMotherFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewSwornMotherCompleteAddress').textContent = formData.get('completeAddress') || '-';
            document.getElementById('previewSwornMotherChildName').textContent = formData.get('childName') || '-';
            document.getElementById('previewSwornMotherBirthDate').textContent = formData.get('birthDate') || '-';
            document.getElementById('previewSwornMotherBirthPlace').textContent = formData.get('birthPlace') || '-';
            document.getElementById('previewSwornMotherDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('swornAffidavitMotherDataPreview').style.display = 'block';
        }

        function hideSwornAffidavitMotherData() {
            document.getElementById('swornAffidavitMotherForm').style.display = 'block';
            document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
        }

        function sendSwornAffidavitMother() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('swornAffidavitMotherForm');
                const formData = new FormData(form);
                
                const data = {
                    fullName: formData.get('fullName'),
                    completeAddress: formData.get('completeAddress'),
                    childName: formData.get('childName'),
                    birthDate: formData.get('birthDate'),
                    birthPlace: formData.get('birthPlace'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('swornAffidavitMother', data);
            });
        }

        // Joint Affidavit of Two Disinterested Person (Solo Parent) Modal Functions
        function openJointAffidavitSoloParentModal() {
            document.getElementById('jointAffidavitSoloParentModal').style.display = 'block';
        }

        function closeJointAffidavitSoloParentModal() {
            document.getElementById('jointAffidavitSoloParentModal').style.display = 'none';
        }

        function addChildSoloParent() {
            const container = document.getElementById('childrenContainerSoloParent');
            const childEntry = document.createElement('div');
            childEntry.className = 'child-entry';
            childEntry.innerHTML = `
                <input type="text" name="childrenNames[]" placeholder="Child's Full Name" required>
                <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
                <button type="button" onclick="removeChildSoloParent(this)" class="btn btn-danger btn-sm">Remove</button>
            `;
            container.appendChild(childEntry);
        }

        function removeChildSoloParent(button) {
            button.parentElement.remove();
        }

        function saveJointAffidavitSoloParent() {
            showDataSavedModal();
        }

        function viewJointAffidavitSoloParentData() {
            const form = document.getElementById('jointAffidavitSoloParentForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewAffiant1Name').textContent = formData.get('affiant1Name') || '-';
            document.getElementById('previewAffiant2Name').textContent = formData.get('affiant2Name') || '-';
            document.getElementById('previewAffiantsAddress').textContent = formData.get('affiantsAddress') || '-';
            document.getElementById('previewSoloParentName').textContent = formData.get('soloParentName') || '-';
            document.getElementById('previewSoloParentAddress').textContent = formData.get('soloParentAddress') || '-';
            
            // Handle children
            const names = formData.getAll('childrenNames[]');
            const ages = formData.getAll('childrenAges[]');
            const children = names.map((name, index) => {
                const age = ages[index] || '';
                return name ? (age ? `${name} (${age})` : name) : null;
            }).filter(Boolean);
            document.getElementById('previewChildrenSoloParent').textContent = children.length ? children.join(', ') : '-';
            
            document.getElementById('previewAffiant1ValidId').textContent = formData.get('affiant1ValidId') || '-';
            document.getElementById('previewAffiant2ValidId').textContent = formData.get('affiant2ValidId') || '-';
            document.getElementById('previewJointSoloParentDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'block';
        }

        function hideJointAffidavitSoloParentData() {
            document.getElementById('jointAffidavitSoloParentForm').style.display = 'block';
            document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'none';
        }

        function sendJointAffidavitSoloParent() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('jointAffidavitSoloParentForm');
                const formData = new FormData(form);
                
                const data = {
                    affiant1Name: formData.get('affiant1Name'),
                    affiant2Name: formData.get('affiant2Name'),
                    affiantsAddress: formData.get('affiantsAddress'),
                    soloParentName: formData.get('soloParentName'),
                    soloParentAddress: formData.get('soloParentAddress'),
                    childrenNames: formData.getAll('childrenNames[]'),
                    childrenAges: formData.getAll('childrenAges[]'),
                    affiant1ValidId: formData.get('affiant1ValidId'),
                    affiant2ValidId: formData.get('affiant2ValidId'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('jointAffidavitSoloParent', data);
            });
        }

        // Senior ID Loss Modal Functions
        function openSeniorIDLossModal() {
            document.getElementById('seniorIDLossModal').style.display = 'block';
        }

        function closeSeniorIDLossModal() {
            document.getElementById('seniorIDLossModal').style.display = 'none';
        }

        function saveSeniorIDLoss() {
            const form = document.getElementById('seniorIDLossForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewSeniorFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewSeniorCompleteAddress').textContent = formData.get('completeAddress') || '-';
            document.getElementById('previewSeniorRelationship').textContent = formData.get('relationship') || '-';
            document.getElementById('previewSeniorCitizenName').textContent = formData.get('seniorCitizenName') || '-';
            document.getElementById('previewSeniorDetailsOfLoss').textContent = formData.get('detailsOfLoss') || '-';
            document.getElementById('previewSeniorDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('seniorIDLossDataPreview').style.display = 'block';
            
            showDataSavedModal();
        }

        function viewSeniorIDLossData() {
            const form = document.getElementById('seniorIDLossForm');
            const formData = new FormData(form);
            
            // Update preview values
            document.getElementById('previewSeniorFullName').textContent = formData.get('fullName') || '-';
            document.getElementById('previewSeniorCompleteAddress').textContent = formData.get('completeAddress') || '-';
            document.getElementById('previewSeniorRelationship').textContent = formData.get('relationship') || '-';
            document.getElementById('previewSeniorCitizenName').textContent = formData.get('seniorCitizenName') || '-';
            document.getElementById('previewSeniorDetailsOfLoss').textContent = formData.get('detailsOfLoss') || '-';
            document.getElementById('previewSeniorDateOfNotary').textContent = formData.get('dateOfNotary') || '-';
            
            // Show preview, hide form
            form.style.display = 'none';
            document.getElementById('seniorIDLossDataPreview').style.display = 'block';
        }

        function hideSeniorIDLossData() {
            document.getElementById('seniorIDLossForm').style.display = 'block';
            document.getElementById('seniorIDLossDataPreview').style.display = 'none';
        }

        function sendSeniorIDLoss() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('seniorIDLossForm');
                const formData = new FormData(form);
                
                const data = {
                    fullName: formData.get('fullName'),
                    completeAddress: formData.get('completeAddress'),
                    relationship: formData.get('relationship'),
                    seniorCitizenName: formData.get('seniorCitizenName'),
                    detailsOfLoss: formData.get('detailsOfLoss'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('seniorIDLoss', data);
            });
        }


        // Send Document Functions
        function sendAffidavitLoss() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('affidavitLossForm');
                const formData = new FormData(form);
                
                const data = {
                    fullName: formData.get('fullName'),
                    completeAddress: formData.get('completeAddress'),
                    specifyItemLost: formData.get('specifyItemLost'),
                    itemLost: formData.get('itemLost'),
                    itemDetails: formData.get('itemDetails'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('affidavitLoss', data);
            });
        }

        // sendSoloParent removed - direct generation now

        function sendPWDLoss() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('pwdLossForm');
                const formData = new FormData(form);
                
                const data = {
                    fullName: formData.get('fullName'),
                    fullAddress: formData.get('fullAddress'),
                    detailsOfLoss: formData.get('detailsOfLoss'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('pwdLoss', data);
            });
        }

        function sendBoticabLoss() {
            showSendConfirmationModal(() => {
                const form = document.getElementById('boticabLossForm');
                const formData = new FormData(form);
                
                const data = {
                    fullName: formData.get('fullName'),
                    fullAddress: formData.get('fullAddress'),
                    detailsOfLoss: formData.get('detailsOfLoss'),
                    dateOfNotary: formData.get('dateOfNotary')
                };

                sendDocumentToEmployee('boticabLoss', data);
            });
        }

        // Generic function to send document to employee
        function sendDocumentToEmployee(formType, formData) {
            // Get the send button and prevent double-clicking
            const sendBtn = event.target;
            if (sendBtn.disabled) {
                return; // Already processing
            }
            
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            sendBtn.disabled = true;

            // Send data to server
            fetch('send_document_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'form_type=' + encodeURIComponent(formType) + '&form_data=' + encodeURIComponent(JSON.stringify(formData))
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(result => {
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
                
                if (result.status === 'success') {
                    showSuccessModal();
                    // Close the modal
                    const modal = document.querySelector('.modal[style*="block"]');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                    // Refresh notifications to show any new ones
                    loadNotifications();
                } else {
                    showErrorModal('Error: ' + result.message);
                    console.error('Error details:', result.debug_info);
                }
            })
            .catch(error => {
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
                console.error('Error:', error);
                showErrorModal('Error sending document: ' + error.message);
            });
        }

        // Load document status
        function loadDocumentStatus() {
            fetch('get_client_document_status.php')
                .then(response => response.json())
                .then(data => {
                    displayDocumentStatus(data.documents);
                })
                .catch(error => {
                    console.error('Error loading document status:', error);
                    document.getElementById('documentStatusContainer').innerHTML = 
                        '<div class="error-state" style="text-align: center; padding: 40px; color: #dc3545;">' +
                        '<i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>' +
                        '<p>Error loading document status. Please try again later.</p>' +
                        '</div>';
                });
        }

        function displayDocumentStatus(documents) {
            const container = document.getElementById('documentStatusContainer');
            
            if (!documents || documents.length === 0) {
                container.innerHTML = 
                    '<div class="empty-state">' +
                    '<i class="fas fa-file-alt"></i>' +
                    '<h3>No Document Submissions</h3>' +
                    '<p>You haven\'t submitted any documents yet. Use the forms above to generate and submit your legal documents.</p>' +
                    '</div>';
                return;
            }
                
                // Format document type for display
            const formatDocumentType = (type) => {
                if (!type) return 'Unknown Document Type';
                return type.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());
            };
            
            const tableHtml = `
                <table class="document-table">
                    <thead>
                        <tr>
                            <th>Document Type</th>
                            <th>Request ID</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Reviewed</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${documents.map(doc => {
                            const statusClass = doc.status.toLowerCase();
                return `
                                <tr>
                                    <td>
                                        <div class="document-type">${formatDocumentType(doc.document_type)}</div>
                                    </td>
                                    <td>
                                        <span class="request-id">${doc.request_id}</span>
                                    </td>
                                    <td>
                                        <span class="status-badge ${statusClass}">
                                <span class="status-indicator status-${statusClass}"></span>
                                            ${doc.status}
                                        </span>
                                    </td>
                                    <td class="date-cell">${formatDate(doc.submitted_at)}</td>
                                    <td class="date-cell">${doc.reviewed_at ? formatDate(doc.reviewed_at) : '-'}</td>
                                    <td class="rejection-reason-cell">
                                        ${doc.rejection_reason ? 
                                            `<span class="rejection-reason-text" onclick="showRejectionReasonModal('${doc.rejection_reason.replace(/'/g, "\\'")}')" title="Click to view full reason">
                                                ${doc.rejection_reason.length > 50 ? doc.rejection_reason.substring(0, 50) + '...' : doc.rejection_reason}
                                            </span>` : 
                                            '<span class="no-reason">-</span>'
                                        }
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
            
            container.innerHTML = tableHtml;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Request Access Modal Functions
        function openDocumentRequestModal() {
            document.getElementById('requestAccessModal').style.display = 'block';
        }
        
        function closeRequestAccessModal() {
            document.getElementById('requestAccessModal').style.display = 'none';
        }
        
        function showRequestForm() {
            document.getElementById('requestStatusView').style.display = 'none';
            document.getElementById('requestFormView').style.display = 'block';
        }
        
        function showRequestStatus() {
            document.getElementById('requestFormView').style.display = 'none';
            document.getElementById('requestStatusView').style.display = 'block';
        }
        
        // Image preview functions for Request Access modal
        function showFrontPreview(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('front-image').src = e.target.result;
                    document.getElementById('front-filename').textContent = file.name;
                    document.getElementById('front-image-container').style.display = 'block';
                    
                    // Update the upload button text
                    document.querySelector('label[for="valid_id_front"] span').textContent = file.name;
                    
                    // Show success message
                    showUploadSuccessModal('Front ID uploaded successfully! You can see your image below.');
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        function showBackPreview(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('back-image').src = e.target.result;
                    document.getElementById('back-filename').textContent = file.name;
                    document.getElementById('back-image-container').style.display = 'block';
                    
                    // Update the upload button text
                    document.querySelector('label[for="valid_id_back"] span').textContent = file.name;
                    
                    // Show success message
                    showUploadSuccessModal('Back ID uploaded successfully! You can see your image below.');
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        function removeFrontImage() {
            // Clear the file input
            document.getElementById('valid_id_front').value = '';
            
            // Hide the preview container
            document.getElementById('front-image-container').style.display = 'none';
            
            // Reset the upload button text
            document.querySelector('label[for="valid_id_front"] span').textContent = 'Choose Front Image';
            
            // Clear the image source
            document.getElementById('front-image').src = '';
            document.getElementById('front-filename').textContent = '';
            
            showRemoveSuccessModal('Front ID image removed. You can now upload a different image.');
        }
        
        function removeBackImage() {
            // Clear the file input
            document.getElementById('valid_id_back').value = '';
            
            // Hide the preview container
            document.getElementById('back-image-container').style.display = 'none';
            
            // Reset the upload button text
            document.querySelector('label[for="valid_id_back"] span').textContent = 'Choose Back Image';
            
            // Clear the image source
            document.getElementById('back-image').src = '';
            document.getElementById('back-filename').textContent = '';
            
            showRemoveSuccessModal('Back ID image removed. You can now upload a different image.');
        }
        
        // Modal functions for upload and remove success messages
        function showUploadSuccessModal(message) {
            // Create a temporary success modal
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
                z-index: 10000;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                    <div style="color: #28a745; font-size: 48px; margin-bottom: 15px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 style="color: #28a745; margin-bottom: 15px;">Success!</h3>
                    <p style="color: #666; margin-bottom: 20px;">${message}</p>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        OK
                    </button>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 3000);
        }
        
        function showRemoveSuccessModal(message) {
            // Create a temporary success modal
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
                z-index: 10000;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                    <div style="color: #dc3545; font-size: 48px; margin-bottom: 15px;">
                        <i class="fas fa-trash"></i>
                    </div>
                    <h3 style="color: #dc3545; margin-bottom: 15px;">Image Removed</h3>
                    <p style="color: #666; margin-bottom: 20px;">${message}</p>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        OK
                    </button>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 3000);
        }
        
        // ZIP Code validation - only allow numbers
        document.getElementById('zip_code').addEventListener('input', function(e) {
            // Remove any non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 4 digits
            if (this.value.length > 4) {
                this.value = this.value.substring(0, 4);
            }
        });
        
        // Handle form submission
        document.getElementById('requestAccessForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
            
            fetch('client_request_access.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Check if submission was successful by looking for success indicators
                if (data.includes('success') || data.includes('Request submitted successfully')) {
                    showRequestSubmittedModal();
                    closeRequestAccessModal();
                    // Reload the page to update the status
                    location.reload();
                } else {
                    showErrorModal('Error submitting request. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error submitting request. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Show confirmation modal for sending document
        function showSendConfirmationModal(callback) {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'modal-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.3s ease;
                max-width: 500px;
                width: 90%;
            `;

            // Create modal header
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            modalHeader.innerHTML = `
                <i class="fas fa-exclamation-triangle" style="font-size: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></i>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Confirm Document Send</h3>
            `;

            // Create modal body
            const modalBody = document.createElement('div');
            modalBody.style.cssText = `
                padding: 25px;
                background: #f8f9fa;
                text-align: center;
            `;
            modalBody.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #333;">
                    <p style="margin: 0 0 15px 0; font-weight: 500;">Are you sure you want to send this document to the employee?</p>
                    <p style="margin: 0; color: #dc3545; font-weight: 600;">This action cannot be undone.</p>
                </div>
            `;

            // Create modal footer
            const modalFooter = document.createElement('div');
            modalFooter.style.cssText = `
                padding: 15px 25px;
                background: white;
                border-top: 1px solid #e1e5e9;
                border-radius: 0 0 16px 16px;
                text-align: center;
                display: flex;
                gap: 10px;
                justify-content: center;
            `;
            modalFooter.innerHTML = `
                <button onclick="this.closest('.modal-overlay').remove()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button onclick="confirmSend()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-paper-plane"></i> Send Document
                </button>
            `;

            // Assemble modal
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            modalContent.appendChild(modalFooter);
            modalOverlay.appendChild(modalContent);

            // Add to document
            document.body.appendChild(modalOverlay);

            // Close on overlay click
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                }
            });

            // Close on escape key
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    modalOverlay.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);

            // Confirm send function
            window.confirmSend = function() {
                modalOverlay.remove();
                if (callback) callback();
            };
        }

        // Show success modal
        function showSuccessModal() {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'modal-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.3s ease;
                max-width: 500px;
                width: 90%;
            `;

            // Create modal header
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            modalHeader.innerHTML = `
                <i class="fas fa-check-circle" style="font-size: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></i>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Document Sent Successfully!</h3>
            `;

            // Create modal body
            const modalBody = document.createElement('div');
            modalBody.style.cssText = `
                padding: 25px;
                background: #f8f9fa;
                text-align: center;
            `;
            modalBody.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #333;">
                    <p style="margin: 0 0 15px 0; font-weight: 500;">Your document has been successfully sent to the employee.</p>
                    <p style="margin: 0; color: #28a745; font-weight: 600;">You will be notified once it's reviewed.</p>
                </div>
            `;

            // Create modal footer
            const modalFooter = document.createElement('div');
            modalFooter.style.cssText = `
                padding: 15px 25px;
                background: white;
                border-top: 1px solid #e1e5e9;
                border-radius: 0 0 16px 16px;
                text-align: center;
            `;
            modalFooter.innerHTML = `
                <button onclick="this.closest('.modal-overlay').remove()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-check"></i> OK
                </button>
            `;

            // Assemble modal
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            modalContent.appendChild(modalFooter);
            modalOverlay.appendChild(modalContent);

            // Add to document
            document.body.appendChild(modalOverlay);

            // Close on overlay click
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                }
            });

            // Close on escape key
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    modalOverlay.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);

            // Auto close after 3 seconds
            setTimeout(() => {
                if (modalOverlay.parentNode) {
                    modalOverlay.remove();
                }
            }, 3000);
        }

        // Show data saved success modal
        function showDataSavedModal() {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'modal-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.3s ease;
                max-width: 400px;
                width: 90%;
            `;

            // Create modal header
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                background: linear-gradient(135deg, #17a2b8, #138496);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            modalHeader.innerHTML = `
                <i class="fas fa-save" style="font-size: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></i>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Data Saved!</h3>
            `;

            // Create modal body
            const modalBody = document.createElement('div');
            modalBody.style.cssText = `
                padding: 25px;
                background: #f8f9fa;
                text-align: center;
            `;
            modalBody.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #333;">
                    <p style="margin: 0; font-weight: 500;">Your data has been saved successfully!</p>
                </div>
            `;

            // Create modal footer
            const modalFooter = document.createElement('div');
            modalFooter.style.cssText = `
                padding: 15px 25px;
                background: white;
                border-top: 1px solid #e1e5e9;
                border-radius: 0 0 16px 16px;
                text-align: center;
            `;
            modalFooter.innerHTML = `
                <button onclick="this.closest('.modal-overlay').remove()" style="background: #17a2b8; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-check"></i> OK
                </button>
            `;

            // Assemble modal
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            modalContent.appendChild(modalFooter);
            modalOverlay.appendChild(modalContent);

            // Add to document
            document.body.appendChild(modalOverlay);

            // Close on overlay click
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                }
            });

            // Close on escape key
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    modalOverlay.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);

            // Auto close after 2 seconds
            setTimeout(() => {
                if (modalOverlay.parentNode) {
                    modalOverlay.remove();
                }
            }, 2000);
        }

        // Show error modal
        function showErrorModal(message) {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'modal-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.3s ease;
                max-width: 500px;
                width: 90%;
            `;

            // Create modal header
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            modalHeader.innerHTML = `
                <i class="fas fa-exclamation-triangle" style="font-size: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></i>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Error</h3>
            `;

            // Create modal body
            const modalBody = document.createElement('div');
            modalBody.style.cssText = `
                padding: 25px;
                background: #f8f9fa;
                text-align: center;
            `;
            modalBody.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #333;">
                    <p style="margin: 0; font-weight: 500;">${message}</p>
                </div>
            `;

            // Create modal footer
            const modalFooter = document.createElement('div');
            modalFooter.style.cssText = `
                padding: 15px 25px;
                background: white;
                border-top: 1px solid #e1e5e9;
                border-radius: 0 0 16px 16px;
                text-align: center;
            `;
            modalFooter.innerHTML = `
                <button onclick="this.closest('.modal-overlay').remove()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-times"></i> OK
                </button>
            `;

            // Assemble modal
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            modalContent.appendChild(modalFooter);
            modalOverlay.appendChild(modalContent);

            // Add to document
            document.body.appendChild(modalOverlay);

            // Close on overlay click
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                }
            });

            // Close on escape key
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    modalOverlay.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }

        // Show request submitted success modal
        function showRequestSubmittedModal() {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'modal-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.3s ease;
                max-width: 500px;
                width: 90%;
            `;

            // Create modal header
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            modalHeader.innerHTML = `
                <i class="fas fa-paper-plane" style="font-size: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></i>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Request Submitted!</h3>
            `;

            // Create modal body
            const modalBody = document.createElement('div');
            modalBody.style.cssText = `
                padding: 25px;
                background: #f8f9fa;
                text-align: center;
            `;
            modalBody.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #333;">
                    <p style="margin: 0 0 15px 0; font-weight: 500;">Request submitted successfully!</p>
                    <p style="margin: 0; color: #28a745; font-weight: 600;">You will be notified once it's reviewed.</p>
                </div>
            `;

            // Create modal footer
            const modalFooter = document.createElement('div');
            modalFooter.style.cssText = `
                padding: 15px 25px;
                background: white;
                border-top: 1px solid #e1e5e9;
                border-radius: 0 0 16px 16px;
                text-align: center;
            `;
            modalFooter.innerHTML = `
                <button onclick="this.closest('.modal-overlay').remove()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-check"></i> OK
                </button>
            `;

            // Assemble modal
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            modalContent.appendChild(modalFooter);
            modalOverlay.appendChild(modalContent);

            // Add to document
            document.body.appendChild(modalOverlay);

            // Close on overlay click
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                }
            });

            // Close on escape key
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    modalOverlay.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);

            // Auto close after 3 seconds
            setTimeout(() => {
                if (modalOverlay.parentNode) {
                    modalOverlay.remove();
                }
            }, 3000);
        }

        // Show rejection reason modal
        function showRejectionReasonModal(reason) {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'modal-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            `;

            // Create modal content
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideInUp 0.3s ease;
                max-height: 80vh;
                overflow-y: auto;
                max-width: 600px;
                width: 90%;
            `;

            // Create modal header
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            modalHeader.innerHTML = `
                <i class="fas fa-exclamation-triangle" style="font-size: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></i>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Rejection Reason</h3>
            `;

            // Create modal body
            const modalBody = document.createElement('div');
            modalBody.style.cssText = `
                padding: 25px;
                background: #f8f9fa;
            `;
            modalBody.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #333; word-wrap: break-word; white-space: pre-wrap;">${reason}</div>
            `;

            // Create modal footer
            const modalFooter = document.createElement('div');
            modalFooter.style.cssText = `
                padding: 15px 25px;
                background: white;
                border-top: 1px solid #e1e5e9;
                border-radius: 0 0 16px 16px;
                text-align: center;
            `;
            modalFooter.innerHTML = `
                <button onclick="this.closest('.modal-overlay').remove()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fas fa-times"></i> Close
                </button>
            `;

            // Assemble modal
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            modalContent.appendChild(modalFooter);
            modalOverlay.appendChild(modalContent);

            // Add to document
            document.body.appendChild(modalOverlay);

            // Close on overlay click
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                }
            });

            // Close on escape key
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    modalOverlay.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }

        // Load document status on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDocumentStatus();
        });
    </script>


<script src="assets/js/unread-messages.js?v=1761535513"></script></body>
</html> 