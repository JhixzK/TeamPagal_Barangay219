<?php
/**
 * E-Barangay Information Management System
 * Resident Household Information
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Require login and check if user is a resident
requireLogin();

if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

// Use officials layout components for consistent header/sidebar
$page_title = 'Household Information';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

// Get user information
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? 'Resident';
$email = $_SESSION['email'] ?? '';
$residentId = $_SESSION['resident_id'] ?? null;

// Get resident name from database
$db = Database::getInstance();
$residentName = $username;

if ($residentId) {
    $sql = "SELECT first_name, middle_name, last_name FROM residents WHERE id = ?";
    $resident = $db->fetchOne($sql, [$residentId]);
    if ($resident) {
        $residentName = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
    }
}

// Cache-busting for JS and CSS
$jsVersion = urlencode((string)@filemtime(__DIR__ . '/resident_household.js'));
$cssVersion = urlencode((string)@filemtime(__DIR__ . '/resident_household.css'));
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>resident_household.css?v=<?php echo $cssVersion; ?>">

<div class="main-content module-page resident-household-page resident-theme">
    <div class="container-fluid">
    <div class="module-hero dashboard-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div class="hero-copy">
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
                    <h2 class="mb-1"><i class="bi bi-house-door me-2"></i>Household Information</h2>
          <p class="hero-subtitle mb-0">View your household overview, membership details, and history updates.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
          <div class="hero-chips mt-2">
            <span class="hero-chip"><i class="bi bi-person-check me-1"></i>Resident View</span>
            <span class="hero-chip"><i class="bi bi-people me-1"></i>Household Module</span>
          </div>
                </div>
            </div>
        </div>

      <!-- Loading Container -->
      <div id="loadingContainer" class="loading-container">
        <div class="spinner"></div>
        <p>Loading household information...</p>
      </div>

      <!-- Main Content Container -->
      <div id="contentContainer" style="display: none;">
        <section class="panel panel-primary" id="householdDetailsPanel" style="display: none;">
          <div class="panel-header">
            <div>
              <h2>Household Overview</h2>
              <p class="panel-subtitle">Core household profile and identification details</p>
            </div>
            <div class="action-row">
              <button class="btn-secondary btn-small" id="leaveHouseholdBtn" data-action="leaveHousehold" style="display:none;">
                <i class="bi bi-box-arrow-right"></i> Leave Household
              </button>
              <button class="btn-primary btn-small" id="btnUpdateOverview" data-action="openUpdateOverview" style="display:none;">
                <i class="bi bi-pencil-square"></i> Update Overview
              </button>
            </div>
          </div>
          <div class="panel-body">
            <div class="details-grid">
              <div class="detail-item"><span class="detail-label">Household ID Code</span><span class="detail-value" id="displayHouseholdIdCode">--</span></div>
              <div class="detail-item"><span class="detail-label">Family Head Code</span><span class="detail-value" id="displayFamilyHeadCode">--</span></div>
              <div class="detail-item"><span class="detail-label">Head of Household</span><span class="detail-value" id="displayHead">--</span></div>
              <div class="detail-item"><span class="detail-label">Complete Address</span><span class="detail-value" id="displayAddress">--</span></div>
              <div class="detail-item"><span class="detail-label">Household Type</span><span class="detail-value" id="displayHouseholdType">--</span></div>
              <div class="detail-item"><span class="detail-label">Housing Status</span><span class="detail-value" id="displayHousingStatus">--</span></div>
              <div class="detail-item"><span class="detail-label">Years of Residency</span><span class="detail-value" id="displayYearsResidency">--</span></div>
              <div class="detail-item"><span class="detail-label">Total Members</span><span class="detail-value" id="displayMembers">0</span></div>
              <div class="detail-item"><span class="detail-label">Created</span><span class="detail-value" id="displayCreated">--</span></div>
            </div>

            <!-- Program Tags removed on resident side -->
          </div>
        </section>

        <section class="panel panel-primary" id="membersPanel" style="display: none;">
          <div class="panel-header">
            <div>
              <h2>Household Members</h2>
              <p class="panel-subtitle">Status, profile details, and role-based member actions</p>
            </div>
            <div class="action-row">
              <button class="btn-primary btn-small" id="btnManageMembers" data-action="openManageMembers" style="display:none;">
                <i class="bi bi-people"></i> Manage Members
              </button>
            </div>
          </div>
          <div class="panel-body table-responsive">
            <table class="members-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Household Role</th>
                  <th>Sex</th>
                  <th>Age</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="membersTableBody"></tbody>
            </table>
          </div>
        </section>

        <section class="panel panel-primary" id="historyPanel" style="display: none;">
          <div class="panel-header">
            <h2>Household History Log</h2>
          </div>
          <div class="panel-body">
            <div id="historyList" class="history-list"></div>
          </div>
        </section>

      </div><!-- End contentContainer -->

      <!-- MODALS (OUTSIDE contentContainer) -->

      <!-- Role Selection Modal -->
      <div id="roleSelectionModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Join Household</h3>
            <button class="modal-close" data-action="closeRoleModal">&times;</button>
          </div>
          <div class="modal-body">
            <p>You don't have a household yet. Enter the Family Head Code to join an existing household.</p>
            <div class="role-selection-grid">
              <div class="role-card" data-role="member">
                <i class="bi bi-people"></i>
                <h4>Household Member</h4>
                <p>Join using Family Head Code</p>
                <p class="role-desc">Example: FH-01234</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Head of Household Form Modal -->
      <div id="headFormModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Create Household</h3>
            <button class="modal-close" data-action="closeHeadModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="headFormContainer">
              <div class="form-group">
                <label for="householdAddress">Household Address *</label>
                <textarea id="householdAddress" placeholder="Enter complete household address" required></textarea>
              </div>
              <div class="form-group">
                <label for="householdStreet">Street / Block *</label>
                <input type="text" id="householdStreet" placeholder="e.g., Espada Street, Block 23" required>
              </div>
              <div class="form-group">
                <label for="householdCity">City / Municipality *</label>
                <input type="text" id="householdCity" placeholder="e.g., Manila" value="Manila" required>
              </div>
              <div class="form-group">
                <label for="householdProvince">Province *</label>
                <input type="text" id="householdProvince" placeholder="e.g., Metro Manila" value="Metro Manila" required>
              </div>
              <div class="form-group">
                <label for="headFormHouseholdType">Household Type *</label>
                <select id="headFormHouseholdType" required>
                  <option value="">Select Household Type</option>
                  <option value="Family Household">Family Household</option>
                  <option value="Couple Only">Couple Only</option>
                  <option value="Single Inhabitant">Single Inhabitant</option>
                  <option value="Non-Relative Household (Shared / Boarders)">Non-Relative Household (Shared / Boarders)</option>
                  <option value="Other (Specify)">Other (Specify)</option>
                </select>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeHeadModal">Cancel</button>
            <button class="btn-primary" id="submitHeadBtn" data-action="submitHeadForm">Create Household</button>
          </div>
        </div>
      </div>

      <!-- Update Household Overview Modal -->
      <div id="overviewUpdateModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Update Household Overview</h3>
            <button class="modal-close" data-action="closeOverviewModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="overviewFormContainer">
              <div class="form-row">
                <div class="form-group">
                  <label for="overviewHouseholdType">Household Type</label>
                  <select id="overviewHouseholdType">
                    <option value="">Select Household Type</option>
                    <option value="Family Household">Family Household</option>
                    <option value="Couple Only">Couple Only</option>
                    <option value="Single Inhabitant">Single Inhabitant</option>
                    <option value="Non-Relative Household (Shared / Boarders)">Non-Relative Household (Shared / Boarders)</option>
                    <option value="Other (Specify)">Other (Specify)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="overviewHousingStatus">Housing Status *</label>
                  <select id="overviewHousingStatus" required>
                    <option value="owned">Owned</option>
                    <option value="renting">Renting</option>
                    <option value="informal_settler">Informal Settler</option>
                    <option value="government_housing">Government Housing</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label for="overviewYearsResidency">Years of Residency *</label>
                <input type="number" id="overviewYearsResidency" min="0" max="120" required>
              </div>

            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeOverviewModal">Cancel</button>
            <button class="btn-primary" id="submitOverviewUpdateBtn" data-action="submitOverviewUpdate">Save Overview</button>
          </div>
        </div>
      </div>

      <!-- Member Join Modal -->
      <div id="memberJoinModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Join Household</h3>
            <button class="modal-close" data-action="closeMemberModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="memberFormContainer">
              <div class="form-group">
                <label for="familyHeadCodeInput">Family Head Code *</label>
                <input type="text" id="familyHeadCodeInput" placeholder="FH-01234" maxlength="9" required>
                <p class="form-hint">Ask your household head for the Family Head Code.</p>
              </div>
              <div class="form-group">
                <label for="joinRelationshipSelect">Relationship to Head *</label>
                <select id="joinRelationshipSelect" required>
                  <option value="">Select Relationship</option>
                  <option value="Spouse">Spouse</option>
                  <option value="Son">Son</option>
                  <option value="Daughter">Daughter</option>
                  <option value="Mother">Mother</option>
                  <option value="Father">Father</option>
                  <option value="Grandmother">Grandmother</option>
                  <option value="Grandfather">Grandfather</option>
                  <option value="Brother">Brother</option>
                  <option value="Sister">Sister</option>
                  <option value="Grandchild">Grandchild</option>
                  <option value="Grandparent">Grandparent</option>
                  <option value="Son-in-Law">Son-in-Law</option>
                  <option value="Daughter-in-Law">Daughter-in-Law</option>
                  <option value="Sibling-in-Law">Sibling-in-Law</option>
                  <option value="Nephew">Nephew</option>
                  <option value="Niece">Niece</option>
                  <option value="Uncle">Uncle</option>
                  <option value="Aunt">Aunt</option>
                  <option value="Cousin">Cousin</option>
                  <option value="Boarder">Boarder</option>
                  <option value="Tenant">Tenant</option>
                  <option value="Helper">Helper</option>
                  <option value="Non-Relative">Non-Relative</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeMemberModal">Cancel</button>
            <button class="btn-primary" id="submitMemberBtn" data-action="submitMemberJoin">Join Household</button>
          </div>
        </div>
      </div>

      <!-- Manage Members Modal (Head Only) - Fresh custom modal -->
      <div id="manageMembersModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content modal-lg">
          <div class="modal-header">
            <h3><i class="bi bi-people me-2"></i>Manage Members</h3>
            <div class="d-flex align-items-center gap-2">
              <button type="button" class="btn-primary btn-small" id="btnAddDependent" data-action="openAddDependentModal" style="display:none;">
                <i class="bi bi-person-plus"></i> Add Family Member
              </button>
              <button class="modal-close" data-action="closeManageMembersModal">&times;</button>
            </div>
          </div>
          <div class="modal-body">
            <div class="text-muted small mb-2">Manage household member actions here. The household head cannot be removed.</div>
            <div class="table-responsive">
              <table class="members-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Household Role</th>
                    <th>Age</th>
                    <th>Birth Date</th>
                    <th style="width:200px;">Actions</th>
                  </tr>
                </thead>
                <tbody id="manageMembersTableBody"></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeManageMembersModal">Close</button>
          </div>
        </div>
      </div>

      <!-- Transfer Head Reason Modal -->
      <div id="transferHeadReasonModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Transfer Head Role</h3>
            <button class="modal-close" data-action="closeTransferHeadReasonModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="transferHeadReasonForm">
              <div class="form-group">
                <label for="transferHeadReason">Reason *</label>
                <select id="transferHeadReason" required>
                  <option value="">-- Select reason --</option>
                  <option value="Work relocation">Work relocation</option>
                  <option value="Health condition">Health condition</option>
                  <option value="Travel / long absence">Travel / long absence</option>
                  <option value="Separation / family arrangement">Separation / family arrangement</option>
                  <option value="Deceased">Deceased</option>
                  <option value="Others">Others</option>
                </select>
              </div>
              <div class="form-group" id="transferHeadReasonOtherGroup" style="display:none;">
                <label for="transferHeadReasonOther">Specify (Others) *</label>
                <input type="text" id="transferHeadReasonOther" maxlength="200" placeholder="Enter reason">
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeTransferHeadReasonModal">Cancel</button>
            <button class="btn-primary" id="submitTransferHeadReasonBtn" data-action="submitTransferHeadReason">Continue</button>
          </div>
        </div>
      </div>

      <!-- Add Family Member Modal (Head Only) -->
      <div id="addDependentModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3><i class="bi bi-person-plus me-2"></i>Add Family Member</h3>
            <button class="modal-close" data-action="closeAddDependentModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="addDependentForm" novalidate>
              <div id="addDependentFormError" class="add-dependent-form-error" role="alert"></div>
              <div class="form-row">
                <div class="form-group">
                  <label for="depFirstName">First Name *</label>
                  <input type="text" id="depFirstName" maxlength="100" required>
                </div>
                <div class="form-group">
                  <label for="depMiddleName">Middle Name</label>
                  <input type="text" id="depMiddleName" maxlength="100">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="depLastName">Last Name *</label>
                  <input type="text" id="depLastName" maxlength="100" required>
                </div>
                <div class="form-group">
                  <label for="depSuffix">Suffix</label>
                  <input type="text" id="depSuffix" maxlength="10" placeholder="Jr., Sr.">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="depBirthDate">Birth Date *</label>
                  <input type="date" id="depBirthDate" required>
                </div>
                <div class="form-group">
                  <label for="depGender">Gender *</label>
                  <select id="depGender" required>
                    <option value="">-- Select --</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label for="depRelationship">Relationship to Head *</label>
                <select id="depRelationship" required>
                  <option value="">-- Select --</option>
                  <option value="Spouse">Spouse</option>
                  <option value="Partner">Partner</option>
                  <option value="Son">Son</option>
                  <option value="Daughter">Daughter</option>
                  <option value="Stepson">Stepson</option>
                  <option value="Stepdaughter">Stepdaughter</option>
                  <option value="Father">Father</option>
                  <option value="Mother">Mother</option>
                  <option value="Stepfather">Stepfather</option>
                  <option value="Stepmother">Stepmother</option>
                  <option value="Grandfather">Grandfather</option>
                  <option value="Grandmother">Grandmother</option>
                  <option value="Grandson">Grandson</option>
                  <option value="Granddaughter">Granddaughter</option>
                  <option value="Brother">Brother</option>
                  <option value="Sister">Sister</option>
                  <option value="Nephew">Nephew</option>
                  <option value="Niece">Niece</option>
                  <option value="Uncle">Uncle</option>
                  <option value="Aunt">Aunt</option>
                  <option value="Cousin">Cousin</option>
                  <option value="Father-in-law">Father-in-law</option>
                  <option value="Mother-in-law">Mother-in-law</option>
                  <option value="Son-in-law">Son-in-law</option>
                  <option value="Daughter-in-law">Daughter-in-law</option>
                  <option value="Relative">Relative</option>
                  <option value="Boarder">Boarder</option>
                  <option value="Member">Member</option>
                </select>
                <p class="form-hint">For babies/underage members, you can still add them here without creating an account.</p>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" data-action="closeAddDependentModal">Cancel</button>
            <button type="submit" class="btn-primary" id="submitAddDependentBtn" form="addDependentForm">Add Member</button>
          </div>
        </div>
      </div>

      <!-- Edit Member Modal -->
      <div id="editMemberModal" class="modal custom-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Edit Member Information</h3>
            <button class="modal-close" data-action="closeEditMemberModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="editMemberForm">
              <input type="hidden" id="editMemberId">
              <div class="form-group">
                <label for="editMemberName">Member Name</label>
                <input type="text" id="editMemberName" placeholder="Full name" disabled>
                <p class="form-hint">Name cannot be changed. Contact barangay office if needed.</p>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="editMemberDOB">Date of Birth *</label>
                  <input type="date" id="editMemberDOB" required>
                </div>
                <div class="form-group">
                  <label for="editMemberGender">Gender *</label>
                  <select id="editMemberGender" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label for="editMemberRelationship">Relationship <span id="relationshipLabel">(Locked for members)</span> *</label>
                <select id="editMemberRelationship" required>
                  <option value="Spouse">Spouse</option>
                  <option value="Partner">Partner</option>
                  <option value="Son">Son</option>
                  <option value="Daughter">Daughter</option>
                  <option value="Stepson">Stepson</option>
                  <option value="Stepdaughter">Stepdaughter</option>
                  <option value="Father">Father</option>
                  <option value="Mother">Mother</option>
                  <option value="Stepfather">Stepfather</option>
                  <option value="Stepmother">Stepmother</option>
                  <option value="Grandfather">Grandfather</option>
                  <option value="Grandmother">Grandmother</option>
                  <option value="Brother">Brother</option>
                  <option value="Sister">Sister</option>
                  <option value="Grandson">Grandson</option>
                  <option value="Granddaughter">Granddaughter</option>
                  <option value="Nephew">Nephew</option>
                  <option value="Niece">Niece</option>
                  <option value="Uncle">Uncle</option>
                  <option value="Aunt">Aunt</option>
                  <option value="Cousin">Cousin</option>
                  <option value="Father-in-law">Father-in-law</option>
                  <option value="Mother-in-law">Mother-in-law</option>
                  <option value="Son-in-law">Son-in-law</option>
                  <option value="Daughter-in-law">Daughter-in-law</option>
                  <option value="In-law">In-law</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeEditMemberModal">Cancel</button>
            <button class="btn-primary" id="submitEditMemberBtn" data-action="submitEditMember">Save Changes</button>
          </div>
        </div>
      </div>

      <!-- Error/Success Message Container -->
      <div id="messageContainer"></div>

    </div>
<!-- main-content wrapper closed by includes/footer.php -->

<style>
.resident-household-page .dashboard-hero {
  border-radius: 16px;
  background: radial-gradient(circle at 0% 0%, rgba(147, 197, 253, 0.24), transparent 36%), linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
  border: 1px solid rgba(59, 130, 246, 0.2) !important;
  box-shadow: 0 16px 34px -24px rgba(37, 99, 235, 0.45);
}

.resident-household-page .dashboard-hero .card-body {
  padding: 1.2rem 1.3rem;
}

.resident-household-page .hero-kicker {
  color: #334155;
  letter-spacing: 0.08em;
  font-weight: 700;
}

.resident-household-page .hero-copy h2 {
  color: #0f172a;
  font-weight: 700;
}

.resident-household-page .hero-subtitle {
  color: #475569;
  max-width: 640px;
}

.resident-household-page .hero-date-badge {
  display: inline-block;
  border-radius: 999px;
  background: rgba(37, 99, 235, 0.12);
  color: #1e3a8a;
  border: 1px solid rgba(37, 99, 235, 0.22);
  font-weight: 600;
}

.resident-household-page .hero-chips {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.resident-household-page .hero-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  font-size: 0.78rem;
  color: #334155;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(148, 163, 184, 0.35);
}

.resident-household-page .panel {
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.18);
}

.resident-household-page .panel-header {
  border-bottom: 1px solid #f1f5f9;
}

.resident-household-page .panel-header h2 {
  font-size: 1rem;
  color: #1e293b;
}

.resident-household-page .panel-subtitle {
  color: #64748b;
}

.resident-household-page .details-grid .detail-item {
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}

.resident-household-page .members-table thead th {
  background: #f8fafc;
  color: #64748b;
}

.resident-household-page .btn-primary {
  background: #2563eb;
}

.resident-household-page .btn-primary:hover {
  background: #1d4ed8;
}

.resident-household-page .btn-secondary {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.resident-household-page .btn-secondary:hover {
  background: #dbeafe;
}

@media (max-width: 992px) {
  .resident-household-page .hero-chips {
    justify-content: flex-start;
  }

  .resident-household-page .hero-meta {
    text-align: left !important;
    width: 100%;
  }
}
</style>

<script>
  // Keep API URL deployment-safe by using relative paths.
  const HOUSEHOLD_API = '../api/households';
  const RESIDENT_SESSION_ID = <?php echo (int)$residentId; ?>;
</script>
<script src="<?php echo BASE_URL; ?>resident_household.js?v=<?php echo $jsVersion; ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
