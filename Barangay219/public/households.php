<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Households Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('households');

include __DIR__ . '/../includes/sidebar.php';
?> 

<div class="main-content module-page households-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Records Module</p>
                    <h2 class="mb-1"><i class="bi bi-house-door me-2"></i>Households Management</h2>
                    <p class="module-subtitle mb-0">Track household records, family heads, and member composition.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#householdModal" onclick="resetForm(); loadResidentsForDropdown();">
                    <i class="bi bi-plus-circle"></i> Add New Household
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4 module-stats" data-module="households">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-primary text-white" data-range="all" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-house"></i></div>
                    <div class="stat-value" data-stat="total_households">-</div>
                    <div class="stat-label">Total Households</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-success text-white" data-range="all" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-value" data-stat="total_members">-</div>
                    <div class="stat-label">Total Members</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-info text-white" data-range="month" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-calendar-plus"></i></div>
                    <div class="stat-value" data-stat="new_this_month">-</div>
                    <div class="stat-label">New This Month</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-secondary text-white" data-range="year" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-calendar"></i></div>
                    <div class="stat-value" data-stat="new_this_year">-</div>
                    <div class="stat-label">New This Year</div>
                </div>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchHousehold" placeholder="Search by address or family head...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchHouseholds()"><i class="bi bi-search"></i> Search</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetHouseholds()"><i class="bi bi-arrow-clockwise"></i> Reset</button>
                </div>
            </div>
        </div>

        <div class="data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th class="text-center">Family Head</th>
                            <th class="text-center">Address</th>
                            <th class="text-center">Total Members</th>
                            <th class="text-center">Registration Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="householdsTableBody">
                        <tr><td colspan="6" class="text-center"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Households</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Registration From</label>
                    <input type="date" class="form-control" id="filterFrom">
                </div>
                <div class="mb-3">
                    <label class="form-label">Registration To</label>
                    <input type="date" class="form-control" id="filterTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="householdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="householdModalTitle">Add New Household</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="householdForm">
                <div class="modal-body">
                    <input type="hidden" id="householdId" name="id">
                    <div class="mb-3">
                        <label for="family_head_id" class="form-label">Family Head <span class="text-danger">*</span></label>
                        <select class="form-select" id="family_head_id" name="family_head_id" required>
                            <option value="">-- Select Resident --</option>
                        </select>
                        <small class="text-muted">Select the head of the household. Add resident first if not in list.</small>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_members" class="form-label">Total Members</label>
                            <input type="number" min="1" class="form-control" id="total_members" name="total_members" value="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="registration_date" class="form-label">Registration Date</label>
                            <input type="date" class="form-control" id="registration_date" name="registration_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Household Modal -->
<div class="modal fade" id="viewHouseholdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Household Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewHouseholdInfo"></div>
                <hr>
                <h6>Members</h6>
                <div id="viewHouseholdMembers"></div>
                <hr>
                <h6>Add Member</h6>
                <div class="input-group mb-2">
                    <select class="form-select" id="addMemberResident">
                        <option value="">-- Select resident to add --</option>
                    </select>
                    <button class="btn btn-primary" type="button" id="btnAddMember">Add</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>if (typeof window.API_URL === 'undefined') window.API_URL = '<?php echo addslashes(API_URL); ?>';</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/households.js?v=<?php echo time(); ?>"></script>
