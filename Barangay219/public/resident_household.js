/**
 * Resident Household Management
 */

let currentHouseholdContext = null;
let currentHouseholdData = null;
let currentMembers = [];
let availableResidents = [];
let pendingTransferHeadTarget = null; // { member_id?: number, resident_id?: number }

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

function normalizeRelationship(value) {
  if (!value) return "Relative";
  return RELATIONSHIP_MAP[value] || value;
}

function isHeadRelationship(value) {
  const rel = (value || "").toString().trim().toLowerCase();
  return rel === "head" || rel === "head of family" || rel === "family head" || rel === "household head";
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
    ["openManageMembers", openManageMembersModal],
    ["closeManageMembersModal", closeManageMembersModal],
    ["closeTransferHeadReasonModal", closeTransferHeadReasonModal],
    ["submitTransferHeadReason", submitTransferHeadReason],
    ["openAddDependentModal", openAddDependentModal],
    ["closeAddDependentModal", closeAddDependentModal],
    ["closeEditMemberModal", closeEditMemberModal],
    ["submitEditMember", submitEditMember],
    ["openUpdateOverview", editHousehold],
    ["closeOverviewModal", closeOverviewUpdateModal],
    ["submitOverviewUpdate", submitOverviewUpdate],
    ["leaveHousehold", leaveHousehold]
  ].forEach(([action, handler]) => {
    document.querySelectorAll(`[data-action="${action}"]`).forEach((btn) => {
      btn.addEventListener("click", handler);
    });
  });

  // Explicit bindings for dynamically shown buttons (safety net).
  const addDepBtn = document.getElementById("btnAddDependent");
  if (addDepBtn) {
    addDepBtn.addEventListener("click", (e) => {
      e.preventDefault();
      openAddDependentModal();
    });
  }
  const addDependentForm = document.getElementById("addDependentForm");
  if (addDependentForm) {
    addDependentForm.addEventListener("submit", (e) => {
      e.preventDefault();
      submitAddDependent(e);
    });
  }

  document.querySelectorAll(".modal-backdrop").forEach((backdrop) => {
    backdrop.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal-backdrop")) {
        closeAllModals();
      }
    });
  });

  const householdSelect = document.getElementById("householdSelect");
  if (householdSelect) {
    householdSelect.remove();
  }

  const reasonSel = document.getElementById("transferHeadReason");
  reasonSel?.addEventListener("change", () => {
    const otherGroup = document.getElementById("transferHeadReasonOtherGroup");
    const otherInput = document.getElementById("transferHeadReasonOther");
    const isOther = (reasonSel.value || "") === "Others";
    if (otherGroup) otherGroup.style.display = isOther ? "block" : "none";
    if (!isOther && otherInput) otherInput.value = "";
  });

  // Add member removed on resident side
}

