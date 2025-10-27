// Document Viewer Functions
// This file contains all document viewer modal functions

// Document Viewer Modal Functions
function openDocumentViewer(documentUrl, formId) {
    const modal = document.getElementById('documentViewerModal');
    const documentContent = document.getElementById('documentContent');
    
    // Add loading class and show loading state
    documentContent.className = 'document-content loading';
    documentContent.innerHTML = `
        <div class="loading-content">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Generating document...</p>
        </div>
    `;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
    
    // Generate the document preview using actual PDF generation files
    setTimeout(() => {
        const form = document.getElementById(formId);
        const formData = new FormData(form);
        
        // Get form data based on form type
        let data = {};
        
        if (formId === 'affidavitLossForm') {
            data = {
                fullName: formData.get('fullName') || '',
                completeAddress: formData.get('completeAddress') || '',
                specifyItemLost: formData.get('specifyItemLost') || '',
                itemLost: formData.get('itemLost') || '',
                itemDetails: formData.get('itemDetails') || '',
                dateOfNotary: formData.get('dateOfNotary') || ''
            };
        } else if (formId === 'soloParentForm') {
            data = {
                fullName: formData.get('fullName') || '',
                completeAddress: formData.get('completeAddress') || '',
                childrenNames: formData.get('childrenNames') || '',
                yearsUnderCase: formData.get('yearsUnderCase') || '',
                reasonSection: formData.get('reasonSection') || '',
                otherReason: formData.get('otherReason') || '',
                employmentStatus: formData.get('employmentStatus') || '',
                employeeAmount: formData.get('employeeAmount') || '',
                selfEmployedAmount: formData.get('selfEmployedAmount') || '',
                unemployedDependent: formData.get('unemployedDependent') || '',
                dateOfNotary: formData.get('dateOfNotary') || ''
            };
        } else if (formId === 'pwdLossForm') {
            data = {
                fullName: formData.get('fullName') || '',
                fullAddress: formData.get('fullAddress') || '',
                detailsOfLoss: formData.get('detailsOfLoss') || '',
                dateOfNotary: formData.get('dateOfNotary') || ''
            };
        } else if (formId === 'boticabLossForm') {
            data = {
                fullName: formData.get('fullName') || '',
                fullAddress: formData.get('fullAddress') || '',
                detailsOfLoss: formData.get('detailsOfLoss') || '',
                dateOfNotary: formData.get('dateOfNotary') || ''
            };
        }

        // Create URL with form data for view-only mode
        const params = new URLSearchParams(data);
        const viewUrl = `${documentUrl}?${params.toString()}&view_only=1`;
        
        // Remove loading class and display using iframe with actual PDF generation file
        documentContent.className = 'document-content';
        documentContent.innerHTML = `
            <iframe src="${viewUrl}" 
                    sandbox="allow-same-origin allow-scripts"
                    oncontextmenu="return false;"
                    onselectstart="return false;"
                    onload="this.style.opacity='1'"
                    style="opacity: 0; transition: opacity 0.3s ease; width: 100%; height: 100%; border: none;">
            </iframe>
        `;
    }, 1000);
}

