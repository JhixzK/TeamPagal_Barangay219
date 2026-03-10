<?php
/**
 * E-Barangay Information Management System
 * Resident Profile
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
$username = $_SESSION['username'] ?? '';
$email = $_SESSION['email'] ?? '';
$residentId = $_SESSION['resident_id'] ?? null;

// Initialize default values
$residentData = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
  'birth_date' => '',
    'date_of_birth' => '',
    'place_of_birth' => '',
  'sex' => '',
    'gender' => '',
    'civil_status' => '',
  'citizenship' => 'Filipino',
    'national_id' => '',
  'valid_id_type' => '',
  'valid_id_number' => '',
  'contact_number' => '',
    'mobile_number' => '',
  'email' => '',
    'emergency_contact_name' => '',
    'emergency_contact_number' => '',
  'emergency_contact_relationship' => '',
  'house_number' => '',
    'house_no' => '',
    'street' => '',
  'purok_sitio' => '',
    'barangay' => 'Barangay 219',
    'city' => 'Manila',
    'province' => 'Metro Manila',
  'length_of_residency_years' => '',
    'years_of_residency' => '',
  'educational_attainment' => '',
    'occupation' => '',
    'employment_status' => '',
  'is_senior_citizen' => 0,
  'is_pwd' => 0,
  'pwd_id_number' => '',
  'is_solo_parent' => 0,
  'solo_parent_id_number' => '',
  'is_ip_member' => 0,
  'ip_group' => '',
  'is_4ps_beneficiary' => 0,
    'employer_name' => '',
    'household_head_name' => '',
    'relationship_to_head' => '',
    'household_members_count' => '',
    'created_at' => '',
  'record_status' => 'pending',
  'status' => 'pending',
    'verification_status' => 'pending',
    'id_document_path' => ''
];

$residentName = $username;
$residentFullAddress = 'Barangay 219, Tondo, Manila';

// Get resident details from database
$db = Database::getInstance();
if ($residentId) {
    $sql = "SELECT * FROM residents WHERE id = ?";
    $resident = $db->fetchOne($sql, [$residentId]);
    if ($resident) {
        $residentData = array_merge($residentData, $resident);
        $residentName = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
        $residentFullAddress = trim(($resident['house_no'] ?? '') . ' ' . ($resident['street'] ?? '')) . ', Barangay 219, Tondo, Manila';
    }
}

  // Helper to safely resolve equivalent column names across schema versions.
  $pickValue = static function (array $data, array $keys, $default = '') {
    foreach ($keys as $key) {
      if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
        return $data[$key];
      }
    }
    return $default;
  };

  $fullAddressParts = array_filter([
    $pickValue($residentData, ['house_number', 'house_no']),
    $pickValue($residentData, ['street']),
    $pickValue($residentData, ['purok_sitio']),
    $pickValue($residentData, ['barangay']),
    $pickValue($residentData, ['city']),
    $pickValue($residentData, ['province'])
  ]);
  $residentFullAddress = !empty($fullAddressParts) ? implode(', ', $fullAddressParts) : $residentFullAddress;

  $dobRaw = $pickValue($residentData, ['birth_date', 'date_of_birth']);
  $yearsResidency = $pickValue($residentData, ['length_of_residency_years', 'years_of_residency']);
  $verificationStatus = strtolower((string)$pickValue($residentData, ['verification_status', 'record_status', 'status'], 'pending'));
  $idNumber = $pickValue($residentData, ['national_id', 'valid_id_number']);
  $idTypeRaw = $pickValue($residentData, ['valid_id_type']);
  $idType = $idTypeRaw ? ucwords(str_replace('_', ' ', $idTypeRaw)) : 'N/A';
  $mobileNumber = $pickValue($residentData, ['mobile_number', 'contact_number']);
  $genderValue = $pickValue($residentData, ['gender', 'sex']);
  $houseStreet = trim($pickValue($residentData, ['house_number', 'house_no']) . ' ' . $pickValue($residentData, ['street']));

// Format data for display
$displayData = [
    'full_name' => $residentName,
    'resident_id' => $residentId ? 'RES-219-2026-' . str_pad($residentId, 4, '0', STR_PAD_LEFT) : 'N/A',
    'address' => $residentFullAddress,
    'first_name' => $residentData['first_name'] ?: 'N/A',
    'middle_name' => $residentData['middle_name'] ?: 'N/A',
    'last_name' => $residentData['last_name'] ?: 'N/A',
    'suffix' => $residentData['suffix'] ?: 'N/A',
    'date_of_birth' => $dobRaw ? date('F d, Y', strtotime($dobRaw)) : 'N/A',
    'place_of_birth' => $residentData['place_of_birth'] ?: 'N/A',
    'gender' => $genderValue ? ucfirst($genderValue) : 'N/A',
    'civil_status' => ucfirst(str_replace('_', ' ', $residentData['civil_status'])) ?: 'N/A',
    'citizenship' => $residentData['citizenship'] ?: 'N/A',
    'national_id' => $idNumber ?: 'N/A',
    'valid_id_type' => $idType,
    'mobile_number' => $mobileNumber ?: 'N/A',
    'email' => ($residentData['email'] ?: $email) ?: 'N/A',
    'emergency_contact_name' => $residentData['emergency_contact_name'] ?: 'N/A',
    'emergency_contact_number' => $residentData['emergency_contact_number'] ?: 'N/A',
    'emergency_contact_relationship' => $residentData['emergency_contact_relationship'] ?: 'N/A',
    'house_street' => $houseStreet ?: 'N/A',
    'purok_sitio' => $residentData['purok_sitio'] ?: 'N/A',
    'barangay' => $residentData['barangay'] ?: 'Barangay 219',
    'city' => $residentData['city'] ?: 'Manila',
    'province' => $residentData['province'] ?: 'Metro Manila',
    'years_of_residency' => $yearsResidency !== '' ? $yearsResidency . ' years' : 'N/A',
    'household_head_name' => $residentData['household_head_name'] ?: 'N/A',
    'relationship_to_head' => ucfirst($residentData['relationship_to_head']) ?: 'N/A',
    'household_members_count' => $residentData['household_members_count'] ?: 'N/A',
    'educational_attainment' => $residentData['educational_attainment'] ?: 'N/A',
    'occupation' => $residentData['occupation'] ?: 'N/A',
    'employment_status' => ucfirst(str_replace('_', ' ', $residentData['employment_status'])) ?: 'N/A',
    'is_senior_citizen' => (int)$residentData['is_senior_citizen'] === 1 ? 'Yes' : 'No',
    'is_pwd' => (int)$residentData['is_pwd'] === 1 ? 'Yes' : 'No',
    'pwd_id_number' => $residentData['pwd_id_number'] ?: 'N/A',
    'is_solo_parent' => (int)$residentData['is_solo_parent'] === 1 ? 'Yes' : 'No',
    'solo_parent_id_number' => $residentData['solo_parent_id_number'] ?: 'N/A',
    'is_ip_member' => (int)$residentData['is_ip_member'] === 1 ? 'Yes' : 'No',
    'ip_group' => $residentData['ip_group'] ?: 'N/A',
    'is_4ps_beneficiary' => (int)$residentData['is_4ps_beneficiary'] === 1 ? 'Yes' : 'No',
    'employer_name' => $residentData['employer_name'] ?: 'N/A',
    'username' => $username,
    'account_created' => $residentData['created_at'] ? date('F d, Y', strtotime($residentData['created_at'])) : 'N/A',
    'id_document' => $residentData['id_document_path'] ? basename($residentData['id_document_path']) : 'No ID uploaded',
    'verification_status' => $verificationStatus
];

$verificationBadge = match($displayData['verification_status']) {
    'verified', 'active' => '<span class="status-badge verified"><i class="fa-solid fa-circle-check"></i> Verified</span>',
    'pending' => '<span class="status-badge pending">Pending Verification</span>',
    default => '<span class="status-badge">' . ucfirst($displayData['verification_status']) . '</span>'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="resident_dashboard.css">
  <link rel="stylesheet" href="resident_profile.css">
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
        <h3><?php echo htmlspecialchars($displayData['full_name']); ?></h3>
        <p>Resident</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-group">
        <p class="group-title label">ACCOUNT</p>
        <a class="nav-item active" href="resident_profile.php">
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
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_household.php">
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

  <main class="main-content">
    <section class="page-head">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>My Profile</h2>
        <p class="page-subtitle">View and manage your resident account information.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <section class="profile-summary card">
      <div class="summary-left">
        <img src="https://i.pravatar.cc/140?img=12" alt="Resident profile photo">
        <div class="summary-meta">
          <h3><?php echo htmlspecialchars($displayData['full_name']); ?></h3>
          <p class="resident-id">Resident ID: <?php echo htmlspecialchars($displayData['resident_id']); ?></p>
          <p class="resident-address"><?php echo htmlspecialchars($displayData['address']); ?></p>
          <?php echo $verificationBadge; ?>
        </div>
      </div>
      <button class="btn-primary" data-action="edit-profile">
        <i class="fa-regular fa-pen-to-square"></i>
        Edit Profile
      </button>
    </section>

    <section class="cards-grid">
      <article class="card info-card">
        <div class="card-head">
          <h3>Personal Information</h3>
          <button class="btn-link" data-action="edit-personal"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>First Name</span><strong><?php echo htmlspecialchars($displayData['first_name']); ?></strong></div>
          <div class="info-row"><span>Middle Name</span><strong><?php echo htmlspecialchars($displayData['middle_name']); ?></strong></div>
          <div class="info-row"><span>Last Name</span><strong><?php echo htmlspecialchars($displayData['last_name']); ?></strong></div>
          <div class="info-row"><span>Suffix</span><strong><?php echo htmlspecialchars($displayData['suffix']); ?></strong></div>
          <div class="info-row"><span>Date of Birth</span><strong><?php echo htmlspecialchars($displayData['date_of_birth']); ?></strong></div>
          <div class="info-row"><span>Place of Birth</span><strong><?php echo htmlspecialchars($displayData['place_of_birth']); ?></strong></div>
          <div class="info-row"><span>Gender</span><strong><?php echo htmlspecialchars($displayData['gender']); ?></strong></div>
          <div class="info-row"><span>Civil Status</span><strong><?php echo htmlspecialchars($displayData['civil_status']); ?></strong></div>
          <div class="info-row"><span>Citizenship</span><strong><?php echo htmlspecialchars($displayData['citizenship']); ?></strong></div>
          <div class="info-row"><span>Government ID Type</span><strong><?php echo htmlspecialchars($displayData['valid_id_type']); ?></strong></div>
          <div class="info-row"><span>National ID / Government ID Number</span><strong><?php echo htmlspecialchars($displayData['national_id']); ?></strong></div>
        </div>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <h3>Contact Information</h3>
          <button class="btn-link" data-action="edit-contact"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Mobile Number</span><strong><?php echo htmlspecialchars($displayData['mobile_number']); ?></strong></div>
          <div class="info-row"><span>Email Address</span><strong><?php echo htmlspecialchars($displayData['email']); ?></strong></div>
          <div class="info-row"><span>Emergency Contact Person</span><strong><?php echo htmlspecialchars($displayData['emergency_contact_name']); ?></strong></div>
          <div class="info-row"><span>Emergency Contact Number</span><strong><?php echo htmlspecialchars($displayData['emergency_contact_number']); ?></strong></div>
          <div class="info-row"><span>Emergency Contact Relationship</span><strong><?php echo htmlspecialchars($displayData['emergency_contact_relationship']); ?></strong></div>
        </div>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <h3>Address Information</h3>
          <button class="btn-link" data-action="edit-address"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>House Number / Street</span><strong><?php echo htmlspecialchars($displayData['house_street']); ?></strong></div>
          <div class="info-row"><span>Purok / Sitio</span><strong><?php echo htmlspecialchars($displayData['purok_sitio']); ?></strong></div>
          <div class="info-row"><span>Barangay</span><strong><?php echo htmlspecialchars($displayData['barangay']); ?></strong></div>
          <div class="info-row"><span>City / Municipality</span><strong><?php echo htmlspecialchars($displayData['city']); ?></strong></div>
          <div class="info-row"><span>Province</span><strong><?php echo htmlspecialchars($displayData['province']); ?></strong></div>
          <div class="info-row"><span>Length of Residency</span><strong><?php echo htmlspecialchars($displayData['years_of_residency']); ?></strong></div>
        </div>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <h3>Household Information</h3>
          <button class="btn-link" data-action="edit-household"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Household Head Name</span><strong><?php echo htmlspecialchars($displayData['household_head_name']); ?></strong></div>
          <div class="info-row"><span>Relationship to Household Head</span><strong><?php echo htmlspecialchars($displayData['relationship_to_head']); ?></strong></div>
          <div class="info-row"><span>Number of Household Members</span><strong><?php echo htmlspecialchars($displayData['household_members_count']); ?></strong></div>
        </div>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <h3>Employment Information</h3>
          <button class="btn-link" data-action="edit-employment"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Educational Attainment</span><strong><?php echo htmlspecialchars($displayData['educational_attainment']); ?></strong></div>
          <div class="info-row"><span>Occupation</span><strong><?php echo htmlspecialchars($displayData['occupation']); ?></strong></div>
          <div class="info-row"><span>Employment Status</span><strong><?php echo htmlspecialchars($displayData['employment_status']); ?></strong></div>
          <div class="info-row"><span>Employer Name</span><strong><?php echo htmlspecialchars($displayData['employer_name']); ?></strong></div>
          <div class="info-row"><span>Senior Citizen</span><strong><?php echo htmlspecialchars($displayData['is_senior_citizen']); ?></strong></div>
          <div class="info-row"><span>PWD</span><strong><?php echo htmlspecialchars($displayData['is_pwd']); ?></strong></div>
          <div class="info-row"><span>PWD ID Number</span><strong><?php echo htmlspecialchars($displayData['pwd_id_number']); ?></strong></div>
          <div class="info-row"><span>Solo Parent</span><strong><?php echo htmlspecialchars($displayData['is_solo_parent']); ?></strong></div>
          <div class="info-row"><span>Solo Parent ID Number</span><strong><?php echo htmlspecialchars($displayData['solo_parent_id_number']); ?></strong></div>
          <div class="info-row"><span>IP Member</span><strong><?php echo htmlspecialchars($displayData['is_ip_member']); ?></strong></div>
          <div class="info-row"><span>IP Group</span><strong><?php echo htmlspecialchars($displayData['ip_group']); ?></strong></div>
          <div class="info-row"><span>4Ps Beneficiary</span><strong><?php echo htmlspecialchars($displayData['is_4ps_beneficiary']); ?></strong></div>
        </div>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <h3>Account Information</h3>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Username</span><strong><?php echo htmlspecialchars($displayData['username']); ?></strong></div>
          <div class="info-row"><span>Password</span><strong>************</strong></div>
          <div class="info-row action-row"><span>Change Password</span><button class="btn-ghost" data-action="change-password">Change Password</button></div>
          <div class="info-row"><span>Account Created Date</span><strong><?php echo htmlspecialchars($displayData['account_created']); ?></strong></div>
        </div>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <h3>Verification</h3>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Uploaded Government ID</span><strong id="uploadedIdValue"><?php echo htmlspecialchars($displayData['id_document']); ?></strong></div>
          <div class="info-row"><span>Verification Status</span><strong><?php echo $verificationBadge; ?></strong></div>
          <div class="info-row action-row"><span>Upload ID</span><button class="btn-ghost" data-action="upload-id">Upload ID</button></div>
        </div>
      </article>
    </section>
  </main>

  <input type="file" id="idUploadInput" accept="image/*,.pdf" hidden>

  <script src="resident_profile.js"></script>
</body>
</html>
