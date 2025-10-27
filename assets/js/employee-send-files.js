// Employee Send Files JavaScript Functions
// This file contains all JavaScript functions for employee_send_files.php

// Profile Dropdown Functions
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('show');
}

function editProfile() {
    alert('Profile editing functionality will be implemented.');
}

// File Management Functions
function viewDocumentData(fileId, documentType) {
    // Fetch document data and display in modal
    fetch('employee_send_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_document_data&file_id=' + fileId
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            showDocumentDataModal(result.data, documentType);
        } else {
            alert('Error loading document data: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading document data. Please try again.');
    });
}

function generatePDF(fileId, documentType) {
    if (confirm('Generate and download PDF from this document data?')) {
        // Show loading state
        const generateBtn = event.target;
        const originalText = generateBtn.innerHTML;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        generateBtn.disabled = true;
        
        console.log('Generating PDF for file ID:', fileId, 'Document Type:', documentType);
        console.log('Form will be submitted to:', 'employee_send_files.php');
        
        // Create form to submit PDF generation request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'employee_send_files.php';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'generate_pdf_download';
        
        const fileIdInput = document.createElement('input');
        fileIdInput.type = 'hidden';
        fileIdInput.name = 'file_id';
        fileIdInput.value = fileId;
        
        const documentTypeInput = document.createElement('input');
        documentTypeInput.type = 'hidden';
        documentTypeInput.name = 'document_type';
        documentTypeInput.value = documentType;
        
        form.appendChild(actionInput);
        form.appendChild(fileIdInput);
        form.appendChild(documentTypeInput);
        document.body.appendChild(form);
        
        console.log('Form created with data:', {
            action: 'generate_pdf_download',
            file_id: fileId,
            document_type: documentType
        });
        
        // Submit form to trigger PDF download
        form.submit();
        
        // Clean up and restore button
        setTimeout(() => {
            if (document.body.contains(form)) {
                document.body.removeChild(form);
            }
            generateBtn.innerHTML = originalText;
            generateBtn.disabled = false;
        }, 3000);
    }
}

function downloadPDF(fileId) {
    // Show loading state
    const downloadBtn = event.target;
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
    downloadBtn.disabled = true;
    
    // Create form to submit download request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'employee_send_files.php';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'download_pdf';
    
    const fileIdInput = document.createElement('input');
    fileIdInput.type = 'hidden';
    fileIdInput.name = 'file_id';
    fileIdInput.value = fileId;
    
    form.appendChild(actionInput);
    form.appendChild(fileIdInput);
    document.body.appendChild(form);
    
    // Submit form to trigger download
    form.submit();
    
    // Clean up
    setTimeout(() => {
        document.body.removeChild(form);
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
    }, 2000);
}

function updateDocumentStatus(fileId, status) {
    const action = status === 'approved' ? 'approve' : 'reject';
    const message = status === 'approved' ? 'approve' : 'reject';
    const actionText = status === 'approved' ? 'Approve' : 'Reject';
    
    if (status === 'approved') {
        // For approval, use simple confirmation
        if (confirm(`Are you sure you want to ${message} this document?`)) {
            processDocumentStatus(fileId, action, '', event.target);
        }
    } else {
        // For rejection, show custom modal
        showRejectionModal(fileId, event.target);
    }
}

function showRejectionModal(fileId, button) {
    // Create modal overlay
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'rejection-modal-overlay';
    modalOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'rejection-modal-content';
    modalContent.style.cssText = `
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        animation: slideInUp 0.3s ease-out;
        position: relative;
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
        <div style="background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-times-circle" style="font-size: 20px;"></i>
        </div>
        <div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 600;">Reject Document</h3>
            <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">Please provide a reason for rejection</p>
        </div>
    `;
    
    // Create modal body
    const modalBody = document.createElement('div');
    modalBody.style.cssText = `
        padding: 25px;
    `;
    
    // Create reason input
    const reasonLabel = document.createElement('label');
    reasonLabel.style.cssText = `
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
        font-size: 14px;
    `;
    reasonLabel.textContent = 'Rejection Reason *';
    
    const reasonTextarea = document.createElement('textarea');
    reasonTextarea.style.cssText = `
        width: 100%;
        min-height: 120px;
        padding: 12px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        resize: vertical;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    `;
    reasonTextarea.placeholder = 'Please provide a detailed reason for rejecting this document...';
    reasonTextarea.addEventListener('focus', function() {
        this.style.borderColor = '#dc3545';
    });
    reasonTextarea.addEventListener('blur', function() {
        this.style.borderColor = '#e1e5e9';
    });
    
    modalBody.appendChild(reasonLabel);
    modalBody.appendChild(reasonTextarea);
    
    // Create modal footer
    const modalFooter = document.createElement('div');
    modalFooter.style.cssText = `
        padding: 20px 25px;
        border-top: 1px solid #e1e5e9;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    `;
    
    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.style.cssText = `
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s ease;
    `;
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('mouseenter', function() {
        this.style.background = '#5a6268';
    });
    cancelBtn.addEventListener('mouseleave', function() {
        this.style.background = '#6c757d';
    });
    cancelBtn.addEventListener('click', function() {
        modalOverlay.remove();
    });
    
    const rejectBtn = document.createElement('button');
    rejectBtn.type = 'button';
    rejectBtn.style.cssText = `
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    `;
    rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject Document';
    rejectBtn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-1px)';
        this.style.boxShadow = '0 4px 12px rgba(220, 53, 69, 0.4)';
    });
    rejectBtn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
    rejectBtn.addEventListener('click', function() {
        const reason = reasonTextarea.value.trim();
        if (!reason) {
            reasonTextarea.style.borderColor = '#dc3545';
            reasonTextarea.focus();
            showNotification('Please provide a reason for rejection', 'error');
            return;
        }
        
        // Show custom confirmation modal
        showConfirmationModal('Are you sure you want to reject this document?', 'This action cannot be undone and will notify the client.', function(confirmed) {
            if (confirmed) {
                // Show loading state
                rejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting...';
                rejectBtn.disabled = true;
                
                processDocumentStatus(fileId, 'reject', reason, button);
                modalOverlay.remove();
            }
        });
    });
    
    modalFooter.appendChild(cancelBtn);
    modalFooter.appendChild(rejectBtn);
    
    // Assemble modal
    modalContent.appendChild(modalHeader);
    modalContent.appendChild(modalBody);
    modalContent.appendChild(modalFooter);
    modalOverlay.appendChild(modalContent);
    
    // Add to document
    document.body.appendChild(modalOverlay);
    
    // Focus on textarea
    setTimeout(() => {
        reasonTextarea.focus();
    }, 100);
    
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

