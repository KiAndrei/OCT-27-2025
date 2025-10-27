// Attorney Document Generation JavaScript
// Separate variables to store form data for each document type
let affidavitLossData = {};
let seniorIDLossData = {};
let soloParentData = {};
let pwdLossData = {};
let boticabLossData = {};
let jointAffidavitData = {};
let jointAffidavitSoloParentData = {};
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

function openJointAffidavitSoloParentModal() {
    document.getElementById('jointAffidavitSoloParentModal').style.display = 'block';
    currentDocumentType = 'jointAffidavitSoloParent';
    
    // If we have saved data, show the preview instead of the form
    if (Object.keys(jointAffidavitSoloParentData).length > 0) {
        document.getElementById('jointAffidavitSoloParentForm').style.display = 'none';
        document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'block';
        viewJointAffidavitSoloParentData();
    } else {
        document.getElementById('jointAffidavitSoloParentForm').style.display = 'block';
        document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'none';
    }
}

function closeJointAffidavitSoloParentModal() {
    document.getElementById('jointAffidavitSoloParentModal').style.display = 'none';
    // Don't reset form data or clear currentFormData - keep it for next time
}

// Save Functions
function saveAffidavitLoss() {
    const form = document.getElementById('affidavitLossForm');
    const formData = new FormData(form);
    
    affidavitLossData = {};
    for (let [key, value] of formData.entries()) {
        affidavitLossData[key] = value;
    }
    
    // Show preview
    document.getElementById('affidavitLossForm').style.display = 'none';
    document.getElementById('affidavitLossDataPreview').style.display = 'block';
    viewAffidavitLossData();
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
    
    // Show preview
    document.getElementById('soloParentForm').style.display = 'none';
    document.getElementById('soloParentDataPreview').style.display = 'block';
    viewSoloParentData();
}

function saveJointAffidavit() {
    const form = document.getElementById('jointAffidavitForm');
    const formData = new FormData(form);
    
    jointAffidavitData = {};
    for (let [key, value] of formData.entries()) {
        jointAffidavitData[key] = value;
    }
    
    // Show preview
    document.getElementById('jointAffidavitForm').style.display = 'none';
    document.getElementById('jointAffidavitDataPreview').style.display = 'block';
    viewJointAffidavitData();
}

// View Data Functions
function viewAffidavitLossData() {
    document.getElementById('previewFullName').textContent = affidavitLossData.fullName || '-';
    document.getElementById('previewCompleteAddress').textContent = affidavitLossData.completeAddress || '-';
    document.getElementById('previewSpecifyItemLost').textContent = affidavitLossData.specifyItemLost || '-';
    document.getElementById('previewItemLost').textContent = affidavitLossData.itemLost || '-';
    document.getElementById('previewItemDetails').textContent = affidavitLossData.itemDetails || '-';
    document.getElementById('previewDateOfNotary').textContent = affidavitLossData.dateOfNotary || '-';
}

function viewSoloParentData() {
    document.getElementById('previewSoloFullName').textContent = soloParentData.fullName || '-';
    document.getElementById('previewSoloCompleteAddress').textContent = soloParentData.completeAddress || '-';
    
    // Children
    const childrenNames = soloParentData['childrenNames[]'] || [];
    const childrenAges = soloParentData['childrenAges[]'] || [];
    let childrenText = '';
    for (let i = 0; i < childrenNames.length; i++) {
        if (childrenText) childrenText += ', ';
        childrenText += `${childrenNames[i]} (${childrenAges[i]} years old)`;
    }
    document.getElementById('previewChildren').textContent = childrenText || '-';
    
    document.getElementById('previewYearsUnderCase').textContent = soloParentData.yearsUnderCase || '-';
    
    // Reason
    let reasonText = soloParentData.reasonSection || '-';
    if (soloParentData.reasonSection === 'Other reason, please state' && soloParentData.otherReason) {
        reasonText = soloParentData.otherReason;
    }
    document.getElementById('previewReason').textContent = reasonText;
    
    // Employment
    let employmentText = soloParentData.employmentStatus || '-';
    if (soloParentData.employmentStatus === 'Employee and earning' && soloParentData.employeeAmount) {
        employmentText += ` - ${soloParentData.employeeAmount}`;
    } else if (soloParentData.employmentStatus === 'Self-employed and earning' && soloParentData.selfEmployedAmount) {
        employmentText += ` - ${soloParentData.selfEmployedAmount}`;
    } else if (soloParentData.employmentStatus === 'Un-employed and dependent upon' && soloParentData.unemployedDependent) {
        employmentText += ` - ${soloParentData.unemployedDependent}`;
    }
    document.getElementById('previewEmployment').textContent = employmentText;
    
    document.getElementById('previewSoloDateOfNotary').textContent = soloParentData.dateOfNotary || '-';
}

