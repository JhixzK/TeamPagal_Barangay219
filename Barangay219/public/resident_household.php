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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Household Information | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="resident_dashboard.css">
  <link rel="stylesheet" href="resident_household.css?v=<?php echo $cssVersion; ?>">
</head>
<body>
  <header class="top-header">
    <div class="header-left">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="logo-wrap" aria-hidden="true">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <div class="system-text">
        <h1>E-Barangay Information Management System</h1>
        <p>Barangay 219, Tondo, Manila</p>
      </div>
    </div>

    <div class="header-right">
      <span class="date-badge" id="topDateBadge"><?php echo date('F d, Y'); ?></span>
      <?php if (canSwitchToResidentView()): ?>
        <div class="view-switch" role="group" aria-label="View mode switch">
          <span class="view-label">Official</span>
          <label class="switch">
            <input type="checkbox" data-view-mode-toggle <?php echo isResidentView() ? 'checked' : ''; ?>>
            <span class="slider"></span>
          </label>
          <span class="view-label">Resident</span>
        </div>
      <?php endif; ?>
      <button class="icon-btn" aria-label="Notifications">
        <i class="fa-regular fa-bell"></i>
      </button>
      <div class="profile-dropdown" id="profileDropdown">
        <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
          <img src="https://i.pravatar.cc/100?img=12" alt="Resident avatar">
          <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-menu" id="dropdownMenu" role="menu">
          <a href="resident_profile.php" role="menuitem">View Profile</a>
          <a href="#" role="menuitem">Account Settings</a>
          <a href="../api/auth.php?action=logout" role="menuitem">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-profile">
      <img src="https://i.pravatar.cc/120?img=12" alt="Resident profile image">
      <div class="profile-meta label">
        <h3><?php echo htmlspecialchars($residentName); ?></h3>
        <p>Resident</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-group">
        <p class="group-title label">ACCOUNT</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_profile.php">
          <i class="fa-regular fa-user"></i>
          <span class="label">My Profile</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">MAIN</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_dashboard.php">
          <i class="fa-solid fa-gauge-high"></i>
          <span class="label">Dashboard</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">SERVICES</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>request_certificate.php">
          <i class="fa-regular fa-file-lines"></i>
          <span class="label">Request Certificate</span>
        </a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>my_requests.php">
          <i class="fa-solid fa-list-check"></i>
          <span class="label">My Requests</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">HOUSEHOLD</p>
        <a class="nav-item active" href="<?php echo BASE_URL; ?>resident_household.php">
          <i class="fa-solid fa-house-user"></i>
          <span class="label">Household Information</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">COMMUNITY</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_announcements.php">
          <i class="fa-regular fa-newspaper"></i>
          <span class="label">Announcements</span>
        </a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>complaints/my_complaints.php">
          <i class="fa-regular fa-comment-dots"></i>
          <span class="label">Complaints / Reports</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">OTHER</p>
        <a class="nav-item" href="#">
          <i class="fa-regular fa-bell"></i>
          <span class="label">Notifications</span>
        </a>
        <a class="nav-item" href="#">
          <i class="fa-regular fa-circle-question"></i>
          <span class="label">Help / Support</span>
        </a>
      </div>
    </nav>

    <div class="sidebar-bottom">
      <a class="nav-item logout" href="../api/auth.php?action=logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span class="label">Logout</span>
      </a>
    </div>
  </aside>

  <main class="main-content" id="mainContent">
      <div class="page-header">
        <h1><i class="fa-solid fa-house"></i> My Household</h1>
        <p>Manage your household information and members</p>
      </div>

      <!-- Loading Container -->
      <div id="loadingContainer" class="loading-container">
        <div class="spinner"></div>
        <p>Loading household information...</p>
      </div>

      <!-- Main Content Container -->
      <div id="contentContainer" style="display: none;">
        
        <!-- Household Stats Section -->
        <section class="stats-grid" aria-label="Household statistics">
          <article class="stat-card card-1">
            <i class="fa-solid fa-users stat-icon"></i>
            <h3>Total Members</h3>
            <p class="stat-value" id="totalMembers">0</p>
            <p class="stat-note">Living in this household</p>
          </article>
          <article class="stat-card card-2">
            <i class="fa-solid fa-map-marker-alt stat-icon"></i>
            <h3>Address</h3>
            <p class="stat-value" id="householdAddress">--</p>
            <p class="stat-note">Current household address</p>
          </article>
          <article class="stat-card card-3">
            <i class="fa-solid fa-user stat-icon"></i>
            <h3>Head</h3>
            <p class="stat-value" id="headName">--</p>
            <p class="stat-note">Household head</p>
          </article>
        </section>

        <!-- Household Details Panel -->
        <section class="panel panel-primary" id="householdDetailsPanel" style="display: none;">
          <div class="panel-header">
            <h2>Household Information</h2>
            <button class="btn-primary btn-small" id="editHouseholdBtn" data-action="editHousehold">
              <i class="fa-solid fa-edit"></i> Edit
            </button>
          </div>
          <div class="panel-body">
            <div class="details-grid">
              <div class="detail-item">
                <span class="detail-label">Address:</span>
                <span class="detail-value" id="displayAddress">--</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Head of Household:</span>
                <span class="detail-value" id="displayHead">--</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Total Members:</span>
                <span class="detail-value" id="displayMembers">0</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Created:</span>
                <span class="detail-value" id="displayCreated">--</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Members Section -->
        <section class="panel panel-primary" id="membersPanel" style="display: none;">
          <div class="panel-header">
            <h2>Household Members</h2>
            <button class="btn-primary btn-small" id="addMemberBtn" data-action="addMember">
              <i class="fa-solid fa-plus"></i> Add Member
            </button>
          </div>
          <div class="panel-body">
            <table class="members-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Relationship</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="membersTableBody">
              </tbody>
            </table>
          </div>
        </section>

      </div><!-- End contentContainer -->

      <!-- MODALS (OUTSIDE contentContainer) -->

      <!-- Role Selection Modal -->
      <div id="roleSelectionModal" class="modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content modal-lg">
          <div class="modal-header">
            <h3>Select Your Role</h3>
            <button class="modal-close" data-action="closeRoleModal">&times;</button>
          </div>
          <div class="modal-body">
            <p>You don't have a household yet. Choose your role to get started:</p>
            <div class="role-selection-grid">
              <div class="role-card" data-role="head">
                <i class="fa-solid fa-crown"></i>
                <h4>Head of Household</h4>
                <p>I am the head of this household</p>
                <p class="role-desc">You can manage household address and add/remove members</p>
              </div>
              <div class="role-card" data-role="member">
                <i class="fa-solid fa-users"></i>
                <h4>Household Member</h4>
                <p>I am a member of an existing household</p>
                <p class="role-desc">You can view household info and request to join</p>
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
                <label for="householdSelect">Select Household *</label>
                <select id="householdSelect" required>
                  <option value="">-- Select a household --</option>
                </select>
                <p class="form-hint" id="selectedHeadName"></p>
              </div>
              <div class="form-group">
                <label for="relationshipToHead">Relationship to Head *</label>
                <select id="relationshipToHead" required>
                  <option value="">-- Select relationship --</option>
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
            <button class="btn-secondary" data-action="closeMemberModal">Cancel</button>
            <button class="btn-primary" id="submitMemberBtn" data-action="submitMemberJoin">Join Household</button>
          </div>
        </div>
      </div>

      <!-- Add Member Modal (Head Only) -->
      <div id="addMemberModal" class="modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Add Family Member</h3>
            <button class="modal-close" data-action="closeAddMemberModal">&times;</button>
          </div>
          <div class="modal-body">
            <form id="addMemberForm">
              <div class="form-group">
                <label for="newMemberName">Member Name *</label>
                <input type="text" id="newMemberName" placeholder="Full name" required>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="newMemberDOB">Date of Birth *</label>
                  <input type="date" id="newMemberDOB" required>
                </div>
                <div class="form-group">
                  <label for="newMemberGender">Gender *</label>
                  <select id="newMemberGender" required>
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label for="newMemberRelationship">Relationship *</label>
                <select id="newMemberRelationship" required>
                  <option value="">-- Select relationship --</option>
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
            <button class="btn-secondary" data-action="closeAddMemberModal">Cancel</button>
            <button class="btn-primary" id="submitAddMemberBtn" data-action="submitAddMember">Add Member</button>
          </div>
        </div>
      </div>

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

    </main>

  <script>
    // API base URL
    const HOUSEHOLD_API = 'http://<?php echo $_SERVER['HTTP_HOST']; ?>/TeamPagal_Barangay219/Barangay219/api/households';
  </script>
  <script src="resident_household.js?v=<?php echo $jsVersion; ?>"></script>
  <script src="<?php echo ASSETS_URL; ?>css/js/view-mode-switch.js?v=<?php echo time(); ?>"></script>

  <style>
    .loading-container {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 500px;
      text-align: center;
    }
    .spinner {
      width: 50px;
      height: 50px;
      border: 4px solid #f3f3f3;
      border-top: 4px solid #3498db;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-bottom: 20px;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</body>
</html>
