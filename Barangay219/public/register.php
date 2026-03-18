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

$barangay219_street_options = [
    'Road 10',
    'Road 10 Extension',
    'Road 11',
    'Road 11 Extension',
    'Road 12',
    'Road 12 Extension',
    'Road 13',
    'Road 13 Extension',
    'Road 14',
    'Road 15',
    'R-10 (Radial Road 10)',
    'Vitas Road'
];

$barangay219_purok_options = [
    'Purok 1',
    'Purok 2',
    'Purok 3',
    'Purok 4',
    'Purok 5',
    'Sitio 1',
    'Sitio 2',
    'Sitio 3'
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
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
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
            background: #ffffff;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .register-page::before {
            content: '';
            position: fixed;
            inset: 0;
            background: url('<?php echo ASSETS_URL; ?>img/crop219logo.png') no-repeat 95% center;
            background-size: calc(900px * var(--bg-zoom-inverse, 1));
            opacity: 0.90;
            filter: blur(6px);
            transform: scale(1.03);
            pointer-events: none;
            z-index: 0;
        }

        .register-shell {
            border: 1px solid rgba(236, 240, 226, 0.9);
            border-radius: 16px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.80);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            position: relative;
            z-index: 1;
        }

        .register-header {
            background: rgba(255, 255, 255, 0.72);
            border-bottom: 1px solid var(--border-soft);
            padding-top: 1.05rem !important;
            padding-bottom: 1.05rem !important;
        }

        .register-header h4 {
            color: var(--text-primary);
            font-weight: 800;
            margin-bottom: 0.15rem;
        }

        .register-header .portal-title {
            font-size: clamp(1.45rem, 2.8vw, 2rem);
            font-weight: 800;
            letter-spacing: 0.01em;
            line-height: 1.25;
            margin-bottom: 0.2rem;
            display: inline-block;
            padding-bottom: 0.08em;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .register-header p {
            color: var(--text-secondary);
        }

        .register-header .portal-subtitle {
            color: #475569;
            font-weight: 600;
            letter-spacing: 0.01em;
            font-size: 0.98rem;
        }

        .register-header .portal-co-title {
            color: #334155;
            font-weight: 700;
            letter-spacing: 0.01em;
            font-size: 1.05rem;
        }

        .register-header .brand-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            margin-bottom: 0.45rem;
        }

        .register-form-body {
            background: rgba(255, 255, 255, 0.68);
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

        .flatpickr-calendar {
            border: 1px solid var(--border-soft);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
            border-radius: 12px;
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange:hover,
        .flatpickr-day.endRange:hover {
            background: var(--gov-blue);
            border-color: var(--gov-blue);
        }

        .flatpickr-months .flatpickr-month,
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year {
            font-weight: 600;
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
                    <h4 class="portal-title mb-0">Barangay 219 e-Portal</h4>
                    <p class="portal-subtitle mb-0" style="margin-top: -15px;">Tondo, Manila</p>
                    <p class="portal-co-title mb-0" style="margin-top: 15px;">Resident Registration</p>
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
                        <strong>NOTE:</strong> Your application will be reviewed by the barangay officials. After approval, you will receive your Resident ID and instructions to activate your account.
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
                                            <input type="text" name="birth_date" id="birth_date" class="form-control" required autocomplete="off" placeholder="Select your birth date">
                                            <div class="invalid-feedback">You must be 18 years old and above to register.</div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label>Age</label>
                                            <input type="text" id="ageDisplay" class="form-control" readonly placeholder="Computed">
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
                                    <hr>
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
                                                <option value="Member of Household">Member of Household</option>
                                                <option value="Head of Household">Head of Household</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row" id="householdMembersRow" style="display:none;">
                                        <div class="col-md-6 mb-3">
                                            <label>Number of Household Members</label>
                                            <input type="number" id="household_members" name="household_members" class="form-control" min="1" max="99" step="1" inputmode="numeric">
                                            <small class="text-muted">Total household members, including you.</small>
                                        </div>
                                    </div>
                                    <div class="row g-3" id="householdTypeRow" style="display:none;">
                                        <div class="col-md-6 mb-3">
                                            <label>Household Type <span class="text-danger">*</span></label>
                                            <select name="household_type" id="household_type" class="form-select">
                                                <option value="">Select Household Type</option>
                                                <option value="Family Household">Family Household</option>
                                                <option value="Couple Only">Couple Only</option>
                                                <option value="Single Inhabitant">Single Inhabitant</option>
                                                <option value="Non-Relative Household (Shared / Boarders)">Non-Relative Household (Shared / Boarders)</option>
                                                <option value="Other (Specify)">Other (Specify)</option>
                                            </select>
                                            <small class="text-muted">Select your household setup.</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>House Type <span class="text-danger">*</span></label>
                                            <select name="house_type" id="house_type" class="form-select">
                                                <option value="">Select House Type</option>
                                                <option value="Concrete">Concrete</option>
                                                <option value="Semi-Concrete">Semi-Concrete</option>
                                                <option value="Light Materials">Light Materials</option>
                                                <option value="Apartment / Boarding House">Apartment / Boarding House</option>
                                                <option value="Townhouse / Row House">Townhouse / Row House</option>
                                                <option value="Informal / Improvised">Informal / Improvised</option>
                                            </select>
                                            <small class="text-muted">Select your dwelling type.</small>
                                        </div>
                                    </div>
                                    <div class="row g-3" id="householdIncomeRow" style="display:none;">
                                        <div class="col-md-4 mb-3">
                                            <label>Total Household Income (Monthly) <span class="text-danger">*</span></label>
                                            <input type="text" id="household_income" name="household_income" class="form-control" inputmode="decimal" placeholder="e.g., 25,000">
                                            <small class="text-muted">Total monthly income in PHP. Commas are added automatically.</small>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Income per Family Member</label>
                                            <input type="text" id="income_per_member" name="income_per_member" class="form-control" readonly placeholder="Computed value">
                                            <small class="text-muted">Auto-computed.</small>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="d-block">Economic Classification</label>
                                            <input type="text" id="economic_classification" name="economic_classification" class="form-control mt-2" readonly placeholder="Classification">
                                            <small class="text-muted d-block mt-1">Below PHP 12,000/member = Indigent.</small>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Family Code / Head of Family ID <span class="text-danger" id="familyCodeRequiredMark" style="display:none;">*</span></label>
                                            <input type="text" id="family_code" name="family_code" class="form-control" maxlength="30" placeholder="Enter Head Family Code (e.g., BR219-2026-0001)">
                                            <small class="text-muted" id="familyCodeHelpText">Head: auto-generated. Member: enter exact code (BR219-YYYY-####).</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Relationship to Head of Family <span class="text-danger" id="relationshipRequiredMark" style="display:none;">*</span></label>
                                            <select id="relationship_to_head" name="relationship_to_head" class="form-select">
                                                <option value="">Select</option>
                                                <option value="Head">Head</option>
                                                <option value="Spouse">Spouse</option>
                                                <option value="Child">Child</option>
                                                <option value="Grandchild">Grandchild</option>
                                                <option value="Parent">Parent</option>
                                                <option value="Grandparent">Grandparent</option>
                                                <option value="Sibling">Sibling</option>
                                                <option value="Relative">Relative</option>
                                                <option value="Boarder">Boarder</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <small class="text-muted" id="relationshipHelpText">For members only. Do not choose Head.</small>
                                        </div>
                                    </div>
                                    <div class="row" id="parentGuardianRow" style="display:none;">
                                        <div class="col-md-3 mb-3">
                                            <label>Parent / Guardian First Name <span class="text-danger">*</span></label>
                                            <input type="text" id="parent_guardian_first_name" name="parent_guardian_first_name" class="form-control" maxlength="100" placeholder="e.g., Juan">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label>Parent / Guardian Middle Name</label>
                                            <input type="text" id="parent_guardian_middle_name" name="parent_guardian_middle_name" class="form-control" maxlength="100" placeholder="e.g., Santos (optional)">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label>Parent / Guardian Last Name <span class="text-danger">*</span></label>
                                            <input type="text" id="parent_guardian_last_name" name="parent_guardian_last_name" class="form-control" maxlength="100" placeholder="e.g., Dela Cruz">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label>Parent / Guardian Suffix</label>
                                            <input type="text" id="parent_guardian_suffix" name="parent_guardian_suffix" class="form-control" maxlength="20" placeholder="e.g., Jr., Sr., III (optional)">
                                            <small class="text-muted">Required for Child or Grandchild relationship.</small>
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
                                            <input type="tel" name="mobile_number" class="form-control" maxlength="14" inputmode="numeric" autocomplete="tel" required placeholder="+63 9XXXXXXXXX" value="+63 ">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Email Address</label>
                                            <input type="email" name="email" id="email" class="form-control" maxlength="100" autocomplete="email" placeholder="@gmail.com">
                                            <div class="invalid-feedback">Please enter a valid Gmail address (@gmail.com).</div>
                                        </div>
                                    </div>
                                    <hr>
                                    <h6 class="text-secondary mb-3">Residency Details</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>House No. <span class="text-danger">*</span></label>
                                            <input type="text" name="house_number" class="form-control" maxlength="30" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Street <span class="text-danger">*</span></label>
                                            <select name="street" class="form-select" required>
                                                <option value="">Select Street</option>
                                                <?php foreach ($barangay219_street_options as $street_option): ?>
                                                <option value="<?php echo htmlspecialchars($street_option); ?>"><?php echo htmlspecialchars($street_option); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Purok / Sitio <span class="text-danger">*</span></label>
                                            <select name="purok_sitio" class="form-select" required>
                                                <option value="">Select Purok / Sitio</option>
                                                <?php foreach ($barangay219_purok_options as $purok_option): ?>
                                                <option value="<?php echo htmlspecialchars($purok_option); ?>"><?php echo htmlspecialchars($purok_option); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($barangay); ?>">
                                    <input type="hidden" name="city" value="<?php echo htmlspecialchars($city); ?>">
                                    <input type="hidden" name="province" value="<?php echo htmlspecialchars($province); ?>">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label>Barangay</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($barangay); ?>" readonly>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label>City / Municipality</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($city); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Date of Residency Start <span class="text-danger">*</span></label>
                                            <input type="text" name="residency_start_date" id="residency_start_date" class="form-control" placeholder="Select date" required>
                                            <small class="text-muted">Minimum residency requirement is 6 months.</small>
                                            <div class="invalid-feedback">Valid residency start date is required (minimum 6 months ago).</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Computed Length of Residency</label>
                                            <input type="text" id="computed_residency_display" class="form-control" readonly placeholder="Computed value">
                                            <small class="text-muted">Years and months calculated from residency start date.</small>
                                        </div>
                                    </div>
                                    <input type="hidden" name="length_of_residency_years" id="length_of_residency_years" value="">
                                    <input type="hidden" name="length_of_residency" id="length_of_residency" value="">
                                    <hr>
                                    <h6 class="text-secondary mb-3">Emergency Contact</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Name <span class="text-danger">*</span></label>
                                            <input type="text" name="emergency_contact_name" class="form-control" maxlength="100" required placeholder="Enter full name">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Number <span class="text-danger">*</span></label>
                                            <input type="tel" name="emergency_contact_number" class="form-control" maxlength="14" inputmode="numeric" required placeholder="+63 9XXXXXXXXX" value="+63 ">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Relationship <span class="text-danger">*</span></label>
                                            <select name="emergency_contact_relationship" class="form-select" required>
                                                <option value="">Select</option>
                                                <option value="Parent">Parent</option>
                                                <option value="Father">Father</option>
                                                <option value="Mother">Mother</option>
                                                <option value="Spouse">Spouse</option>
                                                <option value="Partner">Partner</option>
                                                <option value="Son">Son</option>
                                                <option value="Daughter">Daughter</option>
                                                <option value="Brother">Brother</option>
                                                <option value="Sister">Sister</option>
                                                <option value="Grandparent">Grandparent</option>
                                                <option value="Uncle / Aunt">Uncle / Aunt</option>
                                                <option value="Cousin">Cousin</option>
                                                <option value="Relative (Other)">Relative (Other)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review & Submit -->
                        <div class="step-content" data-step="4">
                            <div class="step-header">
                                <div class="step-title">Phase 4: Review &amp; Submit</div>
                                <div class="step-counter">Step 4 of 4</div>
                            </div>
                            <div class="note-box mb-3" style="border-left:4px solid #1d4ed8;">
                                <i class="bi bi-pencil-square me-1 text-primary"></i>
                                <strong>Review your information below.</strong> You may edit any field to correct mistakes before submitting.
                            </div>
                            <div id="reviewContent">
                                <!-- Review content will be populated by JavaScript -->
                            </div>
                            <div class="card section-card mb-4">
                                <div class="card-body">
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
                            <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-secondary">Cancel</a>
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
window.addEventListener('DOMContentLoaded', function () {
    var baseOuterInnerRatio = window.outerWidth && window.innerWidth ? window.outerWidth / window.innerWidth : 1;
    if (!isFinite(baseOuterInnerRatio) || baseOuterInnerRatio <= 0) {
        baseOuterInnerRatio = 1;
    }

    function syncBackgroundZoom() {
        var viewportScale = window.visualViewport && window.visualViewport.scale ? window.visualViewport.scale : 1;
        if (!isFinite(viewportScale) || viewportScale <= 0) {
            viewportScale = 1;
        }

        var desktopScale = 1;
        if (window.outerWidth && window.innerWidth) {
            desktopScale = (window.outerWidth / window.innerWidth) / baseOuterInnerRatio;
        }
        if (!isFinite(desktopScale) || desktopScale <= 0) {
            desktopScale = 1;
        }

        var zoomScale = Math.max(viewportScale, desktopScale);
        document.documentElement.style.setProperty('--bg-zoom-inverse', (1 / zoomScale).toFixed(4));
    }

    syncBackgroundZoom();
    window.addEventListener('resize', syncBackgroundZoom, { passive: true });
    window.addEventListener('orientationchange', syncBackgroundZoom, { passive: true });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncBackgroundZoom, { passive: true });
        window.visualViewport.addEventListener('scroll', syncBackgroundZoom, { passive: true });
    }
});

window.API_URL = '<?php echo addslashes(API_URL); ?>';

let currentStep = 1;
const totalSteps = 4;
let isRegisterSubmitting = false;

// Phase 1 name validation - letters, hyphens, apostrophes, periods only (no spaces)
const phase1NameFields = ['first_name', 'middle_name', 'last_name'];
phase1NameFields.forEach(fieldName => {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        field.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\-'.,]/g, '');
        });
        field.addEventListener('blur', function() {
            this.value = this.value.trim();
        });
    }
});

