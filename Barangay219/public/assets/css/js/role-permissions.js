/**
 * E-Barangay Information Management System
 * Role & Permission Management JavaScript
 */

if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

const ROLE_OPTIONS = [
    { value: 'barangay_captain', label: 'Barangay Captain', icon: 'bi-shield-fill-check' },
    { value: 'secretary', label: 'Secretary', icon: 'bi-journal-text' },
    { value: 'treasurer', label: 'Treasurer', icon: 'bi-cash-coin' },
    { value: 'kagawad', label: 'Kagawad', icon: 'bi-people-fill' },
    { value: 'sk_chairman', label: 'SK Chairman', icon: 'bi-award' }
];

const MODULES = [
    { id: 'dashboard', keys: ['dashboard'], label: 'Dashboard' },
    { id: 'certificates_bundle', keys: ['applications', 'certificates'], label: 'Certificates (Applications / Records)' },
    { id: 'resident_applications', keys: ['resident_applications'], label: 'Resident Applications' },
    { id: 'residents', keys: ['residents'], label: 'Residents' },
    { id: 'households', keys: ['households'], label: 'Households' },
    { id: 'blotters', keys: ['blotters'], label: 'Blotters' },
    { id: 'complaints', keys: ['complaints'], label: 'Complaints' },
    { id: 'announcements', keys: ['announcements'], label: 'Announcements' },
    { id: 'reports', keys: ['reports'], label: 'Reports' },
    { id: 'users', keys: ['users'], label: 'Users' }
];

const FIXED_ROLES = new Set(['super_admin', 'barangay_captain']);
let currentPermissionsMode = 'official';
let selectedOfficial = null;
let selectedOfficialHasCustom = false;

document.addEventListener('DOMContentLoaded', function() {
    initPermissionsUI();
});

function initPermissionsUI() {
    const roleSelect = document.getElementById('permissionsRole');
    const roleIcons = document.getElementById('permissionsRoleIcons');
    const moduleTiles = document.getElementById('permissionsModuleTiles');
    if (!roleSelect || !roleIcons || !moduleTiles) {
        return;
    }

    roleSelect.innerHTML = ROLE_OPTIONS.map(role =>
        `<option value="${role.value}">${role.label}</option>`
    ).join('');

    roleIcons.innerHTML = ROLE_OPTIONS.map(role => `
        <button type="button" class="permissions-role-icon" data-role="${role.value}" title="${role.label}" aria-label="${role.label}" aria-pressed="false">
            <i class="bi ${role.icon}"></i>
            <span>${role.label}</span>
        </button>
    `).join('');

    if (!roleSelect.value && ROLE_OPTIONS.length > 0) {
        roleSelect.value = ROLE_OPTIONS[0].value;
    }

    renderPermissionsTiles();
    roleSelect.addEventListener('change', function() {
        syncPermissionsRoleIcons(roleSelect.value);
        if (currentPermissionsMode === 'role') {
            loadRolePermissions();
        }
    });

    roleIcons.addEventListener('click', function(e) {
        const btn = e.target.closest('.permissions-role-icon');
        if (!btn) return;
        const role = btn.getAttribute('data-role');
        selectPermissionsRole(role);
    });

    roleIcons.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const btn = e.target.closest('.permissions-role-icon');
        if (!btn) return;
        e.preventDefault();
        const role = btn.getAttribute('data-role');
        selectPermissionsRole(role);
    });

    syncPermissionsRoleIcons(roleSelect.value);
    initPermissionsModeUI();
    loadOfficialsForPermissions();

    const params = new URLSearchParams(window.location.search);
    if (params.get('mode') === 'role') {
        setPermissionsMode('role');
    } else {
        setPermissionsMode('official');
    }
}

