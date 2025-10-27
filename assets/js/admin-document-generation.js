// Admin Document Generation JavaScript
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
}

function openSwornAffidavitSoloParentModal() {
    document.getElementById('soloParentModal').style.display = 'block';
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

function closeSwornAffidavitSoloParentModal() {
    document.getElementById('soloParentModal').style.display = 'none';
}

// Senior ID Loss Modal Functions
function openSeniorIDLossModal() {
    document.getElementById('seniorIDLossModal').style.display = 'block';
    currentDocumentType = 'seniorIDLoss';
    
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
}

function saveSeniorIDLoss() {
    const form = document.getElementById('seniorIDLossForm');
    const formData = new FormData(form);
    
    seniorIDLossData = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        relationship: formData.get('relationship'),
        seniorCitizenName: formData.get('seniorCitizenName'),
        seniorCitizenAddress: formData.get('seniorCitizenAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    document.getElementById('seniorIDLossForm').style.display = 'none';
    document.getElementById('seniorIDLossDataPreview').style.display = 'block';
    viewSeniorIDLossData();
}

function viewSeniorIDLossData() {
    document.getElementById('previewSeniorFullName').textContent = seniorIDLossData.fullName || '-';
    document.getElementById('previewSeniorCompleteAddress').textContent = seniorIDLossData.completeAddress || '-';
    document.getElementById('previewSeniorRelationship').textContent = seniorIDLossData.relationship || '-';
    document.getElementById('previewSeniorCitizenName').textContent = seniorIDLossData.seniorCitizenName || '-';
    document.getElementById('previewSeniorCitizenAddress').textContent = seniorIDLossData.seniorCitizenAddress || '-';
    document.getElementById('previewSeniorDetailsOfLoss').textContent = seniorIDLossData.detailsOfLoss || '-';
    document.getElementById('previewSeniorDateOfNotary').textContent = seniorIDLossData.dateOfNotary || '-';
}

function editSeniorIDLossData() {
    document.getElementById('seniorIDLossForm').style.display = 'block';
    document.getElementById('seniorIDLossDataPreview').style.display = 'none';
    
    document.getElementById('seniorFullName').value = seniorIDLossData.fullName || '';
    document.getElementById('seniorCompleteAddress').value = seniorIDLossData.completeAddress || '';
    document.getElementById('seniorRelationship').value = seniorIDLossData.relationship || '';
    document.getElementById('seniorCitizenName').value = seniorIDLossData.seniorCitizenName || '';
    document.getElementById('seniorCitizenAddress').value = seniorIDLossData.seniorCitizenAddress || '';
    document.getElementById('seniorDetailsOfLoss').value = seniorIDLossData.detailsOfLoss || '';
    document.getElementById('seniorDateOfNotary').value = seniorIDLossData.dateOfNotary || '';
}

function newSeniorIDLossDocument() {
    seniorIDLossData = {};
    document.getElementById('seniorIDLossForm').reset();
    document.getElementById('seniorIDLossForm').style.display = 'block';
    document.getElementById('seniorIDLossDataPreview').style.display = 'none';
}

function generateSeniorIDLossPDF() {
    if (Object.keys(seniorIDLossData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    const params = new URLSearchParams(seniorIDLossData);
    const url = `files-generation/generate_affidavit_of_loss_senior_id.php?${params.toString()}`;
    window.open(url, '_blank');
}

// PWD Loss Modal Functions
function openPWDLossModal() {
    document.getElementById('pwdLossModal').style.display = 'block';
    currentDocumentType = 'pwdLoss';
    
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
}

function savePWDLoss() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    
    pwdLossData = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        relationship: formData.get('relationship'),
        pwdName: formData.get('pwdName'),
        pwdAddress: formData.get('pwdAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    document.getElementById('pwdLossForm').style.display = 'none';
    document.getElementById('pwdLossDataPreview').style.display = 'block';
    viewPWDLossData();
}

function viewPWDLossData() {
    document.getElementById('previewPwdFullName').textContent = pwdLossData.fullName || '-';
    document.getElementById('previewPwdCompleteAddress').textContent = pwdLossData.completeAddress || '-';
    document.getElementById('previewPwdRelationship').textContent = pwdLossData.relationship || '-';
    document.getElementById('previewPwdName').textContent = pwdLossData.pwdName || '-';
    document.getElementById('previewPwdAddress').textContent = pwdLossData.pwdAddress || '-';
    document.getElementById('previewPwdDetailsOfLoss').textContent = pwdLossData.detailsOfLoss || '-';
    document.getElementById('previewPwdDateOfNotary').textContent = pwdLossData.dateOfNotary || '-';
}

function editPWDLossData() {
    document.getElementById('pwdLossForm').style.display = 'block';
    document.getElementById('pwdLossDataPreview').style.display = 'none';
    
    document.getElementById('pwdFullName').value = pwdLossData.fullName || '';
    document.getElementById('pwdCompleteAddress').value = pwdLossData.completeAddress || '';
    document.getElementById('pwdRelationship').value = pwdLossData.relationship || '';
    document.getElementById('pwdName').value = pwdLossData.pwdName || '';
    document.getElementById('pwdAddress').value = pwdLossData.pwdAddress || '';
    document.getElementById('pwdDetailsOfLoss').value = pwdLossData.detailsOfLoss || '';
    document.getElementById('pwdDateOfNotary').value = pwdLossData.dateOfNotary || '';
}

function newPWDLossDocument() {
    pwdLossData = {};
    document.getElementById('pwdLossForm').reset();
    document.getElementById('pwdLossForm').style.display = 'block';
    document.getElementById('pwdLossDataPreview').style.display = 'none';
}

function generatePWDLossPDF() {
    if (Object.keys(pwdLossData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    const params = new URLSearchParams(pwdLossData);
    const url = `files-generation/generate_affidavit_of_loss_pwd_id.php?${params.toString()}`;
    window.open(url, '_blank');
}

// Boticab Loss Modal Functions
function openBoticabLossModal() {
    document.getElementById('boticabLossModal').style.display = 'block';
    currentDocumentType = 'boticabLoss';
    
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
}

function saveBoticabLoss() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    
    boticabLossData = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    document.getElementById('boticabLossForm').style.display = 'none';
    document.getElementById('boticabLossDataPreview').style.display = 'block';
    viewBoticabLossData();
}

function viewBoticabLossData() {
    document.getElementById('previewBoticabFullName').textContent = boticabLossData.fullName || '-';
    document.getElementById('previewBoticabCompleteAddress').textContent = boticabLossData.completeAddress || '-';
    document.getElementById('previewBoticabDetailsOfLoss').textContent = boticabLossData.detailsOfLoss || '-';
    document.getElementById('previewBoticabDateOfNotary').textContent = boticabLossData.dateOfNotary || '-';
}

function editBoticabLossData() {
    document.getElementById('boticabLossForm').style.display = 'block';
    document.getElementById('boticabLossDataPreview').style.display = 'none';
    
    document.getElementById('boticabFullName').value = boticabLossData.fullName || '';
    document.getElementById('boticabCompleteAddress').value = boticabLossData.completeAddress || '';
    document.getElementById('boticabDetailsOfLoss').value = boticabLossData.detailsOfLoss || '';
    document.getElementById('boticabDateOfNotary').value = boticabLossData.dateOfNotary || '';
}

function newBoticabLossDocument() {
    boticabLossData = {};
    document.getElementById('boticabLossForm').reset();
    document.getElementById('boticabLossForm').style.display = 'block';
    document.getElementById('boticabLossDataPreview').style.display = 'none';
}

function generateBoticabLossPDF() {
    if (Object.keys(boticabLossData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    const params = new URLSearchParams(boticabLossData);
    const url = `files-generation/generate_affidavit_of_loss_boticab.php?${params.toString()}`;
    window.open(url, '_blank');
}

// Sworn Affidavit of Mother Modal Functions
function openSwornAffidavitMotherModal() {
    document.getElementById('swornAffidavitMotherModal').style.display = 'block';
    currentDocumentType = 'swornAffidavitMother';
    
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
}

function saveSwornAffidavitMother() {
    const form = document.getElementById('swornAffidavitMotherForm');
    const formData = new FormData(form);
    
    swornAffidavitMotherData = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        childName: formData.get('childName'),
        childBirthDate: formData.get('childBirthDate'),
        birthPlace: formData.get('birthPlace'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    document.getElementById('swornAffidavitMotherForm').style.display = 'none';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'block';
    viewSwornAffidavitMotherData();
}

function viewSwornAffidavitMotherData() {
    document.getElementById('previewMotherFullName').textContent = swornAffidavitMotherData.fullName || '-';
    document.getElementById('previewMotherCompleteAddress').textContent = swornAffidavitMotherData.completeAddress || '-';
    document.getElementById('previewMotherChildName').textContent = swornAffidavitMotherData.childName || '-';
    document.getElementById('previewMotherChildBirthDate').textContent = swornAffidavitMotherData.childBirthDate || '-';
    document.getElementById('previewMotherBirthPlace').textContent = swornAffidavitMotherData.birthPlace || '-';
    document.getElementById('previewMotherDateOfNotary').textContent = swornAffidavitMotherData.dateOfNotary || '-';
}

function editSwornAffidavitMotherData() {
    document.getElementById('swornAffidavitMotherForm').style.display = 'block';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
    
    document.getElementById('motherFullName').value = swornAffidavitMotherData.fullName || '';
    document.getElementById('motherCompleteAddress').value = swornAffidavitMotherData.completeAddress || '';
    document.getElementById('motherChildName').value = swornAffidavitMotherData.childName || '';
    document.getElementById('motherChildBirthDate').value = swornAffidavitMotherData.childBirthDate || '';
    document.getElementById('motherBirthPlace').value = swornAffidavitMotherData.birthPlace || '';
    document.getElementById('motherDateOfNotary').value = swornAffidavitMotherData.dateOfNotary || '';
}

function newSwornAffidavitMotherDocument() {
    swornAffidavitMotherData = {};
    document.getElementById('swornAffidavitMotherForm').reset();
    document.getElementById('swornAffidavitMotherForm').style.display = 'block';
    document.getElementById('swornAffidavitMotherDataPreview').style.display = 'none';
}

function generateSwornAffidavitMotherPDF() {
    if (Object.keys(swornAffidavitMotherData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    const params = new URLSearchParams(swornAffidavitMotherData);
    const url = `files-generation/generate_sworn_affidavit_of_mother_simple.php?${params.toString()}`;
    window.open(url, '_blank');
}

// Joint Affidavit Modal Functions
function openJointAffidavitModal() {
    document.getElementById('jointAffidavitModal').style.display = 'block';
    currentDocumentType = 'jointAffidavit';
    
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
}

function saveJointAffidavit() {
    const form = document.getElementById('jointAffidavitForm');
    const formData = new FormData(form);
    
    jointAffidavitData = {
        firstPersonName: formData.get('firstPersonName'),
        secondPersonName: formData.get('secondPersonName'),
        firstPersonAddress: formData.get('firstPersonAddress'),
        secondPersonAddress: formData.get('secondPersonAddress'),
        childName: formData.get('childName'),
        fatherName: formData.get('fatherName'),
        motherName: formData.get('motherName'),
        dateOfBirth: formData.get('dateOfBirth'),
        placeOfBirth: formData.get('placeOfBirth'),
        childNameNumber4: formData.get('childNameNumber4'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    document.getElementById('jointAffidavitForm').style.display = 'none';
    document.getElementById('jointAffidavitDataPreview').style.display = 'block';
    viewJointAffidavitData();
}

function viewJointAffidavitData() {
    document.getElementById('previewFirstPersonName').textContent = jointAffidavitData.firstPersonName || '-';
    document.getElementById('previewSecondPersonName').textContent = jointAffidavitData.secondPersonName || '-';
    document.getElementById('previewFirstPersonAddress').textContent = jointAffidavitData.firstPersonAddress || '-';
    document.getElementById('previewSecondPersonAddress').textContent = jointAffidavitData.secondPersonAddress || '-';
    document.getElementById('previewChildName').textContent = jointAffidavitData.childName || '-';
    document.getElementById('previewFatherName').textContent = jointAffidavitData.fatherName || '-';
    document.getElementById('previewMotherName').textContent = jointAffidavitData.motherName || '-';
    document.getElementById('previewDateOfBirth').textContent = jointAffidavitData.dateOfBirth || '-';
    document.getElementById('previewPlaceOfBirth').textContent = jointAffidavitData.placeOfBirth || '-';
    document.getElementById('previewChildNameNumber4').textContent = jointAffidavitData.childNameNumber4 || '-';
    document.getElementById('previewDateOfNotary').textContent = jointAffidavitData.dateOfNotary || '-';
}

function editJointAffidavitData() {
    document.getElementById('jointAffidavitForm').style.display = 'block';
    document.getElementById('jointAffidavitDataPreview').style.display = 'none';
    
    document.getElementById('firstPersonName').value = jointAffidavitData.firstPersonName || '';
    document.getElementById('secondPersonName').value = jointAffidavitData.secondPersonName || '';
    document.getElementById('firstPersonAddress').value = jointAffidavitData.firstPersonAddress || '';
    document.getElementById('secondPersonAddress').value = jointAffidavitData.secondPersonAddress || '';
    document.getElementById('childName').value = jointAffidavitData.childName || '';
    document.getElementById('fatherName').value = jointAffidavitData.fatherName || '';
    document.getElementById('motherName').value = jointAffidavitData.motherName || '';
    document.getElementById('dateOfBirth').value = jointAffidavitData.dateOfBirth || '';
    document.getElementById('placeOfBirth').value = jointAffidavitData.placeOfBirth || '';
    document.getElementById('childNameNumber4').value = jointAffidavitData.childNameNumber4 || '';
    document.getElementById('dateOfNotary').value = jointAffidavitData.dateOfNotary || '';
}

function newJointAffidavitDocument() {
    jointAffidavitData = {};
    document.getElementById('jointAffidavitForm').reset();
    document.getElementById('jointAffidavitForm').style.display = 'block';
    document.getElementById('jointAffidavitDataPreview').style.display = 'none';
}

function generateJointAffidavitPDF() {
    if (Object.keys(jointAffidavitData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    const params = new URLSearchParams(jointAffidavitData);
    const url = `files-generation/generate_joint_affidavit_two-disinterested-person.php?${params.toString()}`;
    window.open(url, '_blank');
}

// Form Functions
function saveAffidavitLoss() {
    const form = document.getElementById('affidavitLossForm');
    const formData = new FormData(form);
    
    affidavitLossData = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        specifyItemLost: formData.get('specifyItemLost'),
        itemLost: formData.get('itemLost'),
        itemDetails: formData.get('itemDetails'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    // Hide form and show preview
    document.getElementById('affidavitLossForm').style.display = 'none';
    document.getElementById('affidavitLossDataPreview').style.display = 'block';
    
    viewAffidavitLossData();
}

function viewAffidavitLossData() {
    document.getElementById('previewLossFullName').textContent = affidavitLossData.fullName || '-';
    document.getElementById('previewLossCompleteAddress').textContent = affidavitLossData.completeAddress || '-';
    document.getElementById('previewLossSpecifyItemLost').textContent = affidavitLossData.specifyItemLost || '-';
    document.getElementById('previewLossItemLost').textContent = affidavitLossData.itemLost || '-';
    document.getElementById('previewLossItemDetails').textContent = affidavitLossData.itemDetails || '-';
    document.getElementById('previewLossDateOfNotary').textContent = affidavitLossData.dateOfNotary || '-';
}

function editAffidavitLossData() {
    // Show form and hide preview
    document.getElementById('affidavitLossForm').style.display = 'block';
    document.getElementById('affidavitLossDataPreview').style.display = 'none';
    
    // Populate form with existing data
    document.getElementById('lossFullName').value = affidavitLossData.fullName || '';
    document.getElementById('lossCompleteAddress').value = affidavitLossData.completeAddress || '';
    document.getElementById('lossSpecifyItemLost').value = affidavitLossData.specifyItemLost || '';
    document.getElementById('lossItemLost').value = affidavitLossData.itemLost || '';
    document.getElementById('lossItemDetails').value = affidavitLossData.itemDetails || '';
    document.getElementById('lossDateOfNotary').value = affidavitLossData.dateOfNotary || '';
}

function newAffidavitLossDocument() {
    // Clear data and show form
    affidavitLossData = {};
    document.getElementById('affidavitLossForm').reset();
    document.getElementById('affidavitLossForm').style.display = 'block';
    document.getElementById('affidavitLossDataPreview').style.display = 'none';
}

function generateAffidavitLossPDF() {
    if (Object.keys(affidavitLossData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    // Create URL with parameters
    const params = new URLSearchParams(affidavitLossData);
    const url = `files-generation/generate_affidavit_of_loss.php?${params.toString()}`;
    
    // Open in new window for PDF download
    window.open(url, '_blank');
}

// Solo Parent Functions
function saveSoloParent() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    
    // Get children data
    const childrenNames = formData.getAll('childrenNames[]');
    const childrenAges = formData.getAll('childrenAges[]');
    
    soloParentData = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        childrenNames: childrenNames,
        childrenAges: childrenAges,
        yearsUnderCase: formData.get('yearsUnderCase'),
        reasonSection: formData.get('reasonSection'),
        otherReason: formData.get('otherReason'),
        employmentStatus: formData.get('employmentStatus'),
        employeeAmount: formData.get('employeeAmount'),
        selfEmployedAmount: formData.get('selfEmployedAmount'),
        unemployedDependent: formData.get('unemployedDependent'),
        dateOfNotary: formData.get('dateOfNotary')
    };
    
    // Hide form and show preview
    document.getElementById('soloParentForm').style.display = 'none';
    document.getElementById('soloParentDataPreview').style.display = 'block';
    
    viewSoloParentData();
}

function viewSoloParentData() {
    document.getElementById('previewSoloParentFullName').textContent = soloParentData.fullName || '-';
    document.getElementById('previewSoloParentCompleteAddress').textContent = soloParentData.completeAddress || '-';
    
    // Format children data
    let childrenText = '-';
    if (soloParentData.childrenNames && soloParentData.childrenNames.length > 0) {
        childrenText = soloParentData.childrenNames.map((name, index) => {
            const age = soloParentData.childrenAges[index] || '';
            return `${name} (${age} years old)`;
        }).join(', ');
    }
    document.getElementById('previewSoloParentChildren').textContent = childrenText;
    
    document.getElementById('previewSoloParentYearsUnderCase').textContent = soloParentData.yearsUnderCase || '-';
    document.getElementById('previewSoloParentReasonSection').textContent = soloParentData.reasonSection || '-';
    document.getElementById('previewSoloParentEmploymentStatus').textContent = soloParentData.employmentStatus || '-';
    document.getElementById('previewSoloParentDateOfNotary').textContent = soloParentData.dateOfNotary || '-';
}

function editSoloParentData() {
    // Show form and hide preview
    document.getElementById('soloParentForm').style.display = 'block';
    document.getElementById('soloParentDataPreview').style.display = 'none';
    
    // Populate form with existing data
    document.getElementById('soloParentFullName').value = soloParentData.fullName || '';
    document.getElementById('soloParentCompleteAddress').value = soloParentData.completeAddress || '';
    document.getElementById('soloParentYearsUnderCase').value = soloParentData.yearsUnderCase || '';
    document.getElementById('soloParentDateOfNotary').value = soloParentData.dateOfNotary || '';
    
    // Set radio buttons
    if (soloParentData.reasonSection) {
        const reasonRadio = document.querySelector(`input[name="reasonSection"][value="${soloParentData.reasonSection}"]`);
        if (reasonRadio) reasonRadio.checked = true;
    }
    
    if (soloParentData.employmentStatus) {
        const employmentRadio = document.querySelector(`input[name="employmentStatus"][value="${soloParentData.employmentStatus}"]`);
        if (employmentRadio) employmentRadio.checked = true;
    }
    
    // Set other fields
    document.getElementById('otherReason').value = soloParentData.otherReason || '';
    document.getElementById('employeeAmount').value = soloParentData.employeeAmount || '';
    document.getElementById('selfEmployedAmount').value = soloParentData.selfEmployedAmount || '';
    document.getElementById('unemployedDependent').value = soloParentData.unemployedDependent || '';
    
    // Trigger conditional field updates
    toggleOtherReason();
    toggleEmploymentFields();
}

function newSoloParentDocument() {
    // Clear data and show form
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

function generateSoloParentPDF() {
    if (Object.keys(soloParentData).length === 0) {
        alert('Please fill up the form first!');
        return;
    }
    
    // Create URL with parameters
    const params = new URLSearchParams();
    
    // Add basic fields
    params.append('fullName', soloParentData.fullName || '');
    params.append('completeAddress', soloParentData.completeAddress || '');
    params.append('yearsUnderCase', soloParentData.yearsUnderCase || '');
    params.append('reasonSection', soloParentData.reasonSection || '');
    params.append('otherReason', soloParentData.otherReason || '');
    params.append('employmentStatus', soloParentData.employmentStatus || '');
    params.append('employeeAmount', soloParentData.employeeAmount || '');
    params.append('selfEmployedAmount', soloParentData.selfEmployedAmount || '');
    params.append('unemployedDependent', soloParentData.unemployedDependent || '');
    params.append('dateOfNotary', soloParentData.dateOfNotary || '');
    
    // Add children arrays
    if (soloParentData.childrenNames) {
        soloParentData.childrenNames.forEach(name => {
            params.append('childrenNames[]', name);
        });
    }
    if (soloParentData.childrenAges) {
        soloParentData.childrenAges.forEach(age => {
            params.append('childrenAges[]', age);
        });
    }
    
    const url = `files-generation/generate_sworn_affidavit_of_solo_parent.php?${params.toString()}`;
    
    // Open in new window for PDF download
    window.open(url, '_blank');
}

// Children Management Functions
function addChild() {
    const container = document.getElementById('childrenContainer');
    const newChildEntry = document.createElement('div');
    newChildEntry.className = 'child-entry';
    newChildEntry.innerHTML = `
        <input type="text" name="childrenNames[]" placeholder="Child's Name" required>
        <input type="number" name="childrenAges[]" placeholder="Age" min="0" max="120" required>
        <button type="button" onclick="removeChild(this)" class="btn btn-danger btn-sm">Remove</button>
    `;
    container.appendChild(newChildEntry);
}

function removeChild(button) {
    const container = document.getElementById('childrenContainer');
    if (container.children.length > 1) {
        button.parentElement.remove();
    } else {
        alert('At least one child entry is required.');
    }
}

// Conditional Field Functions
function toggleOtherReason() {
    const otherReasonRadio = document.querySelector('input[name="reasonSection"][value="Other reason, please state"]');
    const otherReasonContainer = document.getElementById('otherReasonContainer');
    
    if (otherReasonRadio && otherReasonRadio.checked) {
        otherReasonContainer.style.display = 'block';
    } else {
        otherReasonContainer.style.display = 'none';
    }
}

function toggleEmploymentFields() {
    const employmentStatus = document.querySelector('input[name="employmentStatus"]:checked');
    const employeeAmountContainer = document.getElementById('employeeAmountContainer');
    const selfEmployedAmountContainer = document.getElementById('selfEmployedAmountContainer');
    const unemployedDependentContainer = document.getElementById('unemployedDependentContainer');
    
    // Hide all containers first
    employeeAmountContainer.style.display = 'none';
    selfEmployedAmountContainer.style.display = 'none';
    unemployedDependentContainer.style.display = 'none';
    
    // Show relevant container based on selection
    if (employmentStatus) {
        switch(employmentStatus.value) {
            case 'Employee and earning':
                employeeAmountContainer.style.display = 'block';
                break;
            case 'Self-employed and earning':
                selfEmployedAmountContainer.style.display = 'block';
                break;
            case 'Un-employed and dependent upon':
                unemployedDependentContainer.style.display = 'block';
                break;
        }
    }
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

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}