function showConfirmationModal(title, message, callback) {
    // Create modal overlay
    const confirmOverlay = document.createElement('div');
    confirmOverlay.className = 'confirmation-modal-overlay';
    confirmOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    // Create modal content
    const confirmContent = document.createElement('div');
    confirmContent.className = 'confirmation-modal-content';
    confirmContent.style.cssText = `
        background: white;
        border-radius: 16px;
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
        max-width: 450px;
        width: 90%;
        animation: slideInUp 0.3s ease-out;
        position: relative;
        overflow: hidden;
    `;
    
    // Create modal header
    const confirmHeader = document.createElement('div');
    confirmHeader.style.cssText = `
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        padding: 25px;
        text-align: center;
        position: relative;
    `;
    confirmHeader.innerHTML = `
        <div style="background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 28px;"></i>
        </div>
        <h3 style="margin: 0; font-size: 20px; font-weight: 600;">${title}</h3>
    `;
    
    // Create modal body
    const confirmBody = document.createElement('div');
    confirmBody.style.cssText = `
        padding: 25px;
        text-align: center;
    `;
    confirmBody.innerHTML = `
        <p style="margin: 0; color: #666; font-size: 16px; line-height: 1.5;">${message}</p>
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
            <i class="fas fa-info-circle" style="color: #856404; margin-right: 8px;"></i>
            <span style="color: #856404; font-size: 14px; font-weight: 500;">The client will be notified of this rejection</span>
        </div>
    `;
    
    // Create modal footer
    const confirmFooter = document.createElement('div');
    confirmFooter.style.cssText = `
        padding: 20px 25px;
        border-top: 1px solid #e1e5e9;
        display: flex;
        gap: 15px;
        justify-content: center;
    `;
    
    const cancelConfirmBtn = document.createElement('button');
    cancelConfirmBtn.type = 'button';
    cancelConfirmBtn.style.cssText = `
        background: #6c757d;
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        min-width: 100px;
    `;
    cancelConfirmBtn.textContent = 'Cancel';
    cancelConfirmBtn.addEventListener('mouseenter', function() {
        this.style.background = '#5a6268';
        this.style.transform = 'translateY(-1px)';
    });
    cancelConfirmBtn.addEventListener('mouseleave', function() {
        this.style.background = '#6c757d';
        this.style.transform = 'translateY(0)';
    });
    cancelConfirmBtn.addEventListener('click', function() {
        confirmOverlay.remove();
        callback(false);
    });
    
    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.style.cssText = `
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        min-width: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    `;
    confirmBtn.innerHTML = '<i class="fas fa-times"></i> Yes, Reject';
    confirmBtn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-1px)';
        this.style.boxShadow = '0 6px 20px rgba(220, 53, 69, 0.4)';
    });
    confirmBtn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
    confirmBtn.addEventListener('click', function() {
        confirmOverlay.remove();
        callback(true);
    });
    
    confirmFooter.appendChild(cancelConfirmBtn);
    confirmFooter.appendChild(confirmBtn);
    
    // Assemble modal
    confirmContent.appendChild(confirmHeader);
    confirmContent.appendChild(confirmBody);
    confirmContent.appendChild(confirmFooter);
    confirmOverlay.appendChild(confirmContent);
    
    // Add to document
    document.body.appendChild(confirmOverlay);
    
    // Close on overlay click
    confirmOverlay.addEventListener('click', function(e) {
        if (e.target === confirmOverlay) {
            confirmOverlay.remove();
            callback(false);
        }
    });
    
    // Close on escape key
    const handleEscape = function(e) {
        if (e.key === 'Escape') {
            confirmOverlay.remove();
            document.removeEventListener('keydown', handleEscape);
            callback(false);
        }
    };
    document.addEventListener('keydown', handleEscape);
}

