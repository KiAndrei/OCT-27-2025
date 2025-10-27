<?php
session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Generation - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/document-styles.css?v=<?= time() ?>">
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
            <li><a href="attorney_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php" ><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php" class="active"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Document Generation';
        $page_subtitle = 'Generate legal documents with direct PDF download';
        include 'components/profile_header.php'; 
        ?>

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
                    <button onclick="openSwornAffidavitSoloParentModal()" class="btn btn-primary generate-btn">
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
                    <button onclick="openSwornAffidavitSoloParentModal()" class="btn btn-primary generate-btn">
                        <i class="fas fa-edit"></i> Fill Up
                    </button>
                </div>
            </div>
        </div>
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
                        <label for="itemDetails">Item Details <span class="required">*</span></label>
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
                    <div class="data-preview-actions">
                        <button type="button" onclick="editAffidavitLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newAffidavitLossDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateAffidavitLossPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
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
                    <div class="data-preview-actions">
                        <button type="button" onclick="editSoloParentData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newSoloParentDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateSoloParentPDF()" class="btn btn-primary">Generate PDF</button>
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
                        <input type="text" id="seniorCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="relationship">Relationship to Senior Citizen <span class="required">*</span></label>
                        <input type="text" id="relationship" name="relationship" required placeholder="e.g., Son, Daughter, Spouse">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorCitizenName">Senior Citizen Name <span class="required">*</span></label>
                        <input type="text" id="seniorCitizenName" name="seniorCitizenName" required placeholder="Enter senior citizen's name">
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
                            <label class="data-label">Relationship</label>
                            <div class="data-value" id="previewRelationship">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Senior Citizen Name</label>
                            <div class="data-value" id="previewSeniorCitizenName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewSeniorDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-preview-actions">
                        <button type="button" onclick="editSeniorIDLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newSeniorIDLossDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateSeniorIDLossPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PWD Loss Modal -->
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
                        <input type="text" id="pwdFullAddress" name="fullAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdDetailsOfLoss">Details of Loss <span class="required">*</span></label>
                        <textarea id="pwdDetailsOfLoss" name="detailsOfLoss" required placeholder="Describe how the PWD ID was lost"></textarea>
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
                            <div class="data-value" id="previewPWDFullName">-</div>
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
                    <div class="data-preview-actions">
                        <button type="button" onclick="editPWDLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newPWDLossDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generatePWDLossPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boticab Loss Modal -->
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
                        <input type="text" id="boticabFullAddress" name="fullAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="boticabDetailsOfLoss">Details of Loss <span class="required">*</span></label>
                        <textarea id="boticabDetailsOfLoss" name="detailsOfLoss" required placeholder="Describe how the Boticab Booklet/ID was lost"></textarea>
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
                    <div class="data-preview-actions">
                        <button type="button" onclick="editBoticabLossData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newBoticabLossDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateBoticabLossPDF()" class="btn btn-primary">Generate PDF</button>
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
                        <label for="motherFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="motherFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="motherCompleteAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="motherCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="motherChildName">Child Name <span class="required">*</span></label>
                        <input type="text" id="motherChildName" name="childName" required placeholder="Enter child's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="birthDate">Birth Date <span class="required">*</span></label>
                        <input type="date" id="birthDate" name="birthDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="birthPlace">Birth Place <span class="required">*</span></label>
                        <input type="text" id="birthPlace" name="birthPlace" required placeholder="Enter place of birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="motherDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="motherDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
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
                            <div class="data-value" id="previewMotherFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewMotherCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Child Name</label>
                            <div class="data-value" id="previewMotherChildName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Birth Date</label>
                            <div class="data-value" id="previewBirthDate">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Birth Place</label>
                            <div class="data-value" id="previewBirthPlace">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewMotherDateOfNotary">-</div>
                        </div>
                    </div>
                    <div class="data-preview-actions">
                        <button type="button" onclick="editSwornAffidavitMotherData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newSwornAffidavitMotherDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateSwornAffidavitMotherPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Joint Affidavit Solo Parent Modal -->
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
                    <div class="data-preview-actions">
                        <button type="button" onclick="editJointAffidavitSoloParentData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newJointAffidavitSoloParentDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateJointAffidavitSoloParentPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Joint Affidavit Modal -->
    <div id="jointAffidavitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-users"></i> Joint Affidavit (Two Disinterested Person)</h2>
                <span class="close" onclick="closeJointAffidavitModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="jointAffidavitForm" class="modal-form">
                    <div class="form-group">
                        <label for="firstPersonName">First Person Name <span class="required">*</span></label>
                        <input type="text" id="firstPersonName" name="firstPersonName" required placeholder="Enter first person's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="firstPersonAddress">First Person Address <span class="required">*</span></label>
                        <input type="text" id="firstPersonAddress" name="firstPersonAddress" required placeholder="Enter first person's address">
                    </div>
                    
                    <div class="form-group">
                        <label for="secondPersonName">Second Person Name <span class="required">*</span></label>
                        <input type="text" id="secondPersonName" name="secondPersonName" required placeholder="Enter second person's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="secondPersonAddress">Second Person Address <span class="required">*</span></label>
                        <input type="text" id="secondPersonAddress" name="secondPersonAddress" required placeholder="Enter second person's address">
                    </div>
                    
                    <div class="form-group">
                        <label for="childName">Child Name <span class="required">*</span></label>
                        <input type="text" id="childName" name="childName" required placeholder="Enter child's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="fatherName">Father Name <span class="required">*</span></label>
                        <input type="text" id="fatherName" name="fatherName" required placeholder="Enter father's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="motherName">Mother Name <span class="required">*</span></label>
                        <input type="text" id="motherName" name="motherName" required placeholder="Enter mother's name">
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
                        <label for="childNameNumber4">Child Name (Number 4) <span class="required">*</span></label>
                        <input type="text" id="childNameNumber4" name="childNameNumber4" required placeholder="Enter child's name for number 4">
                    </div>
                    
                    <div class="form-group">
                        <label for="dateOfNotaryJoint">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="dateOfNotaryJoint" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
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
                            <label class="data-label">First Person Name</label>
                            <div class="data-value" id="previewFirstPersonName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">First Person Address</label>
                            <div class="data-value" id="previewFirstPersonAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Second Person Name</label>
                            <div class="data-value" id="previewSecondPersonName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Second Person Address</label>
                            <div class="data-value" id="previewSecondPersonAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Child Name</label>
                            <div class="data-value" id="previewChildName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Father Name</label>
                            <div class="data-value" id="previewFatherName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Mother Name</label>
                            <div class="data-value" id="previewMotherName">-</div>
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
                            <label class="data-label">Child Name (Number 4)</label>
                            <div class="data-value" id="previewChildNameNumber4">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewDateOfNotaryJoint">-</div>
                        </div>
                    </div>
                    <div class="data-preview-actions">
                        <button type="button" onclick="editJointAffidavitData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newJointAffidavitDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateJointAffidavitPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/attorney-document-generation.js?v=<?= time() ?>"></script>

    <style>
        /* Data Preview Styles - Matching Client Design */
        .data-preview {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(145deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px var(--shadow-light);
        }
        
        .data-preview-header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .data-preview-header h3 {
            margin: 0;
            color: var(--text-dark);
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .data-preview-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .data-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .data-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .data-value {
            color: #333;
            font-size: 0.95rem;
            line-height: 1.4;
            word-wrap: break-word;
            background: white;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 2px 8px var(--shadow-light);
        }
        
        .data-preview-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
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

        /* Checkbox Styles - Matching Client Design */
        .checkbox-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 16px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }

        .checkbox-container input[type="checkbox"] {
            display: none;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 2px solid rgba(93, 14, 38, 0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .checkbox-label:hover {
            border-color: var(--primary-color);
            background: rgba(93, 14, 38, 0.1);
        }

        .checkbox-container input[type="checkbox"]:checked + .checkbox-label {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            color: var(--primary-color);
        }

        .checkbox-label::before {
            content: '';
            width: 18px;
            height: 18px;
            border: 2px solid var(--primary-color);
            border-radius: 4px;
            background: white;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .checkbox-container input[type="checkbox"]:checked + .checkbox-label::before {
            background: var(--primary-color);
            content: '✓';
            color: white;
            font-size: 12px;
            font-weight: bold;
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

        /* Form Group Enhancements */
        .form-group textarea {
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 80px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            transform: translateY(-1px);
        }

        .form-group select {
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            transform: translateY(-1px);
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

        /* Responsive improvements */
        @media (max-width: 768px) {
            .data-preview-content {
                grid-template-columns: 1fr;
            }
            
            .data-preview-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .radio-group {
                padding: 12px;
            }
            
            .checkbox-container {
                padding: 12px;
            }
        }
    </style>
<script src="assets/js/unread-messages.js?v=1761535512"></script></body>
</html> 