// Other text name-like fields - letters, spaces, hyphens, apostrophes, periods only
const nameFields = ['place_of_birth', 'citizenship', 'emergency_contact_name', 'emergency_contact_relationship', 'occupation', 'ip_group', 'street', 'purok_sitio', 'parent_guardian_first_name', 'parent_guardian_middle_name', 'parent_guardian_last_name', 'parent_guardian_suffix'];
nameFields.forEach(fieldName => {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        field.addEventListener('input', function() {
            // Only allow letters, spaces, hyphens, apostrophes, periods
            this.value = this.value.replace(/[^a-zA-Z\s\-'.,]/g, '');
            // Prevent multiple consecutive spaces
            this.value = this.value.replace(/\s+/g, ' ');
        });
        field.addEventListener('blur', function() {
            // Trim whitespace on blur
            this.value = this.value.trim();
        });
    }
});

// Phone field formatting - enforce +63 prefix with space
function normalizePhoneDigits(raw) {
    if (!raw) return '';
    let digits = String(raw).replace(/\D/g, '');
    if (digits.startsWith('63')) digits = digits.slice(2);
    if (digits.startsWith('0')) digits = digits.slice(1);
    return digits.slice(0, 10);
}

function formatPhoneInput(raw) {
    const digits = normalizePhoneDigits(raw);
    return '+63 ' + digits;
}

const phoneFields = ['mobile_number', 'emergency_contact_number'];
phoneFields.forEach(fieldName => {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        if (!field.value || field.value.trim() === '+63') {
            field.value = '+63 ';
        }
        field.addEventListener('input', function() {
            this.value = formatPhoneInput(this.value);
        });
        field.addEventListener('blur', function() {
            const digits = normalizePhoneDigits(this.value);
            this.value = digits ? ('+63 ' + digits) : '+63 ';
        });
    }
});

