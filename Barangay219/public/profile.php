<?php
define('ACCESS_ALLOWED', true);
$page_title = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

include __DIR__ . '/../includes/sidebar.php';

$db = Database::getInstance();
$userId = getCurrentUserId();
$username = getCurrentUsername();
$role = getCurrentUserRole();

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]) ?? [];
$resident = [];
$official = [];

$residentId = (int)($user['resident_id'] ?? 0);
if ($residentId > 0) {
    $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$residentId]) ?? [];
}

// Check if user is linked to an official record
try {
    $official = $db->fetchOne(
        "SELECT * FROM officials WHERE user_id = ? AND status = 'active' LIMIT 1",
        [$userId]
    ) ?? [];
} catch (Exception $e) {
    $official = [];
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function pick($row, $keys, $default = 'N/A') {
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== null && $row[$k] !== '') return $row[$k];
    }
    return $default;
}

function formatRoleLabel($r) {
    return ucfirst(str_replace('_', ' ', (string)$r));
}

function formatDate($d) {
    if (!$d) return 'N/A';
    $ts = strtotime($d);
    return $ts ? date('F d, Y', $ts) : 'N/A';
}

function formatPhone($v) {
    $raw = trim((string)$v);
    if ($raw === '' || $raw === 'N/A') return 'N/A';
    $digits = preg_replace('/\D+/', '', $raw);
    if (strpos($digits, '63') === 0) $digits = substr($digits, 2);
    if (strpos($digits, '0') === 0) $digits = substr($digits, 1);
    $digits = substr($digits, 0, 10);
    if (strlen($digits) < 10) return $raw;
    return '+63 ' . $digits;
}

$avatar_url = '';
if ($userId) {
    $exts = ['png','jpg','jpeg','gif'];
    foreach ($exts as $e) {
        $f = PUBLIC_PATH . '/uploads/profile/' . $userId . '.' . $e;
        if (file_exists($f)) {
            $avatar_url = BASE_URL . 'uploads/profile/' . $userId . '.' . $e;
            break;
        }
    }
}
if (empty($avatar_url)) {
    $avatar_url = ASSETS_URL . 'img/default-avatar.svg';
}

$fullName = trim(pick($resident, ['first_name'], '') . ' ' . (pick($resident, ['middle_name'], '') ? pick($resident, ['middle_name'], '') . ' ' : '') . pick($resident, ['last_name'], ''));
if ($fullName === '' || $fullName === 'N/A N/A') {
    $fullName = pick($official, ['full_name'], $username);
}

