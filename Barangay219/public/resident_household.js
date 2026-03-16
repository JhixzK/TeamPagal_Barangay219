/**
 * Resident Household Management
 */

const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");

let currentHouseholdContext = null;
let currentHouseholdData = null;
let currentMembers = [];
let availableHouseholds = [];
let availableResidents = [];
let currentEmergencyContact = {};

const RELATIONSHIP_MAP = {
  Father: "Parent",
  Mother: "Parent",
  Brother: "Sibling",
  Sister: "Sibling",
  Grandson: "Relative",
  Granddaughter: "Relative",
  Nephew: "Relative",
  Niece: "Relative",
  Uncle: "Relative",
  Aunt: "Relative",
  Cousin: "Relative",
  "In-law": "Relative",
  Other: "Relative"
};

function formatToday() {
  return new Date().toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "2-digit"
  });
}

function setDateBadges() {
  if (topDateBadge) {
    topDateBadge.textContent = formatToday();
  }
}

function toggleDropdown() {
  const expanded = profileTrigger?.getAttribute("aria-expanded") === "true";
  profileTrigger?.setAttribute("aria-expanded", String(!expanded));
  dropdownMenu?.classList.toggle("open", !expanded);
}

function closeDropdownIfOutside(event) {
  if (!event.target.closest("#profileDropdown")) {
    profileTrigger?.setAttribute("aria-expanded", "false");
    dropdownMenu?.classList.remove("open");
  }
}

function toggleSidebarOnMobile() {
  sidebar?.classList.toggle("expanded");
}

function normalizeRelationship(value) {
  if (!value) return "Relative";
  return RELATIONSHIP_MAP[value] || value;
}

function escapeHtml(value) {
  return String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatDate(value) {
  if (!value) return "--";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "--";
  return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "2-digit" });
}

function calculateAge(dob) {
  if (!dob) return null;
  const today = new Date();
  const birthDate = new Date(dob);
  if (Number.isNaN(birthDate.getTime())) return null;
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return Math.max(0, age);
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
    sidebar?.classList.remove("expanded");
  }
});
setDateBadges();

document.addEventListener("DOMContentLoaded", () => {
  setupEventListeners();
  loadHouseholdInfo();
});

function setupEventListeners() {
  document.querySelectorAll(".role-card").forEach((card) => {
    card.addEventListener("click", () => selectRole(card.dataset.role));
  });

  [
    ["closeRoleModal", closeRoleSelectionModal],
    ["closeHeadModal", closeHeadFormModal],
    ["submitHeadForm", submitHeadForm],
    ["closeMemberModal", closeMemberJoinModal],
    ["submitMemberJoin", submitMemberJoin],
    ["closeAddMemberModal", closeAddMemberModal],
    ["submitAddMember", submitAddMember],
    ["closeEditMemberModal", closeEditMemberModal],
    ["submitEditMember", submitEditMember],
    ["editHousehold", editHousehold],
    ["closeOverviewModal", closeOverviewUpdateModal],
    ["submitOverviewUpdate", submitOverviewUpdate],
    ["addMember", openAddMemberModal],
    ["leaveHousehold", leaveHousehold]
  ].forEach(([action, handler]) => {
    document.querySelectorAll(`[data-action="${action}"]`).forEach((btn) => {
      btn.addEventListener("click", handler);
    });
  });

  document.querySelectorAll(".modal-backdrop").forEach((backdrop) => {
    backdrop.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal-backdrop")) {
        closeAllModals();
      }
    });
  });

  const householdSelect = document.getElementById("householdSelect");
  householdSelect?.addEventListener("change", (e) => {
    const selectedId = Number.parseInt(e.target.value || "0", 10);
    const household = availableHouseholds.find((h) => h.id === selectedId);
    const hint = document.getElementById("selectedHeadName");
    if (hint) {
      hint.textContent = household ? `Head: ${household.head_name} | Address: ${household.address}` : "";
    }
  });

  const addResident = document.getElementById("newMemberResident");
  addResident?.addEventListener("change", () => {
    const opt = addResident.selectedOptions[0];
    document.getElementById("newMemberDOB").value = opt?.dataset?.birthDate || "";
    document.getElementById("newMemberGender").value = (opt?.dataset?.gender || "").toLowerCase();
    const hint = document.getElementById("newMemberResidentHint");
    if (hint) {
      hint.textContent = opt?.value ? `Resident Code: ${opt.dataset.residentCode || "N/A"}` : "Only residents with valid records are listed.";
    }
  });
}