// Title-case all text inputs/areas except email
function toTitleCase(text) {
    if (!text) return '';
    return String(text)
        .trim()
        .split(/\s+/)
        .map(word => {
            if (!word) return '';
            const clean = word.replace(/[^a-zA-Z]/g, '');
            if (clean.length > 0 && clean === clean.toUpperCase() && clean.length <= 3) {
                return word;
            }
            const first = word.charAt(0).toUpperCase();
            const rest = word.slice(1).toLowerCase();
            return first + rest;
        })
        .join(' ');
}

function applyTitleCaseToRegisterForm() {
    const form = document.getElementById('registerForm');
    if (!form) return;
    const fields = form.querySelectorAll('input[type="text"], textarea');
    fields.forEach(field => {
        if (field.name === 'email') return;
        field.value = toTitleCase(field.value);
    });
}

function initRegisterTitleCase() {
    const form = document.getElementById('registerForm');
    if (!form) return;
    const fields = form.querySelectorAll('input[type="text"], textarea');
    fields.forEach(field => {
        if (field.name === 'email') return;
        field.addEventListener('blur', function() {
            this.value = toTitleCase(this.value);
        });
    });
}

// Household members - digits only, max 2 digits
const householdMembersField = document.querySelector('input[name="household_members"]');
if (householdMembersField) {
    householdMembersField.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2);
        if (this.value !== '' && Number(this.value) < 1) {
            this.value = '1';
        }
    });
}

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
    if (typeof flatpickr !== 'undefined') {
        flatpickr(birthDateField, {
            altInput: true,
            altFormat: 'F j, Y',
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            allowInput: false,
            monthSelectorType: 'dropdown',
            animate: true,
            disableMobile: false,
            onChange: function(selectedDates, dateStr) {
                calculateAge(dateStr);
            }
        });
    } else {
        birthDateField.addEventListener('change', function() {
            calculateAge(this.value);
        });
        birthDateField.addEventListener('input', function() {
            calculateAge(this.value);
        });
    }
}

