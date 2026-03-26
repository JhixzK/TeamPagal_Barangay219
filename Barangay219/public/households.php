<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Households Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('households');

$hh_street_options = [];
$streetsPath = __DIR__ . '/../config/barangay219_streets.php';
if (is_readable($streetsPath)) {
    $loaded = require $streetsPath;
    $hh_street_options = is_array($loaded) ? $loaded : [];
}

include __DIR__ . '/../includes/sidebar.php';
?> 

<div class="main-content module-page households-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Records Module</p>
                    <h2 class="mb-1"><i class="bi bi-house-door me-2"></i>Households Management</h2>
                    <p class="module-subtitle mb-0">Manage household groups and add residents to households.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" id="btnCreateHousehold" onclick="newHouseholdForContext()">
                        <i class="bi bi-plus-circle me-1"></i> New Household
                    </button>
                </div>
            </div>
        </div>

        <nav aria-label="breadcrumb" class="hh-breadcrumb-bar mb-3">
            <ol class="breadcrumb mb-0" id="hhBreadcrumb">
                <li class="breadcrumb-item active" aria-current="page">All Streets</li>
            </ol>
        </nav>

        <div class="hh-household-search-bar mb-3 d-none" id="hhHouseholdSearchWrap">
            <div class="row g-2 align-items-center">
                <div class="col min-w-0">
                    <label for="hhHouseholdSearchInput" class="visually-hidden">Search households on this street</label>
                    <input type="search" class="form-control" id="hhHouseholdSearchInput" placeholder="Search by head name, address, or household ID code…" autocomplete="off">
                </div>
                <div class="col-auto d-flex gap-1 align-items-center">
                    <button type="button" class="btn btn-sm btn-primary hh-household-search-icon-btn" id="hhHouseholdSearchBtn" onclick="runHouseholdListSearch()" title="Search" aria-label="Search households">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary hh-household-search-icon-btn" id="hhHouseholdFilterBtn" data-bs-toggle="modal" data-bs-target="#hhFilterModal" title="Filter" aria-label="Filter households">
                        <i class="bi bi-funnel" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary hh-household-search-icon-btn" id="hhHouseholdSearchClear" onclick="clearHouseholdListSearch()" title="Clear search" aria-label="Clear search">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="hh-back-row mb-3 d-none d-flex justify-content-end" id="hhBackBtnWrap">
            <button type="button" class="btn btn-lg btn-outline-primary px-4 hh-back-btn" id="hhBackBtn" onclick="householdNavBack()">
                <i class="bi bi-arrow-left me-2"></i> Back
            </button>
        </div>

        <div id="householdTilesWrap" class="hh-tiles-wrap">
            <div id="householdTiles" class="household-tiles">
                <div class="household-tiles-loading text-center py-5">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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

.households-page .hh-breadcrumb-bar .breadcrumb {
    background: #f8fafc;
    border: 1px solid #e6edf7;
    border-radius: 12px;
    padding: 0.55rem 1rem;
    font-weight: 600;
}

.households-page .hh-breadcrumb-bar .breadcrumb-item + .breadcrumb-item::before {
    color: #94a3b8;
}

.households-page .hh-back-btn.btn-outline-primary {
    color: #1d4ed8;
    border-color: #93c5fd;
    background: #f8fbff;
    font-weight: 600;
}

.households-page .hh-back-btn.btn-outline-primary:hover,
.households-page .hh-back-btn.btn-outline-primary:focus-visible {
    color: #1e40af;
    border-color: #3b82f6;
    background: #e8f0ff;
}

