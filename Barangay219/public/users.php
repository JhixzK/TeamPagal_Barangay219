<?php
/**
 * E-Barangay Information Management System
 * User Management Page
 */

define('ACCESS_ALLOWED', true);
$page_title = 'User Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('users');

include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.users-page .users-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.users-page .users-table {
    margin-bottom: 0;
}

.users-page .users-table-scroll {
    overflow-x: auto;
    overflow-y: visible;
}

.users-page .users-table thead th {
    border: 0;
    border-bottom: 1px solid #e9eef5;
    background: #f8fafc;
    color: #4b5563;
    font-weight: 600;
    letter-spacing: 0.01em;
    font-size: 0.78rem;
    text-transform: uppercase;
    padding: 0.85rem 0.75rem;
    white-space: nowrap;
}

.users-page .users-table tbody td {
    border: 0;
    border-bottom: 1px solid #eef2f7;
    padding: 0.95rem 0.75rem;
    vertical-align: middle;
}

.users-page .users-table tbody tr:last-child td {
    border-bottom: 0;
}

.users-page .users-main-text {
    color: #1f2937;
    font-weight: 500;
}

.users-page .users-subtext {
    color: #6b7280;
    font-size: 0.82rem;
}

.users-page .users-code-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.52rem;
    border-radius: 999px;
    background: #f3f6fb;
    border: 1px solid #e2e8f0;
    color: #1f2937;
    font-size: 0.74rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.users-page .user-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.22rem 0.56rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 600;
    border: 1px solid transparent;
}

.users-page .role-pill {
    background: #e8f3ff;
    border-color: #cfe5ff;
    color: #1d4f91;
}

.users-page .status-active {
    background: #eafaf2;
    border-color: #c9f0dc;
    color: #1f7a4f;
}

.users-page .status-inactive {
    background: #f3f4f6;
    border-color: #e5e7eb;
    color: #4b5563;
}

.users-page .status-suspended {
    background: #fff1f2;
    border-color: #ffd7dd;
    color: #b4233f;
}

.users-page .status-unknown {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #475569;
}

.users-page .users-actions {
    white-space: nowrap;
}

.users-page .action-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #dbe3ee;
    background: #ffffff;
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.16s ease;
}

.users-page .action-icon-btn:hover {
    border-color: #c2cfdf;
    background: #f8fafc;
    color: #1f2937;
}

.users-page .action-icon-btn.btn-activate {
    color: #1f7a4f;
    border-color: #c9f0dc;
    background: #f3fcf7;
}

.users-page .action-icon-btn.btn-activate:hover {
    background: #e8f8f0;
    border-color: #aadfca;
}

.users-page .action-icon-btn.btn-suspend {
    color: #9a3412;
    border-color: #fed7aa;
    background: #fff7ed;
}

.users-page .action-icon-btn.btn-suspend:hover {
    background: #ffedd5;
    border-color: #fdba74;
}

.users-page .action-icon-btn.btn-delete:hover {
    border-color: #f0a9b4;
    background: #fff1f2;
    color: #b4233f;
}
</style>

<div class="main-content module-page users-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Administration Module</p>
                    <h2 class="mb-1"><i class="bi bi-person-gear me-2"></i>User Management</h2>
                    <p class="module-subtitle mb-0">Manage user accounts, role assignment, and account status.</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                        <i class="bi bi-plus-circle"></i> Add New User
                    </button>
                </div>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name, username, or email...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchUsers()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetUsers()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="data-table users-table-wrap">
            <div class="table-responsive users-table-scroll">
                <table class="table table-hover users-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">Username</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Created</th>
                            <th class="text-center users-actions-col actions-col-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-2 mb-1 px-2 py-2 border-top bg-white" id="usersPaginationOuter" style="display:none;" aria-label="User list pages">
                <div id="usersPagination" role="group"></div>
            </div>
        </div>

    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Users</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" id="filterRole">
                        <option value="">All</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="barangay_captain">Barangay Captain</option>
                        <option value="secretary">Secretary</option>
                        <option value="treasurer">Treasurer</option>
                        <option value="kagawad">Kagawad</option>
                        <option value="sk_chairman">SK Chairman</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyUserFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="userId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="passwordField">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                            <small class="text-muted">Required for new accounts</small>
                        </div>
                    </div>

                    <div class="row d-none" id="changePasswordSection">
                        <div class="col-12 mb-3">
                            <label class="form-label fw-semibold"><i class="bi bi-key me-1"></i>Change password</label>
                            <p class="small text-muted mb-2">Leave both fields blank to keep the current password.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="new_password" class="form-label">New password</label>
                                    <input type="password" class="form-control" id="new_password" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm new password</label>
                                    <input type="password" class="form-control" id="confirm_password" autocomplete="new-password">
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">Use letters and numbers only; length must meet system rules.</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="super_admin">Super Admin</option>
                                <option value="barangay_captain">Barangay Captain</option>
                                <option value="secretary">Secretary</option>
                                <option value="treasurer">Treasurer</option>
                                <option value="kagawad">Kagawad</option>
                                <option value="sk_chairman">SK Chairman</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="row" id="adminTwoFactorRow" style="display:none;">
                        <div class="col-12 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="two_factor_enabled" name="two_factor_enabled" value="1">
                                <label class="form-check-label" for="two_factor_enabled">Require email verification code at login</label>
                            </div>
                            <small class="text-muted d-block mt-1">User must have a valid email. Super Admin / Secretary can disable this if someone is locked out.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
window.CURRENT_USER_ID = <?php echo (int)getCurrentUserId(); ?>;
window.IS_ADMIN = <?php echo isSystemAdmin() ? 'true' : 'false'; ?>;
window.CAN_MANAGE_USER_2FA = <?php echo (isSuperAdmin() || hasRole(ROLE_SECRETARY)) ? 'true' : 'false'; ?>;
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/users.js?v=<?php echo time(); ?>"></script>