// Residency date picker - Phase 3 with computed values
function initializeResidencyDatePicker() {
    const startDateField = document.getElementById('residency_start_date');
    if (!startDateField) return;

    // Initialize flatpickr if available, otherwise use HTML5 date input
    if (typeof flatpickr !== 'undefined') {
        flatpickr(startDateField, {
            altFormat: 'F j, Y',
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            allowInput: false,
            monthSelectorType: 'dropdown',
            animate: true,
            disableMobile: false,
            onChange: function(selectedDates, dateStr) {
                computeResidencyDuration(dateStr);
            }
        });
    } else {
        startDateField.addEventListener('change', function() {
            computeResidencyDuration(this.value);
        });
    }
}

function computeResidencyDuration(startDateStr) {
    const startDateField = document.getElementById('residency_start_date');
    const hiddenYears = document.getElementById('length_of_residency_years');
    const hiddenResidency = document.getElementById('length_of_residency');
    const displayField = document.getElementById('computed_residency_display');
    
    if (!startDateField || !hiddenYears || !hiddenResidency || !displayField) return;

    if (!startDateStr || startDateStr.trim() === '') {
        hiddenYears.value = '';
        hiddenResidency.value = '';
        displayField.value = '';
        startDateField.classList.remove('is-valid', 'is-invalid');
        return;
    }

    // Parse the start date
    const startDate = new Date(startDateStr);
    if (isNaN(startDate.getTime())) {
        hiddenYears.value = '';
        hiddenResidency.value = '';
        displayField.value = 'Invalid date';
        startDateField.classList.add('is-invalid');
        return;
    }

    // Check if date is in the future
    if (startDate > new Date()) {
        hiddenYears.value = '';
        hiddenResidency.value = '';
        displayField.value = 'Date cannot be in the future';
        startDateField.classList.remove('is-valid');
        startDateField.classList.add('is-invalid');
        return;
    }

    // Calculate duration
    const today = new Date();
    let years = today.getFullYear() - startDate.getFullYear();
    let months = today.getMonth() - startDate.getMonth();

    // Adjust if birthday hasn't occurred this year or month
    if (months < 0) {
        years--;
        months += 12;
    }

    // Check if the day hasn't arrived in the current month
    if (today.getDate() < startDate.getDate()) {
        months--;
        if (months < 0) {
            years--;
            months += 12;
        }
    }

    // Check minimum 6 months requirement
    const totalMonths = years * 12 + months;
    const isValidResidency = totalMonths >= 6;

    // Update hidden fields
    hiddenYears.value = (years + (months / 12)).toFixed(2);
    const yLabel = `${years} year${years === 1 ? '' : 's'}`;
    const mLabel = `${months} month${months === 1 ? '' : 's'}`;
    hiddenResidency.value = `${yLabel} ${mLabel}`;

    // Update display field
    displayField.value = `${yLabel} ${mLabel}`;

    // Update validation state
    if (isValidResidency) {
        startDateField.classList.remove('is-invalid');
        startDateField.classList.add('is-valid');
    } else {
        startDateField.classList.remove('is-valid');
        startDateField.classList.add('is-invalid');
    }
}

initializeResidencyDatePicker();

// Toggle additional fields for special categories
['is_pwd','is_solo','is_ip'].forEach(id => {
    document.getElementById(id).addEventListener('change', function() {
        const f = document.getElementById(id === 'is_pwd' ? 'pwdIdField' : id === 'is_solo' ? 'soloIdField' : 'ipGroupField');
        f.style.display = this.checked ? 'block' : 'none';
    });
});

function toggleParentGuardianField() {
    const relationshipField = document.getElementById('relationship_to_head');
    const parentGuardianRow = document.getElementById('parentGuardianRow');
    const parentGuardianFirstNameField = document.getElementById('parent_guardian_first_name');
    const parentGuardianMiddleNameField = document.getElementById('parent_guardian_middle_name');
    const parentGuardianLastNameField = document.getElementById('parent_guardian_last_name');
    const parentGuardianSuffixField = document.getElementById('parent_guardian_suffix');

    if (!relationshipField || !parentGuardianRow || !parentGuardianFirstNameField || !parentGuardianMiddleNameField || !parentGuardianLastNameField || !parentGuardianSuffixField) {
        return;
    }

    const relationship = (relationshipField.value || '').trim();
    const needsParentGuardian = relationship === 'Child' || relationship === 'Grandchild';

    parentGuardianRow.style.display = needsParentGuardian ? 'flex' : 'none';
    parentGuardianFirstNameField.required = needsParentGuardian;
    parentGuardianLastNameField.required = needsParentGuardian;

    if (!needsParentGuardian) {
        parentGuardianFirstNameField.value = '';
        parentGuardianMiddleNameField.value = '';
        parentGuardianLastNameField.value = '';
        parentGuardianSuffixField.value = '';
        parentGuardianFirstNameField.classList.remove('is-invalid');
        parentGuardianLastNameField.classList.remove('is-invalid');
    }
}