.households-page .hh-household-search-icon-btn {
    width: 1.875rem;
    height: 1.875rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.households-page .hh-household-search-icon-btn i {
    font-size: 0.8125rem;
}

.households-page .hh-tiles-wrap {
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.households-page .hh-tiles-wrap.hh-tiles-dim {
    opacity: 0.45;
    transform: translateY(4px);
    pointer-events: none;
}

.households-page .street-tile.card {
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}

.households-page .street-tile.card:hover,
.households-page .street-tile.card:focus-visible {
    box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
    border-color: #bfdbfe;
}

.households-page .household-tile.card.hh-tile-clickable {
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}

.households-page .household-tile.card.hh-tile-clickable:hover,
.households-page .household-tile.card.hh-tile-clickable:focus-visible {
    box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
    border-color: #bfdbfe;
}

.households-page .household-tile .tile-actions .action-icon-btn {
    position: relative;
    z-index: 2;
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
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: auto;
    margin-left: auto;
}

#viewHouseholdModal .view-household-modal-dialog {
    max-width: min(1320px, 96vw);
}

.households-page .action-icon-btn,
#viewHouseholdModal .action-icon-btn,
#householdModal .action-icon-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #e6ebf2;
    background: #ffffff;
    color: #5b6678;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
    box-sizing: border-box;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.households-page .action-icon-btn:hover,
.households-page .action-icon-btn:focus-visible,
#viewHouseholdModal .action-icon-btn:hover,
#viewHouseholdModal .action-icon-btn:focus-visible,
#householdModal .action-icon-btn:hover,
#householdModal .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

.households-page .action-icon-btn i,
#viewHouseholdModal .action-icon-btn i,
#householdModal .action-icon-btn i {
    font-size: 1rem;
    line-height: 1;
    display: block;
}

.households-page .action-icon-btn.action-delete:hover,
.households-page .action-icon-btn.action-delete:focus-visible,
#viewHouseholdModal .action-icon-btn.action-delete:hover,
#viewHouseholdModal .action-icon-btn.action-delete:focus-visible,
#householdModal .action-icon-btn.action-delete:hover,
#householdModal .action-icon-btn.action-delete:focus-visible {
    background: #fff1f3;
    border-color: #f6ccd3;
    color: #9f2f3e;
}

#viewHouseholdModal .household-detail-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.35rem;
}

/* Family Groups head tabs — match module app-tabs (outline pills, soft active), not Bootstrap nav-pills fill */
#viewHouseholdModal .household-head-tabs,
#householdModal .household-head-tabs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(132px, 1fr));
    gap: 0.45rem;
    border-bottom: 0;
    flex-wrap: unset;
}

#viewHouseholdModal .household-head-tabs .nav-item,
#householdModal .household-head-tabs .nav-item {
    margin: 0;
    min-width: 0;
}

#viewHouseholdModal .household-head-tabs .nav-link,
#householdModal .household-head-tabs .nav-link {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    font-size: 0.8125rem;
    line-height: 1.25;
    padding: 0.45rem 0.75rem;
    background: #ffffff;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

#viewHouseholdModal .household-head-tabs .nav-link:hover,
#viewHouseholdModal .household-head-tabs .nav-link:focus-visible,
#householdModal .household-head-tabs .nav-link:hover,
#householdModal .household-head-tabs .nav-link:focus-visible {
    color: #1d4ed8;
    background: #f8fafc;
    border-color: #c7d7ee;
}

#viewHouseholdModal .household-head-tabs .nav-link.active,
#householdModal .household-head-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

#viewHouseholdModal .household-head-tabs .household-head-tab-label,
#householdModal .household-head-tabs .household-head-tab-label {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 576px) {
    #viewHouseholdModal .household-head-tabs,
    #householdModal .household-head-tabs {
        grid-template-columns: 1fr;
    }
}

#householdModal .hh-edit-members-scroll {
    max-height: 220px;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}

#householdModal .hh-edit-members-table {
    font-size: 0.8125rem;
}

#householdModal .hh-edit-members-table th {
    white-space: nowrap;
}

#householdModal .hh-edit-members-table td.household-detail-actions {
    vertical-align: middle;
    width: 1%;
    text-align: end;
}

#householdModal .hh-edit-members-table td.household-detail-actions .action-icon-btn + .action-icon-btn {
    margin-left: 0.35rem;
}

#householdModal #editHouseholdAddMemberCollapse {
    background: #f8fafc;
}
</style>