function initPermissionsModeUI() {
    const tabsWrap = document.getElementById('permissionsModeTabs');
    const officialSelect = document.getElementById('officialAccountSelect');
    const clearBtn = document.getElementById('clearUserPermissionsBtn');
    if (!tabsWrap) return;

    tabsWrap.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-mode]');
        if (!btn) return;
        setPermissionsMode(btn.getAttribute('data-mode') || 'official');
    });

    if (officialSelect) {
        officialSelect.addEventListener('change', onOfficialChange);
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearUserPermissions();
        });
    }
}

function setPermissionsMode(mode) {
    currentPermissionsMode = mode === 'role' ? 'role' : 'official';
    const perOfficialBtn = document.getElementById('tabPerOfficial');
    const roleDefaultsBtn = document.getElementById('tabRoleDefaults');
    const officialWrap = document.getElementById('officialSelectorWrap');
    const roleDefaultsSelectorWrap = document.getElementById('roleDefaultsSelectorWrap');
    const roleIcons = document.getElementById('permissionsRoleIcons');

    if (perOfficialBtn) perOfficialBtn.classList.toggle('active', currentPermissionsMode === 'official');
    if (roleDefaultsBtn) roleDefaultsBtn.classList.toggle('active', currentPermissionsMode === 'role');
    if (officialWrap) officialWrap.style.display = currentPermissionsMode === 'official' ? '' : 'none';
    if (roleDefaultsSelectorWrap) roleDefaultsSelectorWrap.style.display = currentPermissionsMode === 'official' ? 'none' : '';
    if (roleIcons) {
        roleIcons.style.opacity = currentPermissionsMode === 'official' ? '0.55' : '1';
        roleIcons.style.pointerEvents = currentPermissionsMode === 'official' ? 'none' : '';
    }

    updateActionButtons();
    if (currentPermissionsMode === 'official') {
        onOfficialChange();
    } else {
        loadRolePermissions();
    }
}

function loadOfficialsForPermissions() {
    const select = document.getElementById('officialAccountSelect');
    if (!select) return;

    fetch(`${window.API_URL}role_permissions.php?action=list_official_users`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.data)) {
                showAlert('error', data.message || 'Failed to load official users');
                return;
            }
            const users = data.data;
            select.innerHTML = '<option value="">Select official account</option>' + users.map(u => {
                const marker = u.has_custom_permissions ? ' (custom)' : '';
                return `<option value="${u.user_id}" data-role="${escapeHtml(u.role || '')}" data-custom="${u.has_custom_permissions ? '1' : '0'}">${escapeHtml(u.full_name || u.username || 'Unknown')} — ${escapeHtml(formatRoleLabel(u.role))}${marker}</option>`;
            }).join('');

            const params = new URLSearchParams(window.location.search);
            const requestedUserId = Number(params.get('user_id') || 0);
            if (requestedUserId > 0) {
                const idx = Array.from(select.options).findIndex(opt => Number(opt.value) === requestedUserId);
                if (idx > 0) {
                    select.selectedIndex = idx;
                } else if (users.length > 0) {
                    select.selectedIndex = 1;
                }
            } else if (users.length > 0) {
                select.selectedIndex = 1;
            }
            if (users.length > 0) {
                onOfficialChange();
            }
        })
        .catch(() => showAlert('error', 'Error loading official users'));
}

function onOfficialChange() {
    if (currentPermissionsMode !== 'official') return;
    const select = document.getElementById('officialAccountSelect');
    const roleSelect = document.getElementById('permissionsRole');
    const info = document.getElementById('officialRoleInfo');
    if (!select || !roleSelect) return;

    const option = select.options[select.selectedIndex];
    if (!option || !option.value) {
        selectedOfficial = null;
        selectedOfficialHasCustom = false;
        if (info) info.textContent = 'Select an official account.';
        updateActionButtons();
        return;
    }

    selectedOfficial = {
        user_id: Number(option.value),
        role: String(option.getAttribute('data-role') || '').toLowerCase(),
    };
    roleSelect.value = selectedOfficial.role;
    syncPermissionsRoleIcons(selectedOfficial.role);
    if (selectedOfficial.role === 'barangay_captain') {
        selectedOfficialHasCustom = false;
        if (info) {
            info.textContent = 'Barangay Captain permissions are fixed and cannot be edited.';
        }
        loadRolePermissions();
        updateActionButtons();
        return;
    }

    loadSelectedUserPermissions();
}