$email = pick($user, ['email'], pick($resident, ['email'], 'N/A'));
$accountStatus = ucfirst(pick($user, ['status'], 'active'));
$createdAt = formatDate(pick($user, ['created_at'], ''));

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = trim($_POST['section'] ?? '');

    if ($section === 'password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($newPassword === '' || strlen($newPassword) < 8) {
            $errorMsg = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsg = 'Password confirmation does not match.';
        } else {
            $userRow = $db->fetchOne('SELECT password FROM users WHERE id = ?', [$userId]);
            if (!$userRow || !password_verify($currentPassword, (string)$userRow['password'])) {
                $errorMsg = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->query('UPDATE users SET password = ? WHERE id = ?', [$newHash, $userId]);
                $successMsg = 'Password changed successfully.';
            }
        }
    }

    if ($section === 'personal' && $residentId > 0) {
        $fields = [];
        $allowed = ['first_name','middle_name','last_name','suffix','place_of_birth','gender','civil_status','citizenship'];
        foreach ($allowed as $f) {
            if (isset($_POST[$f])) {
                $fields[$f] = trim($_POST[$f]);
            }
        }
        if (isset($_POST['birth_date']) && $_POST['birth_date'] !== '') {
            $ts = strtotime($_POST['birth_date']);
            if ($ts) $fields['birth_date'] = date('Y-m-d', $ts);
        }

        if (!empty($fields)) {
            $setParts = [];
            $params = [];
            foreach ($fields as $col => $val) {
                $setParts[] = "`$col` = ?";
                $params[] = $val === '' ? null : $val;
            }
            $params[] = $residentId;
            $db->query('UPDATE residents SET ' . implode(', ', $setParts) . ' WHERE id = ?', $params);
            $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$residentId]) ?? [];
            $fullName = trim(pick($resident, ['first_name'], '') . ' ' . (pick($resident, ['middle_name'], '') ? pick($resident, ['middle_name'], '') . ' ' : '') . pick($resident, ['last_name'], ''));
            $successMsg = 'Personal information updated.';
        }
    }

    if ($section === 'contact') {
        $newEmail = trim($_POST['email'] ?? '');
        if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Invalid email address format.';
        } else {
            $db->query('UPDATE users SET email = ? WHERE id = ?', [$newEmail === '' ? null : $newEmail, $userId]);
            $email = $newEmail === '' ? 'N/A' : $newEmail;
            $user['email'] = $newEmail;

            if ($residentId > 0) {
                $contactFields = [];
                if (isset($_POST['contact_number'])) $contactFields['contact_number'] = trim($_POST['contact_number']);
                if ($newEmail !== '') $contactFields['email'] = $newEmail;
                if (isset($_POST['emergency_contact_name'])) $contactFields['emergency_contact_name'] = trim($_POST['emergency_contact_name']);
                if (isset($_POST['emergency_contact_number'])) $contactFields['emergency_contact_number'] = trim($_POST['emergency_contact_number']);

                if (!empty($contactFields)) {
                    $setParts = [];
                    $params = [];
                    foreach ($contactFields as $col => $val) {
                        $setParts[] = "`$col` = ?";
                        $params[] = $val === '' ? null : $val;
                    }
                    $params[] = $residentId;
                    $db->query('UPDATE residents SET ' . implode(', ', $setParts) . ' WHERE id = ?', $params);
                    $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$residentId]) ?? [];
                }
            }
            $successMsg = 'Contact information updated.';
        }
    }
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>profile-official.css?v=<?php echo time(); ?>">

