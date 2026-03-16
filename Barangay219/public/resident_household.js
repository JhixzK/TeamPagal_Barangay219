/**
 * Resident Household Management
 * Handles household information, role selection, and member management
 */

// ============================================================================
// NAVIGATION & UI
// ============================================================================

const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");

function formatToday() {
  const now = new Date();
  return now.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "2-digit"
  });
}

function setDateBadges() {
  const today = formatToday();
  if (topDateBadge) {
    topDateBadge.textContent = today;
  }
}

function toggleDropdown() {
  const expanded = profileTrigger.getAttribute("aria-expanded") === "true";
  profileTrigger.setAttribute("aria-expanded", String(!expanded));
  dropdownMenu.classList.toggle("open", !expanded);
}

function closeDropdownIfOutside(event) {
  if (!event.target.closest("#profileDropdown")) {
    profileTrigger.setAttribute("aria-expanded", "false");
    dropdownMenu.classList.remove("open");
  }
}

function toggleSidebarOnMobile() {
  sidebar.classList.toggle("expanded");
}

if (profileTrigger) {
  profileTrigger.addEventListener("click", toggleDropdown);
}
if (menuToggle) {
  menuToggle.addEventListener("click", toggleSidebarOnMobile);
}
document.addEventListener("click", closeDropdownIfOutside);
window.addEventListener("resize", () => {
  if (window.innerWidth > 991) {
    sidebar.classList.remove("expanded");
  }
});

setDateBadges();

// ============================================================================
// HOUSEHOLD MANAGEMENT
// ============================================================================

let currentHouseholdContext = null;
let currentHouseholdData = null;
let currentMembers = [];
let availableHouseholds = [];

// ============================================================================
// PAGE LIFECYCLE
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
  setupEventListeners();
  loadHouseholdInfo();
});

function setupEventListeners() {
  // Role selection cards
  document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('click', () => selectRole(card.dataset.role));
  });

  // Close buttons with data-action
  document.querySelectorAll('[data-action="closeRoleModal"]').forEach(btn => {
    btn.addEventListener('click', closeRoleSelectionModal);
  });

  document.querySelectorAll('[data-action="closeHeadModal"]').forEach(btn => {
    btn.addEventListener('click', closeHeadFormModal);
  });

  document.querySelectorAll('[data-action="submitHeadForm"]').forEach(btn => {
    btn.addEventListener('click', submitHeadForm);
  });

  document.querySelectorAll('[data-action="closeMemberModal"]').forEach(btn => {
    btn.addEventListener('click', closeMemberJoinModal);
  });

  document.querySelectorAll('[data-action="submitMemberJoin"]').forEach(btn => {
    btn.addEventListener('click', submitMemberJoin);
  });

  document.querySelectorAll('[data-action="closeAddMemberModal"]').forEach(btn => {
    btn.addEventListener('click', closeAddMemberModal);
  });

  document.querySelectorAll('[data-action="submitAddMember"]').forEach(btn => {
    btn.addEventListener('click', submitAddMember);
  });

  document.querySelectorAll('[data-action="closeEditMemberModal"]').forEach(btn => {
    btn.addEventListener('click', closeEditMemberModal);
  });

  document.querySelectorAll('[data-action="submitEditMember"]').forEach(btn => {
    btn.addEventListener('click', submitEditMember);
  });

  document.querySelectorAll('[data-action="editHousehold"]').forEach(btn => {
    btn.addEventListener('click', editHousehold);
  });

  document.querySelectorAll('[data-action="addMember"]').forEach(btn => {
    btn.addEventListener('click', openAddMemberModal);
  });

  // Modal backdrops
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-backdrop')) {
        closeAllModals();
      }
    });
  });

  // Household select change event
  const householdSelect = document.getElementById('householdSelect');
  if (householdSelect) {
    householdSelect.addEventListener('change', (e) => {
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
}

// ============================================================================
// LOAD HOUSEHOLD INFO
// ============================================================================

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
      currentHouseholdContext = result.data.context || null;
      if (!currentHouseholdContext) {
        const isHeadFromRole = (result.data.role || '') === 'head';
        currentHouseholdContext = {
          household_id: result.data.household?.id || null,
          is_head: !!isHeadFromRole,
          member_row_id: null,
          relationship_to_head: isHeadFromRole ? 'Head' : 'Member'
        };
      }
      currentHouseholdData = result.data.household;
      currentMembers = result.data.members || [];
      availableHouseholds = result.data.available_households || [];

      // Show content
      const contentContainer = document.getElementById('contentContainer');
      const loadingContainer = document.getElementById('loadingContainer');
      if (contentContainer) contentContainer.style.display = 'block';
      if (loadingContainer) loadingContainer.style.display = 'none';

      if (currentHouseholdContext) {
        // User has a household - display info
        displayHouseholdPanel();
        const detailsPanel = document.getElementById('householdDetailsPanel');
        const membersPanel = document.getElementById('membersPanel');
        if (detailsPanel) detailsPanel.style.display = 'block';
        if (membersPanel) membersPanel.style.display = 'block';
      } else {
        // User has no household - show role selection
        openRoleSelectionModal();
        const detailsPanel = document.getElementById('householdDetailsPanel');
        const membersPanel = document.getElementById('membersPanel');
        if (detailsPanel) detailsPanel.style.display = 'none';
        if (membersPanel) membersPanel.style.display = 'none';
      }
    } else {
      throw new Error(result.message || 'Invalid response');
    }
  } catch (error) {
    console.error('Error loading household info:', error);
    const loadingContainer = document.getElementById('loadingContainer');
    const contentContainer = document.getElementById('contentContainer');
    if (loadingContainer) loadingContainer.style.display = 'none';
    if (contentContainer) contentContainer.style.display = 'block';
    showErrorMessage('Failed to load household information: ' + error.message);
  }
}