function loadSelectedUserPermissions() {
    const info = document.getElementById('officialRoleInfo');
    if (!selectedOfficial || !selectedOfficial.user_id) {
        return;
    }

    fetch(`${window.API_URL}role_permissions.php?action=get_user_permissions&user_id=${encodeURIComponent(selectedOfficial.user_id)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Failed to load user permissions');
                return;
            }
            const payload = data.data || {};
            selectedOfficialHasCustom = !!payload.has_custom_permissions;
            applyPermissionsToTiles(payload.permissions || {});
            if (info) {
                const roleLabel = formatRoleLabel(payload.role || selectedOfficial.role);
                info.textContent = selectedOfficialHasCustom
                    ? `Custom override active for ${roleLabel}.`
                    : `Using role default permissions for ${roleLabel}. Save to create override.`;
            }
            updateActionButtons();
            applyPermissionsLock(payload.role || selectedOfficial.role);
        })
        .catch(() => showAlert('error', 'Error loading user permissions'));
}

function renderPermissionsTiles() {
    const container = document.getElementById('permissionsModuleTiles');
    if (!container) return;

    container.innerHTML = MODULES.map(m => `
        <div class="permission-module-tile access-only" data-module="${m.id}">
            <div class="module-tile-head">
                <div class="module-tile-title">
                    <i class="bi bi-grid-1x2 me-2"></i><span>${m.label}</span>
                </div>
            </div>
            <div class="module-tile-body access-only">
                <div class="perm-row">
                    <label class="perm-label" for="perm_${m.id}_access_rolePage">Access</label>
                    <input id="perm_${m.id}_access_rolePage" type="checkbox" class="form-check-input perm-checkbox perm-toggle" data-module="${m.id}" data-perm="can_access">
                </div>
            </div>
        </div>
    `).join('');
}

function loadRolePermissions() {
    const roleSelect = document.getElementById('permissionsRole');
    if (!roleSelect) return;
    const role = roleSelect.value;

    fetch(`${window.API_URL}role_permissions.php?action=get_permissions&role=${encodeURIComponent(role)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Failed to load permissions');
                return;
            }
            applyPermissionsToTiles(data.data.permissions || {});
            applyPermissionsLock(role);
            updateActionButtons();
        })
        .catch(() => showAlert('error', 'Error loading permissions'));
}

function applyPermissionsToTiles(permissions) {
    MODULES.forEach(module => {
        const box = document.querySelector(`input[data-module="${module.id}"][data-perm="can_access"]`);
        if (!box) return;
        const accessValues = (module.keys || []).map(k => !!(permissions?.[k]?.can_access));
        box.checked = accessValues.length ? accessValues.every(Boolean) : false;
    });
}

function buildPermissionsPayloadFromTiles() {
    const permissions = {};
    MODULES.forEach(module => {
        const box = document.querySelector(`input[data-module="${module.id}"][data-perm="can_access"]`);
        const enabled = !!(box && box.checked);
        (module.keys || []).forEach(k => {
            permissions[k] = { can_access: enabled };
        });
    });
    return permissions;
}

