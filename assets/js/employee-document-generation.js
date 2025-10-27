// Employee Document Generation JavaScript
// Separate variables to store form data for each document type
let affidavitLossData = {};
let seniorIDLossData = {};
let soloParentData = {};
let pwdLossData = {};
let boticabLossData = {};
let jointAffidavitData = {};
let swornAffidavitMotherData = {};
let currentDocumentType = '';

// Modal Functions
function openAffidavitLossModal() {
    document.getElementById('affidavitLossModal').style.display = 'block';
    currentDocumentType = 'affidavitLoss';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(affidavitLossData).length > 0) {
        document.getElementById('affidavitLossForm').style.display = 'none';
        document.getElementById('affidavitLossDataPreview').style.display = 'block';
        viewAffidavitLossData();
    } else {
        document.getElementById('affidavitLossForm').style.display = 'block';
        document.getElementById('affidavitLossDataPreview').style.display = 'none';
    }
}

function closeAffidavitLossModal() {
    document.getElementById('affidavitLossModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openSeniorIDLossModal() {
    document.getElementById('seniorIDLossModal').style.display = 'block';
    currentDocumentType = 'seniorIDLoss';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(seniorIDLossData).length > 0) {
        document.getElementById('seniorIDLossForm').style.display = 'none';
        document.getElementById('seniorIDLossDataPreview').style.display = 'block';
        viewSeniorIDLossData();
    } else {
        document.getElementById('seniorIDLossForm').style.display = 'block';
        document.getElementById('seniorIDLossDataPreview').style.display = 'none';
    }
}

function closeSeniorIDLossModal() {
    document.getElementById('seniorIDLossModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openSwornAffidavitSoloParentModal() {
    const modal = document.getElementById('soloParentModal');
    
    if (modal) {
        modal.style.display = 'block';
        currentDocumentType = 'soloParent';
        
        // If we have saved data, show the preview instead of the form
        if (Object.keys(soloParentData).length > 0) {
            document.getElementById('soloParentForm').style.display = 'none';
            document.getElementById('soloParentDataPreview').style.display = 'block';
            viewSoloParentData();
        } else {
            document.getElementById('soloParentForm').style.display = 'block';
            document.getElementById('soloParentDataPreview').style.display = 'none';
        }
    }
}


function closeSoloParentModal() {
    document.getElementById('soloParentModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openPWDLossModal() {
    document.getElementById('pwdLossModal').style.display = 'block';
    currentDocumentType = 'pwdLoss';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(pwdLossData).length > 0) {
        document.getElementById('pwdLossForm').style.display = 'none';
        document.getElementById('pwdLossDataPreview').style.display = 'block';
        viewPWDLossData();
    } else {
        document.getElementById('pwdLossForm').style.display = 'block';
        document.getElementById('pwdLossDataPreview').style.display = 'none';
    }
}

function closePWDLossModal() {
    document.getElementById('pwdLossModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openBoticabLossModal() {
    document.getElementById('boticabLossModal').style.display = 'block';
    currentDocumentType = 'boticabLoss';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(boticabLossData).length > 0) {
        document.getElementById('boticabLossForm').style.display = 'none';
        document.getElementById('boticabLossDataPreview').style.display = 'block';
        viewBoticabLossData();
    } else {
        document.getElementById('boticabLossForm').style.display = 'block';
        document.getElementById('boticabLossDataPreview').style.display = 'none';
    }
}

function closeBoticabLossModal() {
    document.getElementById('boticabLossModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openJointAffidavitModal() {
    document.getElementById('jointAffidavitModal').style.display = 'block';
    currentDocumentType = 'jointAffidavit';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(jointAffidavitData).length > 0) {
        document.getElementById('jointAffidavitForm').style.display = 'none';
        document.getElementById('jointAffidavitDataPreview').style.display = 'block';
        viewJointAffidavitData();
    } else {
        document.getElementById('jointAffidavitForm').style.display = 'block';
        document.getElementById('jointAffidavitDataPreview').style.display = 'none';
    }
}

function closeJointAffidavitModal() {
    document.getElementById('jointAffidavitModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openSwornAffidavitMotherModal() {
    document.getElementById('swornAffidavitMotherModal').style.display = 'block';
    currentDocumentType = 'swornAffidavitMother';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(swornAffidavitMotherData).length > 0) {
        document.getElementById('swornAffidavitMotherForm').style.display = 'none';
        document.getElementById('swornAffidavitMotherDataPreview').style.display = 'block';
        viewSwornAffidavitMotherData();
    } else {
        document.getElementById('swornAffidavitMotherForm').style.display = 'block';
        document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
    }
}

function closeSwornAffidavitMotherModal() {
    document.getElementById('swornAffidavitMotherModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

function openJointAffidavitSoloParentModal() {
    // This should open the same modal as jointAffidavit for now
    openJointAffidavitModal();
}


function closeSwornAffidavitSoloParentModal() {
    document.getElementById('soloParentModal').style.display = 'none';
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

// Solo Parent specific functions
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
    const container = document.getElementById('childrenContainer');
    if (container.children.length > 1) {
        button.parentElement.remove();
    }
}

// Form handling for Solo Parent
document.addEventListener('DOMContentLoaded', function() {
    // Handle reason section radio buttons
    document.querySelectorAll('input[name="reasonSection"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const otherReasonInput = document.getElementById('otherReason');
            if (this.value === 'Other reason, please state') {
                otherReasonInput.style.display = 'block';
                otherReasonInput.required = true;
            } else {
                otherReasonInput.style.display = 'none';
                otherReasonInput.required = false;
                otherReasonInput.value = '';
            }
        });
    });

    // Handle employment status radio buttons
    document.querySelectorAll('input[name="employmentStatus"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const employeeAmount = document.getElementById('employeeAmount');
            const selfEmployedAmount = document.getElementById('selfEmployedAmount');
            const unemployedDependent = document.getElementById('unemployedDependent');
            
            // Hide all first
            employeeAmount.style.display = 'none';
            selfEmployedAmount.style.display = 'none';
            unemployedDependent.style.display = 'none';
            employeeAmount.required = false;
            selfEmployedAmount.required = false;
            unemployedDependent.required = false;
            
            // Show relevant input
            if (this.value === 'Employee and earning') {
                employeeAmount.style.display = 'block';
                employeeAmount.required = true;
            } else if (this.value === 'Self-employed and earning') {
                selfEmployedAmount.style.display = 'block';
                selfEmployedAmount.required = true;
            } else if (this.value === 'Un-employed and dependent upon') {
                unemployedDependent.style.display = 'block';
                unemployedDependent.required = true;
            }
        });
    });
});

