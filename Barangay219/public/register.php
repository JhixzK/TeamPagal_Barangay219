<?php
/**
 * E-Barangay Resident Registration
 * Multi-step wizard-style registration form
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        :root {
            --gov-blue: #1d4ed8;
            --gov-blue-dark: #1e40af;
            --gov-white: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-soft: #dbe5f1;
            --surface-soft: #f8fbff;
            --shadow-soft: 0 14px 34px rgba(15, 23, 42, 0.08);
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        .register-page {
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .register-page::before {
            content: '';
            position: fixed;
            inset: 0;
            background: url('<?php echo ASSETS_URL; ?>img/barangay_logo2.png') no-repeat center 55%;
            background-size: min(500px, 74vw);
            opacity: 0.045;
            pointer-events: none;
            z-index: 0;
        }

        .register-shell {
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            background: #ffffff;
            position: relative;
            z-index: 1;
        }

        .register-header {
            background: #ffffff;
            border-bottom: 1px solid var(--border-soft);
            padding-top: 1.05rem !important;
            padding-bottom: 1.05rem !important;
        }

        .register-header h4 {
            color: var(--text-primary);
            font-weight: 800;
            margin-bottom: 0.15rem;
        }

        .register-header p {
            color: var(--text-secondary);
        }

        .register-header .brand-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            margin-bottom: 0.45rem;
        }

        .register-form-body {
            background: #ffffff;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 0.6rem;
            margin-bottom: 2rem;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
            min-width: 0;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #dbe5f1;
            z-index: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eef4ff;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
            border: 1px solid #dbe5f1;
            transition: all 0.3s ease;
        }
        .step.active .step-circle {
            background: var(--gov-blue);
            border-color: var(--gov-blue);
            color: white;
        }
        .step.completed .step-circle {
            background: #0ea5a0;
            border-color: #0ea5a0;
            color: white;
        }
        .step-label {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            text-align: center;
            color: #6c757d;
            line-height: 1.25;
            max-width: 180px;
        }
        .step.active .step-label {
            color: var(--gov-blue);
            font-weight: 500;
        }
        .step.completed .step-label {
            color: #0f766e;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
        }
        .section-card {
            border: 1px solid var(--border-soft);
            border-left: 4px solid var(--gov-blue);
            border-radius: 12px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        }
        .section-title { color: var(--gov-blue); font-weight: 600; }
        .note-box {
            background: var(--surface-soft);
            border: 1px solid var(--border-soft);
            padding: 0.75rem 1rem;
            border-radius: 0.6rem;
            font-size: 0.9rem;
            color: #334155;
        }
        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .step-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gov-blue);
        }
        .step-counter {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .review-field {
            margin-bottom: 1rem;
        }
        .review-label {
            font-weight: 600;
            color: #495057;
        }
        .review-value {
            color: #6c757d;
        }
        .btn-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            gap: 0.75rem;
        }

        .btn {
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-primary {
            background-color: var(--gov-blue);
            border-color: var(--gov-blue);
        }

        .btn-primary:hover {
            background-color: var(--gov-blue-dark);
            border-color: var(--gov-blue-dark);
        }

        .btn-outline-secondary {
            border-color: #cbd5e1;
            color: #334155;
        }

        .btn-outline-secondary:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e3a8a;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border-color: #cfd8e3;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 0.18rem rgba(29, 78, 216, 0.18);
        }

        .back-to-login {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        @media (max-width: 991.98px) {
            .step-indicator {
                flex-direction: row;
                flex-wrap: nowrap;
                justify-content: flex-start;
                align-items: flex-start;
                gap: 0.5rem;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.35rem;
            }

            .step-indicator::-webkit-scrollbar {
                height: 6px;
            }

            .step-indicator::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 999px;
            }

            .step {
                flex: 0 0 145px;
                min-width: 145px;
                max-width: 145px;
                flex-direction: column;
                align-items: center;
            }

            .step:not(:last-child)::after {
                top: 20px;
                left: 50%;
                width: 100%;
                height: 2px;
            }

            .step-label {
                margin-top: 0.45rem;
                margin-left: 0;
                text-align: center;
                max-width: 140px;
                font-size: 0.76rem;
                line-height: 1.2;
            }
        }

        @media (max-width: 575.98px) {
            .step {
                flex-basis: 130px;
                min-width: 130px;
                max-width: 130px;
            }

            .step-circle {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            .step:not(:last-child)::after {
                top: 18px;
            }
        }
    </style>
</head>
<body class="register-page">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card register-shell">
                <div class="card-header register-header text-center py-3">
                    <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="brand-logo">
                    <h4 class="mb-0">Barangay 219 – Official E-Services Portal</h4>
                    <p class="mb-0 small mt-1">Resident Registration</p>
                </div>
                <div class="card-body p-4 register-form-body">
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step active" data-step="1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Step 1: Personal Information</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Step 2: Family Background</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Step 3: Contact & Residency</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-circle">4</div>
                            <div class="step-label">Step 4: Review & Submit</div>
                        </div>
                    </div>

                    <div class="note-box mb-4">
                        <strong>No username or password needed.</strong>
                        Your application will be reviewed by the barangay. After approval, you will receive your Resident ID and instructions to activate your account.
                    </div>
                    <div id="alertContainer"></div>

                    <form id="registerForm" enctype="multipart/form-data" autocomplete="off">
                        <!-- Step 1: Personal Information -->
                        <div class="step-content active" data-step="1">
                            <div class="step-header">
                                <div class="step-title">Phase 1: Personal Information</div>
                                <div class="step-counter">Step 1 of 4</div>
                            </div>
                            <div class="card section-card mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>First Name <span class="text-danger">*</span></label>
                                            <input type="text" name="first_name" class="form-control" maxlength="50" autocomplete="given-name" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Middle Name</label>
                                            <input type="text" name="middle_name" class="form-control" maxlength="50" autocomplete="additional-name">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Last Name <span class="text-danger">*</span></label>
                                            <input type="text" name="last_name" class="form-control" maxlength="50" autocomplete="family-name" required>
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
                                        <div class="col-md-4 mb-3">
                                            <label>Place of Birth</label>
                                            <input type="text" name="place_of_birth" class="form-control" maxlength="100">
                                        </div>
                                        <div class="col-md-4 mb-3">
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
                                        <div class="col-md-4 mb-3">
                                            <label>Citizenship</label>
                                            <input type="text" name="citizenship" class="form-control" value="Filipino" maxlength="30">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Family Background -->
                        <div class="step-content" data-step="2">
                            <div class="step-header">
                                <div class="step-title">Phase 2: Family Background</div>
                                <div class="step-counter">Step 2 of 4</div>
                            </div>
                            <div class="card section-card mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Household Role <span class="text-danger">*</span></label>
                                            <select name="household_role" class="form-select" required>
                                                <option value="">Select</option>
                                                <option value="Head">Head</option>
                                                <option value="Member">Member</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Number of Household Members</label>
                                            <input type="number" name="household_members" class="form-control" min="1" max="20">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Father's Name</label>
                                            <input type="text" name="father_name" class="form-control" maxlength="100">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Mother's Name</label>
                                            <input type="text" name="mother_name" class="form-control" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Family Code / Head of Family ID</label>
                                            <input type="text" name="family_code" class="form-control" maxlength="30" placeholder="ID only, no names">
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
                        </div>

                        <!-- Step 3: Contact & Residency -->
                        <div class="step-content" data-step="3">
                            <div class="step-header">
                                <div class="step-title">Phase 3: Contact & Residency</div>
                                <div class="step-counter">Step 3 of 4</div>
                            </div>
                            <div class="card section-card mb-4">
                                <div class="card-body">
                                    <h6 class="text-secondary mb-3">Contact Information</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Mobile Number <span class="text-danger">*</span></label>
                                            <input type="tel" name="mobile_number" class="form-control" maxlength="11" inputmode="numeric" autocomplete="tel" required placeholder="09xxxxxxxxx">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Email Address</label>
                                            <input type="email" name="email" class="form-control" maxlength="100" autocomplete="email">
                                        </div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary mb-3">Residency Details</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>House No.</label>
                                            <input type="text" name="house_number" class="form-control" maxlength="30">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Street / Purok / Sitio</label>
                                            <input type="text" name="street" class="form-control" maxlength="100">
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
                                            <label>City / Municipality</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($city); ?>" readonly>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Length of Residency (years)</label>
                                            <input type="number" name="length_of_residency_years" class="form-control" min="0" max="100">
                                        </div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary mb-3">Emergency Contact</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Name <span class="text-danger">*</span></label>
                                            <input type="text" name="emergency_contact_name" class="form-control" maxlength="100" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Number <span class="text-danger">*</span></label>
                                            <input type="tel" name="emergency_contact_number" class="form-control" maxlength="11" inputmode="numeric" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Relationship <span class="text-danger">*</span></label>
                                            <input type="text" name="emergency_contact_relationship" class="form-control" maxlength="30" required placeholder="e.g. Spouse, Parent">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review & Submit -->
                        <div class="step-content" data-step="4">
                            <div class="step-header">
                                <div class="step-title">Phase 4: Review Summary</div>
                                <div class="step-counter">Step 4 of 4</div>
                            </div>
                            <div id="reviewContent">
                                <!-- Review content will be populated by JavaScript -->
                            </div>
                            <div class="card section-card mb-4">
                                <div class="card-body">
                                    <h6 class="text-secondary mb-3">Education & Employment</h6>
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
                                            <input type="text" name="occupation" class="form-control" maxlength="80">
                                        </div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary mb-3">Special Categories (Optional)</h6>
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
                                            <input type="text" name="pwd_id_number" class="form-control mt-1" placeholder="PWD ID No." maxlength="30" style="display:none" id="pwdIdField">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_solo_parent" id="is_solo" value="1">
                                                <label class="form-check-label" for="is_solo">Solo Parent</label>
                                            </div>
                                            <input type="text" name="solo_parent_id_number" class="form-control mt-1" placeholder="Solo Parent ID No." maxlength="30" style="display:none" id="soloIdField">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_ip_member" id="is_ip" value="1">
                                                <label class="form-check-label" for="is_ip">IP Member</label>
                                            </div>
                                            <input type="text" name="ip_group" class="form-control mt-1" placeholder="IP Group" maxlength="80" style="display:none" id="ipGroupField">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_4ps_beneficiary" id="is_4ps" value="1">
                                                <label class="form-check-label" for="is_4ps">4Ps Beneficiary</label>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary mb-3">Identification & Verification</h6>
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
                                            <input type="text" name="valid_id_number" class="form-control" maxlength="50" required>
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
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" id="confirm_info" required>
                                        <label class="form-check-label" for="confirm_info">
                                            I confirm that the information provided is true and correct.
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="btn-navigation">
                            <a href="<?php echo BASE_URL; ?>home.php" class="btn btn-outline-secondary">Back to Home</a>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary" id="prevBtn" style="display: none;">Back</button>
                                <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
                                <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">Submit Application</button>
                            </div>
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

let currentStep = 1;
const totalSteps = 4;

// Professional name field validation - Letters, spaces, hyphens, apostrophes, periods only
const nameFields = ['first_name', 'middle_name', 'last_name', 'place_of_birth', 'citizenship', 'emergency_contact_name', 'emergency_contact_relationship', 'occupation', 'ip_group', 'street', 'purok_sitio', 'father_name', 'mother_name'];
nameFields.forEach(fieldName => {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        field.addEventListener('input', function() {
            // Only allow letters, spaces, hyphens, apostrophes, periods
            this.value = this.value.replace(/[^a-zA-Z\s\-'.,]/g, '').trim();
            // Prevent multiple consecutive spaces
            this.value = this.value.replace(/\s+/g, ' ');
        });
        field.addEventListener('blur', function() {
            // Trim whitespace on blur
            this.value = this.value.trim();
        });
    }
});

// Professional number field validation - Digits only for phone numbers
const phoneFields = ['mobile_number', 'emergency_contact_number'];
phoneFields.forEach(fieldName => {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        field.addEventListener('input', function() {
            // Remove all non-digits
            let digits = this.value.replace(/[^0-9]/g, '');
            // Limit to 11 digits (Philippine standard)
            this.value = digits.slice(0, 11);
        });
    }
});

// Professional ID field validation - Letters, numbers, hyphens, slashes, spaces, periods
const idFields = ['valid_id_number', 'pwd_id_number', 'solo_parent_id_number', 'family_code'];
idFields.forEach(fieldName => {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        field.addEventListener('input', function() {
            // Allow: letters, numbers, hyphens, slashes, spaces, periods
            this.value = this.value.replace(/[^a-zA-Z0-9\-\/\s.]/g, '').trim();
            // Prevent multiple consecutive spaces
            this.value = this.value.replace(/\s+/g, ' ');
        });
    }
});

// House number - alphanumeric
const houseField = document.querySelector(`input[name="house_number"]`);
if (houseField) {
    houseField.addEventListener('input', function() {
        // Allow letters, numbers, hyphens, slashes, spaces
        this.value = this.value.replace(/[^a-zA-Z0-9\-\/\s]/g, '').trim();
        this.value = this.value.replace(/\s+/g, ' ');
    });
}

// Birth date - calculate age automatically
function calculateAge(dateString) {
    if (!dateString) {
        document.getElementById('ageDisplay').value = '';
        return;
    }

    const birthDate = new Date(dateString);
    const today = new Date();

    // Validate the date
    if (isNaN(birthDate.getTime())) {
        document.getElementById('ageDisplay').value = '';
        return;
    }

    // Calculate age
    let age = today.getFullYear() - birthDate.getFullYear();
    const month = today.getMonth() - birthDate.getMonth();
    const day = today.getDate() - birthDate.getDate();

    // Adjust age if birthday hasn't occurred this year
    if (month < 0 || (month === 0 && day < 0)) {
        age--;
    }

    // Display the age
    if (age >= 0) {
        document.getElementById('ageDisplay').value = age;
    } else {
        document.getElementById('ageDisplay').value = '';
    }
}

// Attach event listeners for birth date calculation
const birthDateField = document.getElementById('birth_date');
if (birthDateField) {
    birthDateField.addEventListener('change', function() {
        calculateAge(this.value);
    });
    birthDateField.addEventListener('input', function() {
        calculateAge(this.value);
    });
}

// Toggle additional fields for special categories
['is_pwd','is_solo','is_ip'].forEach(id => {
    document.getElementById(id).addEventListener('change', function() {
        const f = document.getElementById(id === 'is_pwd' ? 'pwdIdField' : id === 'is_solo' ? 'soloIdField' : 'ipGroupField');
        f.style.display = this.checked ? 'block' : 'none';
    });
});

// Step navigation
function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(content => {
        content.classList.remove('active');
    });
    // Show current step
    document.querySelector(`.step-content[data-step="${step}"]`).classList.add('active');

    // Update step indicator
    document.querySelectorAll('.step').forEach((stepEl, index) => {
        const stepNum = index + 1;
        stepEl.classList.remove('active', 'completed');
        if (stepNum < step) {
            stepEl.classList.add('completed');
        } else if (stepNum === step) {
            stepEl.classList.add('active');
        }
    });

    // Update navigation buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (step === 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'inline-block';
        submitBtn.style.display = 'none';
    } else if (step === totalSteps) {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-block';
        populateReview();
    } else {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'inline-block';
        submitBtn.style.display = 'none';
    }

    // Update step counter
    document.querySelectorAll('.step-counter').forEach(counter => {
        counter.textContent = `Step ${step} of ${totalSteps}`;
    });

    currentStep = step;
}

function validateStep(step) {
    const stepContent = document.querySelector(`.step-content[data-step="${step}"]`);
    const requiredFields = stepContent.querySelectorAll('input[required], select[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    // Additional validations
    if (step === 1) {
        // Check if birth date is not in future
        const birthDate = new Date(document.getElementById('birth_date').value);
        const today = new Date();
        if (birthDate > today) {
            document.getElementById('birth_date').classList.add('is-invalid');
            isValid = false;
        }
    }

    if (step === 3) {
        // Validate mobile number format
        const mobile = document.querySelector('input[name="mobile_number"]');
        if (mobile.value && !/^09\d{9}$/.test(mobile.value)) {
            mobile.classList.add('is-invalid');
            isValid = false;
        }
        // Validate email if provided
        const email = document.querySelector('input[name="email"]');
        if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            email.classList.add('is-invalid');
            isValid = false;
        }
    }

    return isValid;
}

function populateReview() {
    const reviewContent = document.getElementById('reviewContent');
    reviewContent.innerHTML = '';

    const formData = new FormData(document.getElementById('registerForm'));
    const reviewSections = [
        { title: 'Personal Information', fields: ['first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'age', 'place_of_birth', 'civil_status', 'citizenship'] },
        { title: 'Family Background', fields: ['household_role', 'father_name', 'mother_name', 'household_members', 'family_code', 'relationship_to_head'] },
        { title: 'Contact & Residency', fields: ['mobile_number', 'email', 'house_number', 'street', 'purok_sitio', 'barangay', 'city', 'province', 'length_of_residency_years', 'emergency_contact_name', 'emergency_contact_number', 'emergency_contact_relationship'] }
    ];

    reviewSections.forEach(section => {
        let sectionHtml = `<div class="card section-card mb-4"><div class="card-body"><h6 class="text-secondary mb-3">${section.title}</h6>`;
        section.fields.forEach(fieldName => {
            const value = formData.get(fieldName) || '';
            const label = document.querySelector(`label[for="${fieldName}"]`) || document.querySelector(`input[name="${fieldName}"]`)?.previousElementSibling?.textContent || fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            if (value) {
                sectionHtml += `<div class="review-field"><span class="review-label">${label.replace('*', '').trim()}:</span> <span class="review-value">${value}</span></div>`;
            }
        });
        sectionHtml += '</div></div>';
        reviewContent.innerHTML += sectionHtml;
    });
}

// Event listeners
document.getElementById('nextBtn').addEventListener('click', function() {
    if (validateStep(currentStep)) {
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    } else {
        alert('Please fill in all required fields correctly.');
    }
});

document.getElementById('prevBtn').addEventListener('click', function() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
});

// Form submission
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!validateStep(4)) {
        alert('Please complete all required fields and confirm the information.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    const alc = document.getElementById('alertContainer');
    alc.innerHTML = '';
    const fd = new FormData(this);
    try {
        const r = await fetch(API_URL + 'register.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.success) {
            alc.innerHTML = '<div class="alert alert-success"> ' + data.message + '<br><strong>Reference:</strong> ' + (data.data?.application_ref || '') + '</div>';
            this.reset();
            document.getElementById('ageDisplay').value = '';
            showStep(1);
        } else {
            alc.innerHTML = '<div class="alert alert-danger"> ' + data.message + '</div>';
        }
    } catch (err) {
        alc.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
    }
    btn.disabled = false;
});

// Initialize
showStep(1);
</script>
</body>
</html>