async function requestJson(url, options) {
  const response = await fetch(url, {
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    ...options
  });
  let result;
  try {
    result = await response.json();
  } catch (e) {
    throw new Error(response.statusText || "Request failed");
  }
  if (!response.ok || !result.success) {
    throw new Error((result && result.message) || "Request failed");
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
    availableResidents = data.available_residents || [];

    document.getElementById("contentContainer").style.display = "block";
    document.getElementById("loadingContainer").style.display = "none";

    if (!currentHouseholdContext || !currentHouseholdContext.household_id) {
      document.getElementById("householdDetailsPanel").style.display = "none";
      document.getElementById("membersPanel").style.display = "none";
      document.getElementById("historyPanel").style.display = "none";
      openRoleSelectionModal();
      return;
    }

    closeRoleSelectionModal();
    document.getElementById("householdDetailsPanel").style.display = "block";
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

  const hhCodeEl = document.getElementById("displayHouseholdIdCode");
  if (hhCodeEl) hhCodeEl.textContent = household.household_id_code || household.household_code || "--";
  const fhCodeEl = document.getElementById("displayFamilyHeadCode");
  if (fhCodeEl) fhCodeEl.textContent = household.family_head_code || "--";
  document.getElementById("displayHead").textContent = household.head_name || "--";
  document.getElementById("displayAddress").textContent = household.address || "--";
  document.getElementById("displayHouseholdType").textContent = household.household_type || "--";
  document.getElementById("displayHousingStatus").textContent = household.housing_status || "--";
  document.getElementById("displayYearsResidency").textContent = `${household.years_of_residency ?? 0} year(s)`;
  document.getElementById("displayMembers").textContent = household.total_members ?? stats.total ?? members.length;
  document.getElementById("displayCreated").textContent = formatDate(household.created_at);

  const leaveBtn = document.getElementById("leaveHouseholdBtn");
  const newUpdateOverviewBtn = document.getElementById("btnUpdateOverview");
  const newManageBtn = document.getElementById("btnManageMembers");
  if (newUpdateOverviewBtn) newUpdateOverviewBtn.style.display = isHead ? "inline-flex" : "none";
  if (leaveBtn) leaveBtn.style.display = isHead ? "none" : "inline-flex";
  if (newManageBtn) newManageBtn.style.display = isHead ? "inline-flex" : "none";
  const addDepBtn = document.getElementById("btnAddDependent");
  if (addDepBtn) addDepBtn.style.display = isHead ? "inline-flex" : "none";

  renderMembersTable(members, isHead);
  renderHistory(data.history_logs || []);
}

function renderMembersTable(members, isHead) {
  const tbody = document.getElementById("membersTableBody");
  if (!tbody) return;

  if (!members.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="empty-row">No members found.</td></tr>';
    return;
  }

  tbody.innerHTML = members
    .map((member) => {
      const isSelf = !!member.is_self;
      const memberId = Number(member.id || 0);
      const isMemberHead = isHeadRelationship(member.relationship_to_head) || (Number(member.resident_id || 0) === Number(currentHouseholdData?.family_head_id || 0));
      const canRemove = memberId > 0 && !member.readonly && isHead && !isSelf && !isMemberHead;
      const canAssignHead = memberId > 0 && !member.readonly && isHead && !isSelf;
      const roleBadge = isMemberHead
        ? '<span class="badge bg-primary">Head</span>'
        : '<span class="badge bg-light text-dark border">' + escapeHtml(member.relationship_to_head || "Member") + '</span>';

      return `
        <tr>
          <td>${escapeHtml(member.name || "Unknown")}${isSelf ? ' <span class="self-tag">(You)</span>' : ""}</td>
          <td>${roleBadge}</td>
          <td>${escapeHtml(member.sex || "-")}</td>
          <td>${member.age ?? calculateAge(member.date_of_birth) ?? "-"}</td>
          <td><span class="badge text-bg-light border">${escapeHtml(member.status || "Active")}</span></td>
        </tr>
      `;
    })
    .join("");
}

function clearAddDependentFormError() {
  const el = document.getElementById("addDependentFormError");
  if (!el) return;
  el.textContent = "";
  el.classList.remove("is-visible");
}

function showAddDependentFormError(message) {
  const el = document.getElementById("addDependentFormError");
  if (el) {
    el.textContent = message;
    el.classList.add("is-visible");
    return;
  }
  showErrorMessage(message);
}

function openAddDependentModal() {
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage("Only household head can add members.");
    return;
  }
  const modal = document.getElementById("addDependentModal");
  if (!modal) return;
  clearAddDependentFormError();
  const form = document.getElementById("addDependentForm");
  form?.reset();
  const bd = document.getElementById("depBirthDate");
  if (bd) {
    const t = new Date();
    const yyyy = t.getFullYear();
    const mm = String(t.getMonth() + 1).padStart(2, "0");
    const dd = String(t.getDate()).padStart(2, "0");
    bd.max = `${yyyy}-${mm}-${dd}`;
  }
  modal.style.display = "flex";
  document.body.style.overflow = "hidden";
}