function toggleHouseholdTypeField() {
    const householdRoleField = document.querySelector('select[name="household_role"]');
    const householdTypeRow = document.getElementById('householdTypeRow');
    const householdTypeField = document.getElementById('household_type');
    const houseTypeField = document.getElementById('house_type');
    const householdMembersRow = document.getElementById('householdMembersRow');
    const householdMembersField = document.querySelector('input[name="household_members"]');
    const householdIncomeRow = document.getElementById('householdIncomeRow');
    const householdIncomeField = document.getElementById('household_income');
    const familyCodeField = document.getElementById('family_code');
    const relationshipField = document.getElementById('relationship_to_head');
    const familyCodeRequiredMark = document.getElementById('familyCodeRequiredMark');
    const relationshipRequiredMark = document.getElementById('relationshipRequiredMark');
    const familyCodeHelpText = document.getElementById('familyCodeHelpText');
    const relationshipHelpText = document.getElementById('relationshipHelpText');

    if (!householdRoleField || !householdTypeRow || !householdTypeField || !houseTypeField || !householdMembersRow || !householdMembersField || !householdIncomeRow || !householdIncomeField || !familyCodeField || !relationshipField) {
        return;
    }

    const buildFamilyCodePreview = () => {
        const year = new Date().getFullYear();
        const token = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
        return `BR219-${year}-${token}`;
    };

    const isHeadOfHousehold = householdRoleField.value === 'Head of Household';
    const isMemberOfHousehold = householdRoleField.value === 'Member of Household';
    householdTypeRow.style.display = isHeadOfHousehold ? 'flex' : 'none';
    householdTypeField.required = isHeadOfHousehold;
    houseTypeField.required = isHeadOfHousehold;
    householdMembersRow.style.display = isHeadOfHousehold ? 'flex' : 'none';
    householdMembersField.required = isHeadOfHousehold;
    householdIncomeRow.style.display = isHeadOfHousehold ? 'flex' : 'none';
    householdIncomeField.required = isHeadOfHousehold;

    if (!isHeadOfHousehold) {
        householdTypeField.value = '';
        householdTypeField.classList.remove('is-invalid');
        houseTypeField.value = '';
        houseTypeField.classList.remove('is-invalid');
        householdMembersField.value = '';
        householdMembersField.classList.remove('is-invalid');
        householdIncomeField.value = '';
        householdIncomeField.classList.remove('is-invalid');
        const incomePerMemberField = document.getElementById('income_per_member');
        const economicClassificationField = document.getElementById('economic_classification');
        if (incomePerMemberField) incomePerMemberField.value = '';
        if (economicClassificationField) economicClassificationField.value = '';
    }

    familyCodeField.required = isMemberOfHousehold;
    relationshipField.required = isMemberOfHousehold;

    if (familyCodeRequiredMark) familyCodeRequiredMark.style.display = isMemberOfHousehold ? 'inline' : 'none';
    if (relationshipRequiredMark) relationshipRequiredMark.style.display = isMemberOfHousehold ? 'inline' : 'none';

    if (isHeadOfHousehold) {
        familyCodeField.readOnly = true;
        familyCodeField.value = buildFamilyCodePreview();
        familyCodeField.dataset.autogenerated = '1';
        familyCodeField.classList.remove('is-invalid');
        if (familyCodeHelpText) familyCodeHelpText.textContent = 'Auto-generated for Head of Household. Final code is assigned during registration.';

        relationshipField.value = 'Head';
        relationshipField.disabled = true;
        relationshipField.classList.remove('is-invalid');
        if (relationshipHelpText) relationshipHelpText.textContent = 'Automatically set to Head for household heads.';
    } else if (isMemberOfHousehold) {
        relationshipField.disabled = false;
        if (relationshipField.value === 'Head') {
            relationshipField.value = '';
        }
        if (familyCodeField.dataset.autogenerated === '1') {
            familyCodeField.value = '';
            delete familyCodeField.dataset.autogenerated;
        }
        familyCodeField.readOnly = false;
        familyCodeField.placeholder = 'Enter Head Family Code (e.g., BR219-2026-0001)';
        if (familyCodeHelpText) familyCodeHelpText.textContent = 'Enter the Head of Household Family Code to link your registration.';
        if (relationshipHelpText) relationshipHelpText.textContent = 'Choose your relationship to the Head. This is required.';
    } else {
        relationshipField.disabled = false;
        if (familyCodeField.dataset.autogenerated === '1') {
            delete familyCodeField.dataset.autogenerated;
        }
        familyCodeField.readOnly = false;
        familyCodeField.value = '';
        relationshipField.value = '';
        if (familyCodeHelpText) familyCodeHelpText.textContent = 'Select a household role first.';
        if (relationshipHelpText) relationshipHelpText.textContent = 'Select a household role first.';
    }

    toggleParentGuardianField();
    computeHouseholdIncomeClassification();
}

