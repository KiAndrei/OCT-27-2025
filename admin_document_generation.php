<?php
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
    <!-- Hamburger Menu Button -->
    <button class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    
    <!-- Sidebar -->
    <div class="sidebar admin-sidebar">
                <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="admin_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="admin_document_generation.php" class="active"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="admin_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="admin_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="admin_usermanagement.php"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
            <li><a href="admin_managecases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="admin_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="admin_messages.php"><i class="fas fa-comments"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
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
                        <label for="lossFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="lossFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="lossCompleteAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="lossCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="lossSpecifyItemLost">Specify Item Lost <span class="required">*</span></label>
                        <input type="text" id="lossSpecifyItemLost" name="specifyItemLost" required placeholder="e.g., Driver's License, Passport, ID Card">
                    </div>
                    
                    <div class="form-group">
                        <label for="lossItemLost">Item Lost <span class="required">*</span></label>
                        <input type="text" id="lossItemLost" name="itemLost" required placeholder="Describe the specific item that was lost">
                    </div>
                    
                    <div class="form-group">
                        <label for="lossItemDetails">Item Details <span class="required">*</span></label>
                        <input type="text" id="lossItemDetails" name="itemDetails" required placeholder="Provide detailed description of the lost item">
                    </div>
                    
                    <div class="form-group">
                        <label for="lossDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="lossDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
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
                            <div class="data-value" id="previewLossFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewLossCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Specify Item Lost</label>
                            <div class="data-value" id="previewLossSpecifyItemLost">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Item Lost</label>
                            <div class="data-value" id="previewLossItemLost">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Item Details</label>
                            <div class="data-value" id="previewLossItemDetails">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewLossDateOfNotary">-</div>
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
                        <label for="seniorRelationship">Relationship to Senior Citizen <span class="required">*</span></label>
                        <input type="text" id="seniorRelationship" name="relationship" required placeholder="e.g., Son, Daughter, Spouse">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorCitizenName">Senior Citizen Name <span class="required">*</span></label>
                        <input type="text" id="seniorCitizenName" name="seniorCitizenName" required placeholder="Enter senior citizen's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorCitizenAddress">Senior Citizen Address <span class="required">*</span></label>
                        <input type="text" id="seniorCitizenAddress" name="seniorCitizenAddress" required placeholder="Enter senior citizen's address">
                    </div>
                    
                    <div class="form-group">
                        <label for="seniorDetailsOfLoss">Details of Loss <span class="required">*</span></label>
                        <textarea id="seniorDetailsOfLoss" name="detailsOfLoss" required placeholder="Describe how the Senior ID was lost"></textarea>
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
                            <div class="data-value" id="previewSeniorRelationship">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Senior Citizen Name</label>
                            <div class="data-value" id="previewSeniorCitizenName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Senior Citizen Address</label>
                            <div class="data-value" id="previewSeniorCitizenAddress">-</div>
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
                        <label for="pwdCompleteAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="pwdCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdRelationship">Relationship to PWD <span class="required">*</span></label>
                        <input type="text" id="pwdRelationship" name="relationship" required placeholder="e.g., Son, Daughter, Spouse">
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdName">PWD Name <span class="required">*</span></label>
                        <input type="text" id="pwdName" name="pwdName" required placeholder="Enter PWD's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="pwdAddress">PWD Address <span class="required">*</span></label>
                        <input type="text" id="pwdAddress" name="pwdAddress" required placeholder="Enter PWD's address">
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
                            <div class="data-value" id="previewPwdFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewPwdCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Relationship</label>
                            <div class="data-value" id="previewPwdRelationship">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">PWD Name</label>
                            <div class="data-value" id="previewPwdName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">PWD Address</label>
                            <div class="data-value" id="previewPwdAddress">-</div>
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
                        <label for="boticabCompleteAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="boticabCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
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
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewBoticabCompleteAddress">-</div>
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
                        <label for="motherChildBirthDate">Child Birth Date <span class="required">*</span></label>
                        <input type="date" id="motherChildBirthDate" name="childBirthDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="motherBirthPlace">Birth Place <span class="required">*</span></label>
                        <input type="text" id="motherBirthPlace" name="birthPlace" required placeholder="Enter birth place">
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
                            <label class="data-label">Child Birth Date</label>
                            <div class="data-value" id="previewMotherChildBirthDate">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Birth Place</label>
                            <div class="data-value" id="previewMotherBirthPlace">-</div>
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
                        <label for="secondPersonName">Second Person Name <span class="required">*</span></label>
                        <input type="text" id="secondPersonName" name="secondPersonName" required placeholder="Enter second person's name">
                    </div>
                    
                    <div class="form-group">
                        <label for="firstPersonAddress">First Person Address <span class="required">*</span></label>
                        <input type="text" id="firstPersonAddress" name="firstPersonAddress" required placeholder="Enter first person's address">
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
                        <label for="childNameNumber4">Name of the Person for Late Registration <span class="required">*</span></label>
                        <input type="text" id="childNameNumber4" name="childNameNumber4" required placeholder="Enter person's name for late registration">
                    </div>
                    
                    <div class="form-group">
                        <label for="dateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="dateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
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
                            <label class="data-label">Second Person Name</label>
                            <div class="data-value" id="previewSecondPersonName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">First Person Address</label>
                            <div class="data-value" id="previewFirstPersonAddress">-</div>
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
                            <label class="data-label">Name of the Person for Late Registration</label>
                            <div class="data-value" id="previewChildNameNumber4">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewDateOfNotary">-</div>
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

    <!-- Sworn Affidavit of Solo Parent Modal -->
    <div id="soloParentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> Sworn Affidavit of Solo Parent</h2>
                <span class="close" onclick="closeSwornAffidavitSoloParentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="soloParentForm" class="modal-form">
                    <div class="form-group">
                        <label for="soloParentFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="soloParentFullName" name="fullName" required placeholder="Enter your complete name">
                    </div>
                    
                    <div class="form-group">
                        <label for="soloParentCompleteAddress">Complete Address <span class="required">*</span></label>
                        <input type="text" id="soloParentCompleteAddress" name="completeAddress" required placeholder="Enter your complete address">
                    </div>
                    
                    <div class="form-group">
                        <label>Children Information <span class="required">*</span></label>
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
                        <label for="soloParentYearsUnderCase">Years Under Case <span class="required">*</span></label>
                        <input type="number" id="soloParentYearsUnderCase" name="yearsUnderCase" required placeholder="Enter number of years" min="1" max="100">
                    </div>
                    
                    <div class="form-group">
                        <label>Reason Section <span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="reasonSection" value="Left the family home and abandoned us" onchange="toggleOtherReason()">
                                <span>Left the family home and abandoned us</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="reasonSection" value="Died last" onchange="toggleOtherReason()">
                                <span>Died last</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="reasonSection" value="Other reason, please state" onchange="toggleOtherReason()">
                                <span>Other reason, please state</span>
                            </label>
                        </div>
                        <div id="otherReasonContainer" style="display: none;">
                            <input type="text" id="otherReason" name="otherReason" placeholder="Please specify other reason">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Employment Status <span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="employmentStatus" value="Employee and earning" onchange="toggleEmploymentFields()">
                                <span>Employee and earning</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="employmentStatus" value="Self-employed and earning" onchange="toggleEmploymentFields()">
                                <span>Self-employed and earning</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="employmentStatus" value="Un-employed and dependent upon" onchange="toggleEmploymentFields()">
                                <span>Un-employed and dependent upon</span>
                            </label>
                        </div>
                        <div id="employeeAmountContainer" style="display: none;">
                            <input type="number" id="employeeAmount" name="employeeAmount" placeholder="Monthly salary amount" min="0">
                        </div>
                        <div id="selfEmployedAmountContainer" style="display: none;">
                            <input type="number" id="selfEmployedAmount" name="selfEmployedAmount" placeholder="Monthly income amount" min="0">
                        </div>
                        <div id="unemployedDependentContainer" style="display: none;">
                            <input type="text" id="unemployedDependent" name="unemployedDependent" placeholder="Who are you dependent upon">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="soloParentDateOfNotary">Date of Notary <span class="required">*</span></label>
                        <input type="date" id="soloParentDateOfNotary" name="dateOfNotary" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeSwornAffidavitSoloParentModal()" class="btn btn-secondary">Cancel</button>
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
                            <div class="data-value" id="previewSoloParentFullName">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Complete Address</label>
                            <div class="data-value" id="previewSoloParentCompleteAddress">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Children</label>
                            <div class="data-value" id="previewSoloParentChildren">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Years Under Case</label>
                            <div class="data-value" id="previewSoloParentYearsUnderCase">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Reason Section</label>
                            <div class="data-value" id="previewSoloParentReasonSection">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Employment Status</label>
                            <div class="data-value" id="previewSoloParentEmploymentStatus">-</div>
                        </div>
                        <div class="data-item">
                            <label class="data-label">Date of Notary</label>
                            <div class="data-value" id="previewSoloParentDateOfNotary">-</div>
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
                    <div class="data-preview-actions">
                        <button type="button" onclick="editJointAffidavitSoloParentData()" class="btn btn-secondary">Edit</button>
                        <button type="button" onclick="newJointAffidavitSoloParentDocument()" class="btn btn-secondary">New Document</button>
                        <button type="button" onclick="generateJointAffidavitSoloParentPDF()" class="btn btn-primary">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin-document-generation.js?v=<?= time() ?>"></script>
    <script src="assets/js/hamburger-menu.js?v=1761531376"></script>
<script src="assets/js/unread-messages.js?v=1761535511"></script></body>
</html> 