function viewJointAffidavitData() {
    document.getElementById('previewFirstPersonName').textContent = jointAffidavitData.firstPersonName || '-';
    document.getElementById('previewFirstPersonAddress').textContent = jointAffidavitData.firstPersonAddress || '-';
    document.getElementById('previewSecondPersonName').textContent = jointAffidavitData.secondPersonName || '-';
    document.getElementById('previewSecondPersonAddress').textContent = jointAffidavitData.secondPersonAddress || '-';
    document.getElementById('previewChildName').textContent = jointAffidavitData.childName || '-';
    document.getElementById('previewFatherName').textContent = jointAffidavitData.fatherName || '-';
    document.getElementById('previewMotherName').textContent = jointAffidavitData.motherName || '-';
    document.getElementById('previewDateOfBirth').textContent = jointAffidavitData.dateOfBirth || '-';
    document.getElementById('previewPlaceOfBirth').textContent = jointAffidavitData.placeOfBirth || '-';
    document.getElementById('previewChildNameNumber4').textContent = jointAffidavitData.childNameNumber4 || '-';
    document.getElementById('previewDateOfNotaryJoint').textContent = jointAffidavitData.dateOfNotary || '-';
}

// Edit Functions
function editAffidavitLossData() {
    document.getElementById('affidavitLossForm').style.display = 'block';
    document.getElementById('affidavitLossDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('fullName').value = affidavitLossData.fullName || '';
    document.getElementById('completeAddress').value = affidavitLossData.completeAddress || '';
    document.getElementById('specifyItemLost').value = affidavitLossData.specifyItemLost || '';
    document.getElementById('itemLost').value = affidavitLossData.itemLost || '';
    document.getElementById('itemDetails').value = affidavitLossData.itemDetails || '';
    document.getElementById('dateOfNotary').value = affidavitLossData.dateOfNotary || '';
}

function editSoloParentData() {
    document.getElementById('soloParentForm').style.display = 'block';
    document.getElementById('soloParentDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('soloFullName').value = soloParentData.fullName || '';
    document.getElementById('soloCompleteAddress').value = soloParentData.completeAddress || '';
    document.getElementById('yearsUnderCase').value = soloParentData.yearsUnderCase || '';
    document.getElementById('soloDateOfNotary').value = soloParentData.dateOfNotary || '';
    
    // Handle radio buttons
    if (soloParentData.reasonSection) {
        document.querySelector(`input[name="reasonSection"][value="${soloParentData.reasonSection}"]`).checked = true;
        toggleOtherReason();
        if (soloParentData.reasonSection === 'Other reason, please state') {
            document.getElementById('otherReason').value = soloParentData.otherReason || '';
        }
    }
    
    if (soloParentData.employmentStatus) {
        document.querySelector(`input[name="employmentStatus"][value="${soloParentData.employmentStatus}"]`).checked = true;
        toggleEmploymentFields();
        if (soloParentData.employeeAmount) {
            document.getElementById('employeeAmount').value = soloParentData.employeeAmount;
        }
        if (soloParentData.selfEmployedAmount) {
            document.getElementById('selfEmployedAmount').value = soloParentData.selfEmployedAmount;
        }
        if (soloParentData.unemployedDependent) {
            document.getElementById('unemployedDependent').value = soloParentData.unemployedDependent;
        }
    }
    
    // Handle children
    const childrenNames = soloParentData['childrenNames[]'] || [];
    const childrenAges = soloParentData['childrenAges[]'] || [];
    const container = document.getElementById('childrenContainer');
    container.innerHTML = '';
    
    for (let i = 0; i < childrenNames.length; i++) {
        addChild();
        const entries = container.querySelectorAll('.child-entry');
        const lastEntry = entries[entries.length - 1];
        lastEntry.querySelector('input[name="childrenNames[]"]').value = childrenNames[i];
        lastEntry.querySelector('input[name="childrenAges[]"]').value = childrenAges[i];
    }
}

function editJointAffidavitData() {
    document.getElementById('jointAffidavitForm').style.display = 'block';
    document.getElementById('jointAffidavitDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('firstPersonName').value = jointAffidavitData.firstPersonName || '';
    document.getElementById('firstPersonAddress').value = jointAffidavitData.firstPersonAddress || '';
    document.getElementById('secondPersonName').value = jointAffidavitData.secondPersonName || '';
    document.getElementById('secondPersonAddress').value = jointAffidavitData.secondPersonAddress || '';
    document.getElementById('childName').value = jointAffidavitData.childName || '';
    document.getElementById('fatherName').value = jointAffidavitData.fatherName || '';
    document.getElementById('motherName').value = jointAffidavitData.motherName || '';
    document.getElementById('dateOfBirth').value = jointAffidavitData.dateOfBirth || '';
    document.getElementById('placeOfBirth').value = jointAffidavitData.placeOfBirth || '';
    document.getElementById('childNameNumber4').value = jointAffidavitData.childNameNumber4 || '';
    document.getElementById('dateOfNotaryJoint').value = jointAffidavitData.dateOfNotary || '';
}

// New Document Functions
function newAffidavitLossDocument() {
    affidavitLossData = {};
    document.getElementById('affidavitLossForm').reset();
    document.getElementById('affidavitLossForm').style.display = 'block';
    document.getElementById('affidavitLossDataPreview').style.display = 'none';
}

function newSoloParentDocument() {
    soloParentData = {};
    document.getElementById('soloParentForm').reset();
    document.getElementById('soloParentForm').style.display = 'block';
    document.getElementById('soloParentDataPreview').style.display = 'none';
    
    // Reset children container
    const container = document.getElementById('childrenContainer');
    container.innerHTML = '<div class="child-entry"><input type="text" name="childrenNames[]" placeholder="Child\'s Name" required><input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required><button type="button" onclick="removeChild(this)" class="btn btn-danger btn-sm">Remove</button></div>';
}

function newJointAffidavitDocument() {
    jointAffidavitData = {};
    document.getElementById('jointAffidavitForm').reset();
    document.getElementById('jointAffidavitForm').style.display = 'block';
    document.getElementById('jointAffidavitDataPreview').style.display = 'none';
}

// Generate PDF Functions
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

function generateSoloParentPDF() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        if (key === 'childrenNames[]' || key === 'childrenAges[]') {
            if (!data[key]) {
                data[key] = [];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    }
    
    // Convert children arrays to comma-separated strings
    if (data['childrenNames[]'] && data['childrenAges[]']) {
        data.childrenNames = data['childrenNames[]'].join(',');
        data.childrenAges = data['childrenAges[]'].join(',');
        delete data['childrenNames[]'];
        delete data['childrenAges[]'];
    }
    
    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.yearsUnderCase || !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    // Validate employment fields based on selection
    if (data.employmentStatus === 'Employee and earning' && !data.employeeAmount) {
        alert('Please enter monthly amount for employee.');
        return;
    } else if (data.employmentStatus === 'Self-employed and earning' && !data.selfEmployedAmount) {
        alert('Please enter monthly amount for self-employed.');
        return;
    } else if (data.employmentStatus === 'Un-employed and dependent upon' && !data.unemployedDependent) {
        alert('Please enter dependent upon information.');
        return;
    }
    
    generatePDF('soloParent', data);
}

function generateJointAffidavitPDF() {
    const form = document.getElementById('jointAffidavitForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Validate required fields
    if (!data.firstPersonName || !data.firstPersonAddress || !data.secondPersonName || 
        !data.secondPersonAddress || !data.childName || !data.fatherName || 
        !data.motherName || !data.dateOfBirth || !data.placeOfBirth || 
        !data.childNameNumber4 || !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    generatePDF('jointAffidavit', data);
}

// Helper Functions
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

function toggleOtherReason() {
    const otherReasonRadio = document.querySelector('input[name="reasonSection"][value="Other reason, please state"]');
    const otherReasonContainer = document.getElementById('otherReasonContainer');
    
    if (otherReasonRadio && otherReasonContainer) {
        otherReasonContainer.style.display = otherReasonRadio.checked ? 'block' : 'none';
    }
}

function toggleEmploymentFields() {
    const employmentRadios = document.querySelectorAll('input[name="employmentStatus"]');
    const employeeAmountContainer = document.getElementById('employeeAmountContainer');
    const selfEmployedAmountContainer = document.getElementById('selfEmployedAmountContainer');
    const unemployedDependentContainer = document.getElementById('unemployedDependentContainer');
    
    // Hide all containers first
    if (employeeAmountContainer) employeeAmountContainer.style.display = 'none';
    if (selfEmployedAmountContainer) selfEmployedAmountContainer.style.display = 'none';
    if (unemployedDependentContainer) unemployedDependentContainer.style.display = 'none';
    
    // Show appropriate container based on selection
    employmentRadios.forEach(radio => {
        if (radio.checked) {
            if (radio.value === 'Employee and earning' && employeeAmountContainer) {
                employeeAmountContainer.style.display = 'block';
            } else if (radio.value === 'Self-employed and earning' && selfEmployedAmountContainer) {
                selfEmployedAmountContainer.style.display = 'block';
            } else if (radio.value === 'Un-employed and dependent upon' && unemployedDependentContainer) {
                unemployedDependentContainer.style.display = 'block';
            }
        }
    });
}

// Additional Save Functions
function saveSeniorIDLoss() {
    const form = document.getElementById('seniorIDLossForm');
    const formData = new FormData(form);
    
    seniorIDLossData = {};
    for (let [key, value] of formData.entries()) {
        seniorIDLossData[key] = value;
    }
    
    // Show preview
    document.getElementById('seniorIDLossForm').style.display = 'none';
    document.getElementById('seniorIDLossDataPreview').style.display = 'block';
    viewSeniorIDLossData();
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

function saveSwornAffidavitMother() {
    const form = document.getElementById('swornAffidavitMotherForm');
    const formData = new FormData(form);
    
    swornAffidavitMotherData = {};
    for (let [key, value] of formData.entries()) {
        swornAffidavitMotherData[key] = value;
    }
    
    // Show preview
    document.getElementById('swornAffidavitMotherForm').style.display = 'none';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'block';
    viewSwornAffidavitMotherData();
}

// Additional View Data Functions
function viewSeniorIDLossData() {
    document.getElementById('previewSeniorFullName').textContent = seniorIDLossData.fullName || '-';
    document.getElementById('previewSeniorCompleteAddress').textContent = seniorIDLossData.completeAddress || '-';
    document.getElementById('previewRelationship').textContent = seniorIDLossData.relationship || '-';
    document.getElementById('previewSeniorCitizenName').textContent = seniorIDLossData.seniorCitizenName || '-';
    document.getElementById('previewSeniorDateOfNotary').textContent = seniorIDLossData.dateOfNotary || '-';
}

function viewPWDLossData() {
    document.getElementById('previewPWDFullName').textContent = pwdLossData.fullName || '-';
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

function viewSwornAffidavitMotherData() {
    document.getElementById('previewMotherFullName').textContent = swornAffidavitMotherData.fullName || '-';
    document.getElementById('previewMotherCompleteAddress').textContent = swornAffidavitMotherData.completeAddress || '-';
    document.getElementById('previewMotherChildName').textContent = swornAffidavitMotherData.childName || '-';
    document.getElementById('previewBirthDate').textContent = swornAffidavitMotherData.birthDate || '-';
    document.getElementById('previewBirthPlace').textContent = swornAffidavitMotherData.birthPlace || '-';
    document.getElementById('previewMotherDateOfNotary').textContent = swornAffidavitMotherData.dateOfNotary || '-';
}

// Additional Edit Functions
function editSeniorIDLossData() {
    document.getElementById('seniorIDLossForm').style.display = 'block';
    document.getElementById('seniorIDLossDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('seniorFullName').value = seniorIDLossData.fullName || '';
    document.getElementById('seniorCompleteAddress').value = seniorIDLossData.completeAddress || '';
    document.getElementById('relationship').value = seniorIDLossData.relationship || '';
    document.getElementById('seniorCitizenName').value = seniorIDLossData.seniorCitizenName || '';
    document.getElementById('seniorDateOfNotary').value = seniorIDLossData.dateOfNotary || '';
}

function editPWDLossData() {
    document.getElementById('pwdLossForm').style.display = 'block';
    document.getElementById('pwdLossDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('pwdFullName').value = pwdLossData.fullName || '';
    document.getElementById('pwdFullAddress').value = pwdLossData.fullAddress || '';
    document.getElementById('pwdDetailsOfLoss').value = pwdLossData.detailsOfLoss || '';
    document.getElementById('pwdDateOfNotary').value = pwdLossData.dateOfNotary || '';
}

function editBoticabLossData() {
    document.getElementById('boticabLossForm').style.display = 'block';
    document.getElementById('boticabLossDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('boticabFullName').value = boticabLossData.fullName || '';
    document.getElementById('boticabFullAddress').value = boticabLossData.fullAddress || '';
    document.getElementById('boticabDetailsOfLoss').value = boticabLossData.detailsOfLoss || '';
    document.getElementById('boticabDateOfNotary').value = boticabLossData.dateOfNotary || '';
}

function editSwornAffidavitMotherData() {
    document.getElementById('swornAffidavitMotherForm').style.display = 'block';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('motherFullName').value = swornAffidavitMotherData.fullName || '';
    document.getElementById('motherCompleteAddress').value = swornAffidavitMotherData.completeAddress || '';
    document.getElementById('motherChildName').value = swornAffidavitMotherData.childName || '';
    document.getElementById('birthDate').value = swornAffidavitMotherData.birthDate || '';
    document.getElementById('birthPlace').value = swornAffidavitMotherData.birthPlace || '';
    document.getElementById('motherDateOfNotary').value = swornAffidavitMotherData.dateOfNotary || '';
}

// Additional New Document Functions
function newSeniorIDLossDocument() {
    seniorIDLossData = {};
    document.getElementById('seniorIDLossForm').reset();
    document.getElementById('seniorIDLossForm').style.display = 'block';
    document.getElementById('seniorIDLossDataPreview').style.display = 'none';
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

function newSwornAffidavitMotherDocument() {
    swornAffidavitMotherData = {};
    document.getElementById('swornAffidavitMotherForm').reset();
    document.getElementById('swornAffidavitMotherForm').style.display = 'block';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
}

// Additional Generate PDF Functions
function generateSeniorIDLossPDF() {
    const params = new URLSearchParams(seniorIDLossData);
    window.open(`files-generation/generate_affidavit_of_loss_senior_id.php?${params.toString()}`, '_blank');
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

// Joint Affidavit Solo Parent Functions
function saveJointAffidavitSoloParent() {
    const form = document.getElementById('jointAffidavitSoloParentForm');
    const formData = new FormData(form);
    
    jointAffidavitSoloParentData = {};
    for (let [key, value] of formData.entries()) {
        if (key.includes('[]')) {
            // Handle array fields like childrenNames[] and childrenAges[]
            if (!jointAffidavitSoloParentData[key]) {
                jointAffidavitSoloParentData[key] = [];
            }
            jointAffidavitSoloParentData[key].push(value);
        } else {
        jointAffidavitSoloParentData[key] = value;
        }
    }
    
    // Show preview
    document.getElementById('jointAffidavitSoloParentForm').style.display = 'none';
    document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'block';
    viewJointAffidavitSoloParentData();
}

function viewJointAffidavitSoloParentData() {
    document.getElementById('previewAffiant1Name').textContent = jointAffidavitSoloParentData.affiant1Name || '-';
    document.getElementById('previewAffiant2Name').textContent = jointAffidavitSoloParentData.affiant2Name || '-';
    document.getElementById('previewAffiantsAddress').textContent = jointAffidavitSoloParentData.affiantsAddress || '-';
    document.getElementById('previewSoloParentName').textContent = jointAffidavitSoloParentData.soloParentName || '-';
    document.getElementById('previewSoloParentAddress').textContent = jointAffidavitSoloParentData.soloParentAddress || '-';
    
    // Handle children
    const names = jointAffidavitSoloParentData['childrenNames[]'] || [];
    const ages = jointAffidavitSoloParentData['childrenAges[]'] || [];
    const children = names.map((name, index) => {
        const age = ages[index] || '';
        return name ? `${name} (Age: ${age})` : '';
    }).filter(child => child !== '');
    document.getElementById('previewChildrenSoloParent').textContent = children.length > 0 ? children.join(', ') : '-';
    
    document.getElementById('previewAffiant1ValidId').textContent = jointAffidavitSoloParentData.affiant1ValidId || '-';
    document.getElementById('previewAffiant2ValidId').textContent = jointAffidavitSoloParentData.affiant2ValidId || '-';
    document.getElementById('previewJointSoloParentDateOfNotary').textContent = jointAffidavitSoloParentData.dateOfNotary || '-';
}

function editJointAffidavitSoloParentData() {
    document.getElementById('jointAffidavitSoloParentForm').style.display = 'block';
    document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'none';
    
    // Populate form with saved data
    document.getElementById('affiant1Name').value = jointAffidavitSoloParentData.affiant1Name || '';
    document.getElementById('affiant2Name').value = jointAffidavitSoloParentData.affiant2Name || '';
    document.getElementById('affiantsAddress').value = jointAffidavitSoloParentData.affiantsAddress || '';
    document.getElementById('soloParentName').value = jointAffidavitSoloParentData.soloParentName || '';
    document.getElementById('soloParentAddress').value = jointAffidavitSoloParentData.soloParentAddress || '';
    document.getElementById('affiant1ValidId').value = jointAffidavitSoloParentData.affiant1ValidId || '';
    document.getElementById('affiant2ValidId').value = jointAffidavitSoloParentData.affiant2ValidId || '';
    document.getElementById('jointSoloParentDateOfNotary').value = jointAffidavitSoloParentData.dateOfNotary || '';
    
    // Handle children - clear existing and repopulate
    const container = document.getElementById('childrenContainerSoloParent');
    container.innerHTML = '';
    
    const names = jointAffidavitSoloParentData['childrenNames[]'] || [];
    const ages = jointAffidavitSoloParentData['childrenAges[]'] || [];
    
    if (names.length > 0) {
        names.forEach((name, index) => {
            const age = ages[index] || '';
            const childEntry = document.createElement('div');
            childEntry.className = 'child-entry';
            childEntry.innerHTML = `
                <input type="text" name="childrenNames[]" placeholder="Child's Full Name" value="${name}" required>
                <input type="number" name="childrenAges[]" placeholder="Age" value="${age}" min="0" max="120" required>
                <button type="button" onclick="removeChildSoloParent(this)" class="btn btn-danger btn-sm">Remove</button>
            `;
            container.appendChild(childEntry);
        });
    } else {
        // Add default empty entry
        const childEntry = document.createElement('div');
        childEntry.className = 'child-entry';
        childEntry.innerHTML = `
            <input type="text" name="childrenNames[]" placeholder="Child's Full Name" required>
            <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
            <button type="button" onclick="removeChildSoloParent(this)" class="btn btn-danger btn-sm">Remove</button>
        `;
        container.appendChild(childEntry);
    }
}

function newJointAffidavitSoloParentDocument() {
    jointAffidavitSoloParentData = {};
    document.getElementById('jointAffidavitSoloParentForm').reset();
    document.getElementById('jointAffidavitSoloParentForm').style.display = 'block';
    document.getElementById('jointAffidavitSoloParentDataPreview').style.display = 'none';
    
    // Reset children container
    const container = document.getElementById('childrenContainerSoloParent');
    container.innerHTML = `
        <div class="child-entry">
            <input type="text" name="childrenNames[]" placeholder="Child's Full Name" required>
            <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
            <button type="button" onclick="removeChildSoloParent(this)" class="btn btn-danger btn-sm">Remove</button>
        </div>
    `;
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
    const container = document.getElementById('childrenContainerSoloParent');
    if (container.children.length > 1) {
        button.parentElement.remove();
    } else {
        alert('At least one child entry is required.');
    }
}

function generateJointAffidavitSoloParentPDF() {
    const form = document.getElementById('jointAffidavitSoloParentForm');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Validate required fields
    if (!data.affiant1Name || !data.affiant2Name || !data.affiantsAddress || 
        !data.soloParentName || !data.soloParentAddress || !data.affiant1ValidId || 
        !data.affiant2ValidId || !data.dateOfNotary) {
        alert('Please fill in all required fields before generating PDF.');
        return;
    }
    
    // Check if at least one child is provided
    const childrenNames = data['childrenNames[]'] || [];
    if (!childrenNames || childrenNames.length === 0 || !childrenNames[0]) {
        alert('Please add at least one child before generating PDF.');
        return;
    }
    
    generatePDF('jointAffidavitSoloParent', data);
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
    
    // Submit to attorney document handler
    console.log('Submitting to attorney_document_handler.php');
    fetch('attorney_document_handler.php', {
        method: 'POST',
        body: submitData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        if (response.ok) {
            // If response is a PDF file, trigger download
            return response.blob();
        } else {
            console.error('Response not OK:', response.status, response.statusText);
            return response.text().then(text => {
                console.error('Error response:', text);
                throw new Error('Failed to generate PDF: ' + text);
            });
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
        
        // Reset button state
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    })
    .catch(error => {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please try again.');
        
        // Reset button state
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}