function saveRolePermissions() {
    const roleSelect = document.getElementById('permissionsRole');
    if (!roleSelect) return;

    const role = String(roleSelect.value || '').trim().toLowerCase();
    const permissions = buildPermissionsPayloadFromTiles();

    if (currentPermissionsMode === 'official') {
        if (!selectedOfficial || !selectedOfficial.user_id) {
            showAlert('error', 'Select an official account first.');
            return;
        }
        if (selectedOfficial.role === 'barangay_captain') {
            showAlert('error', 'Barangay Captain permissions are fixed and cannot be edited.');
            return;
        }
        fetch(window.API_URL + 'role_permissions.php?action=save_user_permissions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: selectedOfficial.user_id, permissions })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    selectedOfficialHasCustom = true;
                    updateActionButtons();
                    showAlert('success', data.message || 'Custom permissions saved');
                    loadOfficialsForPermissions();
                } else {
                    showAlert('error', data.message || 'Failed to save custom permissions');
                }
            })
            .catch(() => showAlert('error', 'Error saving custom permissions'));
        return;
    }

    if (FIXED_ROLES.has(role)) {
        showAlert('error', `${formatRoleLabel(role)} permissions are fixed and cannot be edited`);
        return;
    }

    fetch(window.API_URL + 'role_permissions.php?action=save_permissions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role, permissions })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message || 'Permissions saved');
            } else {
                showAlert('error', data.message || 'Failed to save permissions');
            }
        })
        .catch(() => showAlert('error', 'Error saving permissions'));
}

function clearUserPermissions() {
    if (currentPermissionsMode !== 'official' || !selectedOfficial || !selectedOfficial.user_id) {
        return;
    }
    if (selectedOfficial.role === 'barangay_captain') {
        showAlert('error', 'Barangay Captain permissions are fixed and cannot be edited.');
        return;
    }
    if (!confirm('Clear custom permissions and use role defaults for this official?')) {
        return;
    }
    fetch(window.API_URL + 'role_permissions.php?action=clear_user_permissions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: selectedOfficial.user_id })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Failed to clear custom permissions');
                return;
            }
            selectedOfficialHasCustom = false;
            showAlert('success', data.message || 'Custom permissions cleared');
            loadSelectedUserPermissions();
            loadOfficialsForPermissions();
        })
        .catch(() => showAlert('error', 'Error clearing custom permissions'));
}

function applyPermissionsLock(role) {
    const normalizedRole = String(role || '').trim().toLowerCase();
    const isRoleModeFixedRole = currentPermissionsMode === 'role' && FIXED_ROLES.has(normalizedRole);
    const isPerOfficialCaptainLocked = currentPermissionsMode === 'official' && normalizedRole === 'barangay_captain';
    const isLocked = isRoleModeFixedRole || isPerOfficialCaptainLocked;
    const boxes = document.querySelectorAll('#permissionsModuleTiles .perm-checkbox');
    boxes.forEach(box => {
        box.disabled = isLocked;
        if (isLocked) {
            box.checked = true;
        }
    });
}

function updateActionButtons() {
    const saveBtn = document.getElementById('savePermissionsBtn');
    const clearBtn = document.getElementById('clearUserPermissionsBtn');
    const role = String((document.getElementById('permissionsRole') || {}).value || '').trim().toLowerCase();

    if (saveBtn) {
        if (currentPermissionsMode === 'official') {
            const captainLocked = !!selectedOfficial && selectedOfficial.role === 'barangay_captain';
            saveBtn.disabled = !selectedOfficial || !selectedOfficial.user_id || captainLocked;
            saveBtn.innerHTML = '<i class="bi bi-save"></i> Save Custom';
        } else {
            saveBtn.disabled = FIXED_ROLES.has(role);
            saveBtn.innerHTML = '<i class="bi bi-save"></i> Save';
        }
    }

    if (clearBtn) {
        clearBtn.style.display = 'none';
        clearBtn.disabled = true;
    }
}

function selectPermissionsRole(role) {
    const roleSelect = document.getElementById('permissionsRole');
    if (!roleSelect || !role) return;
    if (roleSelect.value === role) return;
    roleSelect.value = role;
    roleSelect.dispatchEvent(new Event('change'));
}

function syncPermissionsRoleIcons(activeRole) {
    document.querySelectorAll('#permissionsRoleIcons .permissions-role-icon').forEach(btn => {
        const isActive = btn.getAttribute('data-role') === activeRole;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
    alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
    setTimeout(() => alertDiv.remove(), 5000);
}

function formatRoleLabel(role) {
    const normalized = String(role || '').trim().toLowerCase();
    if (!normalized) return 'Unknown';
    return normalized.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}