async function requestJson(url, options) {
  const response = await fetch(url, {
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    ...options
  });
  const result = await response.json();
  if (!response.ok || !result.success) {
    throw new Error(result.message || "Request failed");
  }
  return result;
}

async function loadHouseholdInfo() {
  try {
    const result = await requestJson(`${HOUSEHOLD_API}/info.php`, { method: "GET" });
    const data = result.data || {};

    currentHouseholdContext = data.context || null;
    currentHouseholdData = data.household || null;
    currentMembers = data.members || [];
    availableHouseholds = data.available_households || [];
    availableResidents = data.available_residents || [];

    document.getElementById("contentContainer").style.display = "block";
    document.getElementById("loadingContainer").style.display = "none";

    if (!currentHouseholdContext || !currentHouseholdContext.household_id) {
      document.getElementById("householdDetailsPanel").style.display = "none";
      document.getElementById("contactsPanel").style.display = "none";
      document.getElementById("membersPanel").style.display = "none";
      document.getElementById("historyPanel").style.display = "none";
      openRoleSelectionModal();
      return;
    }

    closeRoleSelectionModal();
    document.getElementById("householdDetailsPanel").style.display = "block";
    document.getElementById("contactsPanel").style.display = "block";
    document.getElementById("membersPanel").style.display = "block";
    document.getElementById("historyPanel").style.display = "block";

    displayHouseholdPanel(data);
  } catch (error) {
    console.error(error);
    document.getElementById("loadingContainer").style.display = "none";
    document.getElementById("contentContainer").style.display = "block";
    showErrorMessage(`Failed to load household information: ${error.message}`);
  }
}

function displayHouseholdPanel(data) {
  const isHead = !!currentHouseholdContext?.is_head;
  const household = currentHouseholdData || {};
  const members = currentMembers || [];
  const stats = data.member_stats || {};
  const emergency = data.emergency_contact || {};
  currentEmergencyContact = emergency;

  document.getElementById("totalMembers").textContent = household.total_members ?? stats.total ?? members.length;
  document.getElementById("childrenCount").textContent = stats.children ?? 0;
  document.getElementById("adultsCount").textContent = stats.adults ?? 0;
  document.getElementById("seniorsCount").textContent = stats.seniors ?? 0;

  document.getElementById("displayFamilyCode").textContent = household.family_code || "--";
  document.getElementById("displayHead").textContent = household.head_name || "--";
  document.getElementById("displayAddress").textContent = household.address || "--";
  document.getElementById("displayHouseholdType").textContent = household.household_type || "--";
  document.getElementById("displayHousingStatus").textContent = household.housing_status || "--";
  document.getElementById("displayYearsResidency").textContent = `${household.years_of_residency ?? 0} year(s)`;
  document.getElementById("displayMembers").textContent = household.total_members ?? stats.total ?? members.length;
  document.getElementById("displayCreated").textContent = formatDate(household.created_at);

  document.getElementById("displayEmergencyName").textContent = emergency.name || "--";
  document.getElementById("displayEmergencyRelationship").textContent = emergency.relationship || "--";
  document.getElementById("displayEmergencyNumber").textContent = emergency.contact_number || "--";

  const editBtn = document.getElementById("editHouseholdBtn");
  const addMemberBtn = document.getElementById("addMemberBtn");
  const leaveBtn = document.getElementById("leaveHouseholdBtn");
  if (editBtn) editBtn.style.display = isHead ? "inline-flex" : "none";
  if (addMemberBtn) addMemberBtn.style.display = isHead ? "inline-flex" : "none";
  if (leaveBtn) leaveBtn.style.display = isHead ? "none" : "inline-flex";

  renderProgramTags(data.program_tags || []);
  renderMembersTable(members, isHead);
  renderHistory(data.history_logs || []);
}

