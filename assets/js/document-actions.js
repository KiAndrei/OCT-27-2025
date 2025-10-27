// Document Actions Functions (Save/View)
// This file contains functions for saving and viewing documents

// Show success modal for document save
function showDocumentSaveModal() {
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
        <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Document Saved Successfully!</h3>
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
            <p style="margin: 0 0 15px 0;">Document saved locally!</p>
            <p style="margin: 0; font-weight: 500; color: #5D0E26;">
                Click <strong>"View"</strong> to preview and <strong>"Send"</strong> to submit to employee.
            </p>
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
            <i class="fas fa-check"></i> Got it!
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

    // Auto close after 5 seconds
    setTimeout(() => {
        if (modalOverlay.parentNode) {
            modalOverlay.remove();
        }
    }, 5000);
}

// Save Affidavit Loss function (Local save only - no database submission)
function saveAffidavitLoss() {
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

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.specifyItemLost || !data.itemLost || !data.itemDetails || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="saveAffidavitLoss()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        showDocumentSaveModal();
    }, 1000);
}

// View Affidavit Loss function
function viewAffidavitLoss() {
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

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.specifyItemLost || !data.itemLost || !data.itemDetails || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewAffidavitLoss()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_loss.php', 'affidavitLossForm');
        window.currentFormType = 'affidavitLoss';
    }, 1500);
}

// Save Solo Parent function (Local save only - no database submission)
function saveSoloParent() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        childrenNames: formData.get('childrenNames'),
        yearsUnderCase: formData.get('yearsUnderCase'),
        reasonSection: formData.get('reasonSection'),
        otherReason: formData.get('otherReason'),
        employmentStatus: formData.get('employmentStatus'),
        employeeAmount: formData.get('employeeAmount'),
        selfEmployedAmount: formData.get('selfEmployedAmount'),
        unemployedDependent: formData.get('unemployedDependent'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.childrenNames || !data.yearsUnderCase || !data.reasonSection || !data.employmentStatus || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="saveSoloParent()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        showDocumentSaveModal();
    }, 1000);
}

// View Solo Parent function
function viewSoloParent() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        childrenNames: formData.get('childrenNames'),
        yearsUnderCase: formData.get('yearsUnderCase'),
        reasonSection: formData.get('reasonSection'),
        otherReason: formData.get('otherReason'),
        employmentStatus: formData.get('employmentStatus'),
        employeeAmount: formData.get('employeeAmount'),
        selfEmployedAmount: formData.get('selfEmployedAmount'),
        unemployedDependent: formData.get('unemployedDependent'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.childrenNames || !data.yearsUnderCase || !data.reasonSection || !data.employmentStatus || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewSoloParent()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_solo_parent.php', 'soloParentForm');
        window.currentFormType = 'soloParent';
    }, 1500);
}

// Save PWD Loss function (Local save only - no database submission)
function savePWDLoss() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="savePWDLoss()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        showDocumentSaveModal();
    }, 1000);
}

// View PWD Loss function
function viewPWDLoss() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewPWDLoss()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_loss_pwd_id.php', 'pwdLossForm');
        window.currentFormType = 'pwdLoss';
    }, 1500);
}

// Save Boticab Loss function (Local save only - no database submission)
function saveBoticabLoss() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="saveBoticabLoss()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        showDocumentSaveModal();
    }, 1000);
}

// View Boticab Loss function
function viewBoticabLoss() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewBoticabLoss()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_loss_boticab.php', 'boticabLossForm');
        window.currentFormType = 'boticabLoss';
    }, 1500);
}