function closeDocumentViewer() {
    const modal = document.getElementById('documentViewerModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Clear content and reset classes
    const documentContent = document.getElementById('documentContent');
    documentContent.className = 'document-content';
    documentContent.innerHTML = '';
}

// Download Document Function
function downloadDocument() {
    if (window.currentFormType) {
        // Get the current form data
        let formData = {};
        let documentUrl = '';
        
        switch(window.currentFormType) {
            case 'affidavitLoss':
                const affidavitForm = document.getElementById('affidavitLossForm');
                if (affidavitForm) {
                    const form = new FormData(affidavitForm);
                    formData = {
                        fullName: form.get('fullName'),
                        completeAddress: form.get('completeAddress'),
                        specifyItemLost: form.get('specifyItemLost'),
                        itemLost: form.get('itemLost'),
                        itemDetails: form.get('itemDetails'),
                        dateOfNotary: form.get('dateOfNotary')
                    };
                    documentUrl = 'files-generation/generate_affidavit_of_loss.php';
                }
                break;
            case 'soloParent':
                const soloForm = document.getElementById('soloParentForm');
                if (soloForm) {
                    const form = new FormData(soloForm);
                    formData = {
                        fullName: form.get('fullName'),
                        completeAddress: form.get('completeAddress'),
                        childrenNames: form.get('childrenNames'),
                        yearsUnderCase: form.get('yearsUnderCase'),
                        reasonSection: form.get('reasonSection'),
                        otherReason: form.get('otherReason'),
                        employmentStatus: form.get('employmentStatus'),
                        employeeAmount: form.get('employeeAmount'),
                        selfEmployedAmount: form.get('selfEmployedAmount'),
                        unemployedDependent: form.get('unemployedDependent'),
                        dateOfNotary: form.get('dateOfNotary')
                    };
                    documentUrl = 'files-generation/generate_affidavit_of_solo_parent.php';
                }
                break;
            case 'pwdLoss':
                const pwdForm = document.getElementById('pwdLossForm');
                if (pwdForm) {
                    const form = new FormData(pwdForm);
                    formData = {
                        fullName: form.get('fullName'),
                        fullAddress: form.get('fullAddress'),
                        detailsOfLoss: form.get('detailsOfLoss'),
                        dateOfNotary: form.get('dateOfNotary')
                    };
                    documentUrl = 'files-generation/generate_affidavit_of_loss_pwd_id.php';
                }
                break;
            case 'boticabLoss':
                const boticabForm = document.getElementById('boticabLossForm');
                if (boticabForm) {
                    const form = new FormData(boticabForm);
                    formData = {
                        fullName: form.get('fullName'),
                        fullAddress: form.get('fullAddress'),
                        detailsOfLoss: form.get('detailsOfLoss'),
                        dateOfNotary: form.get('dateOfNotary')
                    };
                    documentUrl = 'files-generation/generate_affidavit_of_loss_boticab.php';
                }
                break;
        }

        if (documentUrl && Object.keys(formData).length > 0) {
            // Create URL with form data for download
            const params = new URLSearchParams(formData);
            const downloadUrl = `${documentUrl}?${params.toString()}&download=1`;
            
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `${window.currentFormType}_document.pdf`;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            alert('No document data available for download');
        }
    } else {
        alert('No document available for download');
    }
}

// Edit Document Function
function editDocument() {
    // Close the document viewer modal
    closeDocumentViewer();
    
    // Get the current form type from the global variable
    if (window.currentFormType) {
        // Open the appropriate form modal based on the current form type
        switch(window.currentFormType) {
            case 'affidavitLoss':
                openAffidavitLossModal();
                break;
            case 'soloParent':
                openSoloParentModal();
                break;
            case 'pwdLoss':
                openPWDLossModal();
                break;
            case 'boticabLoss':
                openBoticabLossModal();
                break;
            default:
                alert('Form type not recognized');
        }
    } else {
        alert('No form data available to edit');
    }
}

// Send Document Function
function sendDocument() {
    // Show confirmation dialog
    if (confirm('Are you sure you want to send this document to the employee? This action cannot be undone.')) {
        // Get the send button and prevent double-clicking
        const sendBtn = document.querySelector('button[onclick="sendDocument()"]');
        if (sendBtn.disabled) {
            return; // Already processing
        }
        
        const originalText = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        sendBtn.disabled = true;

        // Get the current form data
        let formData = {};
        let formType = '';
        
        if (window.currentFormType) {
            formType = window.currentFormType;
            
            // Get form data based on current form type
            switch(window.currentFormType) {
                case 'affidavitLoss':
                    const affidavitForm = document.getElementById('affidavitLossForm');
                    if (affidavitForm) {
                        const form = new FormData(affidavitForm);
                        formData = {
                            fullName: form.get('fullName'),
                            completeAddress: form.get('completeAddress'),
                            specifyItemLost: form.get('specifyItemLost'),
                            itemLost: form.get('itemLost'),
                            itemDetails: form.get('itemDetails'),
                            dateOfNotary: form.get('dateOfNotary')
                        };
                    }
                    break;
                case 'soloParent':
                    const soloForm = document.getElementById('soloParentForm');
                    if (soloForm) {
                        const form = new FormData(soloForm);
                        formData = {
                            fullName: form.get('fullName'),
                            completeAddress: form.get('completeAddress'),
                            childrenNames: form.get('childrenNames'),
                            yearsUnderCase: form.get('yearsUnderCase'),
                            reasonSection: form.get('reasonSection'),
                            otherReason: form.get('otherReason'),
                            employmentStatus: form.get('employmentStatus'),
                            employeeAmount: form.get('employeeAmount'),
                            selfEmployedAmount: form.get('selfEmployedAmount'),
                            unemployedDependent: form.get('unemployedDependent'),
                            dateOfNotary: form.get('dateOfNotary')
                        };
                    }
                    break;
                case 'pwdLoss':
                    const pwdForm = document.getElementById('pwdLossForm');
                    if (pwdForm) {
                        const form = new FormData(pwdForm);
                        formData = {
                            fullName: form.get('fullName'),
                            fullAddress: form.get('fullAddress'),
                            detailsOfLoss: form.get('detailsOfLoss'),
                            dateOfNotary: form.get('dateOfNotary')
                        };
                    }
                    break;
                case 'boticabLoss':
                    const boticabForm = document.getElementById('boticabLossForm');
                    if (boticabForm) {
                        const form = new FormData(boticabForm);
                        formData = {
                            fullName: form.get('fullName'),
                            fullAddress: form.get('fullAddress'),
                            detailsOfLoss: form.get('detailsOfLoss'),
                            dateOfNotary: form.get('dateOfNotary')
                        };
                    }
                    break;
            }
        }

        // Debug: Log what we're sending
        console.log('Sending document:', {
            formType: formType,
            formData: formData
        });

        // Send data to server
        fetch('send_document_handler_simple.php', {
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
            return response.json();
        })
        .then(result => {
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
            
            // Debug: Log server response
            console.log('Server response:', result);
            
            if (result.status === 'success') {
                alert('Document sent successfully to the employee!');
                closeDocumentViewer();
            } else {
                alert('Error: ' + result.message);
                console.error('Error details:', result.debug_info);
            }
        })
        .catch(error => {
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
            console.error('Error:', error);
            alert('Error sending document: ' + error.message);
        });
    }
}

// Setup document viewer event listeners
function setupDocumentViewerListeners() {
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('documentViewerModal');
        if (event.target === modal) {
            closeDocumentViewer();
        }
    });

    // Handle escape key to close modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('documentViewerModal');
            if (modal.style.display === 'block') {
                closeDocumentViewer();
            }
        }
    });
}

// Initialize document viewer functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupDocumentViewerListeners();
});