// ============================================================================
// DISPLAY FUNCTIONS
// ============================================================================

function displayHouseholdPanel() {
  const isHead = currentHouseholdContext?.is_head || false;
  const household = currentHouseholdData;
  const members = currentMembers;
  const memberCount = parseInt(household?.total_members ?? members.length, 10) || 0;

  // Update stats
  const totalMembers = document.getElementById('totalMembers');
  if (totalMembers) totalMembers.textContent = memberCount;
  
  const householdAddress = document.getElementById('householdAddress');
  if (householdAddress) householdAddress.textContent = household?.address || household?.street || '--';
  
  const headName = document.getElementById('headName');
  if (headName) headName.textContent = household?.head_name || '--';

  // Update details panel
  const displayAddress = document.getElementById('displayAddress');
  if (displayAddress) displayAddress.textContent = household?.address || household?.street || '--';
  
  const displayHead = document.getElementById('displayHead');
  if (displayHead) displayHead.textContent = household?.head_name || '--';
  
  const displayMembers = document.getElementById('displayMembers');
  if (displayMembers) displayMembers.textContent = memberCount;

  const displayFamilyCode = document.getElementById('displayFamilyCode');
  if (displayFamilyCode) displayFamilyCode.textContent = household?.family_code || '--';
  
  const displayCreated = document.getElementById('displayCreated');
  if (displayCreated) {
    displayCreated.textContent = household?.created_at ? 
      new Date(household.created_at).toLocaleDateString() : '--';
  }

  // Show/hide edit button for head
  const editBtn = document.getElementById('editHouseholdBtn');
  const addMemberBtn = document.getElementById('addMemberBtn');
  if (editBtn) editBtn.style.display = isHead ? 'inline-block' : 'none';
  if (addMemberBtn) addMemberBtn.style.display = isHead ? 'inline-block' : 'none';

  // Render members table
  renderMembersTable(members, isHead);
}

function renderMembersTable(members, isHead) {
  const tbody = document.getElementById('membersTableBody');
  if (!tbody) return;
  
  tbody.innerHTML = '';

  if (members.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No members found</td></tr>';
    return;
  }

  members.forEach(member => {
    const canEditMember = Number(member.id || 0) > 0 && !member.readonly;
    const canRemoveMember = canEditMember && isHead && member.id !== currentHouseholdContext.member_row_id;
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${member.name || member.resident_name || 'Unknown'}</td>
      <td>${member.relationship_to_head || '-'}</td>
      <td><span class="status-badge">${member.status || 'Active'}</span></td>
      <td>
        ${canEditMember ? `<button class="btn-action btn-sm" onclick="editMember(${member.id})" title="Edit">
          <i class="fa-solid fa-edit"></i>
        </button>` : ''}
        ${canRemoveMember ? `
        <button class="btn-action btn-sm btn-danger" onclick="removeMember(${member.id})" title="Remove">
          <i class="fa-solid fa-trash"></i>
        </button>
        ` : ''}
      </td>
    `;
    tbody.appendChild(row);
  });
}

// ============================================================================
// ROLE SELECTION & HOUSEHOLD CREATION
// ============================================================================

function selectRole(role) {
  closeRoleSelectionModal();
  
  if (role === 'head') {
    openHeadFormModal();
  } else if (role === 'member') {
    openMemberJoinModal();
  }
}

function openRoleSelectionModal() {
  const modal = document.getElementById('roleSelectionModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeRoleSelectionModal() {
  const modal = document.getElementById('roleSelectionModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}

async function submitHeadForm(e) {
  e?.preventDefault();
  
  const address = document.getElementById('householdAddress')?.value || '';
  const street = document.getElementById('householdStreet')?.value || '';
  const city = document.getElementById('householdCity')?.value || '';
  const province = document.getElementById('householdProvince')?.value || '';

  if (!address || !street || !city || !province) {
    showErrorMessage('Please fill in all address fields');
    return;
  }

  try {
    const response = await fetch(`${HOUSEHOLD_API}/info.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'create_household',
        house_number: address,
        street: street,
        barangay: 'Barangay 219',
        city: city,
        province: province
      })
    });

    const result = await response.json();

    if (result.success) {
      const code = result.data?.family_code ? ` Family Code: ${result.data.family_code}` : '';
      showSuccessMessage('Household created successfully!' + code);
      closeHeadFormModal();
      // Reload household info
      await new Promise(r => setTimeout(r, 500));
      loadHouseholdInfo();
    } else {
      showErrorMessage(result.message || 'Failed to create household');
    }
  } catch (error) {
    showErrorMessage('Error: ' + error.message);
  }
}