function renderProgramTags(tags) {
  const wrap = document.getElementById("programTags");
  if (!wrap) return;
  if (!tags.length) {
    wrap.innerHTML = '<span class="tag tag-empty">No active household tags</span>';
    return;
  }
  wrap.innerHTML = tags
    .map((tag) => `<span class="tag ${escapeHtml(tag.class || "")}">${escapeHtml(tag.label || tag.key)}</span>`)
    .join("");
}

function memberProgramBadges(member) {
  const programs = [];
  if (Number(member.is_pwd) === 1) programs.push("PWD");
  if (Number(member.is_4ps_beneficiary) === 1) programs.push("4Ps");
  if (Number(member.is_solo_parent) === 1) programs.push("Solo Parent");
  if (!programs.length) return '<span class="tag tag-empty">None</span>';
  return programs.map((p) => `<span class="tag tag-mini">${escapeHtml(p)}</span>`).join(" ");
}

function renderMembersTable(members, isHead) {
  const tbody = document.getElementById("membersTableBody");
  if (!tbody) return;

  if (!members.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No members found.</td></tr>';
    return;
  }

  tbody.innerHTML = members
    .map((member) => {
      const isSelf = !!member.is_self;
      const memberId = Number(member.id || 0);
      const canEdit = memberId > 0 && !member.readonly && (isHead || isSelf);
      const canRemove = memberId > 0 && !member.readonly && isHead && !isSelf && (member.relationship_to_head || "").toLowerCase() !== "head";
      const canAssignHead = memberId > 0 && !member.readonly && isHead && !isSelf;

      const actions = [];
      if (canEdit) {
        actions.push(`<button class="btn-action btn-sm" onclick="editMember(${memberId})" title="Edit"><i class="fa-solid fa-pen"></i></button>`);
      }
      if (canAssignHead) {
        actions.push(`<button class="btn-action btn-sm" onclick="assignNewHead(${memberId})" title="Assign as Head"><i class="fa-solid fa-crown"></i></button>`);
      }
      if (canRemove) {
        actions.push(`<button class="btn-action btn-sm btn-danger" onclick="removeMember(${memberId})" title="Remove"><i class="fa-solid fa-trash"></i></button>`);
      }
      if (!actions.length) {
        actions.push('<span class="text-muted">No action</span>');
      }

      return `
        <tr>
          <td>${escapeHtml(member.name || "Unknown")}${isSelf ? ' <span class="self-tag">(You)</span>' : ""}</td>
          <td>${escapeHtml(member.relationship_to_head || "-")}</td>
          <td>${escapeHtml(member.sex || "-")}</td>
          <td>${member.age ?? calculateAge(member.date_of_birth) ?? "-"}</td>
          <td><span class="status-badge">${escapeHtml(member.status || "Active")}</span></td>
          <td>${memberProgramBadges(member)}</td>
          <td>${actions.join(" ")}</td>
        </tr>
      `;
    })
    .join("");
}

function renderHistory(items) {
  const list = document.getElementById("historyList");
  if (!list) return;

  if (!items.length) {
    list.innerHTML = '<p class="empty-row">No history logs yet.</p>';
    return;
  }

  list.innerHTML = items
    .map((item) => {
      const detail = item.details ? `<p>${escapeHtml(item.details)}</p>` : "";
      return `
        <article class="history-item">
          <div class="history-head">
            <strong>${escapeHtml(item.action || "Update")}</strong>
            <span>${formatDate(item.created_at)}</span>
          </div>
          <p>By: ${escapeHtml(item.performed_by || "System")}</p>
          ${detail}
        </article>
      `;
    })
    .join("");
}

