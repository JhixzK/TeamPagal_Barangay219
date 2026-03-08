<?php
/**
 * E-Barangay Information Management System
 * Request Certificate
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
    'date_of_birth' => '',
    'gender' => '',
    'civil_status' => '',
    'mobile_number' => '',
    'house_no' => '',
    'street' => ''
];

$residentName = $username;
$residentFullAddress = 'Barangay 219, Tondo, Manila';
$dateOfBirth = '';
$civilStatus = '';
$gender = '';
$contactNumber = '';

// Get resident details from database
$db = Database::getInstance();
if ($residentId) {
    $sql = "SELECT first_name, middle_name, last_name, date_of_birth, gender, civil_status, mobile_number, house_no, street FROM residents WHERE id = ?";
    $resident = $db->fetchOne($sql, [$residentId]);
    if ($resident) {
        $residentData = array_merge($residentData, $resident);
        $residentName = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
        $residentFullAddress = trim(($resident['house_no'] ?? '') . ' ' . ($resident['street'] ?? '')) . ', Barangay 219, Tondo, Manila';
        $dateOfBirth = $resident['date_of_birth'] ? date('F d, Y', strtotime($resident['date_of_birth'])) : '';
        $civilStatus = ucfirst(str_replace('_', ' ', $resident['civil_status'] ?? ''));
        $gender = ucfirst($resident['gender'] ?? '');
        $contactNumber = $resident['mobile_number'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request Certificate | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="request_certificate.css">
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
        <a class="nav-item active" href="request_certificate.php">
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
        <a class="nav-item" href="#">
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

  <main class="main-content">
    <section class="page-head">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Request Certificate</h2>
        <p class="page-subtitle">Submit a request for official barangay documents.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <form id="requestForm" novalidate>
      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-regular fa-file-lines"></i> Certificate Type</h3>
        </div>
        <div class="form-grid two-col">
          <label class="field">
            <span>Certificate Type</span>
            <select id="certificateType" required>
              <option value="">Select certificate type</option>
              <option value="Barangay Clearance">Barangay Clearance</option>
              <option value="Certificate of Residency">Certificate of Residency</option>
              <option value="Certificate of Indigency">Certificate of Indigency</option>
              <option value="Certificate of Good Moral Character">Certificate of Good Moral Character</option>
              <option value="Business Clearance">Business Clearance</option>
              <option value="Barangay ID Request">Barangay ID Request</option>
            </select>
            <small class="error" id="certificateTypeError"></small>
          </label>

          <label class="field">
            <span>Purpose of Request</span>
            <select id="purpose" required>
              <option value="">Select purpose</option>
              <option value="Employment">Employment</option>
              <option value="Scholarship">Scholarship</option>
              <option value="Business Requirement">Business Requirement</option>
              <option value="School Requirement">School Requirement</option>
              <option value="Government Requirement">Government Requirement</option>
              <option value="Personal Use">Personal Use</option>
              <option value="Others">Others</option>
            </select>
            <small class="error" id="purposeError"></small>
          </label>

          <label class="field hidden" id="purposeOtherWrap">
            <span>Please specify purpose</span>
            <input type="text" id="purposeOther" placeholder="Type your purpose">
            <small class="error" id="purposeOtherError"></small>
          </label>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-regular fa-id-card"></i> Resident Information (Auto Filled)</h3>
        </div>
        <div class="form-grid two-col">
          <label class="field">
            <span>Full Name</span>
            <input type="text" id="residentName" value="<?php echo htmlspecialchars($residentName); ?>" readonly>
          </label>
          <label class="field">
            <span>Date of Birth</span>
            <input type="text" id="dob" value="<?php echo htmlspecialchars($dateOfBirth); ?>" readonly>
          </label>
          <label class="field">
            <span>Address</span>
            <input type="text" id="address" value="<?php echo htmlspecialchars($residentFullAddress); ?>" readonly>
          </label>
          <label class="field">
            <span>Civil Status</span>
            <input type="text" id="civilStatus" value="<?php echo htmlspecialchars($civilStatus); ?>" readonly>
          </label>
          <label class="field">
            <span>Gender</span>
            <input type="text" id="gender" value="<?php echo htmlspecialchars($gender); ?>" readonly>
          </label>
          <label class="field">
            <span>Contact Number</span>
            <input type="text" id="contact" value="<?php echo htmlspecialchars($contactNumber); ?>" readonly>
          </label>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-solid fa-sliders"></i> Additional Information</h3>
        </div>
        <div class="form-grid two-col" id="additionalFields">
          <label class="field additional-field" data-key="yearsResidency">
            <span>Years of Residency</span>
            <input type="number" id="yearsResidency" min="0" placeholder="Enter years">
            <small class="error" id="yearsResidencyError"></small>
          </label>

          <label class="field additional-field" data-key="monthlyIncome">
            <span>Monthly Income</span>
            <input type="number" id="monthlyIncome" min="0" placeholder="Enter amount">
            <small class="error" id="monthlyIncomeError"></small>
          </label>

          <label class="field additional-field" data-key="businessName">
            <span>Business Name</span>
            <input type="text" id="businessName" placeholder="Enter business name">
            <small class="error" id="businessNameError"></small>
          </label>

          <label class="field additional-field" data-key="businessAddress">
            <span>Business Address</span>
            <input type="text" id="businessAddress" placeholder="Enter business address">
            <small class="error" id="businessAddressError"></small>
          </label>

          <label class="field additional-field" data-key="dependents">
            <span>Number of Dependents</span>
            <input type="number" id="dependents" min="0" placeholder="Enter number">
            <small class="error" id="dependentsError"></small>
          </label>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-solid fa-cloud-arrow-up"></i> Upload Documents</h3>
        </div>
        <div class="upload-box" id="uploadBox">
          <input type="file" id="documents" accept=".jpg,.jpeg,.png,.pdf" multiple hidden>
          <button type="button" class="btn-ghost" id="browseBtn"><i class="fa-regular fa-folder-open"></i> Choose Files</button>
          <p>Drag files here or click "Choose Files"</p>
          <small>Accepted: JPG, PNG, PDF | Max file size: 5MB each</small>
        </div>
        <ul class="file-list" id="fileList"></ul>
        <small class="error" id="documentsError"></small>
      </section>

      <section class="card form-card payment-section">
        <div class="card-head">
          <h3><i class="fa-solid fa-wallet"></i> Fee Information</h3>
        </div>
        <div class="payment-grid">
          <div class="amount-row"><span>Certificate Fee</span><strong id="certificateFee">PHP 0.00</strong></div>
          <div class="amount-row"><span>Processing Fee</span><strong id="processingFee">PHP 25.00</strong></div>
          <div class="amount-row total"><span>Total Amount</span><strong id="totalFee">PHP 25.00</strong></div>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-solid fa-list-check"></i> Request Summary</h3>
        </div>
        <div class="summary-grid">
          <div class="summary-item"><span>Certificate Type</span><strong id="summaryCertificate">-</strong></div>
          <div class="summary-item"><span>Purpose</span><strong id="summaryPurpose">-</strong></div>
          <div class="summary-item"><span>Resident Name</span><strong id="summaryName"><?php echo htmlspecialchars($residentName); ?></strong></div>
          <div class="summary-item"><span>Address</span><strong id="summaryAddress"><?php echo htmlspecialchars($residentFullAddress); ?></strong></div>
          <div class="summary-item"><span>Uploaded Documents</span><strong id="summaryDocuments">None</strong></div>
          <div class="summary-item"><span>Total Fee</span><strong id="summaryTotal">PHP 25.00</strong></div>
        </div>

        <div class="actions">
          <button type="submit" class="btn-primary"><i class="fa-regular fa-paper-plane"></i> Submit Request</button>
          <button type="button" class="btn-secondary" id="cancelBtn">Cancel</button>
        </div>

        <div class="submission-result hidden" id="submissionResult">
          <h4>Request Submitted Successfully</h4>
          <p>Reference Number: <strong id="referenceNumber">-</strong></p>
          <p>Status: <strong class="status pending">Pending</strong></p>
        </div>
      </section>
    </form>
  </main>

  <script src="request_certificate.js"></script>
</body>
</html>
