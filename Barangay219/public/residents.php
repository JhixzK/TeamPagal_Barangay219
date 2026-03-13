<?php
/**
 * E-Barangay Information Management System
 * Residents Management Page
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('residents');

$page_title = 'Residents Management';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page residents-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Records Module</p>
                    <h2 class="mb-1"><i class="bi bi-people me-2"></i>Residents Management</h2>
                    <p class="module-subtitle mb-0">Maintain resident profiles, status records, and household-linked information.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#residentModal" onclick="resetForm()">
                    <i class="bi bi-plus-circle"></i> Add New Resident
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4 module-stats" data-module="residents">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-primary text-white" data-status="" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-value" data-stat="total">-</div>
                    <div class="stat-label">Total Residents</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card bg-success text-white" data-status="active" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                    <div class="stat-value" data-stat="active">-</div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card bg-secondary text-white" data-status="inactive" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-person-dash"></i></div>
                    <div class="stat-value" data-stat="inactive">-</div>
                    <div class="stat-label">Inactive</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card bg-dark text-white" data-status="deceased" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-person-x"></i></div>
                    <div class="stat-value" data-stat="deceased">-</div>
                    <div class="stat-label">Deceased</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-info text-white" data-status="transferred" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-arrow-left-right"></i></div>
                    <div class="stat-value" data-stat="transferred">-</div>
                    <div class="stat-label">Transferred</div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name or address...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchResidents()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetResidents()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Residents Table -->
        <div class="data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Resident ID</th>
                            <th>Full Name</th>
                            <th>Birth Date</th>
                            <th>Gender</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Household Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="residentsTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center" id="pagination">
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Residents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="deceased">Deceased</option>
                        <option value="transferred">Transferred</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" id="filterGender">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Age</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="filterAgeFrom" placeholder="From" min="0">
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="filterAgeTo" placeholder="To" min="0">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Resident Modal -->
<div class="modal fade" id="residentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="residentModalTitle">Add New Resident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="residentForm">
                <div class="modal-body">
                    <input type="hidden" id="residentId" name="id">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="col-md-1 mb-3">
                            <label for="suffix" class="form-label">Suffix</label>
                            <input type="text" class="form-control" id="suffix" name="suffix" placeholder="Jr.">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="birth_date" class="form-label">Birth Date <span class="text-danger">*</span> <small class="text-muted">mm/dd/yyyy</small></label>
                            <input type="date" class="form-control" id="birth_date" name="birth_date" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="civil_status" name="civil_status" required>
                                <option value="">Select Status</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="widowed">Widowed</option>
                                <option value="divorced">Divorced</option>
                                <option value="separated">Separated</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="deceased">Deceased</option>
                                <option value="transferred">Transferred</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="occupation" class="form-label">Occupation <span class="text-danger">*</span></label>
                            <select class="form-select" id="occupation" name="occupation" required>
                                <option value="">Select Occupation</option>
                                <option value="Student">Student</option>
                                <option value="Unemployed">Unemployed</option>
                                <option value="Self-Employed">Self-Employed</option>
                                <option value="Farmer">Farmer</option>
                                <option value="Fisherman">Fisherman</option>
                                <option value="Vendor">Vendor</option>
                                <option value="Driver">Driver</option>
                                <option value="Construction Worker">Construction Worker</option>
                                <option value="Factory Worker">Factory Worker</option>
                                <option value="Office Staff">Office Staff</option>
                                <option value="Teacher">Teacher</option>
                                <option value="Nurse">Nurse</option>
                                <option value="Barangay Staff">Barangay Staff</option>
                                <option value="OFW">OFW</option>
                                <option value="Retired">Retired</option>
                                <option value="Homemaker">Homemaker</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="citizenship" class="form-label">Citizenship <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="citizenship" name="citizenship" value="Filipino" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="+63" maxlength="13" inputmode="numeric" pattern="\+63\d{10}" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-9 mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="household_id" class="form-label">Household (Optional)</label>
                            <select class="form-select" id="household_id" name="household_id">
                                <option value="">-- None --</option>
                            </select>
                            <small class="text-muted">Link to a household</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Resident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Resident Modal -->
<div class="modal fade" id="viewResidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewResidentBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnEditFromView"><i class="bi bi-pencil"></i> Edit</button>
                <a href="#" id="linkCertificates" class="btn btn-info"><i class="bi bi-file-earmark-text"></i> Certificates</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- Define API URL and constants for JavaScript -->
<script>
    if (typeof window.API_URL === 'undefined') {
        window.API_URL = '<?php echo API_URL; ?>';
    }
    window.ITEMS_PER_PAGE = <?php echo ITEMS_PER_PAGE; ?>;
    window.BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/residents.js?v=<?php echo time(); ?>"></script>