function selectRole(role) {
  closeRoleSelectionModal();
  if (role === "head") {
    openHeadFormModal();
  } else if (role === "member") {
    openMemberJoinModal();
  }
}

function openRoleSelectionModal() {
  const modal = document.getElementById("roleSelectionModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeRoleSelectionModal() {
  const modal = document.getElementById("roleSelectionModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

async function submitHeadForm(e) {
  e?.preventDefault();

  const address = document.getElementById("householdAddress")?.value || "";
  const street = document.getElementById("householdStreet")?.value || "";
  const city = document.getElementById("householdCity")?.value || "";
  const province = document.getElementById("householdProvince")?.value || "";

  if (!address || !street || !city || !province) {
    showErrorMessage("Please fill in all address fields.");
    return;
  }

  try {
    const result = await requestJson(`${HOUSEHOLD_API}/info.php`, {
      method: "POST",
      body: JSON.stringify({
        action: "create_household",
        house_number: address,
        street,
        barangay: "Barangay 219",
        city,
        province
      })
    });
    showSuccessMessage(`Household created successfully. Family Code: ${result.data?.family_code || "N/A"}`);
    closeHeadFormModal();
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

function openHeadFormModal() {
  const modal = document.getElementById("headFormModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeHeadFormModal() {
  const modal = document.getElementById("headFormModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
  document.getElementById("headFormContainer")?.reset();
}

async function submitMemberJoin(e) {
  e?.preventDefault();
  const householdSelect = document.getElementById("householdSelect");
  const householdId = householdSelect?.value;
  const familyCode = householdSelect?.selectedOptions?.[0]?.dataset?.familyCode || "";
  const relationshipRaw = document.getElementById("relationshipToHead")?.value;
  const relationship = normalizeRelationship(relationshipRaw);

  if (!householdId || !familyCode || !relationship) {
    showErrorMessage("Please select household and relationship.");
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/info.php`, {
      method: "POST",
      body: JSON.stringify({
        action: "join_household",
        household_id: Number.parseInt(householdId, 10),
        family_code: familyCode,
        relationship_to_head: relationship
      })
    });
    showSuccessMessage("You have successfully joined the household.");
    closeMemberJoinModal();
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

function openMemberJoinModal() {
  const householdSelect = document.getElementById("householdSelect");
  if (householdSelect) {
    householdSelect.innerHTML = '<option value="">-- Select a household --</option>';
    availableHouseholds.forEach((h) => {
      const option = document.createElement("option");
      option.value = h.id;
      option.dataset.familyCode = h.family_code || "";
      option.textContent = `${h.family_code || "No Code"} | ${h.address || ""} (Head: ${h.head_name})`;
      householdSelect.appendChild(option);
    });
  }

  const modal = document.getElementById("memberJoinModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeMemberJoinModal() {
  const modal = document.getElementById("memberJoinModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
  document.getElementById("memberFormContainer")?.reset();
}

function populateAddMemberResidents() {
  const residentSelect = document.getElementById("newMemberResident");
  if (!residentSelect) return;

  residentSelect.innerHTML = '<option value="">-- Select resident --</option>';
  availableResidents
    .filter((r) => Number(r.resident_id) !== Number(RESIDENT_SESSION_ID))
    .forEach((r) => {
      const option = document.createElement("option");
      option.value = r.resident_id;
      option.dataset.gender = (r.gender || "").toLowerCase();
      option.dataset.birthDate = r.birth_date || "";
      option.dataset.residentCode = r.resident_code || "";
      option.textContent = `${r.last_name}, ${r.first_name}${r.middle_name ? ` ${r.middle_name}` : ""}`;
      residentSelect.appendChild(option);
    });
}

function openAddMemberModal() {
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage("Only household head can add members.");
    return;
  }
  populateAddMemberResidents();
  const modal = document.getElementById("addMemberModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
  document.getElementById("addMemberForm")?.reset();
}

function closeAddMemberModal() {
  const modal = document.getElementById("addMemberModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

async function submitAddMember(e) {
  e?.preventDefault();
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage("Only household head can add members.");
    return;
  }

  const residentId = Number.parseInt(document.getElementById("newMemberResident")?.value || "0", 10);
  const dob = document.getElementById("newMemberDOB")?.value || "";
  const gender = (document.getElementById("newMemberGender")?.value || "").toLowerCase();
  const relationship = normalizeRelationship(document.getElementById("newMemberRelationship")?.value || "");

  if (!residentId || !dob || !gender || !relationship) {
    showErrorMessage("Please complete all required fields.");
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/member.php`, {
      method: "POST",
      body: JSON.stringify({
        resident_id: residentId,
        date_of_birth: dob,
        gender,
        relationship_to_head: relationship,
        civil_status: "single"
      })
    });
    closeAddMemberModal();
    showSuccessMessage("Member saved successfully.");
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

function editMember(memberId) {
  const member = currentMembers.find((m) => Number(m.id) === Number(memberId));
  if (!member) {
    showErrorMessage("Member not found.");
    return;
  }

  document.getElementById("editMemberId").value = memberId;
  document.getElementById("editMemberName").value = member.name || "";
  document.getElementById("editMemberDOB").value = member.date_of_birth || "";
  document.getElementById("editMemberGender").value = (member.sex || "").toLowerCase();
  document.getElementById("editMemberRelationship").value = normalizeRelationship(member.relationship_to_head || "Relative");

  const rel = document.getElementById("editMemberRelationship");
  const allowRelationshipEdit = !!currentHouseholdContext?.is_head;
  if (rel) rel.disabled = !allowRelationshipEdit;

  const modal = document.getElementById("editMemberModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeEditMemberModal() {
  const modal = document.getElementById("editMemberModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

async function submitEditMember(e) {
  e?.preventDefault();

  const memberId = Number.parseInt(document.getElementById("editMemberId")?.value || "0", 10);
  const dob = document.getElementById("editMemberDOB")?.value || "";
  const gender = (document.getElementById("editMemberGender")?.value || "").toLowerCase();
  const relationship = normalizeRelationship(document.getElementById("editMemberRelationship")?.value || "Relative");

  if (!memberId || !dob || !gender) {
    showErrorMessage("Please complete required fields.");
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/member.php`, {
      method: "PUT",
      body: JSON.stringify({
        member_id: memberId,
        date_of_birth: dob,
        gender,
        relationship_to_head: relationship,
        civil_status: "single"
      })
    });
    closeEditMemberModal();
    showSuccessMessage("Member updated successfully.");
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

async function assignNewHead(memberId) {
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage("Only current head can reassign household head.");
    return;
  }

  if (!confirm("Assign this member as the new household head?")) {
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/member.php`, {
      method: "PUT",
      body: JSON.stringify({ action: "assign_head", member_id: memberId })
    });
    showSuccessMessage("Household head updated.");
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

async function removeMember(memberId) {
  if (!confirm("Remove this member from household?")) {
    return;
  }
  try {
    await requestJson(`${HOUSEHOLD_API}/member.php`, {
      method: "DELETE",
      body: JSON.stringify({ member_id: memberId })
    });
    showSuccessMessage("Member removed successfully.");
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

async function leaveHousehold() {
  if (!confirm("Leave this household?")) {
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/info.php`, {
      method: "POST",
      body: JSON.stringify({ action: "leave_household" })
    });
    showSuccessMessage("You have left the household.");
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

async function editHousehold() {
  if (!currentHouseholdContext?.is_head || !currentHouseholdData) {
    showErrorMessage("Only household head can update overview details.");
    return;
  }

  const form = document.getElementById("overviewFormContainer");
  if (form) {
    form.reset();
  }

  const householdType = document.getElementById("overviewHouseholdType");
  const housingStatus = document.getElementById("overviewHousingStatus");
  const yearsResidency = document.getElementById("overviewYearsResidency");
  const emergencyName = document.getElementById("overviewEmergencyName");
  const emergencyRelationship = document.getElementById("overviewEmergencyRelationship");
  const emergencyNumber = document.getElementById("overviewEmergencyNumber");

  if (householdType) householdType.value = currentHouseholdData.household_type || "nuclear";
  if (housingStatus) housingStatus.value = currentHouseholdData.housing_status || "owned";
  if (yearsResidency) yearsResidency.value = String(currentHouseholdData.years_of_residency ?? 0);
  if (emergencyName) emergencyName.value = currentEmergencyContact.name || "";
  if (emergencyRelationship) emergencyRelationship.value = currentEmergencyContact.relationship || "";
  if (emergencyNumber) emergencyNumber.value = currentEmergencyContact.contact_number || "";

  const modal = document.getElementById("overviewUpdateModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeOverviewUpdateModal() {
  const modal = document.getElementById("overviewUpdateModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

async function submitOverviewUpdate(e) {
  e?.preventDefault();

  if (!currentHouseholdContext?.is_head || !currentHouseholdData) {
    showErrorMessage("Only household head can update overview details.");
    return;
  }

  const householdType = (document.getElementById("overviewHouseholdType")?.value || "").trim();
  const housingStatus = (document.getElementById("overviewHousingStatus")?.value || "").trim();
  const yearsRaw = (document.getElementById("overviewYearsResidency")?.value || "").trim();
  const emergencyName = (document.getElementById("overviewEmergencyName")?.value || "").trim();
  const emergencyRelationship = (document.getElementById("overviewEmergencyRelationship")?.value || "").trim();
  const emergencyNumber = (document.getElementById("overviewEmergencyNumber")?.value || "").trim();

  const years = Number.parseInt(yearsRaw, 10);
  if (!householdType || !housingStatus || !Number.isFinite(years) || years < 0 || years > 120) {
    showErrorMessage("Please provide valid household type, housing status, and residency years (0-120).");
    return;
  }

  if (emergencyNumber && !/^[0-9+\-\s()]+$/.test(emergencyNumber)) {
    showErrorMessage("Emergency contact number contains invalid characters.");
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/info.php`, {
      method: "POST",
      body: JSON.stringify({
        action: "update_household_meta",
        household_type: householdType,
        housing_status: housingStatus,
        years_of_residency: years,
        emergency_contact_name: emergencyName,
        emergency_contact_relationship: emergencyRelationship,
        emergency_contact_number: emergencyNumber
      })
    });

    closeOverviewUpdateModal();
    showSuccessMessage("Household overview updated.");
    await loadHouseholdInfo();
  } catch (error) {
    showErrorMessage(error.message);
  }
}

function closeAllModals() {
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.style.display = "none";
  });
  document.body.style.overflow = "auto";
}

function showErrorMessage(message) {
  const container = document.getElementById("messageContainer");
  if (!container) return;

  const msgDiv = document.createElement("div");
  msgDiv.className = "alert alert-danger";
  msgDiv.innerHTML = `
    <i class="fa-solid fa-exclamation-circle"></i>
    <div>
      <strong>Error</strong>
      <p>${escapeHtml(message)}</p>
    </div>
    <button class="close-alert" onclick="this.parentElement.remove();">&times;</button>
  `;
  container.prepend(msgDiv);

  setTimeout(() => {
    if (msgDiv.parentElement) msgDiv.remove();
  }, 5000);
}

function showSuccessMessage(message) {
  const container = document.getElementById("messageContainer");
  if (!container) return;

  const msgDiv = document.createElement("div");
  msgDiv.className = "alert alert-success";
  msgDiv.innerHTML = `
    <i class="fa-solid fa-check-circle"></i>
    <div>
      <strong>Success</strong>
      <p>${escapeHtml(message)}</p>
    </div>
    <button class="close-alert" onclick="this.parentElement.remove();">&times;</button>
  `;
  container.prepend(msgDiv);

  setTimeout(() => {
    if (msgDiv.parentElement) msgDiv.remove();
  }, 5000);
}
