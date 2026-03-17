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

<div class="main-content module-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Resident Portal</p>
                    <h2 class="mb-1"><i class="bi bi-house-door me-2"></i>Household Information</h2>
                    <p class="module-subtitle mb-0">View your household overview and members.</p>
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
                <i class="fa-solid fa-right-from-bracket"></i> Leave Household
              </button>
              <button class="btn-primary btn-small" id="editHouseholdBtn" data-action="editHousehold" style="display:none;">
                <i class="fa-solid fa-pen-to-square"></i> Update Overview
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
              <button class="btn-primary btn-small" id="manageMembersBtn" data-action="manageMembers" style="display:none;">
                <i class="fa-solid fa-user-gear"></i> Manage Members
              </button>
            </div>
          </div>
          <div class="panel-body table-responsive">
            <table class="members-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Relationship</th>
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
      <div id="roleSelectionModal" class="modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content modal-lg">
          <div class="modal-header">
            <h3>Join Household</h3>
            <button class="modal-close" data-action="closeRoleModal">&times;</button>
          </div>
          <div class="modal-body">
            <p>You don't have a household yet. Enter the Family Head Code to join an existing household.</p>
            <div class="role-selection-grid">
              <div class="role-card" data-role="member">
                <i class="fa-solid fa-users"></i>
                <h4>Household Member</h4>
                <p>Join using Family Head Code</p>
                <p class="role-desc">Example: FH-01234</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Head of Household Form Modal -->
      <div id="headFormModal" class="modal" style="display: none;">
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
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeHeadModal">Cancel</button>
            <button class="btn-primary" id="submitHeadBtn" data-action="submitHeadForm">Create Household</button>
          </div>
        </div>
      </div>

      <!-- Update Household Overview Modal -->
      <div id="overviewUpdateModal" class="modal" style="display: none;">
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
                  <label for="overviewHouseholdType">Household Type *</label>
                  <select id="overviewHouseholdType" required>
                    <option value="nuclear">Nuclear</option>
                    <option value="extended">Extended</option>
                    <option value="single_parent">Single Parent</option>
                    <option value="others">Others</option>
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
      <div id="memberJoinModal" class="modal" style="display: none;">
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
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" data-action="closeMemberModal">Cancel</button>
            <button class="btn-primary" id="submitMemberBtn" data-action="submitMemberJoin">Join Household</button>
          </div>
        </div>
      </div>

      <!-- Manage Members Modal (Head Only) -->
      <div id="manageMembersModal" class="modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content modal-lg">
          <div class="modal-header">
            <h3>Manage Members</h3>
            <button class="modal-close" data-action="closeManageMembersModal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="small text-muted mb-2">Actions are managed here instead of inside the table.</div>
            <div class="panel-body table-responsive">
              <table class="members-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Relationship</th>
                    <th style="width:160px;">Actions</th>
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
      <div id="transferHeadReasonModal" class="modal" style="display: none;">
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

      <!-- Add Member Modal (Head Only) -->
      <!-- Add Member Modal removed on resident side -->

      <!-- Edit Member Modal -->
      <div id="editMemberModal" class="modal" style="display: none;">
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
                  <option value="Son">Son</option>
                  <option value="Daughter">Daughter</option>
                  <option value="Father">Father</option>
                  <option value="Mother">Mother</option>
                  <option value="Brother">Brother</option>
                  <option value="Sister">Sister</option>
                  <option value="Grandson">Grandson</option>
                  <option value="Granddaughter">Granddaughter</option>
                  <option value="Nephew">Nephew</option>
                  <option value="Niece">Niece</option>
                  <option value="Uncle">Uncle</option>
                  <option value="Aunt">Aunt</option>
                  <option value="Cousin">Cousin</option>
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
</div>

<script>
  // Keep API URL deployment-safe by using relative paths.
  const HOUSEHOLD_API = '../api/households';
  const RESIDENT_SESSION_ID = <?php echo (int)$residentId; ?>;
</script>
<script src="<?php echo BASE_URL; ?>resident_household.js?v=<?php echo $jsVersion; ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
