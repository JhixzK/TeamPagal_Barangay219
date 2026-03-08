/**
 * Resident Household Information JavaScript
 * Handles CRUD operations, form validation, and UI interactions
 */

// Global variables
let currentMemberId = null;
let isEditMode = false;

// DOM Elements
const memberModal = document.getElementById('memberModal');
const householdModal = document.getElementById('householdModal');
const memberForm = document.getElementById('memberForm');
const householdForm = document.getElementById('householdForm');
const addMemberBtn = document.getElementById('addMemberBtn');
const editHouseholdBtn = document.getElementById('editHouseholdBtn');
const closeMemberModal = document.getElementById('closeMemberModal');
const closeHouseholdModal = document.getElementById('closeHouseholdModal');
const cancelMemberBtn = document.getElementById('cancelMemberBtn');
const cancelHouseholdBtn = document.getElementById('cancelHouseholdBtn');
const modalTitle = document.getElementById('modalTitle');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
  initializeEventListeners();
  validateDateOfBirth();
  updateMemberStatistics();
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
  // Add Member Button
  if (addMemberBtn) {
    addMemberBtn.addEventListener('click', () => openMemberModal());
  }

  // Edit Household Button
  if (editHouseholdBtn) {
    editHouseholdBtn.addEventListener('click', () => openHouseholdModal());
  }

  // Close Modal Buttons
  if (closeMemberModal) {
    closeMemberModal.addEventListener('click', () => closeMemberModalFunc());
  }

  if (closeHouseholdModal) {
    closeHouseholdModal.addEventListener('click', () => closeHouseholdModalFunc());
  }

  if (cancelMemberBtn) {
    cancelMemberBtn.addEventListener('click', () => closeMemberModalFunc());
  }

  if (cancelHouseholdBtn) {
    cancelHouseholdBtn.addEventListener('click', () => closeHouseholdModalFunc());
  }

  // Close modal when clicking outside
  window.addEventListener('click', (e) => {
    if (e.target === memberModal) {
      closeMemberModalFunc();
    }
    if (e.target === householdModal) {
      closeHouseholdModalFunc();
    }
  });

  // Form Submit Events
  if (memberForm) {
    memberForm.addEventListener('submit', handleMemberSubmit);
  }

  if (householdForm) {
    householdForm.addEventListener('submit', handleHouseholdSubmit);
  }

  // Date of Birth Change - Auto-set voter status for minors
  const dobInput = document.getElementById('dateOfBirth');
  if (dobInput) {
    dobInput.addEventListener('change', function() {
      const age = calculateAge(this.value);
      const voterStatusSelect = document.getElementById('voterStatus');
      
      if (age < 18 && voterStatusSelect) {
        voterStatusSelect.value = 'N/A';
      } else if (age >= 18 && voterStatusSelect && voterStatusSelect.value === 'N/A') {
        voterStatusSelect.value = 'Not Registered';
      }
    });
  }

  // Phone number formatting
  const phoneInputs = document.querySelectorAll('input[type="tel"]');
  phoneInputs.forEach(input => {
    input.addEventListener('input', formatPhoneNumber);
  });
}

/**
 * Open Member Modal (Add or Edit)
 */
