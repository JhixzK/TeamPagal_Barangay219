<?php
/**
 * E-Barangay Information Management System
 * Role & Permission Management Page
 */

define('ACCESS_ALLOWED', true);
$page_title = 'Role & Permission Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('users');
if (!isSystemAdmin()) {
    header('Location: ' . BASE_URL . 'home.php?error=access_denied');
    exit();
}

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Administration Module</p>
                    <h2 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Role & Permission Management</h2>
                    <p class="module-subtitle mb-0">Manage role access and permission controls.</p>
                </div>
            </div>
        </div>

        <div class="card role-permissions-card" id="rolePermissionsPanel">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Role Permissions (Access Only)</h5>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="clearUserPermissionsBtn" onclick="clearUserPermissions()" style="display:none;">
                        <i class="bi bi-arrow-counterclockwise"></i> Use Role Defaults
                    </button>
                    <button class="btn btn-sm btn-primary" id="savePermissionsBtn" onclick="saveRolePermissions()">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <ul class="nav nav-pills gap-2" id="permissionsModeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="btn btn-sm btn-outline-primary active" type="button" id="tabPerOfficial" data-mode="official">Per official</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="btn btn-sm btn-outline-primary" type="button" id="tabRoleDefaults" data-mode="role">Role defaults</button>
                        </li>
                    </ul>
                </div>

                <div class="row g-3 align-items-end mb-3" id="officialSelectorWrap">
                    <div class="col-md-8">
                        <label class="form-label" for="officialAccountSelect">Official</label>
                        <select class="form-select" id="officialAccountSelect">
                            <option value="">Select official account</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block" id="officialRoleInfo">Using role defaults</small>
                    </div>
                </div>

                <div class="row g-3 align-items-end mb-3" id="roleDefaultsSelectorWrap">
                    <div class="col-12">
                        <label class="form-label" for="permissionsRole">Role</label>
                        <select class="form-select d-none" id="permissionsRole"></select>
                        <div id="permissionsRoleIcons" class="permissions-role-icons" role="group" aria-label="Select role"></div>
                    </div>
                </div>
                <div id="permissionsModuleTiles" class="permission-module-tiles access-only"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/role-permissions.js?v=<?php echo time(); ?>"></script>
