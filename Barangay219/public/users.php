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

<div class="main-content module-page users-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Administration Module</p>
                    <h2 class="mb-1"><i class="bi bi-person-gear me-2"></i>User Management</h2>
                    <p class="module-subtitle mb-0">Manage user accounts, access roles, status, and permission controls.</p>
                </div>
                <div>
                    <button class="btn btn-outline-secondary me-2" onclick="showActivityLogs()">
                        <i class="bi bi-clock-history"></i> Activity Logs
                    </button>
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
        <div class="data-table">
            <div class="table-responsive users-table-scroll">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th class="text-center">Username</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activity Logs Panel -->
        <div class="card mt-4" id="activityLogsPanel" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> User Activity Logs</h5>
                <button class="btn btn-sm btn-secondary" onclick="document.getElementById('activityLogsPanel').style.display='none'">Close</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>User</th><th>Action</th><th>Module</th><th>Date</th><th>IP</th></tr></thead>
                        <tbody id="activityLogsBody"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Role Permissions Panel -->
        <div class="card mt-4 role-permissions-card" id="rolePermissionsPanel">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Role Permissions (Access Only)</h5>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-primary" id="savePermissionsBtn" onclick="saveRolePermissions()">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-12">
                        <label class="form-label" for="permissionsRole">Role</label>
                        <select class="form-select d-none" id="permissionsRole"></select>
                        <div id="permissionsRoleIcons" class="permissions-role-icons" role="group" aria-label="Select role"></div>
                    </div>
                </div>
                <div id="permissionsModuleTiles" class="permission-module-tiles access-only"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
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
    <div class="modal-dialog modal-lg">
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
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Leave blank to keep current password</small>
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
                            <label for="resident_id" class="form-label">Resident ID (Optional)</label>
                            <input type="number" class="form-control" id="resident_id" name="resident_id">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
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
window.IS_ADMIN = <?php echo isAdmin() ? 'true' : 'false'; ?>;
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/users.js?v=<?php echo time(); ?>"></script>
