<?php
/**
 * E-Barangay Resident Registration
 * No username or password required. Creates PENDING application.
 * Resident ID and account activation after barangay approval.
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';

$barangay = 'Barangay 219, Tondo';
$city = 'Manila';
$province = 'Metro Manila';

$valid_id_types = [
    'psa_birth' => 'PSA Birth Certificate',
    'passport' => 'Passport',
    'drivers_license' => "Driver's License",
    'umid' => 'UMID',
    'postal_id' => 'Postal ID',
    'sss_id' => 'SSS ID',
    'prc_id' => 'PRC ID',
    'voters_id' => "Voter's ID",
    'national_id' => 'Philippine National ID',
    'other' => 'Other Valid ID'
];
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
        .section-card { border-left: 4px solid #0d6efd; }
        .section-title { color: #0d6efd; font-weight: 600; }
        .note-box { background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.9rem; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="mb-0"><i class="bi bi-person-plus"></i> Resident Registration</h4>
                    <p class="mb-0 small mt-1"><?php echo $barangay; ?> • <?php echo $city; ?>, <?php echo $province; ?></p>
                </div>
                <div class="card-body p-4">
                    <div class="note-box mb-4">
                        <i class="bi bi-info-circle"></i> <strong>No username or password needed.</strong> 
                        Your application will be reviewed by the barangay. After approval, you will receive your Resident ID and instructions to activate your account.
                    </div>
                    <div id="alertContainer"></div>

                    <form id="registerForm" enctype="multipart/form-data" autocomplete="off">
                        <!-- 1. Personal Information -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-person"></i> Personal Information</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>First Name <span class="text-danger">*</span></label>
                                        <input type="text" name="first_name" class="form-control" maxlength="100" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Middle Name</label>
                                        <input type="text" name="middle_name" class="form-control" maxlength="100">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Last Name <span class="text-danger">*</span></label>
                                        <input type="text" name="last_name" class="form-control" maxlength="100" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label>Suffix</label>
                                        <select name="suffix" class="form-select">
                                            <option value="">None</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                            <option value="V">V</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Sex <span class="text-danger">*</span></label>
                                        <select name="sex" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Date of Birth <span class="text-danger">*</span></label>
                                        <input type="date" name="birth_date" id="birth_date" class="form-control" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Age</label>
                                        <input type="text" id="ageDisplay" class="form-control" readonly placeholder="Auto">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Place of Birth</label>
                                        <input type="text" name="place_of_birth" class="form-control" maxlength="150">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Civil Status <span class="text-danger">*</span></label>
                                        <select name="civil_status" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="single">Single</option>
                                            <option value="married">Married</option>
                                            <option value="widowed">Widowed</option>
                                            <option value="divorced">Divorced</option>
                                            <option value="separated">Separated</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Citizenship</label>
                                        <input type="text" name="citizenship" class="form-control" value="Filipino" maxlength="50">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Family & Household -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-house-heart"></i> Family & Household Information</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Family Code / Head of Family ID</label>
                                        <input type="text" name="family_code" class="form-control" maxlength="50" placeholder="ID only, no names">
                                        <small class="text-muted">If applicable</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Relationship to Head of Family</label>
                                        <select name="relationship_to_head" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Head">Head</option>
                                            <option value="Spouse">Spouse</option>
                                            <option value="Child">Child</option>
                                            <option value="Parent">Parent</option>
                                            <option value="Sibling">Sibling</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Address & Residency -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-geo-alt"></i> Address & Residency</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>House Number</label>
                                        <input type="text" name="house_number" class="form-control" maxlength="50">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Street</label>
                                        <input type="text" name="street" class="form-control" maxlength="150">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Purok/Sitio</label>
                                        <input type="text" name="purok_sitio" class="form-control" maxlength="100">
                                    </div>
                                </div>
                                <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($barangay); ?>">
                                <input type="hidden" name="city" value="<?php echo htmlspecialchars($city); ?>">
                                <input type="hidden" name="province" value="<?php echo htmlspecialchars($province); ?>">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>Barangay</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($barangay); ?>" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>City</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($city); ?>" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Province</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($province); ?>" readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>Length of Residency (years)</label>
                                        <input type="number" name="length_of_residency_years" class="form-control" min="0" max="100">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Contact & Emergency -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-telephone"></i> Contact & Emergency Information</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Mobile Number <span class="text-danger">*</span></label>
                                        <input type="tel" name="mobile_number" class="form-control" maxlength="11" pattern="\d{10,11}" required placeholder="09xxxxxxxxx">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Email (optional)</label>
                                        <input type="email" name="email" class="form-control" maxlength="100">
                                    </div>
                                </div>
                                <hr>
                                <h6 class="text-secondary">Emergency Contact</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>Name <span class="text-danger">*</span></label>
                                        <input type="text" name="emergency_contact_name" class="form-control" maxlength="150" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Number <span class="text-danger">*</span></label>
                                        <input type="tel" name="emergency_contact_number" class="form-control" maxlength="20" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Relationship <span class="text-danger">*</span></label>
                                        <input type="text" name="emergency_contact_relationship" class="form-control" maxlength="50" required placeholder="e.g. Spouse, Parent">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Education & Employment -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-briefcase"></i> Education & Employment</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label>Educational Attainment</label>
                                        <select name="educational_attainment" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Elementary">Elementary</option>
                                            <option value="High School">High School</option>
                                            <option value="Vocational">Vocational</option>
                                            <option value="College">College</option>
                                            <option value="Postgraduate">Postgraduate</option>
                                            <option value="None">None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Employment Status</label>
                                        <select name="employment_status" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Employed">Employed</option>
                                            <option value="Self-employed">Self-employed</option>
                                            <option value="Unemployed">Unemployed</option>
                                            <option value="Student">Student</option>
                                            <option value="Retired">Retired</option>
                                            <option value="OFW">OFW</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Occupation</label>
                                        <input type="text" name="occupation" class="form-control" maxlength="100">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Special Categories -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-heart"></i> Special Categories (Optional)</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_senior_citizen" id="is_senior" value="1">
                                            <label class="form-check-label" for="is_senior">Senior Citizen (60+)</label>
                                            <small class="d-block text-muted">Auto-validated by birth date</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_pwd" id="is_pwd" value="1">
                                            <label class="form-check-label" for="is_pwd">PWD</label>
                                        </div>
                                        <input type="text" name="pwd_id_number" class="form-control mt-1" placeholder="PWD ID No." maxlength="50" style="display:none" id="pwdIdField">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_solo_parent" id="is_solo" value="1">
                                            <label class="form-check-label" for="is_solo">Solo Parent</label>
                                        </div>
                                        <input type="text" name="solo_parent_id_number" class="form-control mt-1" placeholder="Solo Parent ID No." maxlength="50" style="display:none" id="soloIdField">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_ip_member" id="is_ip" value="1">
                                            <label class="form-check-label" for="is_ip">IP Member</label>
                                        </div>
                                        <input type="text" name="ip_group" class="form-control mt-1" placeholder="IP Group" maxlength="100" style="display:none" id="ipGroupField">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_4ps_beneficiary" id="is_4ps" value="1">
                                            <label class="form-check-label" for="is_4ps">4Ps Beneficiary</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7. Identification & Verification -->
                        <div class="card section-card mb-4">
                            <div class="card-body">
                                <h5 class="section-title mb-3"><i class="bi bi-card-checklist"></i> Identification & Verification</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Valid ID Type <span class="text-danger">*</span></label>
                                        <select name="valid_id_type" class="form-select" required>
                                            <option value="">Select</option>
                                            <?php foreach ($valid_id_types as $k => $v): ?>
                                            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Valid ID Number <span class="text-danger">*</span></label>
                                        <input type="text" name="valid_id_number" class="form-control" maxlength="100" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Upload Valid ID <span class="text-danger">*</span></label>
                                        <input type="file" name="id_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="text-muted">PDF, JPG, PNG. Max 5MB.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Upload Proof of Residency <span class="text-danger">*</span></label>
                                        <input type="file" name="proof_of_residency" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="text-muted">PDF, JPG, PNG. Max 5MB.</small>
                                    </div>
                                </div>
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="data_privacy_consent" id="data_privacy" value="1" required>
                                    <label class="form-check-label" for="data_privacy">
                                        I consent to the collection and processing of my personal data in accordance with the <strong>Data Privacy Act of 2012</strong> and the privacy policy of <?php echo htmlspecialchars($barangay); ?>.
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="bi bi-send"></i> Submit Application
                            </button>
                            <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-secondary btn-lg">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
<script>
window.API_URL = '<?php echo addslashes(API_URL); ?>';

document.getElementById('birth_date').addEventListener('change', function() {
    const dob = this.value;
    if (dob) {
        const age = Math.floor((new Date() - new Date(dob)) / (365.25 * 24 * 60 * 60 * 1000));
        document.getElementById('ageDisplay').value = age;
    }
});

['is_pwd','is_solo','is_ip'].forEach(id => {
    document.getElementById(id).addEventListener('change', function() {
        const f = document.getElementById(id === 'is_pwd' ? 'pwdIdField' : id === 'is_solo' ? 'soloIdField' : 'ipGroupField');
        f.style.display = this.checked ? 'block' : 'none';
    });
});

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    const alc = document.getElementById('alertContainer');
    alc.innerHTML = '';
    const fd = new FormData(this);
    try {
        const r = await fetch(API_URL + 'register.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.success) {
            alc.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' + data.message + '<br><strong>Reference:</strong> ' + (data.data?.application_ref || '') + '</div>';
            this.reset();
            document.getElementById('ageDisplay').value = '';
        } else {
            alc.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + data.message + '</div>';
        }
    } catch (err) {
        alc.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
    }
    btn.disabled = false;
});
</script>
</body>
</html>
