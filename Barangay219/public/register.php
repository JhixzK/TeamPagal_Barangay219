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
$barangay = BARANGAY_NAME;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Required fields
    if (!$first_name || !$last_name || !$dob || !$gender || !$civil_status || !$contact || !$house_street || !$city || !$province || !$family_status) {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($city, $allowed_cities)) {
        $error = 'City is not allowed.';
    } elseif (!in_array($province, $allowed_provinces)) {
        $error = 'Province is not allowed.';
    } elseif ($family_status === 'non-head' && !$role) {
        $error = 'Please select your relationship to the head of family.';
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
                    <form method="post" id="registerForm" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control capitalize" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control capitalize" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control capitalize" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Suffix</label>
                                <input type="text" name="suffix" class="form-control capitalize" value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="dob" id="dob" class="form-control" required value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
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
                                <input type="text" name="contact" class="form-control" required value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>House Number/Street <span class="text-danger">*</span></label>
                            <input type="text" name="house_street" class="form-control" required value="<?php echo htmlspecialchars($_POST['house_street'] ?? ''); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Barangay</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($barangay); ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>City <span class="text-danger">*</span></label>
                                <select name="city" class="form-control" required>
                                    <option value="">Select City</option>
                                    <?php foreach ($allowed_cities as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php if(($_POST['city'] ?? '')===$c) echo 'selected'; ?>><?php echo $c; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Province <span class="text-danger">*</span></label>
                                <select name="province" class="form-control" required>
                                    <option value="">Select Province</option>
                                    <?php foreach ($allowed_provinces as $p): ?>
                                        <option value="<?php echo $p; ?>" <?php if(($_POST['province'] ?? '')===$p) echo 'selected'; ?>><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Occupation</label>
                                <input type="text" name="occupation" class="form-control" value="<?php echo htmlspecialchars($_POST['occupation'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>ID Numbers</label>
                                <input type="text" name="id_numbers" class="form-control" value="<?php echo htmlspecialchars($_POST['id_numbers'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Health Info</label>
                                <input type="text" name="health_info" class="form-control" value="<?php echo htmlspecialchars($_POST['health_info'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Socio-Economic Info</label>
                                <input type="text" name="socio_info" class="form-control" value="<?php echo htmlspecialchars($_POST['socio_info'] ?? ''); ?>">
                            </div>
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
<script>
// Auto-capitalize name fields
['first_name','middle_name','last_name','suffix'].forEach(function(id) {
    var el = document.querySelector('[name="'+id+'"]');
    if (el) {
        el.addEventListener('input', function() {
            this.value = this.value.replace(/\b\w/g, function(l){ return l.toUpperCase(); });
        });
    }
});
// Family status logic
const familyStatus = document.getElementById('familyStatus');
const roleDiv = document.getElementById('roleDiv');
const roleSelect = document.getElementById('roleSelect');
const roleRequired = document.getElementById('roleRequired');
function updateRoleVisibility() {
    if (familyStatus.value === 'non-head') {
        roleDiv.style.display = '';
        roleRequired.style.display = '';
        roleSelect.required = true;
    } else {
        roleDiv.style.display = 'none';
        roleRequired.style.display = 'none';
        roleSelect.required = false;
        roleSelect.value = '';
    }
}
familyStatus.addEventListener('change', updateRoleVisibility);
window.addEventListener('DOMContentLoaded', updateRoleVisibility);
// Auto-calculate age from DOB
document.getElementById('dob').addEventListener('change', function() {
    var dob = this.value;
    var ageInput = document.getElementById('age');
    if (dob) {
        var today = new Date();
        var birthDate = new Date(dob);
        var age = today.getFullYear() - birthDate.getFullYear();
        var m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        ageInput.value = age > 0 ? age : '';
    } else {
        ageInput.value = '';
    }
});
</script>
</body>
</html>