<div class="main-content">
    <div class="container-fluid">
        <div class="profile-page-header">
            <h2><i class="bi bi-person-badge"></i> My Profile</h2>
            <p class="text-muted">Manage your account details and personal information.</p>
        </div>

        <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo h($successMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo h($errorMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Profile Summary Card -->
        <div class="profile-summary-card">
            <div class="profile-avatar-section">
                <img id="profileAvatar" src="<?php echo h($avatar_url); ?>" alt="Avatar" class="profile-avatar">
                <form id="avatarForm" enctype="multipart/form-data" class="avatar-upload-form">
                    <label class="avatar-upload-btn">
                        <i class="bi bi-camera"></i> Change Photo
                        <input type="file" name="avatar" accept="image/*" hidden>
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    </label>
                </form>
                <div id="avatarMsg" class="small text-muted mt-1"></div>
            </div>
            <div class="profile-summary-info">
                <h3><?php echo h($fullName); ?></h3>
                <p class="profile-role-badge"><?php echo h(formatRoleLabel($role)); ?></p>
                <?php if (!empty($official)): ?>
                <p class="profile-position"><?php echo h(formatRoleLabel($official['position'] ?? '')); ?></p>
                <?php endif; ?>
                <div class="profile-meta-row">
                    <span><i class="bi bi-person"></i> <?php echo h($username); ?></span>
                    <span><i class="bi bi-envelope"></i> <?php echo h($email); ?></span>
                    <span class="status-badge status-<?php echo strtolower($accountStatus); ?>"><?php echo h($accountStatus); ?></span>
                </div>
            </div>
        </div>

        <div class="profile-cards-grid">
            <!-- Account Information -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h5><i class="bi bi-shield-lock"></i> Account Information</h5>
                </div>
                <div class="profile-info-list">
                    <div class="profile-info-row">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo h($username); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo h($email); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">System Role</span>
                        <span class="info-value"><?php echo h(formatRoleLabel($role)); ?></span>
                    </div>
                    <?php if (!empty($official)): ?>
                    <div class="profile-info-row">
                        <span class="info-label">Official Position</span>
                        <span class="info-value"><?php echo h(formatRoleLabel($official['position'] ?? '')); ?></span>
                    </div>
                    <?php if (!empty($official['term_start'])): ?>
                    <div class="profile-info-row">
                        <span class="info-label">Term Period</span>
                        <span class="info-value"><?php echo h(formatDate($official['term_start'])); ?> &mdash; <?php echo h(formatDate($official['term_end'] ?? '')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div class="profile-info-row">
                        <span class="info-label">Account Status</span>
                        <span class="info-value"><span class="status-badge status-<?php echo strtolower($accountStatus); ?>"><?php echo h($accountStatus); ?></span></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Account Created</span>
                        <span class="info-value"><?php echo h($createdAt); ?></span>
                    </div>
                    <?php if (!empty($user['updated_at'])): ?>
                    <div class="profile-info-row">
                        <span class="info-label">Last Updated</span>
                        <span class="info-value"><?php echo h(formatDate($user['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h5><i class="bi bi-person-vcard"></i> Personal Information</h5>
                    <?php if ($residentId > 0): ?>
                    <button class="btn btn-sm btn-outline-primary toggle-edit-btn" data-target="form-personal"><i class="bi bi-pencil-square"></i> Edit</button>
                    <?php endif; ?>
                </div>
                <?php if ($residentId > 0): ?>
                <div class="profile-info-list">
                    <div class="profile-info-row">
                        <span class="info-label">First Name</span>
                        <span class="info-value"><?php echo h(pick($resident, ['first_name'])); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Middle Name</span>
                        <span class="info-value"><?php echo h(pick($resident, ['middle_name'])); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Last Name</span>
                        <span class="info-value"><?php echo h(pick($resident, ['last_name'])); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Suffix</span>
                        <span class="info-value"><?php echo h(pick($resident, ['suffix'])); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Date of Birth</span>
                        <span class="info-value"><?php echo h(formatDate(pick($resident, ['birth_date'], ''))); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Place of Birth</span>
                        <span class="info-value"><?php echo h(pick($resident, ['place_of_birth'])); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?php echo h(ucfirst(pick($resident, ['gender']))); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Civil Status</span>
                        <span class="info-value"><?php echo h(ucfirst(pick($resident, ['civil_status']))); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Citizenship</span>
                        <span class="info-value"><?php echo h(pick($resident, ['citizenship'], 'Filipino')); ?></span>
                    </div>
                </div>
                <form method="POST" class="profile-edit-form" id="form-personal" style="display:none;">
                    <input type="hidden" name="section" value="personal">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" value="<?php echo h(pick($resident, ['first_name'], '')); ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" class="form-control" name="middle_name" value="<?php echo h(pick($resident, ['middle_name'], '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" value="<?php echo h(pick($resident, ['last_name'], '')); ?>" required></div>
                        <div class="col-md-3"><label class="form-label">Suffix</label><input type="text" class="form-control" name="suffix" value="<?php echo h(pick($resident, ['suffix'], '')); ?>"></div>
                        <div class="col-md-3"><label class="form-label">Birth Date</label><input type="date" class="form-control" name="birth_date" value="<?php echo h(pick($resident, ['birth_date'], '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Place of Birth</label><input type="text" class="form-control" name="place_of_birth" value="<?php echo h(pick($resident, ['place_of_birth'], '')); ?>"></div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select</option>
                                <option value="male" <?php echo strtolower(pick($resident, ['gender'], '')) === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo strtolower(pick($resident, ['gender'], '')) === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo strtolower(pick($resident, ['gender'], '')) === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Civil Status</label>
                            <select class="form-select" name="civil_status">
                                <option value="">Select</option>
                                <?php foreach(['single','married','widowed','divorced','separated'] as $cs): ?>
                                <option value="<?php echo $cs; ?>" <?php echo strtolower(pick($resident, ['civil_status'], '')) === $cs ? 'selected' : ''; ?>><?php echo ucfirst($cs); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Citizenship</label><input type="text" class="form-control" name="citizenship" value="<?php echo h(pick($resident, ['citizenship'], 'Filipino')); ?>"></div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Changes</button></div>
                </form>
                <?php else: ?>
                <div class="profile-info-list">
                    <p class="text-muted small mb-0">No linked resident record. Personal details are not available.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Contact Information -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h5><i class="bi bi-telephone"></i> Contact Information</h5>
                    <button class="btn btn-sm btn-outline-primary toggle-edit-btn" data-target="form-contact"><i class="bi bi-pencil-square"></i> Edit</button>
                </div>
                <div class="profile-info-list">
                    <div class="profile-info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo h($email); ?></span>
                    </div>
                    <?php if ($residentId > 0): ?>
                    <div class="profile-info-row">
                        <span class="info-label">Mobile Number</span>
                        <span class="info-value"><?php echo h(formatPhone(pick($resident, ['contact_number','mobile_number'], ''))); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Emergency Contact</span>
                        <span class="info-value"><?php echo h(pick($resident, ['emergency_contact_name'])); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="info-label">Emergency Number</span>
                        <span class="info-value"><?php echo h(formatPhone(pick($resident, ['emergency_contact_number'], ''))); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <form method="POST" class="profile-edit-form" id="form-contact" style="display:none;">
                    <input type="hidden" name="section" value="contact">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" class="form-control" name="email" value="<?php echo h(pick($user, ['email'], '')); ?>"></div>
                        <?php if ($residentId > 0): ?>
                        <div class="col-md-6"><label class="form-label">Mobile Number</label><input type="text" class="form-control" name="contact_number" value="<?php echo h(pick($resident, ['contact_number','mobile_number'], '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Emergency Contact</label><input type="text" class="form-control" name="emergency_contact_name" value="<?php echo h(pick($resident, ['emergency_contact_name'], '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Emergency Number</label><input type="text" class="form-control" name="emergency_contact_number" value="<?php echo h(pick($resident, ['emergency_contact_number'], '')); ?>"></div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Changes</button></div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h5><i class="bi bi-key"></i> Change Password</h5>
                    <button class="btn btn-sm btn-outline-primary toggle-edit-btn" data-target="form-password"><i class="bi bi-pencil-square"></i> Change</button>
                </div>
                <div class="profile-info-list">
                    <div class="profile-info-row">
                        <span class="info-label">Password</span>
                        <span class="info-value">************</span>
                    </div>
                </div>
                <form method="POST" class="profile-edit-form" id="form-password" style="display:none;">
                    <input type="hidden" name="section" value="password">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                        <div class="col-md-4"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
                        <div class="col-md-4"><label class="form-label">Confirm Password</label><input type="password" class="form-control" name="confirm_password" minlength="8" required></div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Update Password</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarForm')?.querySelector('input[type="file"]')?.addEventListener('change', function() {
    var form = document.getElementById('avatarForm');
    var fd = new FormData(form);
    var msg = document.getElementById('avatarMsg');
    msg.textContent = 'Uploading...';

    fetch('<?php echo API_URL; ?>profile.php?action=upload', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    }).then(function(res){ return res.json(); })
    .then(function(data){
        if (data.success) {
            var img = document.getElementById('profileAvatar');
            img.src = data.data.url + '?t=' + new Date().getTime();
            msg.textContent = 'Uploaded successfully.';
            msg.className = 'small text-success mt-1';
        } else {
            msg.textContent = data.message || 'Upload failed.';
            msg.className = 'small text-danger mt-1';
        }
    }).catch(function(){ msg.textContent = 'Upload error.'; msg.className = 'small text-danger mt-1'; });
});

document.querySelectorAll('.toggle-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var target = document.getElementById(this.dataset.target);
        if (!target) return;
        var isHidden = target.style.display === 'none';
        document.querySelectorAll('.profile-edit-form').forEach(function(f) { f.style.display = 'none'; });
        document.querySelectorAll('.toggle-edit-btn').forEach(function(b) {
            b.innerHTML = '<i class="bi bi-pencil-square"></i> ' + (b.dataset.target === 'form-password' ? 'Change' : 'Edit');
        });
        if (isHidden) {
            target.style.display = 'block';
            this.innerHTML = '<i class="bi bi-x-lg"></i> Cancel';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