function openHeadFormModal() {
  const modal = document.getElementById('headFormModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeHeadFormModal() {
  const modal = document.getElementById('headFormModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
  // Clear form
  const form = document.getElementById('headFormContainer');
  if (form) form.reset();
}

// ============================================================================
// MEMBER JOIN
// ============================================================================

async function submitMemberJoin(e) {
  e?.preventDefault();

  const householdSelect = document.getElementById('householdSelect');
  const householdId = householdSelect?.value;
  const familyCode = householdSelect?.selectedOptions?.[0]?.dataset?.familyCode || '';
  const relationship = document.getElementById('relationshipToHead')?.value;

  if (!householdId || !relationship || !familyCode) {
    showErrorMessage('Please select a household and relationship');
    return;
  }

  try {
    const response = await fetch(`${HOUSEHOLD_API}/info.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'join_household',
        household_id: parseInt(householdId, 10),
        family_code: familyCode,
        relationship_to_head: relationship
      })
    });

    const result = await response.json();

    if (result.success) {
      showSuccessMessage('Successfully joined household! Waiting for approval...');
      closeMemberJoinModal();
      // Reload household info
      await new Promise(r => setTimeout(r, 500));
      loadHouseholdInfo();
    } else {
      showErrorMessage(result.message || 'Failed to join household');
    }
  } catch (error) {
    showErrorMessage('Error: ' + error.message);
  }
}

function openMemberJoinModal() {
  // Populate household select
  const householdSelect = document.getElementById('householdSelect');
  if (householdSelect && availableHouseholds.length > 0) {
    householdSelect.innerHTML = '<option value="">-- Select a household --</option>';
    availableHouseholds.forEach(h => {
      const option = document.createElement('option');
      option.value = h.id;
      option.dataset.familyCode = h.family_code || '';
      option.textContent = `${h.family_code || 'No Code'} | ${h.address || ''} (Head: ${h.head_name})`;
      householdSelect.appendChild(option);
    });
  }
  
  const modal = document.getElementById('memberJoinModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeMemberJoinModal() {
  const modal = document.getElementById('memberJoinModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
  // Clear form
  const form = document.getElementById('memberFormContainer');
  if (form) form.reset();
}

// ============================================================================
// MEMBER MANAGEMENT (HEAD ONLY)
// ============================================================================

function openAddMemberModal() {
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage('Only household head can add members');
    return;
  }
  
  const modal = document.getElementById('addMemberModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  const form = document.getElementById('addMemberForm');
  if (form) form.reset();
}

function closeAddMemberModal() {
  const modal = document.getElementById('addMemberModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}

async function submitAddMember(e) {
  e?.preventDefault();

  if (!currentHouseholdContext?.is_head) {
    showErrorMessage('Only household head can add members');
    return;
  }

  const name = document.getElementById('newMemberName')?.value;
  const dob = document.getElementById('newMemberDOB')?.value;
  const gender = (document.getElementById('newMemberGender')?.value || '').toLowerCase();
  const relationship = document.getElementById('newMemberRelationship')?.value;

  if (!name || !dob || !gender || !relationship) {
    showErrorMessage('Please fill in all member details');
    return;
  }

  try {
    const response = await fetch(`${HOUSEHOLD_API}/member.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        household_id: currentHouseholdData?.id,
        resident_id: parseInt(RESIDENT_SESSION_ID || 0, 10),
        date_of_birth: dob,
        gender: gender,
        relationship_to_head: relationship
      })
    });

    const result = await response.json();

    if (result.success) {
      showSuccessMessage('Member added successfully!');
      closeAddMemberModal();
      // Reload household info
      await new Promise(r => setTimeout(r, 500));
      loadHouseholdInfo();
    } else {
      showErrorMessage(result.message || 'Failed to add member');
    }
  } catch (error) {
    showErrorMessage('Error: ' + error.message);
  }
}