function openMemberModal(memberId = null) {
  isEditMode = !!memberId;
  currentMemberId = memberId;

  if (isEditMode) {
    modalTitle.textContent = 'Edit Household Member';
    loadMemberData(memberId);
  } else {
    modalTitle.textContent = 'Add Household Member';
    memberForm.reset();
    document.getElementById('memberId').value = '';
  }

  memberModal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

/**
 * Close Member Modal
 */
function closeMemberModalFunc() {
  memberModal.classList.remove('active');
  document.body.style.overflow = 'auto';
  memberForm.reset();
  currentMemberId = null;
  isEditMode = false;
}

/**
 * Open Household Modal
 */
function openHouseholdModal() {
  householdModal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

/**
 * Close Household Modal
 */
function closeHouseholdModalFunc() {
  householdModal.classList.remove('active');
  document.body.style.overflow = 'auto';
}

/**
 * Load Member Data for Editing
 */
async function loadMemberData(memberId) {
  try {
    showLoading('Loading member data...');
    
    const response = await fetch(`../api/households.php?action=get_member&id=${memberId}`);
    const result = await response.json();

    if (result.success && result.data) {
      const member = result.data;
      
      // Populate form fields
      document.getElementById('memberId').value = member.id;
      document.getElementById('firstName').value = member.first_name || '';
      document.getElementById('middleName').value = member.middle_name || '';
      document.getElementById('lastName').value = member.last_name || '';
      document.getElementById('suffix').value = member.suffix || '';
      document.getElementById('relationship').value = member.relationship_to_head || '';
      document.getElementById('dateOfBirth').value = member.date_of_birth || '';
      document.getElementById('gender').value = member.gender || '';
      document.getElementById('civilStatus').value = member.civil_status || '';
      document.getElementById('occupation').value = member.occupation || '';
      document.getElementById('govIdType').value = member.government_id_type || '';
      document.getElementById('govIdNumber').value = member.government_id_number || '';
      document.getElementById('voterStatus').value = member.voter_status || 'Not Registered';
      document.getElementById('voterIdNumber').value = member.voter_id_number || '';
      document.getElementById('contactNumber').value = member.contact_number || '';
      document.getElementById('email').value = member.email || '';
      document.getElementById('isHead').checked = member.is_head == 1;
      document.getElementById('isSenior').checked = member.is_senior_citizen == 1;
      document.getElementById('isPwd').checked = member.is_pwd == 1;
      document.getElementById('is4ps').checked = member.is_4ps_beneficiary == 1;
      document.getElementById('remarks').value = member.remarks || '';
    } else {
      showAlert('error', result.message || 'Failed to load member data');
    }
  } catch (error) {
    console.error('Load member error:', error);
    showAlert('error', 'An error occurred while loading member data');
  } finally {
    hideLoading();
  }
}

/**
 * Handle Member Form Submit
 */
async function handleMemberSubmit(e) {
  e.preventDefault();

  // Validate form
  if (!validateMemberForm()) {
    return;
  }

  const formData = new FormData(memberForm);
  formData.append('action', isEditMode ? 'update_member' : 'add_member');

  // Convert checkboxes to proper values
  formData.set('is_head', document.getElementById('isHead').checked ? '1' : '0');
  formData.set('is_senior_citizen', document.getElementById('isSenior').checked ? '1' : '0');
  formData.set('is_pwd', document.getElementById('isPwd').checked ? '1' : '0');
  formData.set('is_4ps_beneficiary', document.getElementById('is4ps').checked ? '1' : '0');

  try {
    showLoading(isEditMode ? 'Updating member...' : 'Adding member...');

    const response = await fetch('../api/households.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      showAlert('success', result.message || 'Member saved successfully');
      closeMemberModalFunc();
      
      // Reload page to show updated data
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      showAlert('error', result.message || 'Failed to save member');
    }
  } catch (error) {
    console.error('Submit member error:', error);
    showAlert('error', 'An error occurred while saving member');
  } finally {
    hideLoading();
  }
}

/**
 * Handle Household Form Submit
 */
async function handleHouseholdSubmit(e) {
  e.preventDefault();

  const formData = new FormData(householdForm);
  formData.append('action', 'update_household_details');

  try {
    showLoading('Updating household details...');

    const response = await fetch('../api/households.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      showAlert('success', result.message || 'Household details updated successfully');
      closeHouseholdModalFunc();
      
      // Reload page to show updated data
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      showAlert('error', result.message || 'Failed to update household details');
    }
  } catch (error) {
    console.error('Submit household error:', error);
    showAlert('error', 'An error occurred while updating household details');
  } finally {
    hideLoading();
  }
}

/**
 * Edit Member Function (called from PHP)
 */
function editMember(memberId) {
  openMemberModal(memberId);
}

/**
 * Delete Member Function (called from PHP)
 */
async function deleteMember(memberId) {
  if (!confirm('Are you sure you want to delete this household member? This action cannot be undone.')) {
    return;
  }

  try {
    showLoading('Deleting member...');

    const formData = new FormData();
    formData.append('action', 'delete_member');
    formData.append('member_id', memberId);
    formData.append('household_id', householdId);

    const response = await fetch('../api/households.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      showAlert('success', result.message || 'Member deleted successfully');
      
      // Reload page to show updated data
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      showAlert('error', result.message || 'Failed to delete member');
    }
  } catch (error) {
    console.error('Delete member error:', error);
    showAlert('error', 'An error occurred while deleting member');
  } finally {
    hideLoading();
  }
}

/**
 * Validate Member Form
 */
function validateMemberForm() {
  const firstName = document.getElementById('firstName').value.trim();
  const lastName = document.getElementById('lastName').value.trim();
  const relationship = document.getElementById('relationship').value;
  const dateOfBirth = document.getElementById('dateOfBirth').value;
  const gender = document.getElementById('gender').value;
  const civilStatus = document.getElementById('civilStatus').value;
  const voterStatus = document.getElementById('voterStatus').value;

  // Required fields
  if (!firstName || !lastName) {
    showAlert('error', 'First name and last name are required');
    return false;
  }

  if (!relationship) {
    showAlert('error', 'Relationship to head is required');
    return false;
  }

  if (!dateOfBirth) {
    showAlert('error', 'Date of birth is required');
    return false;
  }

  if (!gender) {
    showAlert('error', 'Gender is required');
    return false;
  }

  if (!civilStatus) {
    showAlert('error', 'Civil status is required');
    return false;
  }

  if (!voterStatus) {
    showAlert('error', 'Voter status is required');
    return false;
  }

  // Validate date of birth
  const age = calculateAge(dateOfBirth);
  if (age < 0 || age > 150) {
    showAlert('error', 'Invalid date of birth');
    return false;
  }

  // Validate future date
  if (new Date(dateOfBirth) > new Date()) {
    showAlert('error', 'Date of birth cannot be in the future');
    return false;
  }

  // Validate phone number format (Philippine format)
  const contactNumber = document.getElementById('contactNumber').value.trim();
  if (contactNumber && !validatePhoneNumber(contactNumber)) {
    showAlert('error', 'Invalid phone number format. Use format: 09XX-XXX-XXXX or +639XXXXXXXXX');
    return false;
  }

  // Validate email format
  const email = document.getElementById('email').value.trim();
  if (email && !validateEmail(email)) {
    showAlert('error', 'Invalid email address format');
    return false;
  }

  // Validate voter ID if registered
  const voterIdNumber = document.getElementById('voterIdNumber').value.trim();
  if (voterStatus === 'Registered' && !voterIdNumber) {
    showAlert('error', 'Voter ID number is required for registered voters');
    return false;
  }

  return true;
}

/**
 * Validate date of birth field
 */
function validateDateOfBirth() {
  const dobInput = document.getElementById('dateOfBirth');
  if (dobInput) {
    // Set max date to today
    const today = new Date().toISOString().split('T')[0];
    dobInput.setAttribute('max', today);
    
    // Set min date to 150 years ago
    const minDate = new Date();
    minDate.setFullYear(minDate.getFullYear() - 150);
    dobInput.setAttribute('min', minDate.toISOString().split('T')[0]);
  }
}

/**
 * Calculate age from date of birth
 */
function calculateAge(dateOfBirth) {
  const today = new Date();
  const birthDate = new Date(dateOfBirth);
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  
  return age;
}

/**
 * Validate phone number (Philippine format)
 */
function validatePhoneNumber(phone) {
  // Remove spaces and dashes
  const cleanPhone = phone.replace(/[\s\-()]/g, '');
  
  // Philippine mobile: 09XXXXXXXXX or +639XXXXXXXXX
  const mobilePattern = /^(09\d{9}|(\+?639)\d{9})$/;
  
  // Landline: (02)XXXXXXX or 02XXXXXXX
  const landlinePattern = /^((\(0?2\))|0?2)?\d{7,8}$/;
  
  return mobilePattern.test(cleanPhone) || landlinePattern.test(cleanPhone);
}

/**
 * Format phone number input
 */
function formatPhoneNumber(e) {
  let value = e.target.value.replace(/\D/g, '');
  
  // Format as 09XX-XXX-XXXX
  if (value.startsWith('09') && value.length >= 4) {
    if (value.length <= 4) {
      value = value;
    } else if (value.length <= 7) {
      value = value.slice(0, 4) + '-' + value.slice(4);
    } else {
      value = value.slice(0, 4) + '-' + value.slice(4, 7) + '-' + value.slice(7, 11);
    }
  }
  
  e.target.value = value;
}

/**
 * Validate email address
 */
function validateEmail(email) {
  const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  return emailPattern.test(email);
}

/**
 * Update member statistics
 */
function updateMemberStatistics() {
  // This is called after page load and data is already calculated by PHP
  // Can be extended to calculate client-side if needed
}

/**
 * Show alert message
 */
function showAlert(type, message) {
  // Remove existing alerts
  const existingAlerts = document.querySelectorAll('.alert');
  existingAlerts.forEach(alert => alert.remove());

  // Create alert element
  const alert = document.createElement('div');
  alert.className = `alert alert-${type}`;
  
  const icon = type === 'success' ? 'fa-check-circle' : 
               type === 'error' ? 'fa-exclamation-circle' : 
               'fa-info-circle';
  
  alert.innerHTML = `
    <i class="fa-solid ${icon}"></i>
    <span>${message}</span>
  `;

  // Insert at the beginning of main content
  const mainContent = document.getElementById('mainContent');
  if (mainContent) {
    mainContent.insertBefore(alert, mainContent.firstChild);
    
    // Scroll to alert
    alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 300);
    }, 5000);
  }
}

/**
 * Show loading overlay
 */
function showLoading(message = 'Loading...') {
  let loadingOverlay = document.getElementById('loadingOverlay');
  
  if (!loadingOverlay) {
    loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loadingOverlay';
    loadingOverlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(4px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10001;
    `;
    
    loadingOverlay.innerHTML = `
      <div style="background: white; padding: 30px 40px; border-radius: 12px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div class="loading-spinner" style="width: 40px; height: 40px; margin: 0 auto 15px; border-width: 4px;"></div>
        <p style="margin: 0; font-size: 16px; font-weight: 500; color: #1f2a3d;">${message}</p>
      </div>
    `;
    
    document.body.appendChild(loadingOverlay);
  }
  
  document.body.style.overflow = 'hidden';
}

/**
 * Hide loading overlay
 */
function hideLoading() {
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.remove();
  }
  document.body.style.overflow = 'auto';
}

// Export functions for use in inline event handlers
window.editMember = editMember;
window.deleteMember = deleteMember;
