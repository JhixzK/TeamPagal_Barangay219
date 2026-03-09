/**
 * Resident Household Management
 * Handles household information, role selection, and member management
 */

// HOUSEHOLD_API is defined in resident_household.php

let currentHouseholdContext = null;
let availableHouseholds = [];

// ============================================================================
// PAGE LIFECYCLE
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
  setupEventListeners();
  loadHouseholdInfo();
});

function setupEventListeners() {
  // Role selection
  document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('click', () => selectRole(card.dataset.role));
  });

  // Role selection modal
  document.querySelector('button[data-action="closeRoleModal"]')?.addEventListener('click', closeRoleSelectionModal);

  // Head form modal
  document.querySelector('button[data-action="closeHeadModal"]')?.addEventListener('click', closeHeadFormModal);
  document.querySelector('button[data-action="submitHeadForm"]')?.addEventListener('click', submitHeadForm);

  // Member join modal
  document.querySelector('button[data-action="closeMemberModal"]')?.addEventListener('click', closeMemberJoinModal);
  document.querySelector('button[data-action="submitMemberJoin"]')?.addEventListener('click', submitMemberJoin);

  // Household details
  document.getElementById('startHouseholdBtn')?.addEventListener('click', openRoleSelectionModal);
  document.getElementById('editHouseholdBtn')?.addEventListener('click', () => alert('Edit household feature coming soon'));

  // Member management
  document.getElementById('addMemberBtn')?.addEventListener('click', openAddMemberModal);
  document.querySelector('button[data-action="closeAddMemberModal"]')?.addEventListener('click', closeAddMemberModal);
  document.querySelector('button[data-action="submitAddMember"]')?.addEventListener('click', submitAddMember);

  // Edit member modal
  document.querySelector('button[data-action="closeEditMemberModal"]')?.addEventListener('click', closeEditMemberModal);
  document.querySelector('button[data-action="submitEditMember"]')?.addEventListener('click', submitEditMember);

  // Modal backdrops
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-backdrop')) {
        closeAllModals();
      }
    });
  });

  // Household select change
  document.getElementById('householdSelect')?.addEventListener('change', (e) => {
    const selectedId = e.target.value;
    const household = availableHouseholds.find(h => h.id === parseInt(selectedId));
    const hint = document.getElementById('selectedHeadName');
    if (household) {
      hint.textContent = 'Head: ' + household.head_name + ' | Address: ' + household.address;
    } else {
      hint.textContent = '';
    }
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
 * LOAD HOUSEHOLD INFO
 */

async function loadHouseholdInfo() {
  try {
    const response = await fetch(`${HOUSEHOLD_API}/info.php`, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    });

    const result = await response.json();

    if (!response.ok) {
      if (response.status === 401) {
        window.location.href = 'login.php';
        return;
      }
      throw new Error(result.message || 'Failed to load household info');
    }

    if (result.success && result.data) {
      currentHouseholdContext = result.data.context;
      const householdInfo = result.data.household;
      const membersList = result.data.members || [];
      availableHouseholds = result.data.available_households || [];

      if (currentHouseholdContext) {
        displayHouseholdPanel(householdInfo, membersList);
        document.getElementById('roleViewBadge').textContent = 
          currentHouseholdContext.is_head ? 'Head View' : 'Member View';
        document.getElementById('contentContainer').style.display = 'block';
        document.getElementById('loadingContainer').style.display = 'none';
      } else {
        showNoHouseholdPanel();
        document.getElementById('contentContainer').style.display = 'block';
        document.getElementById('loadingContainer').style.display = 'none';
      }
    } else {
      throw new Error(result.message || 'Invalid response');
    }
  } catch (error) {
    console.error('Error loading household info:', error);
    showErrorMessage('Failed to load household information: ' + error.message);
    document.getElementById('loadingContainer').style.display = 'none';
  }
}

// ============================================================================
// DISPLAY FUNCTIONS
// ============================================================================

function showNoHouseholdPanel() {
  document.getElementById('noHouseholdPanel').style.display = 'block';
  document.getElementById('householdPanel').style.display = 'none';
  document.getElementById('membersPanel').style.display = 'none';
}

function displayHouseholdPanel(household, members) {
  const isHead = currentHouseholdContext.is_head;

  document.getElementById('householdAddress').textContent = 
    `${household.street || ''}, ${household.city || ''}, ${household.province || ''}`;
  document.getElementById('householdHeadName').textContent = household.head_name || '-';
  document.getElementById('householdMemberCount').textContent = members.length;
  document.getElementById('userRole').textContent = isHead ? 'Head of Household' : 'Member';
  document.getElementById('userRelationship').textContent = currentHouseholdContext.relationship_to_head || '-';

  document.getElementById('editHouseholdBtn').style.display = isHead ? 'inline-block' : 'none';
  document.getElementById('addMemberBtn').style.display = isHead ? 'inline-block' : 'none';

  document.getElementById('noHouseholdPanel').style.display = 'none';
  document.getElementById('householdPanel').style.display = 'block';
  document.getElementById('membersPanel').style.display = 'block';

  renderMembersTable(members, isHead);
}

function renderMembersTable(members, isHead) {
  const tbody = document.getElementById('membersTableBody');
  tbody.innerHTML = '';

  if (members.length === 0) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No members found</td></tr>';
    return;
  }

  members.forEach(member => {
    const age = calculateAge(member.dob);
    const row = document.createElement('tr');
    row.className = 'member-row';
    
    let actionsHtml = `
      <button class="btn-action btn-edit" data-member-id="${member.id}" title="View/Edit">
        <i class="fa-solid fa-eye"></i>
      </button>
    `;

    if (isHead && member.id !== currentHouseholdContext.member_row_id) {
      actionsHtml += `
        <button class="btn-action btn-delete" data-member-id="${member.id}" title="Remove">
          <i class="fa-solid fa-trash"></i>
        </button>
      `;
    }

    row.innerHTML = `
      <td><strong>${member.resident_name || 'Unknown'}</strong></td>
      <td>${age}</td>
      <td>${member.gender || '-'}</td>
      <td>${member.relationship_to_head || '-'}</td>
      <td>${member.civil_status || '-'}</td>
      <td class="actions-cell">${actionsHtml}</td>
    `;

    tbody.appendChild(row);
  });

  tbody.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', openEditMemberModal);
  });

  tbody.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', deleteMember);
  });
}

