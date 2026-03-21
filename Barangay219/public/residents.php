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

        <ul class="nav nav-tabs app-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="active">Active</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="inactive">Inactive</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="deceased">Deceased</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="transferred">Transferred</a></li>
        </ul>

        <!-- Residents Table -->
        <div class="data-table residents-table-wrap">
            <div class="table-responsive residents-table-scroll">
                <table class="table table-hover residents-table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">Resident ID</th>
                            <th class="text-center">Full Name</th>
                            <th class="text-center">Birth Date</th>
                            <th class="text-center">Gender</th>
                            <th class="text-center">Address</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Household Code</th>
                            <th class="text-center">Family Head Code</th>
                            <th class="text-center">Verification</th>
                            <th class="text-center">Status</th>
                            <th class="text-center residents-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="residentsTableBody">
                        <tr>
                            <td colspan="11" class="text-center">
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

<style>
.residents-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.45rem;
    border-bottom: 0;
}

.residents-page .app-tabs .nav-item {
    margin: 0;
}

.residents-page .app-tabs .nav-link {
    width: 100%;
    text-align: center;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.5rem 0.8rem;
    background: #ffffff;
}

.residents-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

.residents-page .residents-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #fff;
    padding: 0.35rem;
}

.residents-page .residents-table {
    margin-bottom: 0;
}

.residents-page .residents-table-scroll {
    max-height: min(62vh, 640px);
    overflow-y: auto;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}

.residents-page .residents-table-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.residents-page .residents-table-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
}

.residents-page .residents-table-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 999px;
    border: 2px solid #f1f5f9;
}

.residents-page .residents-table-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

.residents-page .residents-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}

.residents-page .residents-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.9rem 0.85rem;
    vertical-align: middle;
}

.residents-page .residents-table thead th {
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #f9fbfd;
}

.residents-page .residents-table tbody td {
    color: #1f2937;
    font-size: 0.94rem;
}

.residents-page .residents-table tbody tr:hover {
    background: #f8fbff;
}

.residents-page .resident-secondary {
    color: #6b7280;
    font-size: 0.86rem;
}

.residents-page .resident-code-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.28rem 0.55rem;
    border-radius: 999px;
    background: #f2f5fa;
    border: 1px solid #e2e8f0;
    color: #465468;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1;
}

.residents-page .resident-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 86px;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.residents-page .resident-pill.status-active,
.residents-page .resident-pill.verify-verified {
    background: #e9f8ef;
    color: #1f7a3f;
}

.residents-page .resident-pill.status-inactive {
    background: #eef2f7;
    color: #4b5563;
}

.residents-page .resident-pill.status-deceased {
    background: #f1f3f6;
    color: #374151;
}

.residents-page .resident-pill.status-transferred {
    background: #eaf6ff;
    color: #1f5f8b;
}

.residents-page .resident-pill.verify-pending {
    background: #fff4e8;
    color: #9a5b11;
}

.residents-page .resident-pill.verify-rejected {
    background: #ffecee;
    color: #a53a44;
}

.residents-page .resident-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.residents-page .action-icon-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #e6ebf2;
    background: #ffffff;
    color: #5b6678;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.residents-page .action-icon-btn:hover,
.residents-page .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

.residents-page .action-icon-btn.action-delete:hover,
.residents-page .action-icon-btn.action-delete:focus-visible {
    background: #fff1f3;
    border-color: #f6ccd3;
    color: #9f2f3e;
}

.residents-page .residents-actions-col {
    min-width: 170px;
}

@media (max-width: 768px) {
    .residents-page .app-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .residents-page .residents-table > :not(caption) > * > * {
        padding: 0.75rem 0.6rem;
    }

    .residents-page .residents-actions-col {
        min-width: 140px;
    }
}
</style>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="residentModalTitle">Edit Resident</h5>
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
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="+63 " maxlength="14" inputmode="numeric" pattern="\+63\s\d{10}" required placeholder="+63 9XXXXXXXXX">
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
