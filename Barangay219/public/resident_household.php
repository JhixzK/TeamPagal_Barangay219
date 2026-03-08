<?php
/**
 * E-Barangay Information Management System
 * Resident Household Information
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Require login and check if user is a resident
requireLogin();

$currentRole = getCurrentUserRole();
if (normalizeRole($currentRole) !== normalizeRole(ROLE_RESIDENT)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

// Get user information
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? 'Resident';
$email = $_SESSION['email'] ?? '';
$residentId = $_SESSION['resident_id'] ?? null;

// Get resident details from database
$db = Database::getInstance();
$residentName = $username;
$householdId = null;
$householdData = null;
$householdMembers = [];

if ($residentId) {
    // Get resident details
    $sql = "SELECT first_name, middle_name, last_name, household_id FROM residents WHERE id = ?";
    $resident = $db->fetchOne($sql, [$residentId]);
    if ($resident) {
        $residentName = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
        $householdId = $resident['household_id'];
    }
    
    // Get household details if household exists
    if ($householdId) {
        $sql = "SELECT h.*, 
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as head_name,
                       r.date_of_birth as head_dob,
                       r.gender as head_gender,
                       r.contact_number as head_contact
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                WHERE h.id = ?";
        $householdData = $db->fetchOne($sql, [$householdId]);
        
        // Get household members
        $sql = "SELECT * FROM household_members WHERE household_id = ? ORDER BY is_head DESC, date_of_birth ASC";
        $householdMembers = $db->fetchAll($sql, [$householdId]);
    }
}
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
  <link rel="stylesheet" href="resident_household.css">
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
        <a class="nav-item" href="resident_profile.php">
          <i class="fa-regular fa-user"></i>
          <span class="label">My Profile</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">MAIN</p>
        <a class="nav-item" href="resident_dashboard.php">
          <i class="fa-solid fa-gauge-high"></i>
          <span class="label">Dashboard</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">SERVICES</p>
        <a class="nav-item" href="request_certificate.php">
          <i class="fa-regular fa-file-lines"></i>
          <span class="label">Request Certificate</span>
        </a>
        <a class="nav-item" href="my_requests.php">
          <i class="fa-solid fa-list-check"></i>
          <span class="label">My Requests</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">HOUSEHOLD</p>
        <a class="nav-item active" href="resident_household.php">
          <i class="fa-solid fa-house-user"></i>
          <span class="label">Household Information</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">COMMUNITY</p>
        <a class="nav-item" href="#">
          <i class="fa-regular fa-newspaper"></i>
          <span class="label">Announcements</span>
        </a>
        <a class="nav-item" href="#">
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
    <section class="dashboard-head">
      <div>
        <p class="portal-tag">HOUSEHOLD PORTAL</p>
        <h2>Household Information</h2>
        <p class="dashboard-subtitle">Manage your household details and family members information.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <?php if (!$householdId): ?>
    <!-- No Household Message -->
    <section class="no-household-panel">
      <div class="empty-illustration">
        <i class="fa-solid fa-house-circle-xmark"></i>
      </div>
      <h3>No Household Record Found</h3>
      <p>You are not currently associated with any household. Please contact the Barangay office to create or link your household record.</p>
      <a href="resident_dashboard.php" class="btn-primary">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
      </a>
    </section>
    <?php else: ?>

    <!-- Household Stats -->
    <section class="stats-grid" aria-label="Household statistics">
      <article class="stat-card card-1">
        <i class="fa-solid fa-users stat-icon"></i>
        <h3>Total Members</h3>
        <p class="stat-value" id="totalMembers"><?php echo $householdData['total_members'] ?? 0; ?></p>
        <p class="stat-note">Living in this household</p>
      </article>

      <article class="stat-card card-2">
        <i class="fa-solid fa-user-tie stat-icon"></i>
        <h3>Adults</h3>
        <p class="stat-value" id="totalAdults"><?php echo $householdData['number_of_adults'] ?? 0; ?></p>
        <p class="stat-note">18 years and above</p>
      </article>

      <article class="stat-card card-3">
        <i class="fa-solid fa-child stat-icon"></i>
        <h3>Minors</h3>
        <p class="stat-value" id="totalMinors"><?php echo $householdData['number_of_minors'] ?? 0; ?></p>
        <p class="stat-note">Below 18 years old</p>
      </article>

      <article class="stat-card card-4">
        <i class="fa-solid fa-person-cane stat-icon"></i>
        <h3>Senior Citizens</h3>
        <p class="stat-value" id="totalSeniors"><?php echo $householdData['number_of_seniors'] ?? 0; ?></p>
        <p class="stat-note">60 years and above</p>
      </article>
    </section>

    <!-- Household Details Panel -->
    <section class="panel household-details-panel">
      <div class="panel-header">
        <h3><i class="fa-solid fa-house"></i> Household Details</h3>
        <button class="btn-secondary btn-sm" id="editHouseholdBtn">
          <i class="fa-solid fa-pen"></i> Edit Details
        </button>
      </div>
      <div class="detail-grid">
        <div class="detail-group">
          <h4>Household Information</h4>
          <div class="detail-row">
            <span class="detail-label">Household ID:</span>
            <span class="detail-value"><?php echo 'HH-219-' . str_pad($householdId, 5, '0', STR_PAD_LEFT); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Registration Date:</span>
            <span class="detail-value"><?php echo $householdData['registration_date'] ? date('F d, Y', strtotime($householdData['registration_date'])) : 'N/A'; ?></span>
          </div>
        </div>

        <div class="detail-group">
          <h4>Head of Household</h4>
          <div class="detail-row">
            <span class="detail-label">Name:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['head_name'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Age:</span>
            <span class="detail-value">
              <?php 
              if ($householdData['head_dob']) {
                  $age = date_diff(date_create($householdData['head_dob']), date_create('today'))->y;
                  echo $age . ' years old';
              } else {
                  echo 'N/A';
              }
              ?>
            </span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Gender:</span>
            <span class="detail-value"><?php echo ucfirst($householdData['head_gender'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Contact:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['head_contact'] ?? 'N/A'); ?></span>
          </div>
        </div>

        <div class="detail-group full-width">
          <h4>Address</h4>
          <div class="detail-row">
            <span class="detail-label">House/Street:</span>
            <span class="detail-value">
              <?php 
              $houseStreet = trim(($householdData['house_number'] ?? '') . ' ' . ($householdData['street'] ?? ''));
              echo htmlspecialchars($houseStreet ?: ($householdData['address'] ?? 'N/A')); 
              ?>
            </span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Purok/Sitio:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['purok_sitio'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Barangay:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['barangay'] ?? 'Barangay 219'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">City:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['city'] ?? 'Manila'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Province:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['province'] ?? 'Metro Manila'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Postal Code:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['postal_code'] ?? '1013'); ?></span>
          </div>
        </div>

        <div class="detail-group">
          <h4>Emergency Contact</h4>
          <div class="detail-row">
            <span class="detail-label">Name:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['emergency_contact_name'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Phone:</span>
            <span class="detail-value"><?php echo htmlspecialchars($householdData['emergency_contact_phone'] ?? 'N/A'); ?></span>
          </div>
        </div>

        <?php if (!empty($householdData['special_notes'])): ?>
        <div class="detail-group full-width">
          <h4>Special Notes</h4>
          <p class="notes-text"><?php echo nl2br(htmlspecialchars($householdData['special_notes'])); ?></p>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Household Members Panel -->
    <section class="panel household-members-panel">
      <div class="panel-header">
        <h3><i class="fa-solid fa-users"></i> Household Members</h3>
        <button class="btn-primary btn-sm" id="addMemberBtn">
          <i class="fa-solid fa-user-plus"></i> Add Member
        </button>
      </div>

      <div class="table-wrap">
        <?php if (count($householdMembers) > 0): ?>
        <table class="members-table" id="membersTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Relationship</th>
              <th>DOB / Age</th>
              <th>Gender</th>
              <th>Civil Status</th>
              <th>Occupation</th>
              <th>Government ID</th>
              <th>Voter Status</th>
              <th>Contact</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($householdMembers as $member): ?>
            <tr data-member-id="<?php echo $member['id']; ?>">
              <td>
                <?php 
                $fullName = trim($member['first_name'] . ' ' . 
                               ($member['middle_name'] ? substr($member['middle_name'], 0, 1) . '. ' : '') . 
                               $member['last_name'] . 
                               ($member['suffix'] ? ' ' . $member['suffix'] : ''));
                echo htmlspecialchars($fullName);
                if ($member['is_head']) echo ' <span class="badge-head">HEAD</span>';
                ?>
              </td>
              <td><?php echo htmlspecialchars($member['relationship_to_head']); ?></td>
              <td>
                <?php 
                echo date('M d, Y', strtotime($member['date_of_birth']));
                echo '<br><small class="age-text">' . $member['age'] . ' years old</small>';
                ?>
              </td>
              <td><?php echo htmlspecialchars($member['gender']); ?></td>
              <td><?php echo htmlspecialchars($member['civil_status']); ?></td>
              <td><?php echo htmlspecialchars($member['occupation'] ?: 'N/A'); ?></td>
              <td>
                <?php 
                if ($member['government_id_type']) {
                    echo htmlspecialchars($member['government_id_type']);
                    if ($member['government_id_number']) {
                        echo '<br><small>' . htmlspecialchars($member['government_id_number']) . '</small>';
                    }
                } else {
                    echo 'N/A';
                }
                ?>
              </td>
              <td>
                <?php 
                $voterClass = $member['voter_status'] === 'Registered' ? 'status-registered' : 'status-not-registered';
                echo '<span class="' . $voterClass . '">' . htmlspecialchars($member['voter_status']) . '</span>';
                ?>
              </td>
              <td><?php echo htmlspecialchars($member['contact_number'] ?: 'N/A'); ?></td>
              <td>
                <div class="action-btns">
                  <button class="btn-icon btn-edit" onclick="editMember(<?php echo $member['id']; ?>)" title="Edit">
                    <i class="fa-solid fa-pen"></i>
                  </button>
                  <button class="btn-icon btn-delete" onclick="deleteMember(<?php echo $member['id']; ?>)" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">
          <i class="fa-regular fa-user-circle"></i><br>
          No household members recorded yet. Click "Add Member" to start building your household profile.
        </p>
        <?php endif; ?>
      </div>
    </section>

    <?php endif; ?>
  </main>

  <!-- Add/Edit Member Modal -->
  <div class="modal" id="memberModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Add Household Member</h3>
        <button class="modal-close" id="closeMemberModal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="memberForm">
          <input type="hidden" id="memberId" name="member_id">
          <input type="hidden" name="household_id" value="<?php echo $householdId; ?>">
          
          <div class="form-grid">
            <div class="form-group">
              <label for="firstName">First Name <span class="required">*</span></label>
              <input type="text" id="firstName" name="first_name" required>
            </div>

            <div class="form-group">
              <label for="middleName">Middle Name</label>
              <input type="text" id="middleName" name="middle_name">
            </div>

            <div class="form-group">
              <label for="lastName">Last Name <span class="required">*</span></label>
              <input type="text" id="lastName" name="last_name" required>
            </div>

            <div class="form-group">
              <label for="suffix">Suffix</label>
              <select id="suffix" name="suffix">
                <option value="">None</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
              </select>
            </div>

            <div class="form-group">
              <label for="relationship">Relationship to Head <span class="required">*</span></label>
              <select id="relationship" name="relationship_to_head" required>
                <option value="">Select Relationship</option>
                <option value="Head">Head</option>
                <option value="Spouse">Spouse</option>
                <option value="Son">Son</option>
                <option value="Daughter">Daughter</option>
                <option value="Father">Father</option>
                <option value="Mother">Mother</option>
                <option value="Brother">Brother</option>
                <option value="Sister">Sister</option>
                <option value="Grandfather">Grandfather</option>
                <option value="Grandmother">Grandmother</option>
                <option value="Grandson">Grandson</option>
                <option value="Granddaughter">Granddaughter</option>
                <option value="Uncle">Uncle</option>
                <option value="Aunt">Aunt</option>
                <option value="Nephew">Nephew</option>
                <option value="Niece">Niece</option>
                <option value="Cousin">Cousin</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-group">
              <label for="dateOfBirth">Date of Birth <span class="required">*</span></label>
              <input type="date" id="dateOfBirth" name="date_of_birth" required>
            </div>

            <div class="form-group">
              <label for="gender">Gender <span class="required">*</span></label>
              <select id="gender" name="gender" required>
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-group">
              <label for="civilStatus">Civil Status <span class="required">*</span></label>
              <select id="civilStatus" name="civil_status" required>
                <option value="">Select Status</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widowed">Widowed</option>
                <option value="Divorced">Divorced</option>
                <option value="Separated">Separated</option>
              </select>
            </div>

            <div class="form-group">
              <label for="occupation">Occupation</label>
              <input type="text" id="occupation" name="occupation" placeholder="e.g., Teacher, Driver, Student">
            </div>

            <div class="form-group">
              <label for="govIdType">Government ID Type</label>
              <select id="govIdType" name="government_id_type">
                <option value="">Select ID Type</option>
                <option value="National ID">National ID (PhilSys)</option>
                <option value="PhilHealth">PhilHealth ID</option>
                <option value="SSS">SSS ID</option>
                <option value="GSIS">GSIS ID</option>
                <option value="TIN">TIN ID</option>
                <option value="Postal ID">Postal ID</option>
                <option value="Voter's ID">Voter's ID</option>
                <option value="Driver's License">Driver's License</option>
                <option value="Passport">Passport</option>
                <option value="PRC ID">PRC ID</option>
                <option value="Senior Citizen ID">Senior Citizen ID</option>
                <option value="PWD ID">PWD ID</option>
              </select>
            </div>

            <div class="form-group">
              <label for="govIdNumber">Government ID Number</label>
              <input type="text" id="govIdNumber" name="government_id_number" placeholder="e.g., 1234-5678-9012">
            </div>

            <div class="form-group">
              <label for="voterStatus">Voter Status <span class="required">*</span></label>
              <select id="voterStatus" name="voter_status" required>
                <option value="Not Registered">Not Registered</option>
                <option value="Registered">Registered</option>
                <option value="N/A">N/A (Below 18)</option>
              </select>
            </div>

            <div class="form-group">
              <label for="voterIdNumber">Voter ID Number</label>
              <input type="text" id="voterIdNumber" name="voter_id_number" placeholder="e.g., 01234567890123456789">
            </div>

            <div class="form-group">
              <label for="contactNumber">Contact Number</label>
              <input type="tel" id="contactNumber" name="contact_number" placeholder="09XX-XXX-XXXX" pattern="[0-9+\-() ]*">
            </div>

            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" placeholder="member@example.com">
            </div>

            <div class="form-group full-width">
              <label class="checkbox-label">
                <input type="checkbox" id="isHead" name="is_head" value="1">
                <span>This member is the household head</span>
              </label>
            </div>

            <div class="form-group full-width">
              <div class="checkbox-group">
                <label class="checkbox-label">
                  <input type="checkbox" id="isSenior" name="is_senior_citizen" value="1">
                  <span>Senior Citizen (60+)</span>
                </label>
                <label class="checkbox-label">
                  <input type="checkbox" id="isPwd" name="is_pwd" value="1">
                  <span>Person with Disability (PWD)</span>
                </label>
                <label class="checkbox-label">
                  <input type="checkbox" id="is4ps" name="is_4ps_beneficiary" value="1">
                  <span>4Ps Beneficiary</span>
                </label>
              </div>
            </div>

            <div class="form-group full-width">
              <label for="remarks">Remarks</label>
              <textarea id="remarks" name="remarks" rows="3" placeholder="Additional notes or information"></textarea>
            </div>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" id="cancelMemberBtn">Cancel</button>
            <button type="submit" class="btn-primary" id="saveMemberBtn">
              <i class="fa-solid fa-save"></i> Save Member
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Household Modal -->
  <div class="modal" id="householdModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Edit Household Details</h3>
        <button class="modal-close" id="closeHouseholdModal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="householdForm">
          <input type="hidden" name="household_id" value="<?php echo $householdId; ?>">
          
          <div class="form-grid">
            <div class="form-group full-width">
              <h4 class="form-section-title">Address Information</h4>
            </div>

            <div class="form-group">
              <label for="houseNumber">House Number</label>
              <input type="text" id="houseNumber" name="house_number" value="<?php echo htmlspecialchars($householdData['house_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="street">Street</label>
              <input type="text" id="street" name="street" value="<?php echo htmlspecialchars($householdData['street'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="purokSitio">Purok/Sitio</label>
              <input type="text" id="purokSitio" name="purok_sitio" value="<?php echo htmlspecialchars($householdData['purok_sitio'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="postalCode">Postal Code</label>
              <input type="text" id="postalCode" name="postal_code" value="<?php echo htmlspecialchars($householdData['postal_code'] ?? '1013'); ?>">
            </div>

            <div class="form-group full-width">
              <h4 class="form-section-title">Emergency Contact</h4>
            </div>

            <div class="form-group">
              <label for="emergencyName">Emergency Contact Name</label>
              <input type="text" id="emergencyName" name="emergency_contact_name" value="<?php echo htmlspecialchars($householdData['emergency_contact_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="emergencyPhone">Emergency Contact Phone</label>
              <input type="tel" id="emergencyPhone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($householdData['emergency_contact_phone'] ?? ''); ?>" pattern="[0-9+\-() ]*">
            </div>

            <div class="form-group full-width">
              <label for="specialNotes">Special Notes</label>
              <textarea id="specialNotes" name="special_notes" rows="4" placeholder="Any special notes about the household"><?php echo htmlspecialchars($householdData['special_notes'] ?? ''); ?></textarea>
            </div>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" id="cancelHouseholdBtn">Cancel</button>
            <button type="submit" class="btn-primary">
              <i class="fa-solid fa-save"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const householdId = <?php echo json_encode($householdId); ?>;
  </script>
  <script src="resident_dashboard.js"></script>
  <script src="resident_household.js"></script>
</body>
</html>
