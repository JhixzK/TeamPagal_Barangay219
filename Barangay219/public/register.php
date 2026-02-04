<?php
// Resident Registration Page
// (C) 2026

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';

$allowed_cities = [
    'Manila', 'Quezon City', 'Caloocan', 'Makati', 'Pasig', 'Taguig', 'Mandaluyong', 'Parañaque', 'Las Piñas', 'Muntinlupa', 'Marikina', 'Pasay', 'San Juan', 'Navotas', 'Malabon', 'Valenzuela', 'Pateros'
];
$allowed_provinces = [
    'Metro Manila', 'Bulacan', 'Cavite', 'Laguna', 'Rizal', 'Batangas', 'Pampanga', 'Nueva Ecija', 'Tarlac', 'Bataan', 'Zambales', 'Pangasinan'
];
$barangay = 'Barangay 219, Tondo';
$city = 'Manila';
$province = 'Metro Manila';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Username and Password
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        // Username: required, 5-30 chars, alphanumeric/underscore, best practice: check uniqueness in DB (not implemented here)
        if (!$username || !preg_match('/^[A-Za-z0-9_]{5,30}$/', $username)) {
            $field_errors['username'] = 'Username is required, 5-30 characters, letters, numbers, and underscore only.';
        }
        // Password: required, min 8 chars, max 16 chars, at least 1 letter and 1 number
        if (!$password || !preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{' . PASSWORD_MIN_LENGTH . ',' . PASSWORD_MAX_LENGTH . '}$/', $password)) {
            $field_errors['password'] = 'Password must be 8-16 characters with a mix of letters and numbers.';
        }
        // Password confirmation
        if ($password !== $password_confirm) {
            $field_errors['password_confirm'] = 'Passwords do not match.';
        }
    // Validate and sanitize input
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $house_street = trim($_POST['house_street'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $family_status = $_POST['family_status'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $id_numbers = trim($_POST['id_numbers'] ?? '');
    $health_info = trim($_POST['health_info'] ?? '');
    $socio_info = trim($_POST['socio_info'] ?? '');

    $field_errors = [];
    // First Name: required, letters only, max 50
    if (!$first_name || !preg_match('/^[A-Za-z ]+$/', $first_name) || mb_strlen($first_name) > 50) {
        $field_errors['first_name'] = 'First Name is required, letters only, max 50 characters.';
    }
    // Middle Name: optional, letters only, max 50
    if ($middle_name && (!preg_match('/^[A-Za-z ]+$/', $middle_name) || mb_strlen($middle_name) > 50)) {
        $field_errors['middle_name'] = 'Middle Name: letters only, max 50 characters.';
    }
    // Last Name: required, letters only, max 50
    if (!$last_name || !preg_match('/^[A-Za-z ]+$/', $last_name) || mb_strlen($last_name) > 50) {
        $field_errors['last_name'] = 'Last Name is required, letters only, max 50 characters.';
    }
    // Suffix: optional, letters only, max 10
    if ($suffix && (!preg_match('/^[A-Za-z]+$/', $suffix) || mb_strlen($suffix) > 10)) {
        $field_errors['suffix'] = 'Suffix: letters only, max 10 characters.';
    }
    // DOB: required, valid date, not in future
    if (!$dob || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || strtotime($dob) === false || strtotime($dob) > time()) {
        $field_errors['dob'] = 'Date of Birth is required, must be a valid date not in the future.';
    }
    // Gender: required, must be one of allowed
    $allowed_genders = ['Male','Female','Other'];
    if (!$gender || !in_array($gender, $allowed_genders)) {
        $field_errors['gender'] = 'Gender is required.';
    }
    // Civil Status: required, must be one of allowed
    $allowed_civil = ['Single','Married','Widowed','Separated'];
    if (!$civil_status || !in_array($civil_status, $allowed_civil)) {
        $field_errors['civil_status'] = 'Civil Status is required.';
    }
    // Contact: required, digits only, max 11
    if (!$contact || !preg_match('/^\d{11}$/', $contact)) {
        $field_errors['contact'] = 'Contact Number is required, 11 digits only.';
    }
    // Email: optional, valid format, max 100
    if ($email && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)) {
        $field_errors['email'] = 'Email: must be valid format, max 100 characters.';
    }
    // House/Street: required, letters, numbers, spaces, basic punctuation, max 100
    if (!$house_street || !preg_match('/^[A-Za-z0-9 .,#\-\/]+$/', $house_street) || mb_strlen($house_street) > 100) {
        $field_errors['house_street'] = 'House Number/Street: required, max 100 chars, valid characters only.';
    }
    // Occupation: optional, letters, spaces, hyphens, max 50
    if ($occupation && (!preg_match('/^[A-Za-z \-]+$/', $occupation) || mb_strlen($occupation) > 50)) {
        $field_errors['occupation'] = 'Occupation: letters, spaces, hyphens only, max 50 characters.';
    }
    // Socio, ID, Health, Remarks: optional, max 300, valid chars
    if ($socio_info && (!preg_match('/^[A-Za-z0-9 .,#\-\/]+$/', $socio_info) || mb_strlen($socio_info) > 300)) {
        $field_errors['socio_info'] = 'Socio-Economic Info: max 300 chars, valid characters only.';
    }
    if ($id_numbers && (!preg_match('/^[A-Za-z0-9 .,#\-\/]+$/', $id_numbers) || mb_strlen($id_numbers) > 300)) {
        $field_errors['id_numbers'] = 'ID Numbers: max 300 chars, valid characters only.';
    }
    if ($health_info && (!preg_match('/^[A-Za-z0-9 .,#\-\/]+$/', $health_info) || mb_strlen($health_info) > 300)) {
        $field_errors['health_info'] = 'Health Info: max 300 chars, valid characters only.';
    }
    // Barangay, City, Province: fixed, must match
    if ($barangay !== 'Barangay 219, Tondo') {
        $field_errors['barangay'] = 'Barangay must be Barangay 219, Tondo.';
    }
    if ($city !== 'Manila') {
        $field_errors['city'] = 'City must be Manila.';
    }
    if ($province !== 'Metro Manila') {
        $field_errors['province'] = 'Province must be Metro Manila.';
    }
    // Family Status: required, must be head or non-head
    if (!$family_status || !in_array($family_status, ['head','non-head'])) {
        $field_errors['family_status'] = 'Family Status is required.';
    }
    // Role: required if non-head
    if ($family_status === 'non-head' && !$role) {
        $field_errors['role'] = 'Relationship to Head is required for Non-Head.';
    }

    if (count($field_errors) > 0) {
        $error = 'Please correct the highlighted fields.';
    } else {
        // TODO: Save to database (implement DB logic here)
        $success = 'Registration successful! Your information will be reviewed.';
        // Optionally redirect or clear form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registration - <?php echo APP_NAME; ?></title>
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        .capitalize { text-transform: capitalize; }
        .input-group.suffix-group .form-select:focus {
    box-shadow: none;
}
.input-group.suffix-group:hover .form-select,
.input-group.suffix-group:focus-within .form-select {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
}
.input-group.suffix-group:hover .input-group-text,
.input-group.suffix-group:focus-within .input-group-text {
    border-color: #86b7fe;
    background: #e7f1ff;
}
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Resident Registration</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"> <?php echo $error; ?> </div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"> <?php echo $success; ?> </div>
                    <?php endif; ?>
                    <form method="post" id="registerForm" autocomplete="off" enctype="multipart/form-data">
                        <div class="mb-4 p-3 border rounded bg-light">
                            <h5 class="mb-3 text-primary">Account Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control<?php if(isset($field_errors['username'])) echo ' is-invalid'; ?>" maxlength="30" pattern="[A-Za-z0-9_]{5,30}" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                    <?php if(isset($field_errors['username'])): ?><div class="invalid-feedback"><?php echo $field_errors['username']; ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control<?php if(isset($field_errors['password'])) echo ' is-invalid'; ?>" minlength="8" maxlength="16" required autocomplete="new-password">
                                    <?php if(isset($field_errors['password'])): ?><div class="invalid-feedback"><?php echo $field_errors['password']; ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirm" class="form-control<?php if(isset($field_errors['password_confirm'])) echo ' is-invalid'; ?>" minlength="8" maxlength="16" required autocomplete="new-password">
                                    <?php if(isset($field_errors['password_confirm'])): ?><div class="invalid-feedback"><?php echo $field_errors['password_confirm']; ?></div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 p-3 border rounded bg-light">
                            <h5 class="mb-3 text-primary">Personal Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control capitalize<?php if(isset($field_errors['first_name'])) echo ' is-invalid'; ?>" maxlength="50" pattern="[A-Za-z ]+" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                <?php if(isset($field_errors['first_name'])): ?><div class="invalid-feedback"><?php echo $field_errors['first_name']; ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control capitalize<?php if(isset($field_errors['middle_name'])) echo ' is-invalid'; ?>" maxlength="50" pattern="[A-Za-z ]*" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                                <?php if(isset($field_errors['middle_name'])): ?><div class="invalid-feedback"><?php echo $field_errors['middle_name']; ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control capitalize<?php if(isset($field_errors['last_name'])) echo ' is-invalid'; ?>" maxlength="50" pattern="[A-Za-z ]+" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                <?php if(isset($field_errors['last_name'])): ?><div class="invalid-feedback"><?php echo $field_errors['last_name']; ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Suffix</label>
                                <select name="suffix" class="form-select<?php if(isset($field_errors['suffix'])) echo ' is-invalid'; ?>">
                                    <option value="" <?php if(empty($_POST['suffix'])) echo 'selected'; ?>></option>
                                    <option value="Jr." <?php if(($_POST['suffix'] ?? '')==='Jr.') echo 'selected'; ?>>Jr.</option>
                                    <option value="Sr." <?php if(($_POST['suffix'] ?? '')==='Sr.') echo 'selected'; ?>>Sr.</option>
                                    <option value="III" <?php if(($_POST['suffix'] ?? '')==='III') echo 'selected'; ?>>III</option>
                                    <option value="IV" <?php if(($_POST['suffix'] ?? '')==='IV') echo 'selected'; ?>>IV</option>
                                    <option value="V" <?php if(($_POST['suffix'] ?? '')==='V') echo 'selected'; ?>>V</option>
                                </select>
                                <?php if(isset($field_errors['suffix'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['suffix']; ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="dob" id="dob" class="form-control<?php if(isset($field_errors['dob'])) echo ' is-invalid'; ?>" required value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                                <?php if(isset($field_errors['dob'])): ?><div class="invalid-feedback"><?php echo $field_errors['dob']; ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label>Age</label>
                                <input type="text" name="age" id="age" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-control" required>
                                    <option value="">Select</option>
                                    <option value="Male" <?php if(($_POST['gender'] ?? '')==='Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if(($_POST['gender'] ?? '')==='Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if(($_POST['gender'] ?? '')==='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Civil Status <span class="text-danger">*</span></label>
                                <select name="civil_status" class="form-control" required>
                                    <option value="">Select</option>
                                    <option value="Single" <?php if(($_POST['civil_status'] ?? '')==='Single') echo 'selected'; ?>>Single</option>
                                    <option value="Married" <?php if(($_POST['civil_status'] ?? '')==='Married') echo 'selected'; ?>>Married</option>
                                    <option value="Widowed" <?php if(($_POST['civil_status'] ?? '')==='Widowed') echo 'selected'; ?>>Widowed</option>
                                    <option value="Separated" <?php if(($_POST['civil_status'] ?? '')==='Separated') echo 'selected'; ?>>Separated</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Contact Number <span class="text-danger">*</span></label>
                                <input type="text" name="contact" class="form-control<?php if(isset($field_errors['contact'])) echo ' is-invalid'; ?>" maxlength="11" pattern="\d{11}" required value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>">
                                <?php if(isset($field_errors['contact'])): ?><div class="invalid-feedback"><?php echo $field_errors['contact']; ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control<?php if(isset($field_errors['email'])) echo ' is-invalid'; ?>" maxlength="100" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <?php if(isset($field_errors['email'])): ?><div class="invalid-feedback"><?php echo $field_errors['email']; ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>House Number/Street <span class="text-danger">*</span></label>
                            <input type="text" name="house_street" class="form-control<?php if(isset($field_errors['house_street'])) echo ' is-invalid'; ?>" maxlength="100" required value="<?php echo htmlspecialchars($_POST['house_street'] ?? ''); ?>">
                            <?php if(isset($field_errors['house_street'])): ?><div class="invalid-feedback"><?php echo $field_errors['house_street']; ?></div><?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Barangay</label>
                                <input type="text" class="form-control" name="barangay" value="<?php echo htmlspecialchars($barangay); ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>City</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($city); ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Province</label>
                                <input type="text" class="form-control" name="province" value="<?php echo htmlspecialchars($province); ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Family Status <span class="text-danger">*</span></label>
                                <select name="family_status" id="familyStatus" class="form-control" required>
                                    <option value="">Select</option>
                                    <option value="head" <?php if(($_POST['family_status'] ?? '')==='head') echo 'selected'; ?>>Head of Family</option>
                                    <option value="non-head" <?php if(($_POST['family_status'] ?? '')==='non-head') echo 'selected'; ?>>Non-Head of Family</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="roleDiv" style="display:none;">
                                <label>Relationship to Head <span class="text-danger" id="roleRequired" style="display:none;">*</span></label>
                                <select name="role" id="roleSelect" class="form-control">
                                    <option value="">Select Relationship</option>
                                    <option value="Spouse" <?php if(($_POST['role'] ?? '')==='Spouse') echo 'selected'; ?>>Spouse</option>
                                    <option value="Child" <?php if(($_POST['role'] ?? '')==='Child') echo 'selected'; ?>>Child</option>
                                    <option value="Parent" <?php if(($_POST['role'] ?? '')==='Parent') echo 'selected'; ?>>Parent</option>
                                    <option value="Sibling" <?php if(($_POST['role'] ?? '')==='Sibling') echo 'selected'; ?>>Sibling</option>
                                    <option value="Other" <?php if(($_POST['role'] ?? '')==='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <!-- Family Members section will be injected here by JS -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Occupation</label>
                                <input type="text" name="occupation" class="form-control<?php if(isset($field_errors['occupation'])) echo ' is-invalid'; ?>" maxlength="50" value="<?php echo htmlspecialchars($_POST['occupation'] ?? ''); ?>">
                                <?php if(isset($field_errors['occupation'])): ?><div class="invalid-feedback"><?php echo $field_errors['occupation']; ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>ID Uploads <span class="text-info">(Required for approval)</span></label>
                                <input type="file" name="id_uploads[]" class="form-control" accept="application/pdf,image/jpeg,image/png" multiple>
                                <small class="form-text text-muted">Accepted: PDF, JPG, PNG. Max 5MB each. Recommended: PSA Birth Certificate, Passport, Driver’s License, UMID, Postal ID, SSS ID, PRC ID, Voter’s ID.</small>
                            </div>
                        </div>
                        <div class="row">
                            <!-- Health Information and Socio-Economic Information fields removed as requested -->
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="data_privacy" id="data_privacy" required>
                            <label class="form-check-label" for="data_privacy">
                                I consent to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012 and the privacy policy of Barangay 219.
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                        <a href="index.php" class="btn btn-link w-100 mt-2">Back to Login</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>js/register.js"></script>
<script>
// Auto-capitalize name fields and restrict input to letters (and space for names)
function onlyLetters(e) {
    let v = e.target.value.replace(/[^A-Za-z ]/g, '');
    e.target.value = v.replace(/\b\w/g, function(l){ return l.toUpperCase(); });
}
function onlyLettersSuffix(e) {
    let v = e.target.value.replace(/[^A-Za