function editMember(memberId) {
  const member = currentMembers.find(m => m.id === memberId);
  if (!member) {
    showErrorMessage('Member not found');
    return;
  }

  // Populate edit form
  const editMemberId = document.getElementById('editMemberId');
  if (editMemberId) editMemberId.value = memberId;
  
  const editMemberName = document.getElementById('editMemberName');
  if (editMemberName) editMemberName.value = member.name || member.resident_name || '';
  
  const editMemberDOB = document.getElementById('editMemberDOB');
  if (editMemberDOB) editMemberDOB.value = member.date_of_birth || member.dob || '';
  
  const editMemberGender = document.getElementById('editMemberGender');
  if (editMemberGender) editMemberGender.value = member.gender || '';
  
  const editMemberRelationship = document.getElementById('editMemberRelationship');
  if (editMemberRelationship) editMemberRelationship.value = member.relationship_to_head || '';

  // Lock relationship for non-head users
  const isHead = currentHouseholdContext?.is_head;
  if (editMemberRelationship) {
    editMemberRelationship.disabled = !isHead;
  }

  const modal = document.getElementById('editMemberModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeEditMemberModal() {
  const modal = document.getElementById('editMemberModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}

async function submitEditMember(e) {
  e?.preventDefault();

  const memberId = document.getElementById('editMemberId')?.value;
  const dob = document.getElementById('editMemberDOB')?.value;
  const gender = (document.getElementById('editMemberGender')?.value || '').toLowerCase();
  const relationship = document.getElementById('editMemberRelationship')?.value;

  if (!memberId || !dob || !gender) {
    showErrorMessage('Please fill in all required fields');
    return;
  }

  try {
    const response = await fetch(`${HOUSEHOLD_API}/member.php`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        member_id: parseInt(memberId, 10),
        date_of_birth: dob,
        gender: gender,
        relationship_to_head: relationship
      })
    });

    const result = await response.json();

    if (result.success) {
      showSuccessMessage('Member updated successfully!');
      closeEditMemberModal();
      // Reload household info
      await new Promise(r => setTimeout(r, 500));
      loadHouseholdInfo();
    } else {
      showErrorMessage(result.message || 'Failed to update member');
    }
  } catch (error) {
    showErrorMessage('Error: ' + error.message);
  }
}

async function removeMember(memberId) {
  if (!confirm('Are you sure you want to remove this member?')) {
    return;
  }

  try {
    const response = await fetch(`${HOUSEHOLD_API}/member.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        member_id: parseInt(memberId, 10)
      })
    });

    const result = await response.json();

    if (result.success) {
      showSuccessMessage('Member removed successfully!');
      // Reload household info
      await new Promise(r => setTimeout(r, 500));
      loadHouseholdInfo();
    } else {
      showErrorMessage(result.message || 'Failed to remove member');
    }
  } catch (error) {
    showErrorMessage('Error: ' + error.message);
  }
}

function editHousehold() {
  showErrorMessage('Edit household feature coming soon');
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function closeAllModals() {
  document.querySelectorAll('.modal').forEach(modal => {
    modal.style.display = 'none';
  });
  document.body.style.overflow = 'auto';
}

function showErrorMessage(message) {
  const container = document.getElementById('messageContainer');
  if (!container) return;
  
  const msgDiv = document.createElement('div');
  msgDiv.className = 'alert alert-danger';
  msgDiv.innerHTML = `
    <i class="fa-solid fa-exclamation-circle"></i>
    <div>
      <strong>Error</strong>
      <p>${message}</p>
    </div>
    <button class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>
  `;
  container.appendChild(msgDiv);
  
  // Auto-hide after 5 seconds
  setTimeout(() => {
    if (msgDiv.parentElement) msgDiv.remove();
  }, 5000);
}

function showSuccessMessage(message) {
  const container = document.getElementById('messageContainer');
  if (!container) return;
  
  const msgDiv = document.createElement('div');
  msgDiv.className = 'alert alert-success';
  msgDiv.innerHTML = `
    <i class="fa-solid fa-check-circle"></i>
    <div>
      <strong>Success</strong>
      <p>${message}</p>
    </div>
    <button class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>
  `;
  container.appendChild(msgDiv);
  
  // Auto-hide after 5 seconds
  setTimeout(() => {
    if (msgDiv.parentElement) msgDiv.remove();
  }, 5000);
}

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