function computeHouseholdIncomeClassification() {
    const threshold = 12000;
    const householdRoleField = document.querySelector('select[name="household_role"]');
    const membersField = document.getElementById('household_members');
    const incomeField = document.getElementById('household_income');
    const incomePerMemberField = document.getElementById('income_per_member');
    const economicClassificationField = document.getElementById('economic_classification');

    if (!householdRoleField || !membersField || !incomeField || !incomePerMemberField || !economicClassificationField) {
        return;
    }

    if (householdRoleField.value !== 'Head of Household') {
        incomePerMemberField.value = '';
        economicClassificationField.value = '';
        return;
    }

    const membersRaw = String(membersField.value || '').trim();
    const incomeRaw = String(incomeField.value || '').trim().replace(/,/g, '');
    const members = Number(membersRaw);
    const totalIncome = Number(incomeRaw);

    if (!Number.isFinite(members) || members <= 0 || !Number.isFinite(totalIncome) || totalIncome < 0) {
        incomePerMemberField.value = '';
        economicClassificationField.value = '';
        return;
    }

    const incomePerMember = totalIncome / members;
    const classification = incomePerMember < threshold ? 'Indigent' : 'Non-Indigent';

    incomePerMemberField.value = `PHP ${incomePerMember.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    economicClassificationField.value = classification;
}

const householdRoleField = document.querySelector('select[name="household_role"]');
if (householdRoleField) {
    householdRoleField.addEventListener('change', toggleHouseholdTypeField);
    toggleHouseholdTypeField();
}

const relationshipToHeadField = document.getElementById('relationship_to_head');
if (relationshipToHeadField) {
    relationshipToHeadField.addEventListener('change', toggleParentGuardianField);
    relationshipToHeadField.addEventListener('input', toggleParentGuardianField);
    toggleParentGuardianField();
}

const householdIncomeField = document.getElementById('household_income');

function formatHouseholdIncomeInput(raw) {
    const cleaned = String(raw || '').replace(/[^0-9.]/g, '');
    if (!cleaned) return '';

    const firstDot = cleaned.indexOf('.');
    const normalized = firstDot === -1
        ? cleaned
        : cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');

    const parts = normalized.split('.');
    const integerPart = (parts[0] || '').replace(/^0+(?=\d)/, '');
    const formattedInteger = (integerPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    if (parts.length === 1) return formattedInteger;
    const decimalPart = parts[1].slice(0, 2);
    return decimalPart.length ? `${formattedInteger}.${decimalPart}` : `${formattedInteger}.`;
}

function applyHouseholdIncomeFormatting(field) {
    if (!field) return;
    const formatted = formatHouseholdIncomeInput(field.value);
    if (field.value !== formatted) {
        field.value = formatted;
    }
}

if (householdMembersField) {
    householdMembersField.addEventListener('input', computeHouseholdIncomeClassification);
    householdMembersField.addEventListener('change', computeHouseholdIncomeClassification);
    householdMembersField.addEventListener('blur', computeHouseholdIncomeClassification);
}
if (householdIncomeField) {
    householdIncomeField.addEventListener('input', function () {
        applyHouseholdIncomeFormatting(this);
    });
    householdIncomeField.addEventListener('input', computeHouseholdIncomeClassification);
    householdIncomeField.addEventListener('change', computeHouseholdIncomeClassification);
    householdIncomeField.addEventListener('blur', computeHouseholdIncomeClassification);
}

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
        const birthDateField = document.getElementById('birth_date');
        const birthDate = new Date(birthDateField.value);
        const today = new Date();
        birthDateField.setCustomValidity('');

        if (birthDate > today) {
            birthDateField.classList.add('is-invalid');
            birthDateField.setCustomValidity('Please enter a valid birth date.');
            isValid = false;
        }

        // Enforce minimum age of 18 years old
        const ageValue = parseInt(document.getElementById('ageDisplay').value, 10);
        if (!Number.isFinite(ageValue) || ageValue < 18) {
            birthDateField.classList.add('is-invalid');
            birthDateField.setCustomValidity('You must be 18 years old and above to register.');
            isValid = false;
        }
    }

    if (step === 3) {
        // Validate residency start date
        const startDateField = document.getElementById('residency_start_date');
        const startDateValue = startDateField.value.trim();
        startDateField.setCustomValidity('');

        if (!startDateValue) {
            startDateField.classList.add('is-invalid');
            startDateField.setCustomValidity('Residency start date is required.');
            isValid = false;
        } else {
            // Recalculate to validate
            computeResidencyDuration(startDateValue);
            
            // Check if field has invalid class after computation
            if (startDateField.classList.contains('is-invalid')) {
                isValid = false;
            }
        }

        // Validate mobile number format
        const mobile = document.querySelector('input[name="mobile_number"]');
        if (mobile.value && !/^\+63\s\d{10}$/.test(mobile.value)) {
            mobile.classList.add('is-invalid');
            isValid = false;
        }

        // Validate emergency contact number format
        const emergencyContact = document.querySelector('input[name="emergency_contact_number"]');
        if (emergencyContact.value && !/^\+63\s\d{10}$/.test(emergencyContact.value)) {
            emergencyContact.classList.add('is-invalid');
            isValid = false;
        }

        // Validate email if provided - must be a Gmail address
        const email = document.querySelector('input[name="email"]');
        if (email.value && !/^[a-zA-Z0-9._%+\-]+@gmail\.com$/.test(email.value)) {
            email.classList.add('is-invalid');
            isValid = false;
        }
    }

    const roleField = document.querySelector('select[name="household_role"]');
    const householdTypeField = document.getElementById('household_type');
    const houseTypeField = document.getElementById('house_type');
    const familyCodeField = document.getElementById('family_code');
    const relationshipField = document.getElementById('relationship_to_head');
    if (roleField && householdTypeField && houseTypeField && roleField.value === 'Head of Household') {
        if (!householdTypeField.value.trim()) {
            householdTypeField.classList.add('is-invalid');
            isValid = false;
        } else {
            householdTypeField.classList.remove('is-invalid');
        }

        if (!houseTypeField.value.trim()) {
            houseTypeField.classList.add('is-invalid');
            isValid = false;
        } else {
            houseTypeField.classList.remove('is-invalid');
        }

        const householdMembersFieldForValidation = document.querySelector('input[name="household_members"]');
        const householdIncomeFieldForValidation = document.getElementById('household_income');
        if (householdMembersFieldForValidation) {
            const membersValue = parseInt(householdMembersFieldForValidation.value, 10);
            if (!Number.isFinite(membersValue) || membersValue <= 0) {
                householdMembersFieldForValidation.classList.add('is-invalid');
                isValid = false;
            } else {
                householdMembersFieldForValidation.classList.remove('is-invalid');
            }
        }
        if (householdIncomeFieldForValidation) {
            const incomeValue = parseFloat(String(householdIncomeFieldForValidation.value || '').replace(/,/g, ''));
            if (!Number.isFinite(incomeValue) || incomeValue < 0) {
                householdIncomeFieldForValidation.classList.add('is-invalid');
                isValid = false;
            } else {
                householdIncomeFieldForValidation.classList.remove('is-invalid');
            }
        }
        computeHouseholdIncomeClassification();

        if (familyCodeField && !/^BR219-\d{4}-\d{4}$/i.test((familyCodeField.value || '').trim())) {
            familyCodeField.classList.add('is-invalid');
            isValid = false;
        } else if (familyCodeField) {
            familyCodeField.classList.remove('is-invalid');
        }
    }

    if (roleField && roleField.value === 'Member of Household') {
        if (familyCodeField && !/^BR219-\d{4}-\d{4}$/i.test((familyCodeField.value || '').trim())) {
            familyCodeField.classList.add('is-invalid');
            isValid = false;
        } else if (familyCodeField) {
            familyCodeField.classList.remove('is-invalid');
        }

        if (relationshipField) {
            const rel = (relationshipField.value || '').trim();
            if (rel === '' || rel.toLowerCase() === 'head') {
                relationshipField.classList.add('is-invalid');
                isValid = false;
            } else {
                relationshipField.classList.remove('is-invalid');
            }

            const parentGuardianFirstNameField = document.getElementById('parent_guardian_first_name');
            const parentGuardianLastNameField = document.getElementById('parent_guardian_last_name');
            if ((rel === 'Child' || rel === 'Grandchild') && parentGuardianFirstNameField && parentGuardianLastNameField) {
                if (!parentGuardianFirstNameField.value.trim()) {
                    parentGuardianFirstNameField.classList.add('is-invalid');
                    isValid = false;
                } else {
                    parentGuardianFirstNameField.classList.remove('is-invalid');
                }

                if (!parentGuardianLastNameField.value.trim()) {
                    parentGuardianLastNameField.classList.add('is-invalid');
                    isValid = false;
                } else {
                    parentGuardianLastNameField.classList.remove('is-invalid');
                }
            }
        }
    }

    return isValid;
}

function populateReview() {
    const reviewContent = document.getElementById('reviewContent');
    reviewContent.innerHTML = '';

    const syncReviewFieldsFromOriginal = () => {
        const reviewFields = reviewContent.querySelectorAll('.review-edit-field');
        if (!reviewFields.length) return;

        reviewFields.forEach(clonedField => {
            const fieldName = clonedField.dataset.field;
            if (!fieldName) return;

            const originalField = document.querySelector(`[name="${fieldName}"]`);
            if (!originalField) return;

            clonedField.value = originalField.value;
            clonedField.readOnly = !!originalField.readOnly;
            clonedField.disabled = !!originalField.disabled;
            clonedField.required = !!originalField.required;

            const col = clonedField.closest('.col-md-6');
            if (col) {
                col.style.display = '';
            }
        });

        const roleOriginal = document.querySelector('[name="household_role"]');
        const relationshipOriginal = document.querySelector('[name="relationship_to_head"]');
        const isHead = roleOriginal && roleOriginal.value === 'Head of Household';
        const needsParentGuardian = relationshipOriginal && (relationshipOriginal.value === 'Child' || relationshipOriginal.value === 'Grandchild');

        const setReviewFieldVisibility = (fieldName, shouldShow) => {
            const field = reviewContent.querySelector(`.review-edit-field[data-field="${fieldName}"]`);
            if (!field) return;
            const col = field.closest('.col-md-6');
            if (!col) return;
            col.style.display = shouldShow ? '' : 'none';
        };

        setReviewFieldVisibility('household_type', !!isHead);
        setReviewFieldVisibility('house_type', !!isHead);
        setReviewFieldVisibility('household_members', !!isHead);
        setReviewFieldVisibility('household_income', !!isHead);
        setReviewFieldVisibility('income_per_member', !!isHead);
        setReviewFieldVisibility('economic_classification', !!isHead);

        const showParentGuardian = !!needsParentGuardian;
        setReviewFieldVisibility('parent_guardian_first_name', showParentGuardian);
        setReviewFieldVisibility('parent_guardian_middle_name', showParentGuardian);
        setReviewFieldVisibility('parent_guardian_last_name', showParentGuardian);
        setReviewFieldVisibility('parent_guardian_suffix', showParentGuardian);
    };

    const reviewSections = [
        {
            title: 'Personal Information',
            fields: [
                { name: 'first_name', label: 'First Name' },
                { name: 'middle_name', label: 'Middle Name' },
                { name: 'last_name', label: 'Last Name' },
                { name: 'suffix', label: 'Suffix' },
                { name: 'sex', label: 'Sex' },
                { name: 'birth_date', label: 'Date of Birth' },
                { name: 'place_of_birth', label: 'Place of Birth' },
                { name: 'civil_status', label: 'Civil Status' },
                { name: 'citizenship', label: 'Citizenship' }
            ]
        },
        {
            title: 'Family Background',
            fields: [
                { name: 'household_role', label: 'Household Role' },
                { name: 'household_type', label: 'Household Type' },
                { name: 'house_type', label: 'House Type' },
                { name: 'household_members', label: 'No. of Household Members' },
                { name: 'household_income', label: 'Total Household Income (Monthly)' },
                { name: 'income_per_member', label: 'Income per Family Member' },
                { name: 'economic_classification', label: 'Economic Classification' },
                { name: 'family_code', label: 'Family Code' },
                { name: 'relationship_to_head', label: 'Relationship to Head' },
                { name: 'parent_guardian_first_name', label: 'Parent / Guardian First Name' },
                { name: 'parent_guardian_middle_name', label: 'Parent / Guardian Middle Name' },
                { name: 'parent_guardian_last_name', label: 'Parent / Guardian Last Name' },
                { name: 'parent_guardian_suffix', label: 'Parent / Guardian Suffix' }
            ]
        },
        {
            title: 'Contact & Residency',
            fields: [
                { name: 'mobile_number', label: 'Mobile Number' },
                { name: 'email', label: 'Email Address' },
                { name: 'house_number', label: 'House No.' },
                { name: 'street', label: 'Street' },
                { name: 'purok_sitio', label: 'Purok / Sitio' },
                { name: 'residency_start_date', label: 'Date of Residency Start' },
                { name: 'emergency_contact_name', label: 'Emergency Contact Name' },
                { name: 'emergency_contact_number', label: 'Emergency Contact Number' },
                { name: 'emergency_contact_relationship', label: 'Emergency Contact Relationship' }
            ]
        }
    ];

    reviewSections.forEach(section => {
        const card = document.createElement('div');
        card.className = 'card section-card mb-4';
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body';
        const heading = document.createElement('h6');
        heading.className = 'text-secondary mb-3';
        heading.textContent = section.title;
        cardBody.appendChild(heading);

        const row = document.createElement('div');
        row.className = 'row';

        section.fields.forEach(fieldDef => {
            const originalField = document.querySelector(`[name="${fieldDef.name}"]`);
            if (!originalField) return;

            const col = document.createElement('div');
            col.className = 'col-md-6 mb-2';

            const lbl = document.createElement('label');
            lbl.className = 'form-label fw-semibold text-secondary';
            lbl.style.fontSize = '0.82rem';
            lbl.textContent = fieldDef.label;

            // Clone the original input/select to preserve all options
            const cloned = originalField.cloneNode(true);
            if (cloned.tagName === 'SELECT') {
                cloned.className = 'form-select form-select-sm review-edit-field';
            } else {
                cloned.className = 'form-control form-control-sm review-edit-field';
            }
            cloned.removeAttribute('name');
            cloned.removeAttribute('required');
            cloned.removeAttribute('id');
            cloned.style.cssText = '';
            cloned.dataset.field = fieldDef.name;
            // Explicitly set current value (cloneNode copies attributes, not live DOM state)
            cloned.value = originalField.value;

            // Sync edits back to the original form field
            ['input', 'change'].forEach(evt => {
                cloned.addEventListener(evt, function () {
                    if (this.dataset.field === 'household_income') {
                        applyHouseholdIncomeFormatting(this);
                    }

                    const orig = document.querySelector(`[name="${this.dataset.field}"]`);
                    if (orig) orig.value = this.value;
                    if (this.dataset.field === 'residency_start_date') {
                        computeResidencyDuration(this.value);
                    }
                    if (this.dataset.field === 'household_role') {
                        toggleHouseholdTypeField();
                    }
                    if (this.dataset.field === 'household_members' || this.dataset.field === 'household_income') {
                        computeHouseholdIncomeClassification();
                    }
                    if (this.dataset.field === 'relationship_to_head') {
                        toggleParentGuardianField();
                    }

                    syncReviewFieldsFromOriginal();
                });
            });

            col.appendChild(lbl);
            col.appendChild(cloned);
            row.appendChild(col);
        });

        cardBody.appendChild(row);
        card.appendChild(cardBody);
        reviewContent.appendChild(card);
    });

    syncReviewFieldsFromOriginal();
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
    if (isRegisterSubmitting) {
        return;
    }
    applyTitleCaseToRegisterForm();
    if (!validateStep(5)) {
        alert('Please complete all required fields and confirm the information.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    isRegisterSubmitting = true;
    btn.disabled = true;
    const alc = document.getElementById('alertContainer');
    alc.innerHTML = '';
    const fd = new FormData(this);
    try {
        const r = await fetch(API_URL + 'register.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.success) {
            alc.innerHTML = '<div class="alert alert-success">' + data.message + '<br><strong>Reference:</strong> ' + (data.data?.application_ref || '') + '<div class="mt-3"><a href="login.php" class="btn btn-primary">Go to Login</a></div></div>';
            btn.style.display = 'none';
            document.getElementById('nextBtn').style.display = 'none';
            document.getElementById('prevBtn').style.display = 'none';
            const finalStepContent = document.querySelector('.step-content[data-step="4"]');
            const finalStepIndicator = document.querySelector('.step[data-step="4"]');
            if (finalStepContent) finalStepContent.style.display = 'none';
            if (finalStepIndicator) finalStepIndicator.style.display = 'none';
        } else {
            alc.innerHTML = '<div class="alert alert-danger"> ' + data.message + '</div>';
        }
    } catch (err) {
        alc.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
    }
    if (btn.style.display !== 'none') {
        btn.disabled = false;
    }
    isRegisterSubmitting = false;
});

  // Initialize
  initRegisterTitleCase();
  showStep(1);
</script>
</body>
</html>