// ============================================================================
// ROLE SELECTION
// ============================================================================

function openRoleSelectionModal() {
  document.getElementById('roleSelectionModal').style.display = 'flex';
}

function closeRoleSelectionModal() {
  document.getElementById('roleSelectionModal').style.display = 'none';
}

function selectRole(role) {
  if (role === 'head') {
    closeRoleSelectionModal();
    openHeadFormModal();
  } else if (role === 'member') {
    closeRoleSelectionModal();
    populateMemberJoinModal();
    openMemberJoinModal();
  }
}

// ============================================================================
// HEAD OF HOUSEHOLD FORM
// ============================================================================

function openHeadFormModal() {
  document.getElementById('headFormModal').style.display = 'flex';
}

function closeHeadFormModal() {
  document.getElementById('headFormModal').style.display = 'none';
  document.querySelector('#headFormContainer').reset();
}

async function submitHeadForm() {
  const address = document.getElementById('householdAddress').value.trim();
  const street = document.getElementById('householdStreet').value.trim();
  const city = document.getElementById('householdCity').value.trim();
  const province = document.getElementById('householdProvince').value.trim();

  if (!address || !street || !city || !province) {
    alert('Please fill in all address fields');
    return;
  }

  const submitBtn = document.getElementById('submitHeadBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Creating...';

  try {
    const response = await fetch(`${HOUSEHOLD_API}/info.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'createHeadHousehold',
        address,
        street,
        city,
        province
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      showErrorMessage(result.message || 'Failed to create household');
      return;
    }

    closeHeadFormModal();
    showSuccessMessage('Household created successfully');
    setTimeout(() => location.reload(), 1500);
  } catch (error) {
    console.error('Error creating household:', error);
    showErrorMessage('Failed to create household: ' + error.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Create Household';
  }
}

// ============================================================================
// MEMBER JOIN HOUSEHOLD
// ============================================================================

function populateMemberJoinModal() {
  const select = document.getElementById('householdSelect');
  select.innerHTML = '<option value="">-- Select a household --</option>';

  availableHouseholds.forEach(household => {
    const option = document.createElement('option');
    option.value = household.id;
    option.textContent = `${household.head_name} - ${household.address}`;
    select.appendChild(option);
  });
}

function openMemberJoinModal() {
  document.getElementById('memberJoinModal').style.display = 'flex';
}

function closeMemberJoinModal() {
  document.getElementById('memberJoinModal').style.display = 'none';
  document.querySelector('#memberFormContainer').reset();
  document.getElementById('selectedHeadName').textContent = '';
}

async function submitMemberJoin() {
  const householdId = document.getElementById('householdSelect').value;
  const relationship = document.getElementById('relationshipToHead').value;

  if (!householdId || !relationship) {
    alert('Please select both household and relationship');
    return;
  }

  const submitBtn = document.getElementById('submitMemberBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Joining...';

  try {
    const response = await fetch(`${HOUSEHOLD_API}/info.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'joinAsMember',
        household_id: parseInt(householdId),
        relationship_to_head: relationship
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      showErrorMessage(result.message || 'Failed to join household');
      return;
    }

    closeMemberJoinModal();
    showSuccessMessage('Successfully joined household');
    setTimeout(() => location.reload(), 1500);
  } catch (error) {
    console.error('Error joining household:', error);
    showErrorMessage('Failed to join household: ' + error.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Join Household';
  }
}

// ============================================================================
// MEMBER MANAGEMENT (HEAD ONLY)
// ============================================================================

function openAddMemberModal() {
  document.getElementById('addMemberModal').style.display = 'flex';
  document.querySelector('#addMemberForm').reset();
}

function closeAddMemberModal() {
  document.getElementById('addMemberModal').style.display = 'none';
  document.querySelector('#addMemberForm').reset();
}

async function submitAddMember() {
  const name = document.getElementById('newMemberName').value.trim();
  const dob = document.getElementById('newMemberDOB').value;
  const gender = document.getElementById('newMemberGender').value;
  const relationship = document.getElementById('newMemberRelationship').value;
  const civilStatus = document.getElementById('newMemberCivilStatus').value;

  if (!name || !dob || !gender || !relationship || !civilStatus) {
    alert('Please fill in all fields');
    return;
  }

  const submitBtn = document.getElementById('submitAddMemberBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Adding...';

  try {
    const response = await fetch(`${HOUSEHOLD_API}/member.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        resident_name: name,
        dob,
        gender,
        relationship_to_head: relationship,
        civil_status: civilStatus
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      showErrorMessage(result.message || 'Failed to add member');
      return;
    }

    closeAddMemberModal();
    showSuccessMessage('Member added successfully');
    setTimeout(() => loadHouseholdInfo(), 1500);
  } catch (error) {
    console.error('Error adding member:', error);
    showErrorMessage('Failed to add member: ' + error.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Add Member';
  }
}

function openEditMemberModal(event) {
  const memberId = event.currentTarget.dataset.memberId;
  const row = event.currentTarget.closest('.member-row');
  const cells = row.querySelectorAll('td');

  const memberName = cells[0].textContent;
  const gender = cells[2].textContent;
  const relationship = cells[3].textContent;
  const civilStatus = cells[4].textContent;

  document.getElementById('editMemberId').value = memberId;
  document.getElementById('editMemberName').value = memberName;
  document.getElementById('editMemberGender').value = gender;
  document.getElementById('editMemberRelationship').value = relationship;
  document.getElementById('editMemberCivilStatus').value = civilStatus;

  const isHead = currentHouseholdContext.is_head;
  const isOwnRecord = parseInt(memberId) === currentHouseholdContext.member_row_id;
  
  document.getElementById('editMemberRelationship').disabled = !isHead;
  document.getElementById('relationshipLabel').textContent = isHead ? '' : '(Locked for members)';
  document.querySelector('button[data-action="submitEditMember"]').style.display = 
    (isHead || isOwnRecord) ? 'inline-block' : 'none';

  document.getElementById('editMemberModal').style.display = 'flex';
}

function closeEditMemberModal() {
  document.getElementById('editMemberModal').style.display = 'none';
  document.querySelector('#editMemberForm').reset();
}

async function submitEditMember() {
  const memberId = document.getElementById('editMemberId').value;
  const dob = document.getElementById('editMemberDOB').value;
  const gender = document.getElementById('editMemberGender').value;
  const relationship = document.getElementById('editMemberRelationship').value;
  const civilStatus = document.getElementById('editMemberCivilStatus').value;

  if (!dob || !gender || !relationship || !civilStatus) {
    alert('Please fill in all fields');
    return;
  }

  const submitBtn = document.querySelector('button[data-action="submitEditMember"]');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving...';

  try {
    const response = await fetch(`${HOUSEHOLD_API}/member.php`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        member_row_id: parseInt(memberId),
        dob,
        gender,
        relationship_to_head: relationship,
        civil_status: civilStatus
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      showErrorMessage(result.message || 'Failed to update member');
      return;
    }

    closeEditMemberModal();
    showSuccessMessage('Member updated successfully');
    setTimeout(() => loadHouseholdInfo(), 1500);
  } catch (error) {
    console.error('Error updating member:', error);
    showErrorMessage('Failed to update member: ' + error.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Save Changes';
  }
}

async function deleteMember(event) {
  const memberId = event.currentTarget.dataset.memberId;
  
  if (!confirm('Are you sure you want to remove this member from the household?')) {
    return;
  }

  const btn = event.currentTarget;
  btn.disabled = true;

  try {
    const response = await fetch(`${HOUSEHOLD_API}/member.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        member_row_id: parseInt(memberId)
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      showErrorMessage(result.message || 'Failed to remove member');
      btn.disabled = false;
      return;
    }

    showSuccessMessage('Member removed successfully');
    setTimeout(() => loadHouseholdInfo(), 1500);
  } catch (error) {
    console.error('Error removing member:', error);
    showErrorMessage('Failed to remove member: ' + error.message);
    btn.disabled = false;
  }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function calculateAge(dob) {
  const today = new Date();
  const birthDate = new Date(dob);
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age;
}

function closeAllModals() {
  document.querySelectorAll('.modal').forEach(modal => {
    modal.style.display = 'none';
  });
}

function showErrorMessage(message) {
  alert('Error: ' + message);
}

function showSuccessMessage(message) {
  alert('Success: ' + message);
}
