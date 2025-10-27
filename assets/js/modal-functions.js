// Modal Functions for Document Generation
// This file contains all modal-related functions for document generation

// Affidavit of Loss Modal Functions
function openAffidavitLossModal() {
    document.getElementById('affidavitLossModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeAffidavitLossModal() {
    document.getElementById('affidavitLossModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    // Reset form
    document.getElementById('affidavitLossForm').reset();
}

// Solo Parent Modal Functions
function openSoloParentModal() {
    document.getElementById('soloParentModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeSoloParentModal() {
    document.getElementById('soloParentModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    // Reset form
    document.getElementById('soloParentForm').reset();
    // Hide all conditional fields
    document.querySelectorAll('#soloParentModal .conditional-field').forEach(field => {
        field.style.display = 'none';
    });
    // Remove selected styling from radio items
    document.querySelectorAll('#soloParentModal .radio-item').forEach(item => {
        item.style.borderColor = 'var(--border-color)';
        item.style.backgroundColor = 'transparent';
    });
}

// PWD Loss Modal Functions
function openPWDLossModal() {
    document.getElementById('pwdLossModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closePWDLossModal() {
    document.getElementById('pwdLossModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    // Reset form
    document.getElementById('pwdLossForm').reset();
}

// Boticab Loss Modal Functions
function openBoticabLossModal() {
    document.getElementById('boticabLossModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeBoticabLossModal() {
    document.getElementById('boticabLossModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    // Reset form
    document.getElementById('boticabLossForm').reset();
}

// Close modal when clicking outside
function setupModalClickOutside() {
    window.addEventListener('click', function(event) {
        const affidavitModal = document.getElementById('affidavitLossModal');
        const soloModal = document.getElementById('soloParentModal');
        const pwdModal = document.getElementById('pwdLossModal');
        const boticabModal = document.getElementById('boticabLossModal');
        
        if (event.target === affidavitModal) {
            closeAffidavitLossModal();
        }
        if (event.target === soloModal) {
            closeSoloParentModal();
        }
        if (event.target === pwdModal) {
            closePWDLossModal();
        }
        if (event.target === boticabModal) {
            closeBoticabLossModal();
        }
    });
}

// Initialize modal functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupModalClickOutside();
});