function processDocumentStatus(fileId, action, reason, button) {
    // Show loading state on the button
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    button.disabled = true;
    
    let body = 'action=' + action + '_document&file_id=' + fileId;
    
    if (action === 'reject' && reason) {
        body += '&reason=' + encodeURIComponent(reason);
    }
    
    fetch('employee_send_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            // Show success message
            const message = action === 'approve' ? 'approved' : 'rejected';
            showNotification(`Document ${message} successfully!`, 'success');
            
            // Refresh the page after a short delay to show updated status
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            const message = action === 'approve' ? 'approving' : 'rejecting';
            alert(`Error ${message} document: ` + result.message);
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const message = action === 'approve' ? 'approving' : 'rejecting';
        alert(`Error ${message} document. Please try again.`);
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function deleteDocument(fileId) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone and will permanently remove the document from the system.')) {
        // Show loading state on the button
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        button.disabled = true;
        
        fetch('employee_send_files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete_document&file_id=' + fileId
        })
        .then(response => {
            console.log('Delete response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            console.log('Delete result:', result);
            if (result.status === 'success') {
                // Show success message
                showNotification('Document deleted successfully!', 'success');
                
                // Remove the document card from the UI
                const fileCard = button.closest('.file-card');
                if (fileCard) {
                    fileCard.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => {
                        fileCard.remove();
                    }, 300);
                }
                
                // Refresh the page after a short delay to update statistics
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                console.error('Delete error:', result);
                alert('Error deleting document: ' + result.message);
                if (result.debug_info) {
                    console.log('Debug info:', result.debug_info);
                }
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Delete fetch error:', error);
            alert('Error deleting document. Please try again. Check console for details.');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function showDocumentDataModal(data, documentType) {
    // Create modal to display document in proper format
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    // Generate document HTML based on type
    let documentHTML = generateDocumentPreviewHTML(documentType, data);
    
    modal.innerHTML = `
        <div class="modal-content" style= "min-width: 60%;"
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
            max-width: 98vw;
            max-height: 95vh;
            width: 2000px;
            overflow-y: auto;
            animation: slideInUp 0.3s ease-out;
            position: relative;
        ">
            <div class="modal-header" style="
                background: linear-gradient(135deg, #7C0F2F, #8B1538);
                color: white;
                padding: 20px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 0 0 0 0;
                position: sticky;
                top: 0;
                z-index: 10;
            ">
                <h2 style="margin: 0; font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-file-alt"></i> Document Preview - ${getDocumentTypeName(documentType)}
                </h2>
                <span class="close" onclick="this.closest('.modal').remove()" style="
                    background: rgba(255, 255, 255, 0.2);
                    border: none;
                    color: white;
                    font-size: 28px;
                    cursor: pointer;
                    padding: 8px 12px;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                    line-height: 1;
                ">&times;</span>
            </div>
            <div class="modal-body" style="
                padding: 0;
                background: #f8f9fa;
            ">
                <div style="
                    background: white;
                    padding: 50px;
                    font-family: 'Times New Roman', serif;
                    font-size: 13pt;
                    line-height: 1.7;
                    min-height: 1000px;
                    margin: 20px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
                ">
                    ${documentHTML}
                </div>
            </div>
        </div>
    `;
    
    // Add custom scrollbar styles
    const style = document.createElement('style');
    style.textContent = `
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }
        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .modal-content::-webkit-scrollbar-thumb {
            background: #7C0F2F;
            border-radius: 4px;
        }
        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #5D0E26;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
            style.remove();
        }
    });
    
    // Close on escape key
    const handleEscape = function(e) {
        if (e.key === 'Escape') {
            modal.remove();
            style.remove();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);
}

function getDocumentTypeName(documentType) {
    const typeNames = {
        'affidavitLoss': 'Affidavit of Loss',
        'soloParent': 'Sworn Affidavit of Solo Parent',
        'pwdLoss': 'Affidavit of Loss (PWD ID)',
        'boticabLoss': 'Affidavit of Loss (Boticab Booklet/ID)'
    };
    return typeNames[documentType] || documentType;
}

function generateDocumentPreviewHTML(documentType, data) {
    switch (documentType) {
        case 'affidavitLoss':
            return generateAffidavitLossPreview(data);
        case 'soloParent':
            return generateSoloParentPreview(data);
        case 'pwdLoss':
            return generatePWDLossPreview(data);
        case 'boticabLoss':
            return generateBoticabLossPreview(data);
        case 'seniorIDLoss':
            return generateSeniorIDLossPreview(data);
        case 'jointAffidavit':
            return generateJointAffidavitPreview(data);
        case 'jointAffidavitSoloParent':
            return generateJointAffidavitSoloParentPreview(data);
        case 'swornAffidavitMother':
            return generateSwornAffidavitMotherPreview(data);
        default:
            return '<p>Document type not supported</p>';
    }
}

function generateAffidavitLossPreview(data) {
    const fullName = data.fullName || '';
    const completeAddress = data.completeAddress || '';
    const specifyItemLost = data.specifyItemLost || '';
    const itemLost = data.itemLost || '';
    const itemDetails = data.itemDetails || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
                AFFIDAVIT OF LOSS
            </div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>${completeAddress}</b></u>, after <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
            </div>
            <br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That &nbsp;&nbsp;&nbsp; I &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; am &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; true &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and lawful owner/possessor of <br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${specifyItemLost}</b></u>;<br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That unfortunately the said <u><b>${itemLost}</b></u> was lost under the following<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; circumstance:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${itemDetails}</b></u><br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;&nbsp;Despite diligent effort to search for the missing item, the same can no longer <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;be found;
            </div>
            <br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;&nbsp;I am executing this affidavit to attest the truth of the foregoing facts and<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;for whatever intents it may serve in accordance with law.
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, &nbsp; I &nbsp; have &nbsp; hereunto &nbsp; set my hand this ________ day of<br/>
                <u><b>${dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br/>
            <div style="text-align:justify; margin-bottom:15px;">
SUBSCRIBED AND SWORN TO before me this date above mentioned at the City of <br>
Cabuyao, Laguna, affiant exhibiting to me his/her respective proofs of identity, <br>
indicated below their names personally attesting that the foregoing statements is true <br>
to their best of knowledge and belief.
            </div>
            
            <br/>
            <div style="text-align:left; margin-left: -5px;">
Doc. No._______<br/>
                Page No._______<br/>
                Book No._______<br/>
                Series of _______
            </div>
        </div>
    `;
}

function generateSoloParentPreview(data) {
    const fullName = data.fullName || '';
    const completeAddress = data.completeAddress || '';
    const childrenNames = data.childrenNames || [];
    const childrenAges = data.childrenAges || [];
    const yearsUnderCase = data.yearsUnderCase || '';
    const reasonSection = data.reasonSection || '';
    const otherReason = data.otherReason || '';
    const employmentStatus = data.employmentStatus || '';
    const employeeAmount = data.employeeAmount || '';
    const selfEmployedAmount = data.selfEmployedAmount || '';
    const unemployedDependent = data.unemployedDependent || '';
    const dateOfNotary = data.dateOfNotary || '';

    // Normalize children arrays
    const normalizedChildrenNames = Array.isArray(childrenNames) ? childrenNames : (childrenNames ? [childrenNames] : []);
    const normalizedChildrenAges = Array.isArray(childrenAges) ? childrenAges : (childrenAges ? [childrenAges] : []);

    // Build children rows
    let childrenRows = '';
    if (normalizedChildrenNames.length > 0) {
        const count = Math.max(normalizedChildrenNames.length, normalizedChildrenAges.length);
        for (let i = 0; i < count; i++) {
            const name = (normalizedChildrenNames[i] || '').trim();
            const age = (normalizedChildrenAges[i] || '').trim();
            if (name === '' && age === '') continue;
            childrenRows += `<tr>
                <td style="width:80%; padding:8px 5px; border:1px solid #000;">${name !== '' ? name : '&nbsp;'}</td>
                <td style="width:20%; padding:8px 5px; border:1px solid #000; text-align:center;">${age !== '' ? age : '&nbsp;'}</td>
            </tr>`;
        }
    }
    if (childrenRows === '') {
        for (let i = 0; i < 5; i++) {
            childrenRows += '<tr><td style="width:80%; padding:8px 5px; border:1px solid #000;">&nbsp;</td><td style="width:20%; padding:8px 5px; border:1px solid #000;">&nbsp;</td></tr>';
        }
    }

    // Reasons checkboxes (mark the selected one)
    const isLeft = reasonSection === 'Left the family home and abandoned us';
    const isDied = reasonSection === 'Died last';
    const isOther = reasonSection === 'Other reason, please state';

    const reasonOtherText = isOther ? otherReason : '';

    // Employment details
    const isEmp = employmentStatus === 'Employee and earning';
    const isSelf = employmentStatus === 'Self-employed and earning';
    const isUnemp = employmentStatus === 'Un-employed and dependent upon';

    const empAmt = isEmp ? (employeeAmount || '__________') : '__________';
    const selfAmt = isSelf ? (selfEmployedAmount || '__________') : '__________';
    const unempDep = isUnemp ? (unemployedDependent || '__________') : '__________';

    // Helper to draw checkbox with optional X (always show boxed outline)
    const box = (checked) => {
        return `<span style="display:inline-block; width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px; vertical-align:middle;">${checked ? 'X' : '&nbsp;'}</span>`;
    };

    // Build HTML mirroring the detailed Solo Parent TCPDF layout
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
            </div>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">SWORN AFFIDAVIT OF SOLO PARENT</div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;That &nbsp;&nbsp; I, &nbsp; <span style="display:inline-block; border-bottom:1px solid #000; min-width: 300px;">&nbsp;<u><b>${fullName}</b></u>&nbsp;</span>, &nbsp; Filipino &nbsp; Citizen, &nbsp; of &nbsp; legal &nbsp; age, single/ married /<br/>
                widow, with residence and postal address at<span style="display:inline-block; border-bottom:1px solid #000; min-width: 300px;">&nbsp;<u><b>${completeAddress}</b></u>&nbsp;</span><br/>
                City ppf Cabuyao, Laguna after having been duly sworn in accordance with law hereby depose<br/>
                and state that;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am a single parent and the Mother/Father of the following child/children namely:
            </div>
            
            <div style="margin-left: 60px; margin-right: 60px;">
                <table style="width:100%; border-collapse:collapse; margin-bottom:2px;">
                    <tr>
                        <td style="width:80%; text-align:center; border:none;"><b>Name</b></td>
                        <td style="width:20%; text-align:center; border:none;"><b>Age</b></td>
                    </tr>
                </table>
                <table style="width:100%; border-collapse: collapse;">
                    ${childrenRows}
                </table>
                <br/>
                
                <div style="margin-left: -20px;">
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That I am solely taking care and providing for my said child's / children's needs and <br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;everything indispensable for his/her/their wellbeing for <span style="display:inline-block; border-bottom:1px solid #000; min-width: 140px;">&nbsp;<u><b>${yearsUnderCase}</b></u>&nbsp;</span> year/s now<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;since his/her / their biological Mother/Father
                </div>
            </div>
            
            <div style="margin-left: 60px;">
                <table style="border-collapse:collapse;">
                    <tr><td style="width:16px; vertical-align:top; padding-top:2px;">${box(isLeft)}</td><td>left the family home and abandoned us;</td></tr>
                    <tr><td style="width:16px; vertical-align:top; padding-top:2px;">${box(isDied)}</td><td>died last <span style="display:inline-block; border-bottom:1px solid #000; width: 180px;">&nbsp;</span>;</td></tr>
                    <tr><td style="width:16px; vertical-align:top; padding-top:2px;">${box(isOther)}</td><td>(other reason please state) <span style="display:inline-block; border-bottom:1px solid #000; width: 220px;">&nbsp;${isOther && reasonOtherText !== '' ? `<u><b>${reasonOtherText}</b></u>` : ''}&nbsp;</span>;</td></tr>
                </table>
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. I am attesting to the fact that I am not cohabiting with anybody to date;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am currently:<br>
            </div>
            <br>
            
            <div style="margin-left: 60px;">
                <table style="border-collapse:collapse;">
                    <tr><td style="width:16px; vertical-align:top; padding-top:2px;">${box(isEmp)}</td><td>Employed and earning Php <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">&nbsp;${isEmp ? `<u><b>${empAmt}</b></u>` : '__________'}&nbsp;</span> per month;</td></tr>
                    <tr><td style="width:16px; vertical-align:top; padding-top:2px;">${box(isSelf)}</td><td><div>Self-employed and earning Php <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">&nbsp;${isSelf ? `<u><b>${selfAmt}</b></u>` : '__________'}&nbsp;</span> per month, from</div><div>my job as <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">&nbsp;</span>;</div></td></tr>
                    <tr><td style="width:16px; vertical-align:top; padding-top:2px;">${box(isUnemp)}</td><td>Un-employed and dependent upon <span style="display:inline-block; border-bottom:1px solid #000; width: 200px;">&nbsp;${isUnemp ? `<u><b>${unempDep}</b></u>` : '__________'}&nbsp;</span>;</td></tr>
                </table>
            </div>
            
            <div style="text-align:justify; margin-bottom:15px; margin-top:14px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto affixed my signature this<br/>
                <span style="display:inline-block; border-bottom:1px solid #000; min-width: 220px;">&nbsp;<u><b>${dateOfNotary}</b></u>&nbsp;</span> at the City of Cabuyao, Laguna.
            </div>
            
            <div style="text-align:center; margin:15px 0;">____________________________<br/><b>AFFIANT</b></div>
            
            <div style="text-align:justify; margin-bottom:15px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBSCRIBED AND SWORN to before me this _____________________ at the City of Cabuyao, Laguna, affiant personally appeared and exhibiting to me his/her _____________________ with ID No. _____________________ as competent proof of identity.</div>
            
            <div style="text-align:left; margin-left: -5px;">Doc. No. _______<br/>Page No. _______<br/>Book No. _______<br/>Series of 2025</div>
        </div>
    `;
}

function generatePWDLossPreview(data) {
    const fullName = data.fullName || '';
    const fullAddress = data.fullAddress || '';
    const detailsOfLoss = data.detailsOfLoss || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
                AFFIDAVIT OF LOSS<br>
                (PWD)
            </div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; currently residing at <u><b>${fullAddress}</b></u>, after <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; having been duly sworn to in accordance with law do hereby depose and state:
            </div>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am the owner/possessor of a Person with Disability (PWD) <br> 
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Identification card;<br>
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That unfortunately, the said PWD id was lost under the following <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;circumstances:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${detailsOfLoss}</b></u><br>
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;That despite diligent effort to retrive the said PWD id, the same can no longer <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;be restored and therefore considered lost;
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;That I am executing this statement to attest to all above facts and for whatever <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal purpose it may serve in accordance with law;
            </div>
            <br/>
            
AFFIANT FURTHER SAYETH NAUGHT. <br/>
            
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this <u><b>${dateOfNotary}</b></u>, in <br/>
                the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br/>
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; SUBSCRIBED AND SWORN, To before me this______________ at the city of <br>
                Cabuyao, Laguna, affiant exhibiting to me his/her____________________________ as <br>
                respective proofs of identity, <br>
            </div>
            
            <br/>
            <div style="text-align:left;">
Doc. No._______<br/>
                Page No._______<br/>
                Book No._______<br/>
                Series of _______
            </div>
        </div>
    `;
}

function generateBoticabLossPreview(data) {
    const fullName = data.fullName || '';
    const fullAddress = data.fullAddress || '';
    const detailsOfLoss = data.detailsOfLoss || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
                AFFIDAVIT OF LOSS<br>
                (BOTICAB BOOKLET/ID)
            </div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>${fullAddress}</b></u>, after <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
            </div>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am the lawful owner of a Boticab Booklet/ID;
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That the said Boticab Booklet/ID was lost under the following<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; circumstances:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${detailsOfLoss}</b></u><br>
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;That despite diligent efforts to retrieve the said Boticab Booklet/ID, the same can no<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; longer be restored and therefore considered lost;
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;That I am executing this statement to attest to all above facts and for whatever<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal purpose it may serve in accordance with law;
            </div>
            <br/>
            
AFFIANT FURTHER SAYETH NAUGHT.
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this <u><b>${dateOfNotary}</b></u>, in<br/>
                the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br/>
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; SUBSCRIBED AND SWORN, To before me this______________ at the city of <br>
                Cabuyao, Laguna, affiant exhibiting to me his/her____________________________ as <br>
                respective proofs of identity, <br>
            </div>
            
            <br/>
            <div style="text-align:left; margin-left: -5px;">
Doc. No._______<br/>
                Page No._______<br/>
                Book No._______<br/>
                Series of _______
            </div>
        </div>
    `;
}

function generateSeniorIDLossPreview(data) {
    const fullName = data.fullName || '';
    const completeAddress = data.completeAddress || '';
    const relationship = data.relationship || '';
    const seniorCitizenName = data.seniorCitizenName || '';
    const detailsOfLoss = data.detailsOfLoss || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;S.S<br>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
            </div>
            
            <br>
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
                AFFIDAVIT OF LOSS<br/>
                (SENIOR ID)
            </div>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with<br/>
                residence and currently residing at <u><b>${completeAddress}</b></u>, after having been sworn<br/>
                in accordance with law hereby depose and state:
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the <u><b>${relationship}</b></u> of <u><b>${seniorCitizenName}</b></u>, who is<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;the lawful owner of a Senior Citizen ID issued by OSCA-Cabuyao;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That unfortunately, the said Senior ID was lost under the following<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;circumstances:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${detailsOfLoss}</b></u><br>
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That despite diligent efforts to retrieve the said Senior ID, the same can no<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;longer be restored and therefore considered lost;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;for whatever legal intents and purposes whatever legal intents and<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;purposes.
            </div>
            
            <div style="margin-left: 40px; margin-top:15px;">
AFFIANT FURTHER SAYETH NAUGHT.
            </div>
            
            <div style="margin-left: 40px; margin-top:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this<br/>
                <u><b>${dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
                <br>
            
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBSCRIBED AND SWORN to before me, this<br/> 
_____________________________________, in the City of Cabuyao, Laguna, affiant exhibiting<br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;to me his/her ___________________________ as valid proof of identification.
            </div>
            <br>

            <div style="text-align:left; margin-left: -5px;">
Doc. No. _______<br/>
                Page No. _______<br/>
                Book No. _______<br/>
                Series of _______
            </div>
        </div>
    `;
}

function generateJointAffidavitPreview(data) {
    const firstPersonName = data.firstPersonName || '';
    const secondPersonName = data.secondPersonName || '';
    const firstPersonAddress = data.firstPersonAddress || '';
    const secondPersonAddress = data.secondPersonAddress || '';
    const childName = data.childName || '';
    const dateOfBirth = data.dateOfBirth || '';
    const placeOfBirth = data.placeOfBirth || '';
    const fatherName = data.fatherName || '';
    const motherName = data.motherName || '';
    const childNameNumber4 = data.childNameNumber4 || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="text-align:left; font-size:11pt;">
REPUBLIC OF THE PHILIPPINES)<br/>
            PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) SS<br/>
            CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/><br/>
        </div>

        <div style="text-align:center; font-size:13pt; font-weight:bold;">
            <b>JOINT AFFIDAVIT<br/>(Two Disinterested Person)</b>
        </div>
        <br/>

        <div style="text-align:left; font-size:11pt;">
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;WE, <u><b>${firstPersonName}</b></u> and <u><b>${secondPersonName}</b></u><br/>
            Filipinos, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; both &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; age &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; , &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; permanent &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; residents &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of <br/>
            <u><b>${firstPersonAddress}</b></u> and <u><b>${secondPersonAddress}</b></u> both in the<br/>
            City of Cabuyao, Laguna after being duly sworn in accordance with law hereby depose<br/>
            and say that;<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. We &nbsp;&nbsp; are &nbsp; not &nbsp; related &nbsp;&nbsp; by &nbsp;&nbsp; affinity &nbsp;&nbsp; or &nbsp;&nbsp;&nbsp; consanguinity &nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp; the &nbsp;&nbsp; child &nbsp; :<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>${childName}</b></u>, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; who was born on<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>${dateOfBirth}</b></u> in &nbsp;&nbsp;&nbsp;&nbsp;<u><b>${placeOfBirth}</b></u>;<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Laguna, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Philippines, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; his/her &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; parents:<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>${fatherName}</b></u> and &nbsp;<u><b>${motherName}</b></u><br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. We &nbsp; are &nbsp; well &nbsp; acquainted &nbsp;&nbsp; with &nbsp;&nbsp;their family, being neighbors and friends that <br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; we know that circumstances surroundding his/her birth ;<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. However, &nbsp;&nbsp; such &nbsp;&nbsp; facts &nbsp;&nbsp; of &nbsp;&nbsp; birth &nbsp;&nbsp;were &nbsp;&nbsp; not &nbsp;&nbsp; registered &nbsp;&nbsp;as &nbsp; evidenced by a<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; certification issued by the philippine Statistics Authority;<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. We &nbsp;&nbsp;&nbsp; execute &nbsp;&nbsp; this &nbsp;&nbsp; affidavit &nbsp;&nbsp; to attest to the truth of the foregoing facts based<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;on &nbsp;&nbsp; our &nbsp;&nbsp; personal &nbsp;&nbsp; knowledge &nbsp; and experience, and let this instrument be use as<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; requirement &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; for &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Late &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Registration &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;said<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>${childNameNumber4}</b></u> .<br/><br/>
            
AFFIANTS FURTHER SAYETH NAUGHT.<br><br/>

            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao City, Laguna, <u><b>${dateOfNotary}</b></u> .<br/><br/>
            
            <table style="width:100%;">
                <tr>
                    <td style="width:50%; text-align:center;"><u><b>${firstPersonName}</b></u><br/>Affiant<br/>ID Presented: _________________</td>
                    <td style="width:50%; text-align:center;"><u><b>${secondPersonName}</b></u><br/>Affiant<br/>ID Presented: _________________</td>
                </tr>
            </table><br/>
            <br/>
            <br/>
            
WITNESS my hand and seal the date and place above-written.<br/><br/>
            
            
Doc. No. _____<br/>
                Page No. _____<br/>
                Book No. _____<br/>
                Series of _____<br/>
        </div>
    `;
}

function generateSwornAffidavitMotherPreview(data) {
    const fullName = data.fullName || '';
    const completeAddress = data.completeAddress || '';
    const childName = data.childName || '';
    const birthDate = data.birthDate || '';
    const birthPlace = data.birthPlace || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES&nbsp;&nbsp;&nbsp;)<br/>&nbsp;
                PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;S.S
            </div>
            <br>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
                SWORN STATEMENT OF MOTHER
            </div>
            <br>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, married/single, and with residence<br/>
                and postal address at <u><b>${completeAddress}</b></u>, after<br>
                being duly sworn in accordance with law, hereby depose and say that;
            </div>
            <br>
            <br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the biological mother of <u><b>${childName}</b></u>, who was<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;born on <u><b>${birthDate}</b></u> in <u><b>${birthPlace}</b></u>;<br> 
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That the birth of the above-stated child was not registered with the Local Civil<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Registry of Cabuyao City, due to negligence on our part;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That I am now taking the appropriate action to register the birth of my said child.
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and be<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;use for whatever legal purpose it may serve.
            </div>
            <br>
            
            <div style="margin-left: 60px;">
                IN WITNESS WHEREOF, I have hereunto set my hands this <u><b>${dateOfNotary}</b></u>, in the<br>
                City of Cabuyao, Laguna.
                <br>
                <br>
            </div>
            
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br>
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBCRIBED AND SWORN to before me this <u><b>${dateOfNotary}</b></u> in the City of <br>
                Cabuyao, Province of Laguna, affiant personally appeared, exhibiting to me her 
                Valid ID/No. _______________________________ as respective proof of identification.
            </div>
            <br>
            
            <div style="text-align:left; margin-left: -5px;">
                Doc. No. _______<br/>
                Book No. _______<br/>
                Page No. _______<br/>
                Series of _______
            </div>
        </div>
    `;
}

function generateJointAffidavitSoloParentPreview(data) {
    const affiant1Name = data.affiant1Name || '';
    const affiant2Name = data.affiant2Name || '';
    const affiantsAddress = data.affiantsAddress || '';
    const soloParentName = data.soloParentName || '';
    const soloParentAddress = data.soloParentAddress || '';
    const childrenNames = data.childrenNames || [];
    const childrenAges = data.childrenAges || [];
    const affiant1ValidId = data.affiant1ValidId || '';
    const affiant2ValidId = data.affiant2ValidId || '';
    const dateOfNotary = data.dateOfNotary || '';
    
    // Build children table rows
    let childrenRows = '';
    if (childrenNames.length > 0) {
        const count = Math.max(childrenNames.length, childrenAges.length);
        for (let i = 0; i < count; i++) {
            const name = (childrenNames[i] || '').trim();
            const age = (childrenAges[i] || '').trim();
            if (name === '' && age === '') continue;
            childrenRows += `<tr><td style="border:1px solid black; padding:5px; height:20px;">${name !== '' ? name : '&nbsp;'}</td><td style="border:1px solid black; padding:5px; height:20px;">${age !== '' ? age : '&nbsp;'}</td></tr>`;
        }
    }
    if (childrenRows === '') {
        for (let i = 0; i < 4; i++) {
            childrenRows += '<tr><td style="border:1px solid black; padding:5px; height:20px;">&nbsp;</td><td style="border:1px solid black; padding:5px; height:20px;">&nbsp;</td></tr>';
        }
    }
    
    // Use the exact same HTML format as the PHP backend
    return `
        <div style="text-align:left; font-size:11pt;">
REPUBLIC OF THE PHILIPPINES)<br/>
            PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) SS<br/>
            CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/><br/>
        </div>

        <div style="text-align:center; font-size:11pt; font-weight:bold;">
            JOINT AFFIDAVIT OF TWO DISINTERESTED PERSON<br/>(SOLO PARENT)
        </div>
        <br/>

        <div style="text-align:left; font-size:11pt;">
WE, <u><b>${affiant1Name}</b></u> and <u><b>${affiant2Name}</b></u>, Filipinos, both
            of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;legal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;age, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;and &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;permanent &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;residents &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;of<br/>
            <u><b>${affiantsAddress}</b></u> after being duly sworn in 
            accordance with law hereby depose and say:<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That &nbsp;&nbsp;&nbsp;&nbsp;we &nbsp;&nbsp;&nbsp;&nbsp;are &nbsp;&nbsp;&nbsp;&nbsp;not &nbsp;&nbsp;&nbsp;&nbsp;in &nbsp;&nbsp;&nbsp;&nbsp;any &nbsp;&nbsp;&nbsp;&nbsp;way &nbsp;&nbsp;related &nbsp;&nbsp;by &nbsp;&nbsp;affinity &nbsp;&nbsp;or &nbsp;&nbsp;consanguinity &nbsp;&nbsp;to<br> 
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>${soloParentName}</b></u>, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;resident &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;of <br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>${soloParentAddress}</b></u> City of Cabuyao, Laguna;<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That &nbsp;we &nbsp;know &nbsp;her / him &nbsp;as &nbsp;a &nbsp;single &nbsp;parent and the &nbsp;Mother / Father &nbsp;of this children:<br><br>
            
            <table style="width:100%; border:1px solid black;">
                <tr>
                    <td style="width:80%; text-align:center; border:1px solid black; padding:5px; background-color:#f0f0f0;">Name</td>
                    <td style="width:20%; text-align:center; border:1px solid black; padding:5px; background-color:#f0f0f0;">Age</td>
                </tr>
                ${childrenRows}
            </table><br><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That &nbsp;&nbsp;she/he &nbsp;&nbsp;is &nbsp;&nbsp;solely &nbsp;&nbsp;taking &nbsp;&nbsp;care &nbsp;&nbsp;and &nbsp;&nbsp;providing &nbsp;&nbsp;for her/his children's needs and<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;everything &nbsp;&nbsp;indispensable &nbsp;&nbsp;for &nbsp;&nbsp;her / his &nbsp;&nbsp;well-being &nbsp;&nbsp;since &nbsp;&nbsp;the &nbsp;&nbsp;&nbsp;&nbsp;biological &nbsp;&nbsp;&nbsp;&nbsp;Father<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/Mother abandoned her / his children;<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. That &nbsp;&nbsp; we &nbsp;&nbsp;know &nbsp;&nbsp;for &nbsp;&nbsp;a &nbsp;&nbsp;fact &nbsp;&nbsp;that &nbsp;&nbsp;she/he &nbsp;&nbsp;is &nbsp;&nbsp;not &nbsp;&nbsp;cohabitating with any other man / <br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;woman since she / he become a solo parent until present;<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5. That&nbsp;&nbsp; we &nbsp;&nbsp;execute &nbsp;&nbsp;this &nbsp;&nbsp;affidavit &nbsp;&nbsp;to &nbsp;&nbsp;attest &nbsp;&nbsp;to &nbsp;&nbsp;the truth of the foregoing and let this<br> 
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;instrument be useful for whatever legal intents it may serve.<br/><br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, we have hereunto set our hands this <u><b>${dateOfNotary}</b></u> in the City of Cabuyao, Laguna.<br/><br/>
            
            <div style="text-align:center; margin:15px 0;">
                <u><b>${affiant2Name}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            <br>
            
        <table style="width:100%;">
                <tr>
        <td style="width:50%; text-align:left;">Valid ID No. <u><b>${affiant1ValidId}</b></u></td>
        <td style="width:25%; text-align:right;">Valid ID No. <u><b>${affiant2ValidId}</b></u></td>
                </tr>
            </table><br/>
            <br>
SUBSCRIBED AND SWORN TO before me this date above mentioned at the City of Cabuyao, Laguna, affiants exhibiting to me their respective proofs of identity personally attesting that the foregoing statements are true to the best of their knowledge and beliefs.<br/><br/>
            
            Doc. No. _____<br/>
            Book No. _____<br/>
            Page No. _____<br/>
            Series of _____<br/>
        </div>
    `;
}

// Search and Filter Functions
function filterFiles() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const limitFilter = document.getElementById('limitFilter').value;
    const tableRows = document.querySelectorAll('#filesTable tbody tr');

    let visibleCount = 0;
    let totalCount = 0;

    tableRows.forEach(row => {
        const clientName = row.querySelector('.client-name').textContent.toLowerCase();
        const clientEmail = row.querySelector('.client-email').textContent.toLowerCase();
        const requestId = row.querySelector('.request-id').textContent.toLowerCase();
        const documentType = row.querySelector('.document-type').textContent.toLowerCase();
        const fileStatus = row.getAttribute('data-status').toLowerCase();

        const matchesSearch = clientName.includes(searchTerm) || 
                            clientEmail.includes(searchTerm) || 
                            requestId.includes(searchTerm) ||
                            documentType.includes(searchTerm);
        const matchesType = !typeFilter || documentType.includes(typeFilter);
        const matchesStatus = !statusFilter || fileStatus.includes(statusFilter);

        totalCount++;

        if (matchesSearch && matchesType && matchesStatus) {
            // Check if we should show this row based on limit
            if (limitFilter === 'all' || visibleCount < parseInt(limitFilter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        } else {
            row.style.display = 'none';
        }
    });

    // Update document counter
    updateDocumentCounter(visibleCount, totalCount);
}

function updateDocumentCounter(visible, total) {
    const counter = document.getElementById('documentCount');
    if (visible === total) {
        counter.textContent = `${visible} document${visible !== 1 ? 's' : ''}`;
    } else {
        counter.textContent = `${visible} of ${total} document${total !== 1 ? 's' : ''}`;
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize document counter
    filterFiles();
});

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('img') && !event.target.closest('.profile-dropdown')) {
        const dropdowns = document.getElementsByClassName('profile-dropdown-content');
        for (let dropdown of dropdowns) {
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    }
}

// Expandable Submenu Functionality
document.addEventListener('DOMContentLoaded', function() {
    const submenuToggle = document.querySelector('.submenu-toggle');
    const hasSubmenu = document.querySelector('.has-submenu');
    
    if (submenuToggle && hasSubmenu) {
        submenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            hasSubmenu.classList.toggle('active');
        });
    }
    
    // Initialize filters - show all documents by default
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.value = ''; // Show all statuses by default
        filterFiles();
    }
});