function closeAddDependentModal() {
  const modal = document.getElementById("addDependentModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

async function submitAddDependent(e) {
  e?.preventDefault();
  clearAddDependentFormError();
  if (typeof HOUSEHOLD_API === "undefined" || !HOUSEHOLD_API) {
    showAddDependentFormError("Household service URL is missing. Refresh the page and try again.");
    return;
  }
  if (!currentHouseholdContext?.is_head) {
    showAddDependentFormError("Only the household head can add family members.");
    return;
  }

  const payload = {
    first_name: (document.getElementById("depFirstName")?.value || "").trim(),
    middle_name: (document.getElementById("depMiddleName")?.value || "").trim(),
    last_name: (document.getElementById("depLastName")?.value || "").trim(),
    suffix: (document.getElementById("depSuffix")?.value || "").trim(),
    birth_date: (document.getElementById("depBirthDate")?.value || "").trim(),
    gender: (document.getElementById("depGender")?.value || "").trim(),
    relationship_to_head: (document.getElementById("depRelationship")?.value || "").trim()
  };

  if (!payload.first_name || !payload.last_name || !payload.birth_date || !payload.gender || !payload.relationship_to_head) {
    showAddDependentFormError("Please complete all required fields.");
    return;
  }

  if (payload.birth_date) {
    const parsed = new Date(`${payload.birth_date}T12:00:00`);
    const endOfToday = new Date();
    endOfToday.setHours(23, 59, 59, 999);
    if (!Number.isNaN(parsed.getTime()) && parsed > endOfToday) {
      showAddDependentFormError("Birth date cannot be in the future. Use today or an earlier date.");
      return;
    }
  }

  const submitBtn = document.getElementById("submitAddDependentBtn");
  try {
    if (submitBtn) submitBtn.disabled = true;
    await requestJson(`${HOUSEHOLD_API}/dependent.php`, { method: "POST", body: JSON.stringify(payload) });
    clearAddDependentFormError();
    closeAddDependentModal();
    await loadHouseholdInfo();
    showSuccessMessage("Family member added.");
  } catch (error) {
    showAddDependentFormError(error.message || "Failed to add family member.");
  } finally {
    if (submitBtn) submitBtn.disabled = false;
  }
}

function openManageMembersModal() {
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage("Only household head can manage members.");
    return;
  }
  renderManageMembersTable(currentMembers || []);
  const modal = document.getElementById("manageMembersModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeManageMembersModal() {
  const modal = document.getElementById("manageMembersModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

function renderManageMembersTable(members) {
  const tbody = document.getElementById("manageMembersTableBody");
  if (!tbody) return;
  if (!members.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="empty-row">No members found.</td></tr>';
    return;
  }

  const currentUserFc = ((members || []).find(m => m.is_self)?.family_code ?? "").toString().trim();
  tbody.innerHTML = members.map((member) => {
    const isSelf = !!member.is_self;
    const memberId = Number(member.id || 0);
      const isMemberHead = isHeadRelationship(member.relationship_to_head) || (Number(member.resident_id || 0) === Number(currentHouseholdData?.family_head_id || 0));

    const memberFc = (member.family_code || "").toString().trim();
    // Dependents/minors often have no family_code yet; co-heads use distinct codes. Allow transfer when
    // codes match OR either side has no code (same household list is already scoped to this household).
    const familyGroupMatches =
      currentUserFc === "" ||
      memberFc === "" ||
      memberFc === currentUserFc;
    const ageRaw = member.age ?? calculateAge(member.date_of_birth || member.birth_date);
    const ageNum = typeof ageRaw === "number" && !Number.isNaN(ageRaw) ? ageRaw : Number.parseInt(String(ageRaw), 10);
    const meetsHeadMinAge = Number.isFinite(ageNum) && ageNum >= 18;
    const canAssignHead = !isSelf && !isMemberHead && familyGroupMatches && meetsHeadMinAge;
    const canRemove = !isSelf && !isMemberHead;
    const age = member.age ?? calculateAge(member.date_of_birth || member.birth_date) ?? "-";
    const birthDateLabel = formatDate(member.date_of_birth || member.birth_date);
    const roleBadge = isMemberHead
      ? '<span class="badge bg-primary">Head</span>'
      : '<span class="badge bg-light text-dark border">Member</span>';

    const actions = [];
    if (canAssignHead) {
      if (memberId > 0) {
        actions.push(`<button class="btn-action btn-sm" onclick="assignNewHead(${memberId})" title="Assign as Head" aria-label="Assign as Head"><i class="bi bi-person-check"></i></button>`);
      } else {
        actions.push(`<button class="btn-action btn-sm" onclick="assignNewHeadByResident(${Number(member.resident_id || 0)})" title="Assign as Head" aria-label="Assign as Head"><i class="bi bi-person-check"></i></button>`);
      }
    }
    if (canRemove) {
      if (memberId > 0) {
        actions.push(`<button class="btn-action btn-sm btn-danger" onclick="removeMember(${memberId})" title="Remove" aria-label="Remove"><i class="bi bi-person-dash"></i></button>`);
      } else {
        actions.push(`<button class="btn-action btn-sm btn-danger" onclick="removeMemberByResident(${Number(member.resident_id || 0)})" title="Remove" aria-label="Remove"><i class="bi bi-person-dash"></i></button>`);
      }
    }
    if (!actions.length) {
      actions.push('<span class="text-muted">No action</span>');
    }

    return `
      <tr>
        <td>${escapeHtml(member.name || "Unknown")}${isSelf ? ' <span class="self-tag">(You)</span>' : ""}</td>
        <td>${roleBadge}</td>
        <td>${escapeHtml(age)}</td>
        <td>${escapeHtml(birthDateLabel)}</td>
        <td>${actions.join(" ")}</td>
      </tr>
    `;
  }).join("");
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
  if (role === "member") {
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
  const householdType = (document.getElementById("headFormHouseholdType")?.value || "").trim();

  if (!address || !street || !city || !province || !householdType) {
    showErrorMessage("Please fill in all required fields including Household Type.");
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
  const familyHeadCode = (document.getElementById("familyHeadCodeInput")?.value || "").trim().toUpperCase();
  if (!familyHeadCode) {
    showErrorMessage("Please enter the Family Head Code.");
    return;
  }
  const relationship = (document.getElementById("joinRelationshipSelect")?.value || "").trim();
  if (!relationship) {
    showErrorMessage("Please select your relationship to the head.");
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/info.php`, {
      method: "POST",
      body: JSON.stringify({
        action: "join_household",
        family_head_code: familyHeadCode,
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
  const codeInput = document.getElementById("familyHeadCodeInput");
  if (codeInput) codeInput.value = "";
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

// Add member modal/actions removed on resident side.

function editMember(memberId) {
  const member = currentMembers.find((m) => Number(m.id) === Number(memberId));
  if (!member) {
    showErrorMessage("Member not found.");
    return;
  }
  const isMemberHead =
    isHeadRelationship(member.relationship_to_head) ||
    (Number(member.resident_id || 0) === Number(currentHouseholdData?.family_head_id || 0));
  if (isMemberHead) {
    showErrorMessage("Head of the family information cannot be edited here.");
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

  pendingTransferHeadTarget = { member_id: Number(memberId || 0) };
  openTransferHeadReasonModal();
}

function assignNewHeadByResident(residentId) {
  if (!currentHouseholdContext?.is_head) {
    showErrorMessage("Only current head can reassign household head.");
    return;
  }
  pendingTransferHeadTarget = { resident_id: Number(residentId || 0) };
  openTransferHeadReasonModal();
}

function openTransferHeadReasonModal() {
  const modal = document.getElementById("transferHeadReasonModal");
  const form = document.getElementById("transferHeadReasonForm");
  if (form) form.reset();
  const otherGroup = document.getElementById("transferHeadReasonOtherGroup");
  if (otherGroup) otherGroup.style.display = "none";
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

function closeTransferHeadReasonModal() {
  const modal = document.getElementById("transferHeadReasonModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
  pendingTransferHeadTarget = null;
}

async function submitTransferHeadReason(e) {
  e?.preventDefault();
  if (!pendingTransferHeadTarget || (!pendingTransferHeadTarget.member_id && !pendingTransferHeadTarget.resident_id)) {
    showErrorMessage("No member selected.");
    return;
  }
  const reason = (document.getElementById("transferHeadReason")?.value || "").trim();
  const other = (document.getElementById("transferHeadReasonOther")?.value || "").trim();
  let finalReason = reason;
  if (!finalReason) {
    showErrorMessage("Please select a reason.");
    return;
  }
  if (finalReason === "Others") {
    if (!other) {
      showErrorMessage("Please specify the reason.");
      return;
    }
    finalReason = other;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/member.php`, {
      method: "PUT",
      body: JSON.stringify({ action: "assign_head", ...pendingTransferHeadTarget, reason: finalReason })
    });
    closeTransferHeadReasonModal();
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

async function removeMemberByResident(residentId) {
  if (!confirm("Remove this member from household?")) {
    return;
  }
  try {
    await requestJson(`${HOUSEHOLD_API}/member.php`, {
      method: "DELETE",
      body: JSON.stringify({ resident_id: residentId })
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

  if (householdType) {
    const val = (currentHouseholdData.household_type || "").toString().trim();
    const legacy = ["nuclear", "extended", "single_parent", "others"];
    const isLegacy = legacy.includes(val.toLowerCase());
    const hasOpt = Array.from(householdType.options).some(o => o.value === val);
    householdType.value = (val && !isLegacy && hasOpt) ? val : "";
  }
  if (housingStatus) housingStatus.value = currentHouseholdData.housing_status || "owned";
  if (yearsResidency) yearsResidency.value = String(currentHouseholdData.years_of_residency ?? 0);

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
  const years = Number.parseInt(yearsRaw, 10);
  if (!housingStatus || !Number.isFinite(years) || years < 0 || years > 120) {
    showErrorMessage("Please provide valid housing status and residency years (0-120).");
    return;
  }

  try {
    await requestJson(`${HOUSEHOLD_API}/info.php`, {
      method: "POST",
      body: JSON.stringify({
        action: "update_household_meta",
        household_type: householdType || null,
        housing_status: housingStatus,
        years_of_residency: years,
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
  // Close only custom (non-Bootstrap) modals.
  document.querySelectorAll(".modal:not(.fade)").forEach((modal) => {
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
    <i class="bi bi-exclamation-circle"></i>
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
    <i class="bi bi-check-circle"></i>
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