<!-- Filter Modal -->
<div class="modal fade" id="hhFilterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Households</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">No. of Members</label>
                    <select class="form-select" id="hhModalFilterMemberRange">
                        <option value="">All</option>
                        <option value="1">1</option>
                        <option value="2-4">2-4</option>
                        <option value="5-7">5-7</option>
                        <option value="8+">8+</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">House Type</label>
                    <select class="form-select" id="hhModalFilterHouseType">
                        <option value="">All</option>
                        <option value="concrete">Concrete</option>
                        <option value="semi_concrete">Semi-Concrete</option>
                        <option value="light_materials">Light Materials</option>
                        <option value="apartment_boarding">Apartment / Boarding House</option>
                        <option value="townhouse_row">Townhouse / Row House</option>
                        <option value="informal_improvised">Informal / Improvised</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="hhModalFilterIndigentStatus">
                        <option value="">All</option>
                        <option value="indigent">Indigent</option>
                        <option value="non_indigent">Non-indigent</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Years of Residency</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="hhModalFilterResidencyFrom" placeholder="From (years)" min="0" step="0.5">
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="hhModalFilterResidencyTo" placeholder="To (years)" min="0" step="0.5">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" id="hhModalFilterSortBy">
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="members_desc">Members: High to Low</option>
                        <option value="members_asc">Members: Low to High</option>
                    </select>
                </div>
                <div class="mb-0">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hhModalFilterWithSenior">
                        <label class="form-check-label" for="hhModalFilterWithSenior">With Senior Citizen</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hhModalFilterWithMinors">
                        <label class="form-check-label" for="hhModalFilterWithMinors">With Minors</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hhModalFilterSingleOccupant">
                        <label class="form-check-label" for="hhModalFilterSingleOccupant">Single Occupant Household</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hhModalFilterWithRegisteredVoters">
                        <label class="form-check-label" for="hhModalFilterWithRegisteredVoters">With Registered Voters</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hhModalFilterAllMembersVerified">
                        <label class="form-check-label" for="hhModalFilterAllMembersVerified">All Members Verified</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="hhModalFilterWithMissingInfo">
                        <label class="form-check-label" for="hhModalFilterWithMissingInfo">With Missing Information</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyHouseholdFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="householdModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="householdModalTitle">Add New Household</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="householdForm">
                <div class="modal-body">
                    <input type="hidden" id="householdId" name="id">
                    <input type="hidden" id="family_head_id" name="family_head_id" value="">

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <strong><i class="bi bi-geo-alt me-2"></i>Address Information</strong>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">House No.</label>
                                    <input type="text" class="form-control" id="house_number" name="house_number" placeholder="e.g., 123">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">House Type</label>
                                    <select class="form-select" id="house_type" name="house_type">
                                        <option value="">Select House Type</option>
                                        <option value="concrete">Concrete</option>
                                        <option value="semi_concrete">Semi-Concrete</option>
                                        <option value="light_materials">Light Materials</option>
                                        <option value="apartment_boarding">Apartment / Boarding House</option>
                                        <option value="townhouse_row">Townhouse / Row House</option>
                                        <option value="informal_improvised">Informal / Improvised</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="street">Street</label>
                                    <select class="form-select" id="street" name="street">
                                        <option value="">— Select street —</option>
                                        <?php foreach ($hh_street_options as $st) {
                                            if (!is_string($st)) {
                                                continue;
                                            }
                                            $st = trim($st);
                                            if ($st === '') {
                                                continue;
                                            }
                                            echo '<option value="' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . "</option>\n";
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="house_ownership">House Ownership</label>
                                    <select class="form-select" id="house_ownership" name="house_ownership">
                                        <option value="">Select ownership</option>
                                        <option value="owned">Owned</option>
                                        <option value="rented">Rented</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Barangay</label>
                                    <input type="text" class="form-control" value="219" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Zone</label>
                                    <input type="text" class="form-control" value="20" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">District</label>
                                    <input type="text" class="form-control" value="II" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" value="Manila" readonly>
                                </div>
                                <div class="col-12 mt-2">
                                    <label for="address" class="form-label">Full Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2" readonly onfocus="this.blur()"></textarea>
                                </div>
                                <div class="col-md-4" id="totalMembersGroup">
                                    <label for="total_members" class="form-label">Total Members</label>
                                    <input type="number" min="0" class="form-control" id="total_members" name="total_members" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label for="registration_date" class="form-label">Registration Date</label>
                                    <input type="date" class="form-control" id="registration_date" name="registration_date">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="editHouseholdMembersCard" class="card border-0 shadow-sm mb-3 d-none">
                        <div class="card-header bg-light d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-3">
                            <strong class="mb-0"><i class="bi bi-people me-2"></i>Household members</strong>
                            <button type="button" class="btn btn-sm btn-primary" id="btnToggleAddMemberEdit" data-bs-toggle="collapse" data-bs-target="#editHouseholdAddMemberCollapse" aria-expanded="false" aria-controls="editHouseholdAddMemberCollapse">
                                <i class="bi bi-person-plus me-1"></i>Add member
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div id="editHouseholdMembersTableWrap" class="hh-edit-members-scroll">
                                <div id="editHouseholdMembersHost" class="px-2 py-2 px-md-3"></div>
                            </div>
                            <p class="text-muted small mb-0 p-3 d-none" id="editHouseholdMembersEmpty">No members recorded yet.</p>
                            <div class="collapse border-top" id="editHouseholdAddMemberCollapse">
                                <div class="p-3 hh-add-member-inline">
                                    <fieldset id="editHouseholdAddMemberFieldset" class="border-0 p-0 m-0 min-w-0">
                                        <legend class="visually-hidden">Add resident to household</legend>
                                        <label for="addMemberResidentSearch" class="form-label">Find resident</label>
                                        <div class="input-group mb-3">
                                            <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                                            <input type="search" class="form-control" id="addMemberResidentSearch" placeholder="Search by name or resident code…" autocomplete="off">
                                        </div>
                                        <label for="addMemberResidentEdit" class="form-label">Resident</label>
                                        <select class="form-select mb-3" id="addMemberResidentEdit" aria-label="Choose resident to add">
                                            <option value="">— Choose a resident —</option>
                                        </select>
                                        <div class="mb-3" id="addMemberRelationshipGroup">
                                            <label for="addMemberRelationshipToHead" class="form-label">Relationship to head</label>
                                            <select class="form-select" id="addMemberRelationshipToHead">
                                                <option value="">Select relationship</option>
                                                <option value="Spouse">Spouse</option>
                                                <option value="Son">Son</option>
                                                <option value="Daughter">Daughter</option>
                                                <option value="Mother">Mother</option>
                                                <option value="Father">Father</option>
                                                <option value="Brother">Brother</option>
                                                <option value="Sister">Sister</option>
                                                <option value="Grandchild">Grandchild</option>
                                                <option value="Grandparent">Grandparent</option>
                                                <option value="Son-in-Law">Son-in-Law</option>
                                                <option value="Daughter-in-Law">Daughter-in-Law</option>
                                                <option value="Sibling-in-Law">Sibling-in-Law</option>
                                                <option value="Nephew">Nephew</option>
                                                <option value="Niece">Niece</option>
                                                <option value="Uncle">Uncle</option>
                                                <option value="Aunt">Aunt</option>
                                                <option value="Cousin">Cousin</option>
                                                <option value="Boarder">Boarder</option>
                                                <option value="Tenant">Tenant</option>
                                                <option value="Helper">Helper</option>
                                                <option value="Non-Relative">Non-Relative</option>
                                                <option value="Other">Other</option>
                                                <option value="Relative">Relative</option>
                                                <option value="Member">Member</option>
                                            </select>
                                            <p class="small text-muted mb-0 mt-1 d-none" id="addMemberRelationshipHeadNote">This resident will be set as the household head; relationship is recorded as Head.</p>
                                        </div>
                                    </fieldset>
                                    <button type="button" class="btn btn-primary mt-2" id="btnAddMemberEdit">
                                        <i class="bi bi-person-plus me-1"></i>Add to household
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted border-top pt-2 mt-3 mb-0 px-1"><i class="bi bi-info-circle me-1"></i>Address, registration, and other fields above, and residents you add with <strong>Add member</strong>, are only written to the server when you click <strong>Save household details</strong>.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save household details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast container for success messages -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="householdToastContainer" style="z-index: 9999;"></div>

<!-- Remove Member Confirmation Modal -->
<div class="modal fade" id="removeMemberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-dash me-2"></i>Remove Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Remove this member from household?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="removeMemberConfirmBtn" onclick="confirmRemoveMember()"><i class="bi bi-person-dash me-1"></i> Remove</button>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Head Confirmation Modal -->
<div class="modal fade" id="transferHeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Transfer Head Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Transfer head role to this member? The current head will become a member.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="transferHeadConfirmBtn" onclick="confirmTransferHead()"><i class="bi bi-person-badge me-1"></i> Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- View Household Modal -->
<div class="modal fade" id="viewHouseholdModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered view-household-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Household Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewHouseholdInfo"></div>
                <hr>
                <div id="viewHouseholdMembers"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
if (typeof window.API_URL === 'undefined') window.API_URL = '<?php echo addslashes(API_URL); ?>';
if (typeof window.BASE_URL === 'undefined') window.BASE_URL = '<?php echo addslashes(BASE_URL); ?>';
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/households.js?v=<?php echo time(); ?>"></script>