// Save Functions
function saveAffidavitLoss() {
    const form = document.getElementById('affidavitLossForm');
    const formData = new FormData(form);
    
    affidavitLossData = {};
    for (let [key, value] of formData.entries()) {
        affidavitLossData[key] = value;
    }
    
    // Validate required fields
    if (!affidavitLossData.fullName || !affidavitLossData.completeAddress || 
        !affidavitLossData.specifyItemLost || !affidavitLossData.itemLost || 
        !affidavitLossData.itemDetails || !affidavitLossData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewAffidavitLossData();
}

function saveSeniorIDLoss() {
    const form = document.getElementById('seniorIDLossForm');
    const formData = new FormData(form);
    
    seniorIDLossData = {};
    for (let [key, value] of formData.entries()) {
        seniorIDLossData[key] = value;
    }
    
    // Validate required fields
    if (!seniorIDLossData.fullName || !seniorIDLossData.completeAddress || 
        !seniorIDLossData.relationship || !seniorIDLossData.seniorCitizenName || 
        !seniorIDLossData.detailsOfLoss || !seniorIDLossData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewSeniorIDLossData();
}

function saveSoloParent() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    
    soloParentData = {};
    for (let [key, value] of formData.entries()) {
        if (key === 'childrenNames[]' || key === 'childrenAges[]') {
            if (!soloParentData[key]) {
                soloParentData[key] = [];
            }
            soloParentData[key].push(value);
        } else {
            soloParentData[key] = value;
        }
    }
    
    // Validate required fields
    if (!soloParentData.fullName || !soloParentData.completeAddress || 
        !soloParentData['childrenNames[]'] || !soloParentData['childrenAges[]'] ||
        !soloParentData.yearsUnderCase || !soloParentData.reasonSection || 
        !soloParentData.employmentStatus || !soloParentData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewSoloParentData();
}

function savePWDLoss() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    
    pwdLossData = {};
    for (let [key, value] of formData.entries()) {
        pwdLossData[key] = value;
    }
    
    // Validate required fields
    if (!pwdLossData.fullName || !pwdLossData.fullAddress || 
        !pwdLossData.detailsOfLoss || !pwdLossData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewPWDLossData();
}

function saveBoticabLoss() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    
    boticabLossData = {};
    for (let [key, value] of formData.entries()) {
        boticabLossData[key] = value;
    }
    
    // Validate required fields
    if (!boticabLossData.fullName || !boticabLossData.fullAddress || 
        !boticabLossData.detailsOfLoss || !boticabLossData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewBoticabLossData();
}

function saveJointAffidavit() {
    const form = document.getElementById('jointAffidavitForm');
    const formData = new FormData(form);
    
    jointAffidavitData = {};
    for (let [key, value] of formData.entries()) {
        jointAffidavitData[key] = value;
    }
    
    // Validate required fields
    if (!jointAffidavitData.firstPersonName || !jointAffidavitData.secondPersonName || 
        !jointAffidavitData.firstPersonAddress || !jointAffidavitData.secondPersonAddress ||
        !jointAffidavitData.childName || !jointAffidavitData.dateOfBirth || 
        !jointAffidavitData.placeOfBirth || !jointAffidavitData.fatherName || 
        !jointAffidavitData.motherName || !jointAffidavitData.childNameNumber4 || 
        !jointAffidavitData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewJointAffidavitData();
}

function saveSwornAffidavitMother() {
    const form = document.getElementById('swornAffidavitMotherForm');
    const formData = new FormData(form);
    
    swornAffidavitMotherData = {};
    for (let [key, value] of formData.entries()) {
        swornAffidavitMotherData[key] = value;
    }
    
    // Validate required fields
    if (!swornAffidavitMotherData.fullName || !swornAffidavitMotherData.completeAddress || 
        !swornAffidavitMotherData.childName || !swornAffidavitMotherData.birthDate || 
        !swornAffidavitMotherData.birthPlace || !swornAffidavitMotherData.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }
    
    viewSwornAffidavitMotherData();
}

// View Data Functions
function viewAffidavitLossData() {
    document.getElementById('previewFullName').textContent = affidavitLossData.fullName || '-';
    document.getElementById('previewCompleteAddress').textContent = affidavitLossData.completeAddress || '-';
    document.getElementById('previewSpecifyItemLost').textContent = affidavitLossData.specifyItemLost || '-';
    document.getElementById('previewItemLost').textContent = affidavitLossData.itemLost || '-';
    document.getElementById('previewItemDetails').textContent = affidavitLossData.itemDetails || '-';
    document.getElementById('previewDateOfNotary').textContent = affidavitLossData.dateOfNotary || '-';
    
    document.getElementById('affidavitLossForm').style.display = 'none';
    document.getElementById('affidavitLossDataPreview').style.display = 'block';
}

function viewSeniorIDLossData() {
    document.getElementById('previewSeniorFullName').textContent = seniorIDLossData.fullName || '-';
    document.getElementById('previewSeniorCompleteAddress').textContent = seniorIDLossData.completeAddress || '-';
    document.getElementById('previewRelationship').textContent = seniorIDLossData.relationship || '-';
    document.getElementById('previewSeniorCitizenName').textContent = seniorIDLossData.seniorCitizenName || '-';
    document.getElementById('previewSeniorDetailsOfLoss').textContent = seniorIDLossData.detailsOfLoss || '-';
    document.getElementById('previewSeniorDateOfNotary').textContent = seniorIDLossData.dateOfNotary || '-';
    
    document.getElementById('seniorIDLossForm').style.display = 'none';
    document.getElementById('seniorIDLossDataPreview').style.display = 'block';
}

function viewSoloParentData() {
    document.getElementById('previewSoloFullName').textContent = soloParentData.fullName || '-';
    document.getElementById('previewSoloCompleteAddress').textContent = soloParentData.completeAddress || '-';
    
    // Handle children data
    const childrenNames = soloParentData['childrenNames[]'] || [];
    const childrenAges = soloParentData['childrenAges[]'] || [];
    let childrenText = '';
    for (let i = 0; i < Math.max(childrenNames.length, childrenAges.length); i++) {
        const name = childrenNames[i] || '';
        const age = childrenAges[i] || '';
        if (name || age) {
            childrenText += `${name} (${age} years old)`;
            if (i < Math.max(childrenNames.length, childrenAges.length) - 1) {
                childrenText += ', ';
            }
        }
    }
    document.getElementById('previewChildren').textContent = childrenText || '-';
    
    document.getElementById('previewYearsUnderCase').textContent = soloParentData.yearsUnderCase || '-';
    document.getElementById('previewReason').textContent = soloParentData.reasonSection || '-';
    document.getElementById('previewEmployment').textContent = soloParentData.employmentStatus || '-';
    document.getElementById('previewSoloDateOfNotary').textContent = soloParentData.dateOfNotary || '-';
    
    document.getElementById('soloParentForm').style.display = 'none';
    document.getElementById('soloParentDataPreview').style.display = 'block';
}

function viewPWDLossData() {
    document.getElementById('previewPwdFullName').textContent = pwdLossData.fullName || '-';
    document.getElementById('previewPwdFullAddress').textContent = pwdLossData.fullAddress || '-';
    document.getElementById('previewPwdDetailsOfLoss').textContent = pwdLossData.detailsOfLoss || '-';
    document.getElementById('previewPwdDateOfNotary').textContent = pwdLossData.dateOfNotary || '-';
    
    document.getElementById('pwdLossForm').style.display = 'none';
    document.getElementById('pwdLossDataPreview').style.display = 'block';
}

function viewBoticabLossData() {
    document.getElementById('previewBoticabFullName').textContent = boticabLossData.fullName || '-';
    document.getElementById('previewBoticabFullAddress').textContent = boticabLossData.fullAddress || '-';
    document.getElementById('previewBoticabDetailsOfLoss').textContent = boticabLossData.detailsOfLoss || '-';
    document.getElementById('previewBoticabDateOfNotary').textContent = boticabLossData.dateOfNotary || '-';
    
    document.getElementById('boticabLossForm').style.display = 'none';
    document.getElementById('boticabLossDataPreview').style.display = 'block';
}

function viewJointAffidavitData() {
    document.getElementById('previewFirstPersonName').textContent = jointAffidavitData.firstPersonName || '-';
    document.getElementById('previewSecondPersonName').textContent = jointAffidavitData.secondPersonName || '-';
    document.getElementById('previewFirstPersonAddress').textContent = jointAffidavitData.firstPersonAddress || '-';
    document.getElementById('previewSecondPersonAddress').textContent = jointAffidavitData.secondPersonAddress || '-';
    document.getElementById('previewChildName').textContent = jointAffidavitData.childName || '-';
    document.getElementById('previewDateOfBirth').textContent = jointAffidavitData.dateOfBirth || '-';
    document.getElementById('previewPlaceOfBirth').textContent = jointAffidavitData.placeOfBirth || '-';
    document.getElementById('previewFatherName').textContent = jointAffidavitData.fatherName || '-';
    document.getElementById('previewMotherName').textContent = jointAffidavitData.motherName || '-';
    document.getElementById('previewChildNameNumber4').textContent = jointAffidavitData.childNameNumber4 || '-';
    document.getElementById('previewJointDateOfNotary').textContent = jointAffidavitData.dateOfNotary || '-';
    
    document.getElementById('jointAffidavitForm').style.display = 'none';
    document.getElementById('jointAffidavitDataPreview').style.display = 'block';
}

function viewSwornAffidavitMotherData() {
    document.getElementById('previewMotherFullName').textContent = swornAffidavitMotherData.fullName || '-';
    document.getElementById('previewMotherCompleteAddress').textContent = swornAffidavitMotherData.completeAddress || '-';
    document.getElementById('previewMotherChildName').textContent = swornAffidavitMotherData.childName || '-';
    document.getElementById('previewBirthDate').textContent = swornAffidavitMotherData.birthDate || '-';
    document.getElementById('previewBirthPlace').textContent = swornAffidavitMotherData.birthPlace || '-';
    document.getElementById('previewMotherDateOfNotary').textContent = swornAffidavitMotherData.dateOfNotary || '-';
    
    document.getElementById('swornAffidavitMotherForm').style.display = 'none';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'block';
}

// New Document Functions (to start fresh)
function newAffidavitLossDocument() {
    affidavitLossData = {};
    document.getElementById('affidavitLossForm').reset();
    document.getElementById('affidavitLossForm').style.display = 'block';
    document.getElementById('affidavitLossDataPreview').style.display = 'none';
}

function newSeniorIDLossDocument() {
    seniorIDLossData = {};
    document.getElementById('seniorIDLossForm').reset();
    document.getElementById('seniorIDLossForm').style.display = 'block';
    document.getElementById('seniorIDLossDataPreview').style.display = 'none';
}

function newSoloParentDocument() {
    soloParentData = {};
    document.getElementById('soloParentForm').reset();
    document.getElementById('soloParentForm').style.display = 'block';
    document.getElementById('soloParentDataPreview').style.display = 'none';
    // Reset children container
    const container = document.getElementById('childrenContainer');
    container.innerHTML = `
        <div class="child-entry">
            <input type="text" name="childrenNames[]" placeholder="Child's Name" required>
            <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
            <button type="button" onclick="removeChild(this)" class="btn btn-danger btn-sm">Remove</button>
        </div>
    `;
}

function newPWDLossDocument() {
    pwdLossData = {};
    document.getElementById('pwdLossForm').reset();
    document.getElementById('pwdLossForm').style.display = 'block';
    document.getElementById('pwdLossDataPreview').style.display = 'none';
}

function newBoticabLossDocument() {
    boticabLossData = {};
    document.getElementById('boticabLossForm').reset();
    document.getElementById('boticabLossForm').style.display = 'block';
    document.getElementById('boticabLossDataPreview').style.display = 'none';
}

function newJointAffidavitDocument() {
    jointAffidavitData = {};
    document.getElementById('jointAffidavitForm').reset();
    document.getElementById('jointAffidavitForm').style.display = 'block';
    document.getElementById('jointAffidavitDataPreview').style.display = 'none';
}

function newSwornAffidavitMotherDocument() {
    swornAffidavitMotherData = {};
    document.getElementById('swornAffidavitMotherForm').reset();
    document.getElementById('swornAffidavitMotherForm').style.display = 'block';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
}

// Edit Functions
function editAffidavitLossData() {
    document.getElementById('affidavitLossForm').style.display = 'block';
    document.getElementById('affidavitLossDataPreview').style.display = 'none';
}

function editSeniorIDLossData() {
    document.getElementById('seniorIDLossForm').style.display = 'block';
    document.getElementById('seniorIDLossDataPreview').style.display = 'none';
}

function editSoloParentData() {
    document.getElementById('soloParentForm').style.display = 'block';
    document.getElementById('soloParentDataPreview').style.display = 'none';
}

function editPWDLossData() {
    document.getElementById('pwdLossForm').style.display = 'block';
    document.getElementById('pwdLossDataPreview').style.display = 'none';
}

function editBoticabLossData() {
    document.getElementById('boticabLossForm').style.display = 'block';
    document.getElementById('boticabLossDataPreview').style.display = 'none';
}

function editJointAffidavitData() {
    document.getElementById('jointAffidavitForm').style.display = 'block';
    document.getElementById('jointAffidavitDataPreview').style.display = 'none';
}

function editSwornAffidavitMotherData() {
    document.getElementById('swornAffidavitMotherForm').style.display = 'block';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
}

// PDF Generation Functions
function generateAffidavitLossPDF() {
    const form = document.getElementById('affidavitLossForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.specifyItemLost || 
        !data.itemLost || !data.itemDetails || !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    generatePDF('affidavitLoss', data);
}

function generateSeniorIDLossPDF() {
    const form = document.getElementById('seniorIDLossForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.relationship || 
        !data.seniorCitizenName || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    generatePDF('seniorIDLoss', data);
}

function generateSoloParentPDF() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    const data = {};
    const childrenNames = [];
    const childrenAges = [];
    
    for (let [key, value] of formData.entries()) {
        if (key === 'childrenNames[]') {
            childrenNames.push(value);
        } else if (key === 'childrenAges[]') {
            childrenAges.push(value);
        } else {
            data[key] = value;
        }
    }
    
    // Convert children arrays to strings for the PDF generator
    data.childrenNames = childrenNames.join(', ');
    data.childrenAges = childrenAges.join(', ');
    
    // Validate required fields
    if (!data.fullName || !data.completeAddress || !childrenNames.length || 
        !data.yearsUnderCase || !data.reasonSection || !data.employmentStatus || 
        !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    generatePDF('soloParent', data);
}

function generatePWDLossPDF() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    generatePDF('pwdLoss', data);
}

function generateBoticabLossPDF() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    generatePDF('boticabLoss', data);
}

function generateJointAffidavitPDF() {
    const form = document.getElementById('jointAffidavitForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    generatePDF('jointAffidavit', data);
}

function generateSwornAffidavitMotherPDF() {
    const form = document.getElementById('swornAffidavitMotherForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.childName || 
        !data.birthDate || !data.birthPlace || !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    generatePDF('swornAffidavitMother', data);
}

// Main PDF Generation Function
function generatePDF(documentType, formData) {
    // Show loading state
    const generateBtn = event.target;
    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    generateBtn.disabled = true;
    
    // Debug: Log form data
    console.log('Document Type:', documentType);
    console.log('Form Data:', formData);
    
    // Create form data for submission
    const submitData = new FormData();
    submitData.append('action', 'generate_pdf_direct');
    submitData.append('document_type', documentType);
    submitData.append('form_data', JSON.stringify(formData));
    
    // Submit to employee document handler
    fetch('employee_document_handler.php', {
        method: 'POST',
        body: submitData
    })
    .then(response => {
        if (response.ok) {
            // If response is a PDF file, trigger download
            return response.blob();
        } else {
            throw new Error('Failed to generate PDF');
        }
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `${documentType}_${new Date().getTime()}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Show success message
        alert('PDF generated and downloaded successfully!');
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to generate PDF: ' + error.message);
    })
    .finally(() => {
        // Reset button
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
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
    alert('Data saved successfully!');
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

function editJointAffidavitSoloParentData() {
    document.getElementById('jointAffidavitSoloParentForm').style.display = 'block';
    document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'none';
}

function newJointAffidavitSoloParentDocument() {
    document.getElementById('jointAffidavitSoloParentForm').reset();
    document.getElementById('jointAffidavitSoloParentForm').style.display = 'block';
    document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'none';
}

function generateJointAffidavitSoloParentPDF() {
    const form = document.getElementById('jointAffidavitSoloParentForm');
    const formData = new FormData(form);
    
    // Build URL parameters
    const params = new URLSearchParams();
    params.append('affiant1Name', formData.get('affiant1Name') || '');
    params.append('affiant2Name', formData.get('affiant2Name') || '');
    params.append('affiantsAddress', formData.get('affiantsAddress') || '');
    params.append('soloParentName', formData.get('soloParentName') || '');
    params.append('soloParentAddress', formData.get('soloParentAddress') || '');
    params.append('affiant1ValidId', formData.get('affiant1ValidId') || '');
    params.append('affiant2ValidId', formData.get('affiant2ValidId') || '');
    params.append('dateOfNotary', formData.get('dateOfNotary') || '');
    
    // Add children data
    const names = formData.getAll('childrenNames[]');
    const ages = formData.getAll('childrenAges[]');
    names.forEach(name => params.append('childrenNames[]', name));
    ages.forEach(age => params.append('childrenAges[]', age));
    
    // Open PDF generation
    window.open('files-generation/generate_joint_affidavit_solo_parent.php?' + params.toString(), '_blank');
}

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
});
