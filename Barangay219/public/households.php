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
                    <p class="module-subtitle mb-0">Manage household groups and let residents join selected households.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="btnCreateHousehold" onclick="newHousehold()">
                        <i class="bi bi-plus-circle me-1"></i> New Household
                    </button>
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

        <ul class="nav nav-tabs app-tabs mb-3" id="rangeTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-range="all">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-range="month">New This Month</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-range="year">New This Year</a></li>
        </ul>

        <div id="householdTiles" class="household-tiles">
            <div class="household-tiles-loading text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>
</div>

<style>
.households-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
    border-bottom: 0;
}

.households-page .app-tabs .nav-item {
    margin: 0;
}

.households-page .app-tabs .nav-link {
    width: 100%;
    text-align: center;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.5rem 0.8rem;
    background: #ffffff;
}

.households-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

@media (max-width: 768px) {
    .households-page .app-tabs {
        grid-template-columns: 1fr;
    }
}

.households-page .household-tiles {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

@media (max-width: 1200px) {
    .households-page .household-tiles {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .households-page .household-tiles {
        grid-template-columns: 1fr;
    }
}

.households-page .household-tile.card {
    border: 1px solid #e6edf7;
    border-radius: 14px;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.households-page .household-tile .tile-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    background: linear-gradient(135deg, #f8fbff 0%, #ffffff 70%);
    border-bottom: 1px solid #eef2f7;
}

.households-page .household-tile .tile-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.households-page .household-tile .tile-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: #e8f0ff;
    color: #1d4ed8;
    flex: 0 0 auto;
}

.households-page .household-tile .tile-name {
    font-weight: 800;
    margin: 0;
    line-height: 1.1;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.households-page .household-tile .tile-sub {
    margin: 0.2rem 0 0;
    font-size: 0.85rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.households-page .household-tile .tile-body {
    padding: 0.9rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    flex: 1 1 auto;
}

.households-page .household-tile .tile-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem 0.75rem;
    margin: 0;
}

.households-page .household-tile .tile-meta dt {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin: 0;
}

.households-page .household-tile .tile-meta dd {
    margin: 0.1rem 0 0;
    font-weight: 600;
    color: #0f172a;
}

.households-page .household-tile .tile-actions {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-top: auto;
}
</style>

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
                <h5 class="modal-title" id="householdModalTitle">Edit Household</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="householdForm">
                <div class="modal-body">
                    <input type="hidden" id="householdId" name="id">
                    <div class="mb-3">
                        <label for="family_head_id" class="form-label">Family Head</label>
                        <select class="form-select" id="family_head_id" name="family_head_id">
                            <option value="">-- Select Resident --</option>
                        </select>
                        <small class="text-muted">Optional for now. You can create an empty household first, then assign the head later.</small>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="(optional)"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3" id="totalMembersGroup">
                            <label for="total_members" class="form-label">Total Members</label>
                            <input type="number" min="0" class="form-control" id="total_members" name="total_members" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="registration_date" class="form-label">Registration Date</label>
                            <input type="date" class="form-control" id="registration_date" name="registration_date">
                        </div>
                    </div>
                    <div id="joinHouseholdSection">
                        <hr>
                        <h6>Join Selected Household</h6>
                        <div class="input-group mb-2">
                            <select class="form-select" id="addMemberResidentEdit">
                                <option value="">-- Select resident to add --</option>
                            </select>
                            <button class="btn btn-primary" type="button" id="btnAddMemberEdit">Add</button>
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
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>if (typeof window.API_URL === 'undefined') window.API_URL = '<?php echo addslashes(API_URL); ?>';</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/households.js?v=<?php echo time(); ?>"></script>
