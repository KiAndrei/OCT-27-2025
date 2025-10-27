// Form Handling Functions for Document Generation
// This file contains all form-related functions

// Set today's date as default for date fields
function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    
    // Only set values for elements that exist
    const dateFields = [
        'dateOfNotary',
        'saDateOfNotary', 
        'seniorDateOfNotary',
        'pwdDateOfNotary',
        'boticabDateOfNotary',
        'jointDateOfNotary',
        'swornMotherDateOfNotary'
    ];
    
    dateFields.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.value = today;
        }
    });
}

// Handle Solo Parent conditional fields
function handleSoloParentConditionalFields() {
    // Hide all conditional fields
    document.querySelectorAll('#soloParentModal .conditional-field').forEach(field => {
        field.style.display = 'none';
    });

    // Show relevant conditional fields based on selections
    const reasonSection = document.querySelector('#soloParentModal input[name="reasonSection"]:checked');
    const employmentStatus = document.querySelector('#soloParentModal input[name="employmentStatus"]:checked');

    // Handle reason section
    if (reasonSection && reasonSection.value === 'Other reason, please state') {
        document.getElementById('otherReasonField').style.display = 'block';
    }

    // Handle employment status
    if (employmentStatus) {
        switch(employmentStatus.value) {
            case 'Employee and earning':
                document.getElementById('employeeAmountField').style.display = 'block';
                break;
            case 'Self-employed and earning':
                document.getElementById('selfEmployedAmountField').style.display = 'block';
                break;
            case 'Un-employed and dependent upon':
                document.getElementById('unemployedDependentField').style.display = 'block';
                break;
        }
    }
}

// Setup Solo Parent form event listeners
function setupSoloParentFormListeners() {
    // Handle reason section radio buttons
    document.querySelectorAll('#soloParentModal input[name="reasonSection"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove selected styling from all reason radio items
            document.querySelectorAll('#soloParentModal input[name="reasonSection"]').forEach(r => {
                r.closest('.radio-item').style.borderColor = 'var(--border-color)';
                r.closest('.radio-item').style.backgroundColor = 'transparent';
            });
            
            // Add selected styling to current item
            this.closest('.radio-item').style.borderColor = 'var(--primary-color)';
            this.closest('.radio-item').style.backgroundColor = 'rgba(93, 14, 38, 0.05)';
            
            // Handle conditional fields
            handleSoloParentConditionalFields();
        });
    });

    // Handle employment status radio buttons
    document.querySelectorAll('#soloParentModal input[name="employmentStatus"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove selected styling from all employment radio items
            document.querySelectorAll('#soloParentModal input[name="employmentStatus"]').forEach(r => {
                r.closest('.radio-item').style.borderColor = 'var(--border-color)';
                r.closest('.radio-item').style.backgroundColor = 'transparent';
            });
            
            // Add selected styling to current item
            this.closest('.radio-item').style.borderColor = 'var(--primary-color)';
            this.closest('.radio-item').style.backgroundColor = 'rgba(93, 14, 38, 0.05)';
            
            // Handle conditional fields
            handleSoloParentConditionalFields();
        });
    });

    // Add click handlers to radio items for better UX
    document.querySelectorAll('#soloParentModal .radio-item').forEach(item => {
        item.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        });
    });
}

// Initialize form functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setDefaultDates();
    setupSoloParentFormListeners();
});
