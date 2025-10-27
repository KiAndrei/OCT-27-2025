<?php
session_start();
require_once 'config.php';

// Access control: only attorneys (or admin_attorney) allowed
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'] ?? '', ['attorney', 'admin_attorney'])) {
    header('Location: login_form.php');
    exit();
}

$attorney_id = (int)$_SESSION['user_id'];

// Ensure efiling_history table exists (idempotent safety)
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS efiling_history (
  id int(11) NOT NULL AUTO_INCREMENT,
  attorney_id int(11) NOT NULL,
  case_id int(11) DEFAULT NULL,
  document_category varchar(50) DEFAULT NULL,
  file_name varchar(255) NOT NULL,
  original_file_name varchar(255) DEFAULT NULL,
  stored_file_path varchar(500) DEFAULT NULL,
  receiver_email varchar(255) NOT NULL,
  message text,
  status enum('Sent','Failed') NOT NULL DEFAULT 'Sent',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY attorney_id (attorney_id),
  KEY case_id (case_id),
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Get profile image
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

// Fetch cases for dropdown (owned by this attorney) with client information
$cases = [];
$stmt = $conn->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.case_type,
        u.name as client_name
    FROM attorney_cases c
    LEFT JOIN user_form u ON c.client_id = u.id
    WHERE c.attorney_id=? 
    ORDER BY c.title ASC
");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $cases[] = $row;

            // Fetch recent eFiling history for this attorney with case information
            $history = [];
            $stmt = $conn->prepare("
                SELECT 
                    ef.id, 
                    ef.case_id,
                    ef.document_category, 
                    ef.file_name, 
                    ef.original_file_name, 
                    ef.stored_file_path, 
                    ef.receiver_email, 
                    ef.status, 
                    ef.created_at,
                    c.title as case_title,
                    c.case_type,
                    u.name as client_name
                FROM efiling_history ef
                LEFT JOIN attorney_cases c ON ef.case_id = c.id
                LEFT JOIN user_form u ON c.client_id = u.id
                WHERE ef.attorney_id=? 
                ORDER BY ef.created_at DESC 
                LIMIT 100
            ");
            $stmt->bind_param("i", $attorney_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $history[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Filing - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
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
            <li><a href="attorney_cases.php" ><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php" class="active"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'E-Filing';
        $page_subtitle = 'Send court submissions via firm email';
        include 'components/profile_header.php'; 
        ?>

        <!-- eFiling Card -->
        <div class="efiling-grid">
            <div class="efiling-card efiling-submission-card">
                <div class="card-header">
                    <h2><i class="fas fa-paper-plane"></i> New eFiling Submission</h2>
                    <p>Send documents using the firm's Gmail. * Required fields.</p>
                </div>
                <div class="card-body">
                    <form id="efilingForm" enctype="multipart/form-data">
                        <!-- Step 1: Request Details -->
                        <div id="step1" class="form-step active">
                            <div class="step-title-with-progress">
                                <div class="progress-circle" id="progressCircle">
                                    <div class="progress-text">
                                        <div id="currentStep">1</div>
                                        <div class="progress-step">of 5</div>
                                    </div>
                                </div>
                                <h3 class="step-title-text">Request Details</h3>
                            </div>
                            
                        <div class="form-row">
                            <div class="form-group">
                                    <label>Service Type *</label>
                                    <select name="service_type" id="service_type" required>
                                        <option value="">Select Service Type</option>
                                        <option value="File Case">File Case</option>
                                        <option value="Supplemental Pleadings, and Answers">Supplemental Pleadings, and Answers</option>
                                        <option value="Filling of Applications, Motions, or Requests">Filling of Applications, Motions, or Requests</option>
                                    </select>
                                    </div>
                                <div class="form-group">
                                    <label>Payor Type *</label>
                                    <select name="payor_type" id="payor_type" required>
                                        <option value="">Select Payor Type</option>
                                        <option value="Individual">Individual</option>
                                        <option value="Juridical">Juridical</option>
                                    </select>
                                </div>
                </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()" disabled>
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Court Details -->
                        <div id="step2" class="form-step">
                            <div class="step-title-with-progress">
                                <div class="progress-circle">
                                    <div class="progress-text">
                                        <div>2</div>
                                        <div class="progress-step">of 5</div>
                                    </div>
                                </div>
                                <h3 class="step-title-text">Court Details</h3>
                            </div>
                            
                            <div class="form-row">
                            <div class="form-group">
                                    <label>Court Level *</label>
                                    <select name="court_level" id="court_level" required onchange="updateCourtDetails()">
                                        <option value="">Select Court Level</option>
                                        <option value="Courts of Appeals">Courts of Appeals</option>
                                        <option value="Court of Tax Appeals">Court of Tax Appeals</option>
                                        <option value="Sandiganbayan">Sandiganbayan</option>
                                        <option value="Supreme Court">Supreme Court</option>
                                        <option value="2nd Level Court">2nd Level Court</option>
                                        <option value="1st Level Court">1st Level Court</option>
                                </select>
                </div>
                                <div class="form-group">
                                    <label>Court Type *</label>
                                    <select name="court_type" id="court_type" required>
                                        <option value="">Select Court Type</option>
                                    </select>
            </div>
                            </div>

                        <div class="form-row">
                                <div class="form-group">
                                    <label>Region *</label>
                                    <select name="region" id="region" required onchange="updateProvinces()">
                                        <option value="">Select Region</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Province *</label>
                                    <select name="province" id="province" required>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Court Station/Office *</label>
                                    <select name="court_station" id="court_station" required onchange="updateCourtStations()">
                                        <option value="">Select Court Station/Office</option>
                                    </select>
                                </div>
                            <div class="form-group">
                                <label>Receiver Email *</label>
                                    <input type="email" name="receiver_email" id="receiver_email" placeholder="Enter receiver email address" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="nextBtn2" onclick="nextStep()" disabled>
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Case Details -->
                        <div id="step3" class="form-step">
                            <div class="step-title-with-progress">
                                <div class="progress-circle">
                                    <div class="progress-text">
                                        <div>3</div>
                                        <div class="progress-step">of 5</div>
                                    </div>
                                </div>
                                <h3 class="step-title-text">Case Details</h3>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Collection Type *</label>
                                    <select name="case_category" id="case_category" required>
                                        <option value="">Select Collection Type</option>
                                        <option value="An Action Where the Value of the Subject Matter Cannot be Estimated">An Action Where the Value of the Subject Matter Cannot be Estimated</option>
                                        <option value="En Banc Case">En Banc Case</option>
                                        <option value="Petition for Review or Appealed Case (CTA)">Petition for Review or Appealed Case (CTA)</option>
                                        <option value="Refund/Issuance of Tax Credit">Refund/Issuance of Tax Credit</option>
                                        <option value="Review of the Assessment of Commissioner of Internal Revenue">Review of the Assessment of Commissioner of Internal Revenue</option>
                                </select>
                            </div>
                            <div class="form-group">
                                    <label>Case Type *</label>
                                    <select name="case_type" id="case_type" required>
                                        <option value="">Select Case Type</option>
                                        <option value="Criminal">Criminal</option>
                                        <option value="Civil">Civil</option>
                                        <option value="Family">Family</option>
                                        <option value="Administrative">Administrative</option>
                                        <option value="Labor">Labor</option>
                                        <option value="Tax">Tax</option>
                                        <option value="Environmental">Environmental</option>
                                        <option value="Election">Election</option>
                                        <option value="Special Proceedings">Special Proceedings</option>
                                        <option value="Human Rights">Human Rights</option>
                                        <option value="Corporate">Corporate</option>
                                        <option value="Quasi-Judicial">Quasi-Judicial</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                        <div class="form-group">
                                    <label>Case No./Roll/MCLE/Bar Examinee No. *</label>
                                    <input type="text" name="case_number" id="case_number" placeholder="Enter Case Number" required>
                        </div>
                        <div class="form-group">
                                    <label>Case Title/Particulars *</label>
                                    <input type="text" name="case_title" id="case_title" placeholder="Enter Case Title/Particulars" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="nextBtn3" onclick="nextStep()" disabled>
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Personal Information -->
                        <div id="step4" class="form-step">
                            <div class="step-title-with-progress">
                                <div class="progress-circle">
                                    <div class="progress-text">
                                        <div>4</div>
                                        <div class="progress-step">of 5</div>
                                    </div>
                                </div>
                                <h3 class="step-title-text">Personal Information</h3>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Party Type *</label>
                                    <select name="party_type" id="party_type" required>
                                        <option value="">Select Party Type</option>
                                        <option value="Dependant">Dependant</option>
                                        <option value="Plaintiff">Plaintiff</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <!-- Empty space for layout balance -->
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" id="first_name" placeholder="Enter First Name" required>
                                </div>
                                <div class="form-group">
                                    <label>Middle Initial</label>
                                    <input type="text" name="middle_initial" id="middle_initial" placeholder="Enter Middle Initial">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" id="last_name" placeholder="Enter Last Name" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address *</label>
                                    <input type="email" name="email_address" id="email_address" placeholder="Enter Email Address" required>
                                    <div class="error-message" id="email_error">Please enter a valid email address</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Mobile Number *</label>
                                    <input type="tel" name="mobile_number" id="mobile_number" placeholder="Enter Mobile Number (e.g., 09123456789 or +639123456789)" required>
                                    <div class="error-message" id="mobile_error">Please enter a valid mobile number (09XXXXXXXXX or +639XXXXXXXXX)</div>
                                </div>
                                <div class="form-group">
                                    <label>Upload Document *</label>
                                    <input type="file" name="document" id="document" accept=".pdf" required>
                                <small class="hint">Allowed: PDF only • Max 5MB</small>
                        </div>
                        </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="nextBtn4" onclick="nextStep()" disabled>
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 5: Basis of Fees -->
                        <div id="step5" class="form-step">
                            <div class="step-title-with-progress">
                                <div class="progress-circle">
                                    <div class="progress-text">
                                        <div>5</div>
                                        <div class="progress-step">of 5</div>
                                    </div>
                                </div>
                                <h3 class="step-title-text">Basis of Fees</h3>
                            </div>
                            
                            <div class="form-row">
                        <div class="form-group">
                                    <label>Fee Exemption *</label>
                                    <select name="fee_exemption" id="fee_exemption" required onchange="toggleFeeExemptionFields()">
                                        <option value="">Select Option</option>
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Condition *</label>
                                    <select name="fee_condition" id="fee_condition" required>
                                        <option value="">Select Condition</option>
                                        <option value="Indigent Clients of the Public Attorney's Office (PAO) (OCA Circular No. 121-2007)">Indigent Clients of the Public Attorney's Office (PAO) (OCA Circular No. 121-2007)</option>
                                        <option value="Person/s filing a Petition for Writ of Amparo (A.M. No. 07-9-12-SC)">Person/s filing a Petition for Writ of Amparo (A.M. No. 07-9-12-SC)</option>
                                        <option value="Petitions for Voluntary Confinement under Article VIII, Section 54 of Republic Act 9165 (A.M. No. 10-2-03-0)">Petitions for Voluntary Confinement under Article VIII, Section 54 of Republic Act 9165 (A.M. No. 10-2-03-0)</option>
                                        <option value="Indigent Litigant filing a Writ of Habeas Data (Sec. 5, The Rule on the Writ of Habeas Data, AM No. 08-1-16-5C)">Indigent Litigant filing a Writ of Habeas Data (Sec. 5, The Rule on the Writ of Habeas Data, AM No. 08-1-16-5C)</option>
                                        <option value="Reconstitution of Torrens Certificate of Title Lost or Destroyed and Cancellation of Encumbrance Pursuant to Sec. 23...">Reconstitution of Torrens Certificate of Title Lost or Destroyed and Cancellation of Encumbrance Pursuant to Sec. 23...</option>
                                        <option value="Writ of Kalikasan (Sec. 4, Rule 7, Rules of Procedure for Environmental Cases, AM No. 09-6-8-SC)">Writ of Kalikasan (Sec. 4, Rule 7, Rules of Procedure for Environmental Cases, AM No. 09-6-8-SC)</option>
                                        <option value="Clients of the National Committee on Legal Aid (NCLA) (OCA Circular No. 137-2009)">Clients of the National Committee on Legal Aid (NCLA) (OCA Circular No. 137-2009)</option>
                                        <option value="Tenant-Farmer, agricultural lessee or tiller, settler or amortizing owner-cultivator (PD No. 946, Sec. 16)">Tenant-Farmer, agricultural lessee or tiller, settler or amortizing owner-cultivator (PD No. 946, Sec. 16)</option>
                                        <option value="COMELEC filing a Petition for Exclusion of Voters (OCA Circular No. 54-2003)">COMELEC filing a Petition for Exclusion of Voters (OCA Circular No. 54-2003)</option>
                                        <option value="Republic of the Philippines, its agencies and instrumentalities (Sec. 22, A.M. No. 35-2004)">Republic of the Philippines, its agencies and instrumentalities (Sec. 22, A.M. No. 35-2004)</option>
                                        <option value="Indigent Litigants">Indigent Litigants</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>No. of Defendants/Respondents</label>
                                    <input type="text" name="defendants_count" id="defendants_count" placeholder="Enter number of defendants/respondents">
                                </div>
                                <div class="form-group">
                                    <!-- Empty space for layout balance -->
                                </div>
                        </div>

                        <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                    <i class="fas fa-arrow-left"></i> Previous
                            </button>
                                <button type="button" class="btn btn-primary" id="processBtn" onclick="showConfirmationModal()"><i class="fas fa-check-circle"></i> Process</button>
                            <button type="reset" class="btn btn-secondary" id="resetBtn">Clear</button>
                        </div>
                        </div>

                    </form>
                    <div id="resultMsg" class="result" style="display:none;"></div>
                    
                    <!-- Confirmation Modal -->
                    <div id="confirmationModal" class="modal" style="display:none;">
                        <div class="modal-content confirmation-modal">
                            <div class="modal-header">
                                <h3><i class="fas fa-clipboard-check"></i> Review Submission</h3>
                            </div>
                            <div class="modal-body">
                                <div id="confirmationContent">
                                    <!-- Content will be populated by JavaScript -->
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeConfirmationModal()">Cancel</button>
                                <button type="button" class="btn btn-primary" id="confirmSendBtn" onclick="submitEfiling()">
                                    <i class="fas fa-paper-plane"></i> Send eFiling
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Success Modal -->
                    <div id="successModal" class="modal" style="display:none;">
                        <div class="modal-content success-modal">
                            <div class="modal-header">
                                <h3><i class="fas fa-check-circle"></i> Success!</h3>
                            </div>
                            <div class="modal-body">
                                <p id="successMessage">Submission sent successfully!</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="closeSuccessModal()">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="efiling-card history-card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fas fa-history"></i> Submission History</h2>
                    
                    <!-- Pagination Controls aligned with header -->
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label for="historyItemsPerPage" style="font-weight: 600; color: #1f2937; font-size: 0.9rem;">Show per page:</label>
                        <select id="historyItemsPerPage" style="padding: 8px 16px; border: 2px solid #5D0E26; border-radius: 8px; background: white; color: #5D0E26; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease;">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                            <option value="25">25</option>
                            <option value="30">30</option>
                            <option value="all">All</option>
                        </select>
                        <span class="history-items-info" style="font-weight: 500; color: #6c757d; font-size: 0.85rem;"></span>
                    </div>
                </div>
                <div class="card-body">
                    
                    <table class="history-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>File Name (Used)</th>
                                <th>Category</th>
                                <th>Original File Name</th>
                                <th>Receiver Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <?php if (empty($history)): ?>
                                <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px;font-style:italic;font-size:14px;">
                                    <i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block;color:#d1d5db;"></i>
                                    No e-filing submissions yet
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                <tr class="history-row" data-id="<?= $h['id'] ?>">
                                    <td class="date-time"><?= date('M j, Y g:i A', strtotime($h['created_at'])) ?></td>
                                    <td><span class="file-name-cell" title="<?= htmlspecialchars($h['file_name']) ?>"><?= strlen($h['file_name']) > 20 ? substr(htmlspecialchars($h['file_name']), 0, 20) . '...' : htmlspecialchars($h['file_name']) ?></span></td>
                                    <td><span class="category-badge"><?= htmlspecialchars($h['document_category'] ?? 'N/A') ?></span></td>
                                    <td><span class="original-file-name" title="<?= htmlspecialchars($h['original_file_name'] ?? '-') ?>"><?= strlen($h['original_file_name'] ?? '-') > 25 ? substr(htmlspecialchars($h['original_file_name'] ?? '-'), 0, 25) . '...' : htmlspecialchars($h['original_file_name'] ?? '-') ?></span></td>
                                    <td><span class="receiver-email" title="<?= htmlspecialchars($h['receiver_email']) ?>"><?= strlen($h['receiver_email']) > 25 ? substr(htmlspecialchars($h['receiver_email']), 0, 25) . '...' : htmlspecialchars($h['receiver_email']) ?></span></td>
                                    <td><span class="status-badge status-<?= strtolower($h['status']) ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button onclick="viewCaseInfo(<?= $h['case_id'] ?? 'null' ?>, '<?= htmlspecialchars($h['case_title'] ?? '') ?>', '<?= htmlspecialchars($h['case_type'] ?? '') ?>', '<?= htmlspecialchars($h['client_name'] ?? '') ?>', '<?= htmlspecialchars($h['attorney_name'] ?? 'Unknown') ?>')" class="btn-view-case" title="View Case Info">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                        <?php if (!empty($h['stored_file_path']) && file_exists($h['stored_file_path'])): ?>
                                            <a href="view_efiling_file.php?id=<?= $h['id'] ?>" target="_blank" class="btn-view-file" title="View File">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download_efiling_file.php?id=<?= $h['id'] ?>" class="btn-download-file" title="Download File">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#999;font-size:11px;font-style:italic;padding: 8px 12px;background: #f5f5f5;border-radius: 6px;">File not available</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    

                </div>
            </div>
        </div>
    </div>

    <style>
        .efiling-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        .efiling-card { 
            background: #fff; 
            border: 1px solid #e5e7eb; 
            border-radius: 16px; 
            box-shadow: 0 8px 25px rgba(16,24,40,0.08);
            overflow: hidden;
        }
        .efiling-card .card-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid #f1f3f4; 
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
        }
        .efiling-card .card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .efiling-card .card-header h2 { 
            margin: 0; 
            font-size: 1.2rem; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            color: #1f2937;
            font-weight: 600;
        }
        .efiling-card .card-header p { 
            margin: 8px 0 0 32px; 
            color: #6b7280; 
            font-size: 0.9rem; 
        }
        .efiling-card .card-body { 
            padding: 24px; 
        }
        .history-card .card-body { 
            padding: 0 12px 8px 12px; 
        }
        
        .pagination-bottom {
            margin-top: 1rem;
            padding: 0.75rem 0;
        }
        .file-name-cell {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #374151;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .original-file-name {
            font-size: 12px;
            color: #6b7280;
            font-style: italic;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .receiver-email {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #7C0F2F;
            font-weight: 500;
        }
        .date-time {
            font-size: 13px;
            color: #374151;
            font-weight: 500;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#5D0E26; font-size:14px; text-transform:uppercase; letter-spacing:.5px; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:12px 14px; border:1px solid #d6d9de; background:#fafbfc; border-radius:10px; font-size:14px; box-shadow: inset 0 1px 2px rgba(16,24,40,0.04); }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:#8B1538; box-shadow: 0 0 0 4px rgba(139,21,56,.12); }
        .hint { color:#6b7280; font-size:12px; display:block; margin-top:6px; }
        .warn { color:#b91c1c; font-size:12px; }
        .form-actions { display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #edf0f3; padding-top:16px; margin-top:8px; }
        .btn { border:none; border-radius:10px; padding:12px 18px; cursor:pointer; font-weight:600; }
        .btn-primary { background:#7C0F2F; color:#fff; }
        .btn-primary:hover { background:#8B1538; }
        .btn-secondary { background:#697586; color:#fff; }
        
        /* Case Selection Styles */
        .case-selection-container {
            position: relative;
        }
        
        .case-selection-trigger {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d6d9de;
            background: #fafbfc;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .case-selection-trigger:hover {
            border-color: #8B1538;
            background: #fff;
        }
        
        .case-selection-trigger i {
            color: #6b7280;
            transition: transform 0.3s ease;
        }
        
        /* Modal Styles */
        .case-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .case-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }
        
        .case-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f3f4;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .case-modal-header h3 {
            margin: 0;
            color: #1f2937;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .case-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .case-modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .case-modal-body {
            padding: 20px 24px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .case-search-container {
            margin-bottom: 20px;
            position: relative;
        }
        
        .case-search-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 1px solid #d6d9de;
            border-radius: 10px;
            font-size: 14px;
            background: #fafbfc;
        }
        
        .case-search-input:focus {
            outline: none;
            border-color: #8B1538;
            box-shadow: 0 0 0 4px rgba(139,21,56,.12);
            background: #fff;
        }
        
        .case-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 14px;
        }
        
        .case-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .case-group {
            margin-bottom: 20px;
        }
        
        .case-group-title {
            padding: 8px 12px;
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .case-item {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .case-item:hover {
            border-color: #8B1538;
            background: #fef7f7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139,21,56,0.1);
        }
        
        .case-item.selected {
            border-color: #7C0F2F;
            background: #fef7f7;
        }
        
        .case-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .case-details {
            font-size: 12px;
            color: #6b7280;
        }
        
        .case-client {
            margin-top: 2px;
        }
        
        .case-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #f1f3f4;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .history-table{ 
            width:100%; 
            border-collapse:collapse; 
            background:#fff; 
            border-radius:8px; 
            overflow:hidden; 
            box-shadow:0 2px 8px rgba(0,0,0,0.06); 
            font-size:11px;
        }
        .history-table th{ 
            background:linear-gradient(135deg,#7C0F2F,#8B1538); 
            color:#fff; 
            font-weight:600; 
            font-size:10px; 
            text-transform:uppercase; 
            letter-spacing:.3px; 
            padding:8px 6px; 
            text-align:left; 
            border:none; 
        }
        .history-table td{ 
            padding:8px 6px; 
            border-bottom:1px solid #f1f3f4; 
            font-size:11px; 
            color:#374151; 
            vertical-align:middle; 
        }
        .file-name-cell{ 
            font-family:'Courier New',monospace; 
            font-size:10px; 
            color:#374151; 
            background:#f8f9fa; 
            padding:2px 4px; 
            border-radius:3px; 
            display:inline-block; 
            max-width:120px; 
            overflow:hidden; 
            text-overflow:ellipsis; 
            white-space:nowrap; 
        }
        .original-file-name{ 
            font-size:9px; 
            color:#6b7280; 
            font-style:italic; 
            max-width:120px; 
            overflow:hidden; 
            text-overflow:ellipsis; 
            white-space:nowrap; 
        }
        .receiver-email{ 
            font-family:'Courier New',monospace; 
            font-size:10px; 
            color:#7C0F2F; 
            font-weight:500; 
        }
        .date-time{ 
            font-size:10px; 
            color:#374151; 
            font-weight:500; 
        }
        .status-badge{ 
            padding:3px 6px; 
            border-radius:12px; 
            font-size:8px; 
            font-weight:600; 
            text-transform:uppercase; 
            letter-spacing:.2px; 
            display:inline-block; 
        }
        .status-sent{ background:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 2px 4px rgba(16,185,129,.3); }
        .status-failed{ background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; box-shadow:0 2px 4px rgba(239,68,68,.3); }
        .result { margin-top:12px; padding:12px; border-radius:8px; }
        .result.success { background:#e8f5e9; color:#2e7d32; }
        .result.error { background:#ffebee; color:#c62828; }
        .btn-view-file, .btn-download-file, .btn-view-case {
            display:inline-flex; 
            align-items:center; 
            justify-content:center;
            width:24px; 
            height:24px; 
            padding:2px 4px; 
            margin:0 1px;
            border:none; 
            border-radius:4px; 
            text-decoration:none; 
            cursor:pointer;
            font-size:9px; 
            font-weight:600; 
            transition:all .3s ease;
            box-shadow:0 1px 3px rgba(0,0,0,0.1);
        }
        .btn-view-case { background:linear-gradient(135deg,#6b7280,#4b5563); color:#fff; }
        .btn-view-case:hover { background:linear-gradient(135deg,#4b5563,#374151); transform:translateY(-2px); box-shadow:0 4px 12px rgba(107,114,128,.4); }
        .btn-view-file { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
        .btn-view-file:hover { background:linear-gradient(135deg,#2563eb,#1d4ed8); transform:translateY(-2px); box-shadow:0 4px 12px rgba(59,130,246,.4); }
        .btn-download-file { background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
        .btn-download-file:hover { background:linear-gradient(135deg,#059669,#047857); transform:translateY(-2px); box-shadow:0 4px 12px rgba(16,185,129,.4); }
        
        .case-info {
            font-size: 13px;
            line-height: 1.4;
        }
        .case-info strong {
            color: #2c3e50;
            font-weight: 600;
        }
        .case-info small {
            color: #6c757d;
            display: block;
            margin-top: 2px;
        }
        .case-number {
            color: #007bff !important;
            font-weight: 500;
        }
        .case-type {
            color: #28a745 !important;
            font-style: italic;
        }
        .client-name {
            color: #6f42c1 !important;
        }
        .no-case {
            color: #6c757d;
            font-style: italic;
            font-size: 12px;
        }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
        
        /* Multi-step form styles */
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
        }
        
        .step-title-with-progress {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .step-title-text {
            color: #5D0E26;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .progress-circle {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(#7C0F2F 0deg, #7C0F2F var(--progress, 0deg), #e9ecef var(--progress, 0deg));
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(124, 15, 47, 0.2);
        }
        
        .progress-circle::before {
            content: '';
            position: absolute;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            z-index: 1;
        }
        
        .progress-text {
            position: relative;
            z-index: 2;
            font-size: 14px;
            font-weight: 700;
            color: #7C0F2F;
            text-align: center;
            line-height: 1.2;
        }
        
        .progress-step {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 1px solid #edf0f3;
            padding-top: 20px;
            margin-top: 24px;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .success-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        .confirmation-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 1200px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @media (max-width: 768px) {
            .confirmation-modal {
                width: 98%;
                max-width: 98%;
                margin: 10px;
            }
        }
        
        .confirmation-section {
            margin-bottom: 20px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .confirmation-section h4 {
            margin: 0 0 12px 0;
            color: #7C0F2F;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .confirmation-field {
            display: flex;
            margin-bottom: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .confirmation-field:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .confirmation-label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
            font-size: 14px;
        }
        
        .confirmation-value {
            color: #212529;
            font-size: 14px;
            flex: 1;
        }
        
        .confirmation-file {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #1976d2;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .modal-header i {
            font-size: 20px;
        }
        
        .modal-body {
            padding: 24px;
            text-align: center;
        }
        
        .modal-body p {
            margin: 0;
            font-size: 16px;
            color: #374151;
            line-height: 1.5;
        }
        
        .modal-footer {
            padding: 16px 24px 24px;
            text-align: center;
        }
        
        .modal-footer .btn {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-footer .btn-primary {
            background: linear-gradient(135deg, #7C0F2F, #8B1538);
            color: white;
        }
        
        .modal-footer .btn-primary:hover {
            background: linear-gradient(135deg, #8B1538, #9B1A3F);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 15, 47, 0.3);
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
    </style>

    <script>
        // Multi-step form variables
        let currentStep = 1;
        const totalSteps = 5;
        
        const resultMsg = document.getElementById('resultMsg');
        const successModal = document.getElementById('successModal');
        const successMessage = document.getElementById('successMessage');
        const confirmationModal = document.getElementById('confirmationModal');
        const confirmationContent = document.getElementById('confirmationContent');
        const form = document.getElementById('efilingForm');

        // Success modal functions
        function showSuccessModal(message) {
            successMessage.textContent = message;
            successModal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
        
        function closeSuccessModal() {
            successModal.style.display = 'none';
            document.body.classList.remove('modal-open');
            // Reset form and go back to step 1
            form.reset();
            currentStep = 1;
            document.querySelectorAll('.form-step').forEach(step => step.classList.remove('active'));
            document.getElementById('step1').classList.add('active');
            updateProgress();
        }
        
        // Confirmation modal functions
        function showConfirmationModal() {
            // Validate all steps first
            if (!validateAllSteps()) {
                return;
            }
            
            // Generate confirmation content
            generateConfirmationContent();
            
            // Show modal
            confirmationModal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
        
        function closeConfirmationModal() {
            confirmationModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        function generateConfirmationContent() {
            const documentFile = document.getElementById('document').files[0];
            
            const content = `
                <div class="confirmation-section">
                    <h4><i class="fas fa-clipboard-list"></i> Request Details</h4>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Service Type:</div>
                        <div class="confirmation-value">${document.getElementById('service_type').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Payor Type:</div>
                        <div class="confirmation-value">${document.getElementById('payor_type').value}</div>
                    </div>
                </div>
                
                <div class="confirmation-section">
                    <h4><i class="fas fa-gavel"></i> Court Details</h4>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Court Level:</div>
                        <div class="confirmation-value">${document.getElementById('court_level').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Court Type:</div>
                        <div class="confirmation-value">${document.getElementById('court_type').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Region:</div>
                        <div class="confirmation-value">${document.getElementById('region').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Province:</div>
                        <div class="confirmation-value">${document.getElementById('province').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Court Station:</div>
                        <div class="confirmation-value">${document.getElementById('court_station').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Receiver Email:</div>
                        <div class="confirmation-value">${document.getElementById('receiver_email').value}</div>
                    </div>
                </div>
                
                <div class="confirmation-section">
                    <h4><i class="fas fa-file-alt"></i> Case Details</h4>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Collection Type:</div>
                        <div class="confirmation-value">${document.getElementById('case_category').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Case Type:</div>
                        <div class="confirmation-value">${document.getElementById('case_type').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Case Number:</div>
                        <div class="confirmation-value">${document.getElementById('case_number').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Case Title:</div>
                        <div class="confirmation-value">${document.getElementById('case_title').value}</div>
                    </div>
                </div>
                
                <div class="confirmation-section">
                    <h4><i class="fas fa-user"></i> Personal Information</h4>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Party Type:</div>
                        <div class="confirmation-value">${document.getElementById('party_type').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Name:</div>
                        <div class="confirmation-value">${document.getElementById('first_name').value} ${document.getElementById('middle_initial').value} ${document.getElementById('last_name').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Email Address:</div>
                        <div class="confirmation-value">${document.getElementById('email_address').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Mobile Number:</div>
                        <div class="confirmation-value">${document.getElementById('mobile_number').value}</div>
                    </div>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Document:</div>
                        <div class="confirmation-value">
                            <div class="confirmation-file">
                                <i class="fas fa-file-pdf"></i> ${documentFile ? documentFile.name : 'No file selected'}
                                ${documentFile ? `(${(documentFile.size / 1024 / 1024).toFixed(2)} MB)` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="confirmation-section">
                    <h4><i class="fas fa-dollar-sign"></i> Basis of Fees</h4>
                    <div class="confirmation-field">
                        <div class="confirmation-label">Fee Exemption:</div>
                        <div class="confirmation-value">${document.getElementById('fee_exemption').value}</div>
                    </div>
                    ${document.getElementById('fee_exemption').value === 'Yes' ? `
                    <div class="confirmation-field">
                        <div class="confirmation-label">Condition:</div>
                        <div class="confirmation-value">${document.getElementById('fee_condition').value}</div>
                    </div>
                    ` : ''}
                    <div class="confirmation-field">
                        <div class="confirmation-label">No. of Defendants:</div>
                        <div class="confirmation-value">${document.getElementById('defendants_count').value || 'Not specified'}</div>
                    </div>
                </div>
            `;
            
            confirmationContent.innerHTML = content;
        }
        
        function validateAllSteps() {
            // Validate Step 1
            const serviceType = document.getElementById('service_type').value;
            const payorType = document.getElementById('payor_type').value;
            if (!serviceType || !payorType) {
                alert('Please complete Step 1: Request Details');
                return false;
            }
            
            // Validate Step 2
            const courtLevel = document.getElementById('court_level').value;
            const courtType = document.getElementById('court_type').value;
            const region = document.getElementById('region').value;
            const province = document.getElementById('province').value;
            const courtStation = document.getElementById('court_station').value;
            const receiverEmail = document.getElementById('receiver_email').value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValidEmail = emailPattern.test(receiverEmail);
            
            if (!courtLevel || !courtType || !region || !province || !courtStation || !receiverEmail || !isValidEmail) {
                alert('Please complete Step 2: Court Details');
                return false;
            }
            
            // Validate Step 3
            const caseCategory = document.getElementById('case_category').value;
            const caseTypeSelect = document.getElementById('case_type').value;
            const caseNumber = document.getElementById('case_number').value;
            const caseTitle = document.getElementById('case_title').value;
            if (!caseCategory || !caseTypeSelect || !caseNumber || !caseTitle) {
                alert('Please complete Step 3: Case Details');
                return false;
            }
            
            // Validate Step 4
            const partyType = document.getElementById('party_type').value;
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const emailAddress = document.getElementById('email_address').value.trim();
            const mobileNumber = document.getElementById('mobile_number').value.trim();
            const documentFile = document.getElementById('document').files[0];
            
            const emailPattern2 = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValidEmail2 = emailPattern2.test(emailAddress);
            const mobilePattern = /^(\+63|0)9\d{9}$/;
            const isValidMobile = mobilePattern.test(mobileNumber);
            const hasFile = documentFile && documentFile.size > 0;
            const isValidFileSize = hasFile && documentFile.size <= 5 * 1024 * 1024;
            
            if (!partyType || !firstName || !lastName || !emailAddress || !mobileNumber || !isValidEmail2 || !isValidMobile || !hasFile || !isValidFileSize) {
                alert('Please complete Step 4: Personal Information');
                return false;
            }
            
            // Validate Step 5
            const feeExemption = document.getElementById('fee_exemption').value;
            const feeCondition = document.getElementById('fee_condition').value;
            
            if (!feeExemption) {
                alert('Please complete Step 5: Basis of Fees');
                return false;
            }
            
            if (feeExemption === 'Yes' && !feeCondition) {
                alert('Please select a condition for fee exemption');
                return false;
            }
            
            return true;
        }
        
        // Step navigation functions
        function updateProgress() {
            const progressCircles = document.querySelectorAll('.progress-circle');
            const currentStepElement = document.getElementById('currentStep');
            
            // Calculate progress percentage (0-100%)
            const progressPercentage = (currentStep / totalSteps) * 100;
            
            // Update all progress circles
            progressCircles.forEach(circle => {
                circle.style.setProperty('--progress', `${progressPercentage * 3.6}deg`);
            });
            
            // Update step number display in the active step
            if (currentStepElement) {
                currentStepElement.textContent = currentStep;
            }
        }
        
        function nextStep() {
            if (currentStep < totalSteps) {
                document.getElementById(`step${currentStep}`).classList.remove('active');
                currentStep++;
                document.getElementById(`step${currentStep}`).classList.add('active');
                
                // Update progress circle
                updateProgress();
                
                // Enable/disable next buttons based on validation
                validateCurrentStep();
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                document.getElementById(`step${currentStep}`).classList.remove('active');
                currentStep--;
                document.getElementById(`step${currentStep}`).classList.add('active');
                
                // Update progress circle
                updateProgress();
                
                // Enable/disable next buttons based on validation
                validateCurrentStep();
            }
        }
        
        function validateCurrentStep() {
            const nextBtn = document.getElementById('nextBtn');
            const nextBtn2 = document.getElementById('nextBtn2');
            const nextBtn3 = document.getElementById('nextBtn3');
            const nextBtn4 = document.getElementById('nextBtn4');
            
            if (currentStep === 1) {
                const serviceType = document.getElementById('service_type').value;
                const payorType = document.getElementById('payor_type').value;
                nextBtn.disabled = !(serviceType && payorType);
            } else if (currentStep === 2) {
                const courtLevel = document.getElementById('court_level').value;
                const courtType = document.getElementById('court_type').value;
                const region = document.getElementById('region').value;
                const province = document.getElementById('province').value;
                const courtStation = document.getElementById('court_station').value;
                const receiverEmail = document.getElementById('receiver_email').value.trim();
                
                // Email validation
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const isValidEmail = emailPattern.test(receiverEmail);
                
                nextBtn2.disabled = !(courtLevel && courtType && region && province && courtStation && receiverEmail && isValidEmail);
            } else if (currentStep === 3) {
                const caseCategory = document.getElementById('case_category').value;
                const caseTypeSelect = document.getElementById('case_type').value;
                const caseNumber = document.getElementById('case_number').value;
                const caseTitle = document.getElementById('case_title').value;
                nextBtn3.disabled = !(caseCategory && caseTypeSelect && caseNumber && caseTitle);
            } else if (currentStep === 4) {
                const partyType = document.getElementById('party_type').value;
                const firstName = document.getElementById('first_name').value.trim();
                const lastName = document.getElementById('last_name').value.trim();
                const emailAddress = document.getElementById('email_address').value.trim();
                const mobileNumber = document.getElementById('mobile_number').value.trim();
                const documentFile = document.getElementById('document').files[0];
                
                // Email validation
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const isValidEmail = emailPattern.test(emailAddress);
                
                // Mobile number validation (Philippines format: +63XXXXXXXXXX or 09XXXXXXXXX)
                const mobilePattern = /^(\+63|0)9\d{9}$/;
                const isValidMobile = mobilePattern.test(mobileNumber);
                
                // File validation
                const hasFile = documentFile && documentFile.size > 0;
                const isValidFileSize = hasFile && documentFile.size <= 5 * 1024 * 1024; // 5MB
                
                nextBtn4.disabled = !(partyType && firstName && lastName && emailAddress && mobileNumber && isValidEmail && isValidMobile && hasFile && isValidFileSize);
            }
        }
        
        // Fee Exemption toggle function
        function toggleFeeExemptionFields() {
            const feeExemption = document.getElementById('fee_exemption').value;
            const feeCondition = document.getElementById('fee_condition');
            
            if (feeExemption === 'Yes') {
                // Enable condition field when Yes is selected
                feeCondition.disabled = false;
                feeCondition.required = true;
            } else if (feeExemption === 'No') {
                // Disable condition field when No is selected
                feeCondition.disabled = true;
                feeCondition.required = false;
                feeCondition.value = '';
            }
            
            // Validate current step after toggling
            validateCurrentStep();
        }

        // Court details auto-fill function
        function updateCourtDetails() {
            const courtLevel = document.getElementById('court_level').value;
            const courtType = document.getElementById('court_type');
            const region = document.getElementById('region');
            const province = document.getElementById('province');
            const courtStation = document.getElementById('court_station');
            
            // Clear all dependent fields
            courtType.innerHTML = '<option value="">Select Court Type</option>';
            region.innerHTML = '<option value="">Select Region</option>';
            province.innerHTML = '<option value="">Select Province</option>';
            courtStation.innerHTML = '<option value="">Select Court Station/Office</option>';
            
            if (courtLevel === 'Court of Tax Appeals') {
                // Auto-fill for Court of Tax Appeals
                courtType.innerHTML = '<option value="Court of Tax Appeals" selected>Court of Tax Appeals</option>';
                region.innerHTML = '<option value="NCR" selected>NCR</option>';
                province.innerHTML = '<option value="Metro Manila" selected>Metro Manila</option>';
                
                // Ensure Court Station is a dropdown and auto-fill
                const courtStationElement = document.getElementById('court_station');
                if (courtStationElement.tagName === 'INPUT') {
                    // Convert back to select if it was converted to input
                    const courtStationSelect = document.createElement('select');
                    courtStationSelect.name = 'court_station';
                    courtStationSelect.id = 'court_station';
                    courtStationSelect.required = true;
                    courtStationSelect.innerHTML = '<option value="Court of Tax Appeals" selected>Court of Tax Appeals</option>';
                    courtStationElement.parentNode.replaceChild(courtStationSelect, courtStationElement);
                } else {
                    courtStation.innerHTML = '<option value="Court of Tax Appeals" selected>Court of Tax Appeals</option>';
                }
            } else if (courtLevel === 'Sandiganbayan') {
                // Auto-fill for Sandiganbayan
                courtType.innerHTML = '<option value="Sandiganbayan" selected>Sandiganbayan</option>';
                region.innerHTML = '<option value="NCR" selected>NCR</option>';
                province.innerHTML = '<option value="Metro Manila" selected>Metro Manila</option>';
                
                // Ensure Court Station is a dropdown and auto-fill
                const courtStationElement = document.getElementById('court_station');
                if (courtStationElement.tagName === 'INPUT') {
                    // Convert back to select if it was converted to input
                    const courtStationSelect = document.createElement('select');
                    courtStationSelect.name = 'court_station';
                    courtStationSelect.id = 'court_station';
                    courtStationSelect.required = true;
                    courtStationSelect.innerHTML = '<option value="Sandiganbayan" selected>Sandiganbayan</option>';
                    courtStationElement.parentNode.replaceChild(courtStationSelect, courtStationElement);
                } else {
                    courtStation.innerHTML = '<option value="Sandiganbayan" selected>Sandiganbayan</option>';
                }
            } else if (courtLevel === 'Courts of Appeals') {
                // Add options for Courts of Appeals
                courtType.innerHTML = `
                    <option value="">Select Court Type</option>
                    <option value="Court of Appeals - CDO">Court of Appeals - CDO</option>
                    <option value="Court of Appeals - Manila">Court of Appeals - Manila</option>
                    <option value="Court of Appeals - Cebu">Court of Appeals - Cebu</option>
                `;
                populateRegions();
                province.innerHTML = '<option value="">Select Province</option>';
                courtStation.innerHTML = '<option value="">Select Court Station/Office</option>';
            } else if (courtLevel === 'Supreme Court') {
                // Add options for Supreme Court
                courtType.innerHTML = `
                    <option value="">Select Court Type</option>
                    <option value="Office of the Court Administrator">Office of the Court Administrator</option>
                    <option value="Mandatory Continuing Legal Education Office">Mandatory Continuing Legal Education Office</option>
                    <option value="Supreme Court (FMBO)">Supreme Court (FMBO)</option>
                `;
                populateRegions();
                province.innerHTML = '<option value="">Select Province</option>';
                courtStation.innerHTML = '<option value="">Select Court Station/Office</option>';
            } else if (courtLevel === '2nd Level Court') {
                // Add options for 2nd Level Court
                courtType.innerHTML = `
                    <option value="">Select Court Type</option>
                    <option value="Regional Trial Court">Regional Trial Court</option>
                    <option value="Sharia District Court">Sharia District Court</option>
                `;
                populateRegions();
                province.innerHTML = '<option value="">Select Province</option>';
                courtStation.innerHTML = '<option value="">Select Court Station/Office</option>';
            } else if (courtLevel === '1st Level Court') {
                // Add options for 1st Level Court
                courtType.innerHTML = `
                    <option value="">Select Court Type</option>
                    <option value="Metropolitan Trial Court">Metropolitan Trial Court</option>
                    <option value="Municipal Trial Court">Municipal Trial Court</option>
                    <option value="Municipal Circuit Trial Court">Municipal Circuit Trial Court</option>
                    <option value="Municipal Trial Court in Cities">Municipal Trial Court in Cities</option>
                    <option value="Sharia Circuit Court">Sharia Circuit Court</option>
                `;
                populateRegions();
                province.innerHTML = '<option value="">Select Province</option>';
                courtStation.innerHTML = '<option value="">Select Court Station/Office</option>';
            }
            
            // Validate current step after updating
            validateCurrentStep();
        }

        // Populate regions dropdown
        function populateRegions() {
            const regionSelect = document.getElementById('region');
            regionSelect.innerHTML = `
                <option value="">Select Region</option>
                <option value="NCR">NCR</option>
                <option value="Region I">Region I</option>
                <option value="Region II">Region II</option>
                <option value="Region III">Region III</option>
                <option value="Region IV-A">Region IV-A</option>
                <option value="Region IV-B">Region IV-B</option>
                <option value="Region V">Region V</option>
                <option value="Region VI">Region VI</option>
                <option value="Region VII">Region VII</option>
                <option value="Region VIII">Region VIII</option>
                <option value="Region IX">Region IX</option>
                <option value="Region X">Region X</option>
                <option value="Region XI">Region XI</option>
                <option value="Region XII">Region XII</option>
                <option value="CAR">CAR</option>
                <option value="CARAGA">CARAGA</option>
                <option value="BARMM">BARMM</option>
            `;
        }

        // Region to Province mapping
        const regionProvinces = {
            'NCR': ['Metro Manila'],
            'Region I': ['Ilocos Norte', 'Ilocos Sur', 'La Union', 'Pangasinan'],
            'Region II': ['Batanes', 'Cagayan', 'Isabela', 'Nueva Vizcaya', 'Quirino'],
            'Region III': ['Aurora', 'Bataan', 'Bulacan', 'Nueva Ecija', 'Pampanga', 'Tarlac', 'Zambales'],
            'Region IV-A': ['Batangas', 'Cavite', 'Laguna', 'Quezon', 'Rizal'],
            'Region IV-B': ['Marinduque', 'Occidental Mindoro', 'Oriental Mindoro', 'Palawan', 'Romblon'],
            'Region V': ['Albay', 'Camarines Norte', 'Camarines Sur', 'Catanduanes', 'Masbate', 'Sorsogon'],
            'Region VI': ['Aklan', 'Antique', 'Capiz', 'Iloilo', 'Negros Occidental'],
            'Region VII': ['Bohol', 'Cebu', 'Negros Oriental', 'Siquijor'],
            'Region VIII': ['Biliran', 'Eastern Samar', 'Leyte', 'Northern Samar', 'Samar', 'Southern Leyte'],
            'Region IX': ['Zamboanga del Norte', 'Zamboanga del Sur', 'Zamboanga Sibugay'],
            'Region X': ['Bukidnon', 'Camiguin', 'Lanao del Norte', 'Misamis Occidental', 'Misamis Oriental'],
            'Region XI': ['Compostela Valley', 'Davao del Norte', 'Davao del Sur', 'Davao Occidental', 'Davao Oriental'],
            'Region XII': ['Cotabato', 'Sarangani', 'South Cotabato', 'Sultan Kudarat'],
            'CAR': ['Abra', 'Apayao', 'Benguet', 'Ifugao', 'Kalinga', 'Mountain Province'],
            'CARAGA': ['Agusan del Norte', 'Agusan del Sur', 'Dinagat Islands', 'Surigao del Norte', 'Surigao del Sur'],
            'BARMM': ['Basilan', 'Lanao del Sur', 'Maguindanao', 'Sulu', 'Tawi-Tawi']
        };

        // Update provinces based on selected region
        function updateProvinces() {
            const regionSelect = document.getElementById('region');
            const provinceSelect = document.getElementById('province');
            const selectedRegion = regionSelect.value;
            
            // Clear existing options
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            
            if (selectedRegion && regionProvinces[selectedRegion]) {
                // Add provinces for the selected region
                regionProvinces[selectedRegion].forEach(province => {
                    const option = document.createElement('option');
                    option.value = province;
                    option.textContent = province;
                    provinceSelect.appendChild(option);
                });
            }
            
            // Clear court stations when province changes
            const courtStationSelect = document.getElementById('court_station');
            courtStationSelect.innerHTML = '<option value="">Select Court Station/Office</option>';
            
            // Validate current step after updating
            validateCurrentStep();
        }

        // Province to Court Station mapping
        const provinceCourtStations = {
            'Metro Manila': [
                'Manila Regional Trial Court',
                'Quezon City Regional Trial Court',
                'Makati Regional Trial Court',
                'Pasig Regional Trial Court',
                'Taguig Regional Trial Court',
                'Mandaluyong Regional Trial Court',
                'Marikina Regional Trial Court',
                'Muntinlupa Regional Trial Court',
                'Las Piñas Regional Trial Court',
                'Parañaque Regional Trial Court',
                'Pasay Regional Trial Court',
                'Malabon Regional Trial Court',
                'Navotas Regional Trial Court',
                'Valenzuela Regional Trial Court',
                'Caloocan Regional Trial Court',
                'San Juan Regional Trial Court',
                'Pateros Regional Trial Court'
            ],
            'Cebu': [
                'Cebu City Regional Trial Court',
                'Mandaue City Regional Trial Court',
                'Lapu-Lapu City Regional Trial Court',
                'Talisay City Regional Trial Court',
                'Toledo City Regional Trial Court',
                'Danao City Regional Trial Court',
                'Bogo City Regional Trial Court',
                'Carcar City Regional Trial Court',
                'Naga City Regional Trial Court',
                'Balamban Regional Trial Court',
                'Bantayan Regional Trial Court',
                'Barili Regional Trial Court',
                'Carmen Regional Trial Court',
                'Consolacion Regional Trial Court',
                'Cordova Regional Trial Court',
                'Liloan Regional Trial Court',
                'Minglanilla Regional Trial Court',
                'San Fernando Regional Trial Court',
                'San Francisco Regional Trial Court',
                'Sogod Regional Trial Court',
                'Tabogon Regional Trial Court',
                'Tabuelan Regional Trial Court'
            ],
            'Davao del Sur': [
                'Davao City Regional Trial Court',
                'Digos City Regional Trial Court',
                'Bansalan Regional Trial Court',
                'Hagonoy Regional Trial Court',
                'Kiblawan Regional Trial Court',
                'Magsaysay Regional Trial Court',
                'Malalag Regional Trial Court',
                'Matanao Regional Trial Court',
                'Padada Regional Trial Court',
                'Santa Cruz Regional Trial Court',
                'Sulop Regional Trial Court'
            ],
            'Laguna': [
                'Calamba City Regional Trial Court',
                'San Pablo City Regional Trial Court',
                'Biñan City Regional Trial Court',
                'Cabuyao City Regional Trial Court',
                'San Pedro City Regional Trial Court',
                'Santa Rosa City Regional Trial Court',
                'Alaminos Regional Trial Court',
                'Bay Regional Trial Court',
                'Calauan Regional Trial Court',
                'Cavinti Regional Trial Court',
                'Famy Regional Trial Court',
                'Kalayaan Regional Trial Court',
                'Liliw Regional Trial Court',
                'Los Baños Regional Trial Court',
                'Luisiana Regional Trial Court',
                'Lumban Regional Trial Court',
                'Mabitac Regional Trial Court',
                'Magdalena Regional Trial Court',
                'Majayjay Regional Trial Court',
                'Nagcarlan Regional Trial Court',
                'Paete Regional Trial Court',
                'Pagsanjan Regional Trial Court',
                'Pakil Regional Trial Court',
                'Pangil Regional Trial Court',
                'Pila Regional Trial Court',
                'Rizal Regional Trial Court',
                'Santa Cruz Regional Trial Court',
                'Santa Maria Regional Trial Court',
                'Siniloan Regional Trial Court',
                'Victoria Regional Trial Court'
            ],
            'Cavite': [
                'Bacoor City Regional Trial Court',
                'Cavite City Regional Trial Court',
                'Dasmariñas City Regional Trial Court',
                'Imus City Regional Trial Court',
                'Tagaytay City Regional Trial Court',
                'Trece Martires City Regional Trial Court',
                'Alfonso Regional Trial Court',
                'Amadeo Regional Trial Court',
                'Carmona Regional Trial Court',
                'General Emilio Aguinaldo Regional Trial Court',
                'General Mariano Alvarez Regional Trial Court',
                'General Trias Regional Trial Court',
                'Indang Regional Trial Court',
                'Kawit Regional Trial Court',
                'Magallanes Regional Trial Court',
                'Maragondon Regional Trial Court',
                'Mendez Regional Trial Court',
                'Naic Regional Trial Court',
                'Noveleta Regional Trial Court',
                'Rosario Regional Trial Court',
                'Silang Regional Trial Court',
                'Tanza Regional Trial Court',
                'Ternate Regional Trial Court'
            ],
            'Batangas': [
                'Batangas City Regional Trial Court',
                'Lipa City Regional Trial Court',
                'Tanauan City Regional Trial Court',
                'Agoncillo Regional Trial Court',
                'Alitagtag Regional Trial Court',
                'Balayan Regional Trial Court',
                'Balete Regional Trial Court',
                'Bauan Regional Trial Court',
                'Calaca Regional Trial Court',
                'Calatagan Regional Trial Court',
                'Cuenca Regional Trial Court',
                'Ibaan Regional Trial Court',
                'Laurel Regional Trial Court',
                'Lemery Regional Trial Court',
                'Lian Regional Trial Court',
                'Lobo Regional Trial Court',
                'Mabini Regional Trial Court',
                'Malvar Regional Trial Court',
                'Mataasnakahoy Regional Trial Court',
                'Nasugbu Regional Trial Court',
                'Padre Garcia Regional Trial Court',
                'Rosario Regional Trial Court',
                'San Jose Regional Trial Court',
                'San Juan Regional Trial Court',
                'San Luis Regional Trial Court',
                'San Nicolas Regional Trial Court',
                'San Pascual Regional Trial Court',
                'Santa Teresita Regional Trial Court',
                'Santo Tomas Regional Trial Court',
                'Taal Regional Trial Court',
                'Talisay Regional Trial Court',
                'Taysan Regional Trial Court',
                'Tingloy Regional Trial Court',
                'Tuy Regional Trial Court'
            ]
        };

        // Update court stations based on selected province
        function updateCourtStations() {
            const provinceSelect = document.getElementById('province');
            const courtStationSelect = document.getElementById('court_station');
            const selectedProvince = provinceSelect.value;
            
            // Clear existing options
            courtStationSelect.innerHTML = '<option value="">Select Court Station/Office</option>';
            
            if (selectedProvince && provinceCourtStations[selectedProvince]) {
                // Add court stations for the selected province
                provinceCourtStations[selectedProvince].forEach(station => {
                    const option = document.createElement('option');
                    option.value = station;
                    option.textContent = station;
                    courtStationSelect.appendChild(option);
                });
            } else if (selectedProvince) {
                // For provinces not in the mapping, add a generic option
                const option = document.createElement('option');
                option.value = selectedProvince + ' Regional Trial Court';
                option.textContent = selectedProvince + ' Regional Trial Court';
                courtStationSelect.appendChild(option);
            }
            
            // Validate current step after updating
            validateCurrentStep();
        }
        
        // Add event listeners for step validation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize progress circle
            updateProgress();
            
            // Initial validation to set button states
            validateCurrentStep();
            
            const serviceType = document.getElementById('service_type');
            const payorType = document.getElementById('payor_type');
            
            serviceType.addEventListener('change', validateCurrentStep);
            payorType.addEventListener('change', validateCurrentStep);
            
            // Add event listeners for step 2 validation
            const courtLevel = document.getElementById('court_level');
            const courtType = document.getElementById('court_type');
            const region = document.getElementById('region');
            const province = document.getElementById('province');
            const courtStation = document.getElementById('court_station');
            const receiverEmail = document.getElementById('receiver_email');
            
            courtLevel.addEventListener('change', validateCurrentStep);
            courtType.addEventListener('change', validateCurrentStep);
            region.addEventListener('change', function() {
                updateProvinces();
                validateCurrentStep();
            });
            province.addEventListener('change', function() {
                updateCourtStations();
                validateCurrentStep();
            });
            courtStation.addEventListener('change', validateCurrentStep);
            receiverEmail.addEventListener('input', validateCurrentStep);
            
            // Add event listeners for step 3 validation
            const caseCategory = document.getElementById('case_category');
            const caseTypeSelect = document.getElementById('case_type');
            const caseNumber = document.getElementById('case_number');
            const caseTitle = document.getElementById('case_title');
            
            caseCategory.addEventListener('change', validateCurrentStep);
            caseTypeSelect.addEventListener('change', validateCurrentStep);
            caseNumber.addEventListener('input', validateCurrentStep);
            caseTitle.addEventListener('input', validateCurrentStep);
            
            // Add event listeners for step 4 validation
            const partyType = document.getElementById('party_type');
            const firstName = document.getElementById('first_name');
            const middleInitial = document.getElementById('middle_initial');
            const lastName = document.getElementById('last_name');
            const emailAddress = document.getElementById('email_address');
            const mobileNumber = document.getElementById('mobile_number');
            
            partyType.addEventListener('change', validateCurrentStep);
            firstName.addEventListener('input', validateCurrentStep);
            middleInitial.addEventListener('input', validateCurrentStep);
            lastName.addEventListener('input', validateCurrentStep);
            
            // Email validation with real-time feedback
            emailAddress.addEventListener('input', function() {
                validateEmail();
                validateCurrentStep();
            });
            
            // Mobile number validation with real-time feedback
            mobileNumber.addEventListener('input', function() {
                validateMobileNumber();
                validateCurrentStep();
            });
            
            // File validation with real-time feedback
            const documentFile = document.getElementById('document');
            documentFile.addEventListener('change', validateCurrentStep);
            
            // Add event listeners for step 5 validation (Basis of Fees only)
            const feeExemption = document.getElementById('fee_exemption');
            const feeCondition = document.getElementById('fee_condition');
            const defendantsCount = document.getElementById('defendants_count');
            
            feeExemption.addEventListener('change', validateCurrentStep);
            feeCondition.addEventListener('change', validateCurrentStep);
            defendantsCount.addEventListener('input', validateCurrentStep);
        });
        
        // Email validation function
        function validateEmail() {
            const emailInput = document.getElementById('email_address');
            const emailError = document.getElementById('email_error');
            const emailValue = emailInput.value.trim();
            
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValidEmail = emailPattern.test(emailValue);
            
            if (emailValue && !isValidEmail) {
                emailInput.classList.add('error');
                emailInput.classList.remove('valid');
                emailError.style.display = 'block';
            } else if (emailValue && isValidEmail) {
                emailInput.classList.add('valid');
                emailInput.classList.remove('error');
                emailError.style.display = 'none';
            } else {
                emailInput.classList.remove('error', 'valid');
                emailError.style.display = 'none';
            }
        }
        
        // Mobile number validation function
        function validateMobileNumber() {
            const mobileInput = document.getElementById('mobile_number');
            const mobileError = document.getElementById('mobile_error');
            const mobileValue = mobileInput.value.trim();
            
            const mobilePattern = /^(\+63|0)9\d{9}$/;
            const isValidMobile = mobilePattern.test(mobileValue);
            
            if (mobileValue && !isValidMobile) {
                mobileInput.classList.add('error');
                mobileInput.classList.remove('valid');
                mobileError.style.display = 'block';
            } else if (mobileValue && isValidMobile) {
                mobileInput.classList.add('valid');
                mobileInput.classList.remove('error');
                mobileError.style.display = 'none';
            } else {
                mobileInput.classList.remove('error', 'valid');
                mobileError.style.display = 'none';
            }
        }

        document.getElementById('resetBtn').addEventListener('click', function(){ 
            form.reset();
            resultMsg.style.display='none'; 
            // Reset form steps
            currentStep = 1;
            document.querySelectorAll('.form-step').forEach(step => step.classList.remove('active'));
            document.getElementById('step1').classList.add('active');
            updateProgress();
        });

        // Single-submit guard + proper Sending state + timeout + robust parsing
        let isEfilingSubmitting = false;
        
        function submitEfiling() {
            if (isEfilingSubmitting) return;
            
            isEfilingSubmitting = true;
            const confirmSendBtn = document.getElementById('confirmSendBtn');
            const originalText = confirmSendBtn.innerHTML;
            confirmSendBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Sending...`;
            confirmSendBtn.disabled = true;

            const fd = new FormData(form);
            
            // Collect all multi-step form data
            const formData = {
                // Step 1: Request Details
                service_type: document.getElementById('service_type').value,
                payor_type: document.getElementById('payor_type').value,
                
                // Step 2: Court Details
                court_level: document.getElementById('court_level').value,
                court_type: document.getElementById('court_type').value,
                region: document.getElementById('region').value,
                province: document.getElementById('province').value,
                court_station: document.getElementById('court_station').value,
                receiver_email: document.getElementById('receiver_email').value,
                
                // Step 3: Case Details
                case_category: document.getElementById('case_category').value,
                case_type: document.getElementById('case_type').value,
                case_number: document.getElementById('case_number').value,
                case_title: document.getElementById('case_title').value,
                
                // Step 4: Personal Information
                party_type: document.getElementById('party_type').value,
                first_name: document.getElementById('first_name').value,
                middle_initial: document.getElementById('middle_initial').value,
                last_name: document.getElementById('last_name').value,
                email_address: document.getElementById('email_address').value,
                mobile_number: document.getElementById('mobile_number').value,
                
                // Step 5: Basis of Fees
                fee_exemption: document.getElementById('fee_exemption').value,
                fee_condition: document.getElementById('fee_condition').value,
                defendants_count: document.getElementById('defendants_count').value
            };
            
            // Add all form data to FormData
            Object.keys(formData).forEach(key => {
                fd.append(key, formData[key]);
            });
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000); // 1 minute timeout
            fetch('process_efiling.php', { method:'POST', body: fd, signal: controller.signal })
                .then(async (r) => {
                    clearTimeout(timeoutId);
                    const text = await r.text();
                    try { return JSON.parse(text); } catch { throw new Error(text || 'Invalid server response'); }
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Close confirmation modal first
                        closeConfirmationModal();
                        // Then show success modal
                        setTimeout(() => {
                            showSuccessModal(data.message || 'Submission sent successfully.');
                        }, 300);
                    } else {
                        resultMsg.className = 'result error';
                        resultMsg.textContent = data.message || 'Failed to send submission.';
                        resultMsg.style.display = 'block';
                        confirmSendBtn.innerHTML = originalText;
                        confirmSendBtn.disabled = false;
                        isEfilingSubmitting = false;
                    }
                })
                .catch((err) => {
                    resultMsg.className = 'result error';
                    resultMsg.textContent = (err && err.name === 'AbortError') ? 'Request timed out. Please try again.' : (err && err.message ? err.message : 'Unexpected error. Please try again.');
                    resultMsg.style.display = 'block';
                    confirmSendBtn.innerHTML = originalText;
                    confirmSendBtn.disabled = false;
                    isEfilingSubmitting = false;
                });
        }

        function viewCaseInfo(caseId, caseTitle, caseType, clientName, attorneyName) {
            // Check if no case was selected
            if (!caseId || caseId === 'null' || caseId === null) {
                let modalContent = `
                    <div style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); padding: 0; border-radius: 16px; max-width: 450px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 8px 25px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid rgba(255,255,255,0.2);">
                        <!-- Header -->
                        <div style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); padding: 20px 25px; color: white; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin: 0; font-size: 22px; font-weight: 700; display: flex; align-items: center;">
                                    <i class="fas fa-info-circle" style="margin-right: 12px; font-size: 24px; opacity: 0.9;"></i>
                                    Case Information
                                </h3>
                                <button onclick="closeCaseModal()" style="background: rgba(255,255,255,0.2); border: none; font-size: 20px; color: white; cursor: pointer; padding: 8px 12px; border-radius: 8px; transition: all 0.3s ease; backdrop-filter: blur(10px);">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.1) 100%);"></div>
                        </div>
                        
                        <!-- Content -->
                        <div style="padding: 40px 25px; text-align: center;">
                            <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 30px; border-radius: 16px; border-left: 4px solid #ffc107; box-shadow: 0 8px 25px rgba(255, 193, 7, 0.2); margin-bottom: 25px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f57c00; margin-bottom: 15px; display: block;"></i>
                                <h4 style="margin: 0 0 10px 0; color: #e65100; font-size: 18px; font-weight: 600;">No Case Selected</h4>
                                <p style="color: #bf9000; font-size: 14px; margin: 0; line-height: 1.5;">This eFiling submission was sent without associating it to any specific case.</p>
                            </div>
                            
                            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); padding: 20px; border-radius: 12px; border-left: 4px solid #2196f3;">
                                <i class="fas fa-lightbulb" style="color: #2196f3; font-size: 24px; margin-bottom: 10px;"></i>
                                <p style="color: #1976d2; font-size: 13px; margin: 0; font-weight: 500;">Tip: You can select a case when submitting eFiling documents for better organization.</p>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div style="padding: 20px 25px; border-top: 1px solid #e9ecef; text-align: center;">
                            <button onclick="closeCaseModal()" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white; border: none; padding: 12px 30px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);">
                                <i class="fas fa-check" style="margin-right: 8px;"></i>
                                Close
                            </button>
                        </div>
                    </div>
                `;
                
                // Create modal overlay
                const modal = document.createElement('div');
                modal.id = 'caseModal';
                modal.style.cssText = `
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.5); display: flex; justify-content: center; 
                    align-items: center; z-index: 10000; backdrop-filter: blur(3px);
                `;
                modal.innerHTML = modalContent;
                document.body.appendChild(modal);
                
                // Close on overlay click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) closeCaseModal();
                });
                return;
            }

            // Show case information if case was selected
            let modalContent = `
                <div style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); padding: 0; border-radius: 16px; max-width: 550px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 8px 25px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid rgba(255,255,255,0.2);">
                    <!-- Header -->
                    <div style="background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%); padding: 20px 25px; color: white; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 22px; font-weight: 700; display: flex; align-items: center;">
                                <i class="fas fa-gavel" style="margin-right: 12px; font-size: 24px; opacity: 0.9;"></i>
                                Case Information
                            </h3>
                            <button onclick="closeCaseModal()" style="background: rgba(255,255,255,0.2); border: none; font-size: 20px; color: white; cursor: pointer; padding: 8px 12px; border-radius: 8px; transition: all 0.3s ease; backdrop-filter: blur(10px);">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.1) 100%);"></div>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 30px 25px;">
                        <!-- Case Title -->
                        <div style="margin-bottom: 25px; text-align: center;">
                            <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 12px; border-left: 4px solid #7C0F2F; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                                <h4 style="margin: 0; color: #2c3e50; font-size: 18px; font-weight: 600; line-height: 1.4;">${caseTitle}</h4>
                            </div>
                        </div>
                        
                        <!-- Case Details -->
                        <div style="space-y: 15px;">
                            
                            ${caseType ? `
                                <div style="display: flex; align-items: center; padding: 15px; background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border-radius: 10px; border-left: 4px solid #4caf50;">
                                    <i class="fas fa-balance-scale" style="color: #4caf50; font-size: 18px; margin-right: 12px; width: 20px;"></i>
                                    <div>
                                        <div style="color: #388e3c; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Case Type</div>
                                        <div style="color: #2c3e50; font-size: 16px; font-weight: 500; text-transform: capitalize;">${caseType}</div>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${clientName ? `
                                <div style="display: flex; align-items: center; padding: 15px; background: linear-gradient(135deg, #f3e5f5 0%, #fce4ec 100%); border-radius: 10px; border-left: 4px solid #9c27b0;">
                                    <i class="fas fa-user-tie" style="color: #9c27b0; font-size: 18px; margin-right: 12px; width: 20px;"></i>
                                    <div>
                                        <div style="color: #7b1fa2; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Client</div>
                                        <div style="color: #2c3e50; font-size: 16px; font-weight: 500;">${clientName}</div>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${attorneyName ? `
                                <div style="display: flex; align-items: center; padding: 15px; background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border-radius: 10px; border-left: 4px solid #4caf50;">
                                    <i class="fas fa-gavel" style="color: #4caf50; font-size: 18px; margin-right: 12px; width: 20px;"></i>
                                    <div>
                                        <div style="color: #388e3c; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Attorney</div>
                                        <div style="color: #2c3e50; font-size: 16px; font-weight: 500;">${attorneyName}</div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Footer -->
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; text-align: center;">
                            <button onclick="closeCaseModal()" style="background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%); color: white; border: none; padding: 12px 30px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(124, 15, 47, 0.3);">
                                <i class="fas fa-check" style="margin-right: 8px;"></i>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Create modal overlay
            const modal = document.createElement('div');
            modal.id = 'caseModal';
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.5); display: flex; justify-content: center; 
                align-items: center; z-index: 10000; backdrop-filter: blur(3px);
            `;
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            // Close on overlay click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeCaseModal();
            });
        }

        function closeCaseModal() {
            const modal = document.getElementById('caseModal');
            if (modal) {
                modal.remove();
            }
        }

        // Case Selection Modal Functions
        function openCaseSelectionModal() {
            const modal = document.createElement('div');
            modal.className = 'case-modal';
            modal.id = 'caseSelectionModal';
            
            // Group cases by type
            const groupedCases = {};
            <?php foreach ($cases as $c): ?>
                const type = '<?= $c['case_type'] ?: 'Other' ?>';
                if (!groupedCases[type]) groupedCases[type] = [];
                groupedCases[type].push({
                    id: <?= $c['id'] ?>,
                    title: '<?= htmlspecialchars($c['title']) ?>',
                    client: '<?= htmlspecialchars($c['client_name'] ?? '') ?>',
                    type: '<?= htmlspecialchars($c['case_type'] ?? '') ?>'
                });
            <?php endforeach; ?>
            
            modal.innerHTML = `
                <div class="case-modal-content">
                    <div class="case-modal-header">
                        <h3><i class="fas fa-gavel" style="color: #7C0F2F; margin-right: 8px;"></i>Select Case</h3>
                        <button class="case-modal-close" onclick="closeCaseSelectionModal()">&times;</button>
                    </div>
                    <div class="case-modal-body">
                        <div class="case-search-container">
                            <i class="fas fa-search case-search-icon"></i>
                            <input type="text" class="case-search-input" id="caseSearchInput" placeholder="Search cases by title, client, or case type..." onkeyup="filterCasesInModal()">
                        </div>
                        <div class="case-list" id="caseList">
                            ${generateCaseListHTML(groupedCases)}
                        </div>
                    </div>
                    <div class="case-modal-footer">
                        <button class="btn btn-secondary" onclick="clearCaseSelection()">Clear Selection</button>
                        <button class="btn btn-primary" onclick="confirmCaseSelection()">Confirm Selection</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
            
            // Focus search input
            setTimeout(() => {
                document.getElementById('caseSearchInput').focus();
            }, 100);
        }
        
        function generateCaseListHTML(groupedCases) {
            let html = '';
            const sortedTypes = Object.keys(groupedCases).sort();
            
            sortedTypes.forEach(type => {
                html += `
                    <div class="case-group" data-type="${type.toLowerCase()}">
                        <div class="case-group-title">🏛️ ${type} Cases (${groupedCases[type].length})</div>
                `;
                
                groupedCases[type].forEach(caseItem => {
                    html += `
                        <div class="case-item" data-id="${caseItem.id}" data-title="${caseItem.title}" data-client="${caseItem.client}" data-type="${caseItem.type}" onclick="selectCaseInModal(${caseItem.id}, '${caseItem.title}', '${caseItem.client}', '${caseItem.type}')">
                            <div class="case-title">#${caseItem.id} — ${caseItem.title}</div>
                            <div class="case-details">
                                <div class="case-client">👤 ${caseItem.client || 'No client assigned'}</div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
            });
            
            return html;
        }
        
        function selectCaseInModal(caseId, title, client, type) {
            // Remove previous selection
            document.querySelectorAll('.case-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selection to clicked item
            event.currentTarget.classList.add('selected');
            
            // Store selection data
            window.selectedCase = { id: caseId, title: title, client: client, type: type };
        }
        
        function filterCasesInModal() {
            const searchTerm = document.getElementById('caseSearchInput').value.toLowerCase();
            const caseItems = document.querySelectorAll('.case-item');
            const caseGroups = document.querySelectorAll('.case-group');
            
            let visibleCount = 0;
            
            caseItems.forEach(item => {
                const title = item.getAttribute('data-title').toLowerCase();
                const client = item.getAttribute('data-client').toLowerCase();
                const type = item.getAttribute('data-type').toLowerCase();
                const caseId = item.getAttribute('data-id');
                
                if (searchTerm === '' || 
                    title.includes(searchTerm) || 
                    client.includes(searchTerm) || 
                    type.includes(searchTerm) ||
                    caseId.includes(searchTerm)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide groups based on visible items
            caseGroups.forEach(group => {
                const groupItems = group.querySelectorAll('.case-item');
                let groupVisible = false;
                
                groupItems.forEach(item => {
                    if (item.style.display !== 'none') {
                        groupVisible = true;
                    }
                });
                
                group.style.display = groupVisible ? 'block' : 'none';
            });
        }
        
        function clearCaseSelection() {
            window.selectedCase = null;
            closeCaseSelectionModal();
        }
        
        function confirmCaseSelection() {
            if (window.selectedCase) {
                closeCaseSelectionModal();
            }
        }
        
        function closeCaseSelectionModal() {
            const modal = document.getElementById('caseSelectionModal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('caseSelectionModal');
            if (modal && event.target === modal) {
                closeCaseSelectionModal();
            }
        });

        // Pagination functionality for submission history
        (function() {
            // Wait for DOM to be ready
            function initPagination() {
                const historyItemsPerPageSelect = document.getElementById('historyItemsPerPage');
                if (!historyItemsPerPageSelect) {
                    setTimeout(initPagination, 100);
                    return;
                }
                
                const historyItemsInfo = document.querySelector('.history-items-info');
                if (!historyItemsInfo) {
                    console.error('History items info not found!');
                    return;
                }
                
                // Function to update displayed history items
                function updateHistoryDisplay() {
                    const value = historyItemsPerPageSelect.value;
                    
                    // Get all rows
                    const allRows = document.querySelectorAll('.history-table tbody tr');
                    
                    // Filter actual submission rows (exclude empty state)
                    const submissionRows = [];
                    allRows.forEach(row => {
                        const firstCell = row.querySelector('td');
                        if (firstCell && !firstCell.hasAttribute('colspan')) {
                            submissionRows.push(row);
                        }
                    });
                    
                    const totalItems = submissionRows.length;
                    let showCount = totalItems;
                    
                    if (value !== 'all') {
                        showCount = parseInt(value);
                    }
                    
                    console.log('Total submissions:', totalItems, 'Show:', showCount);
                    
                    // Force hide all rows first
                    allRows.forEach(row => {
                        row.setAttribute('style', 'display: none !important;');
                    });
                    
                    // Show only the selected rows
                    submissionRows.forEach((row, index) => {
                        if (value === 'all' || index < showCount) {
                            row.setAttribute('style', 'display: table-row !important;');
                        }
                    });
                    
                    // Update counter
                    const displayCount = value === 'all' ? totalItems : Math.min(showCount, totalItems);
                    if (totalItems > 0) {
                        historyItemsInfo.textContent = `Showing ${displayCount} of ${totalItems} submissions`;
                    } else {
                        historyItemsInfo.textContent = '';
                    }
                }
                
                // Event listener
                historyItemsPerPageSelect.addEventListener('change', updateHistoryDisplay);
                
                // Initial call
                updateHistoryDisplay();
                
                // Hover effects
                historyItemsPerPageSelect.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#8B1538';
                    this.style.boxShadow = '0 2px 8px rgba(93, 14, 38, 0.2)';
                });
                
                historyItemsPerPageSelect.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#5D0E26';
                    this.style.boxShadow = 'none';
                });
            }
            
            // Start initialization
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPagination);
            } else {
                initPagination();
            }
        })();
    </script>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html> 