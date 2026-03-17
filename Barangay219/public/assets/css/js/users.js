/**
 * E-Barangay Information Management System
 * User Management JavaScript
 */

// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

const ROLE_OPTIONS = [
    { value: 'barangay_captain', label: 'Barangay Captain', icon: 'bi-shield-fill-check' },
    { value: 'secretary', label: 'Secretary', icon: 'bi-journal-text' },
    { value: 'treasurer', label: 'Treasurer', icon: 'bi-cash-coin' },
    { value: 'kagawad', label: 'Kagawad', icon: 'bi-people-fill' },
    { value: 'sk_chairman', label: 'SK Chairman', icon: 'bi-stars' }
];

// Role permission modules shown in the UI.
// Notes:
// - These keys must match the strings used in PHP `requireModuleAccess('<key>')`.
// - "Certificates" UI tile maps to both `applications` and `certificates` since the sidebar/page access treats them as a bundle.
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

let userFilters = { q: '', role: '', status: '' };
const USER_MANAGEMENT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('users', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('users', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('users', 'can_delete') : true
};

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    initPermissionsUI();
    applyUsersPagePermissions();
    
    // Form submission
    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveUser();
    });
});

/**
 * Load all users
 */
function loadUsers() {
    const params = new URLSearchParams({ action: 'list' });
    if (userFilters.q) params.append('q', userFilters.q);
    if (userFilters.role) params.append('role', userFilters.role);
    if (userFilters.status) params.append('status', userFilters.status);

    fetch(window.API_URL + 'users.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUsers(data.data);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error loading users');
        });
}

function searchUsers() {
    const query = document.getElementById('searchInput')?.value.trim() || '';
    userFilters.q = query;
    loadUsers();
}

function applyUserFilters() {
    userFilters.role = document.getElementById('filterRole')?.value || '';
    userFilters.status = document.getElementById('filterStatus')?.value || '';
    loadUsers();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetUsers() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    userFilters = { q: '', role: '', status: '' };
    const roleSel = document.getElementById('filterRole');
    const statusSel = document.getElementById('filterStatus');
    if (roleSel) roleSel.value = '';
    if (statusSel) statusSel.value = '';
    loadUsers();
}

/**
 * Display users in table
 */
function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No users found</td></tr>';
        return;
    }

    const statusRank = status => {
        const s = String(status || '').toLowerCase();
        if (s === 'active') return 0;
        if (s === 'inactive') return 2;
        return 1;
    };

    const sortedUsers = users
        .map((user, idx) => ({ user, idx }))
        .sort((a, b) => {
            const ra = statusRank(a.user.status);
            const rb = statusRank(b.user.status);
            if (ra !== rb) return ra - rb;
            return a.idx - b.idx;
        })
        .map(item => item.user);

    tbody.innerHTML = sortedUsers.map(user => `
        <tr>
            <td class="text-center">${user.id}</td>
            <td class="text-center">${escapeHtml(user.username)}</td>
            <td class="text-center">${escapeHtml(user.full_name || '-')}</td>
            <td class="text-center">${escapeHtml(user.email || '-')}</td>
            <td class="text-center"><span class="badge bg-info">${formatRole(user.role)}</span></td>
            <td class="text-center"><span class="badge ${getStatusClass(user.status)}">${formatStatus(user.status)}</span></td>
            <td class="text-center">${formatDate(user.created_at)}</td>
            <td class="text-center">
                ${USER_MANAGEMENT_PERMS.canEdit ? `
                    <button class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit" onclick="editUser(${user.id})">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    ${user.status === 'active' 
                        ? `<button class="btn btn-sm btn-warning" title="Suspend" aria-label="Suspend" onclick="suspendUser(${user.id})">
                            <i class="bi bi-pause-circle"></i>
                           </button>`
                        : `<button class="btn btn-sm btn-success" title="Activate" aria-label="Activate" onclick="activateUser(${user.id})">
                            <i class="bi bi-play-circle"></i>
                           </button>`
                    }
                ` : ''}
                ${USER_MANAGEMENT_PERMS.canDelete && user.id !== (window.CURRENT_USER_ID || 0)
                    ? `<button class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete" onclick="deleteUser(${user.id})">
                        <i class="bi bi-trash"></i>
                       </button>`
                    : ''}
                ${!USER_MANAGEMENT_PERMS.canEdit && !USER_MANAGEMENT_PERMS.canDelete ? '<span class="text-muted">View only</span>' : ''}
            </td>
        </tr>
    `).join('');
}

function applyUsersPagePermissions() {
    const addBtn = document.querySelector('[data-bs-target="#userModal"]');
    if (addBtn && !USER_MANAGEMENT_PERMS.canCreate) {
        addBtn.style.display = 'none';
    }
}

/**
 * Edit user
 */
function editUser(id) {
    if (!USER_MANAGEMENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }

    fetch(`${window.API_URL}users.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.data;
                const currentRole = String(user.role || '').trim().toLowerCase();
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email || '';
                setRoleSelectValue(user.role);
                document.getElementById('resident_id').value = user.resident_id || '';
                document.getElementById('status').value = user.status;
                document.getElementById('password').required = false;
                togglePasswordField(false);
                document.getElementById('userModalTitle').textContent = 'Edit User';

                toggleEditFieldLocks(true);

                const roleSelect = document.getElementById('role');
                if (roleSelect) {
                    roleSelect.disabled = currentRole === 'barangay_captain';
                }

                const form = document.getElementById('userForm');
                if (form) {
                    form.dataset.currentRole = currentRole;
                }
                
                const modal = new bootstrap.Modal(document.getElementById('userModal'));
                modal.show();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error loading user');
        });
}

/**
 * Save user (create or update)
 */
function saveUser() {
    const form = document.getElementById('userForm');
    const formData = new FormData(form);
    const userId = document.getElementById('userId').value;
    const isUpdate = !!userId;

    if ((!isUpdate && !USER_MANAGEMENT_PERMS.canCreate) || (isUpdate && !USER_MANAGEMENT_PERMS.canEdit)) {
        showAlert('error', 'Access denied');
        return;
    }
    
    formData.append('action', userId ? 'update' : 'create');
    if (userId) {
        formData.append('id', userId);
    }
    
    // Remove password if empty during update
    if (userId && !formData.get('password')) {
        formData.delete('password');
    }

    if (userId && form && form.dataset.currentRole === 'barangay_captain') {
        formData.delete('role');
    }
    
    fetch(window.API_URL + 'users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            resetForm();
            loadUsers();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Error saving user');
    });
}

/**
 * Suspend user
 */
function suspendUser(id) {
    if (!USER_MANAGEMENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }

    if (confirm('Are you sure you want to suspend this user?')) {
        const formData = new FormData();
        formData.append('action', 'suspend');
        formData.append('id', id);
        
        fetch(window.API_URL + 'users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadUsers();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error suspending user');
        });
    }
}

/**
 * Activate user
 */
function activateUser(id) {
    if (!USER_MANAGEMENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'activate');
    formData.append('id', id);
    
    fetch(window.API_URL + 'users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            loadUsers();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Error activating user');
    });
}

/**
 * Delete user
 */
function deleteUser(id) {
    if (!USER_MANAGEMENT_PERMS.canDelete) {
        showAlert('error', 'Access denied');
        return;
    }

    if (confirm('Are you sure you want to suspend this user? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        fetch(window.API_URL + 'users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadUsers();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error deleting user');
        });
    }
}

/**
 * Reset form
 */
function resetForm() {
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('password').required = true;
    document.getElementById('userModalTitle').textContent = 'Add New User';
    togglePasswordField(true);
    toggleEditFieldLocks(false);

    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.disabled = false;
    }

    const form = document.getElementById('userForm');
    if (form && form.dataset) {
        delete form.dataset.currentRole;
    }
}

/**
 * Helper functions
 */
function formatRole(role) {
    return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatStatus(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function getStatusClass(status) {
    const classes = {
        'active': 'bg-success',
        'inactive': 'bg-secondary',
        'suspended': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showActivityLogs() {
    const panel = document.getElementById('activityLogsPanel');
    panel.style.display = 'block';
    const tbody = document.getElementById('activityLogsBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
    fetch(window.API_URL + 'users.php?action=activity_logs&limit=50')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data && d.data.length) {
                tbody.innerHTML = d.data.map(l => `
                    <tr>
                        <td>${escapeHtml(l.username || '-')}</td>
                        <td>${escapeHtml(l.action)}</td>
                        <td>${escapeHtml(l.module)}</td>
                        <td>${l.created_at ? new Date(l.created_at).toLocaleString() : '-'}</td>
                        <td>${escapeHtml(l.ip_address || '-')}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No activity logs</td></tr>';
            }
        })
        .catch(() => { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading</td></tr>'; });
}

/**
 * Initialize role permissions UI
 */
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
        loadRolePermissions();
    });

    roleIcons.addEventListener('click', function(e) {
        const btn = e.target.closest('.permissions-role-icon');
        if (!btn) {
            return;
        }
        const role = btn.getAttribute('data-role');
        selectPermissionsRole(role);
    });

    roleIcons.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        const btn = e.target.closest('.permissions-role-icon');
        if (!btn) {
            return;
        }
        e.preventDefault();
        const role = btn.getAttribute('data-role');
        selectPermissionsRole(role);
    });

    syncPermissionsRoleIcons(roleSelect.value);

    loadRolePermissions();
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
                    <label class="perm-label" for="perm_${m.id}_access_usersPage">Access</label>
                    <input id="perm_${m.id}_access_usersPage" type="checkbox" class="form-check-input perm-checkbox perm-toggle" data-module="${m.id}" data-perm="can_access">
                </div>
            </div>
        </div>
    `).join('');
}

function loadRolePermissions() {
    const roleSelect = document.getElementById('permissionsRole');
    if (!roleSelect) {
        return;
    }

    const role = roleSelect.value;
    fetch(`${window.API_URL}users.php?action=get_permissions&role=${encodeURIComponent(role)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                applyPermissionsToTiles(data.data.permissions || {});
                applyPermissionsLock(role);
            } else {
                showAlert('error', data.message || 'Failed to load permissions');
            }
        })
        .catch(() => {
            showAlert('error', 'Error loading permissions');
        });
}

function applyPermissionsToTiles(permissions) {
    MODULES.forEach(module => {
        const box = document.querySelector(`input[data-module="${module.id}"][data-perm="can_access"]`);
        if (!box) return;

        const accessValues = (module.keys || []).map(k => !!(permissions?.[k]?.can_access));
        // Bundle tiles: treat "Access" as enabled only if ALL mapped keys are enabled.
        box.checked = accessValues.length ? accessValues.every(Boolean) : false;
    });
}

function saveRolePermissions() {
    const roleSelect = document.getElementById('permissionsRole');
    if (!roleSelect) {
        return;
    }

    const role = roleSelect.value;
    if (String(role || '').trim().toLowerCase() === 'barangay_captain') {
        showAlert('error', 'Barangay Captain permissions are fixed and cannot be edited');
        return;
    }
    const permissions = {};

    MODULES.forEach(module => {
        const box = document.querySelector(`input[data-module="${module.id}"][data-perm="can_access"]`);
        const enabled = !!(box && box.checked);
        (module.keys || []).forEach(k => {
            permissions[k] = { can_access: enabled };
        });
    });

    fetch(window.API_URL + 'users.php?action=save_permissions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
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
    .catch(() => {
        showAlert('error', 'Error saving permissions');
    });
}

function applyPermissionsLock(role) {
    const normalizedRole = String(role || '').trim().toLowerCase();
    const isCaptain = normalizedRole === 'barangay_captain';
    const boxes = document.querySelectorAll('#permissionsModuleTiles .perm-checkbox');
    boxes.forEach(box => {
        box.disabled = isCaptain;
        if (isCaptain) {
            box.checked = true;
        }
    });

    const saveBtn = document.getElementById('savePermissionsBtn');
    if (saveBtn) {
        saveBtn.disabled = isCaptain;
    }
}

function selectPermissionsRole(role) {
    const roleSelect = document.getElementById('permissionsRole');
    if (!roleSelect || !role) {
        return;
    }
    if (roleSelect.value === role) {
        return;
    }
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
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function togglePasswordField(show) {
    const field = document.getElementById('passwordField');
    if (field) {
        field.style.display = show ? '' : 'none';
    }
}

function toggleEditFieldLocks(isEditing) {
    const lockIds = ['username', 'email', 'resident_id', 'status'];
    lockIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.disabled = !!isEditing;
        }
    });
}

function setRoleSelectValue(role) {
    const roleSelect = document.getElementById('role');
    if (!roleSelect) return;

    const raw = String(role || '').trim();
    const normalized = raw.toLowerCase();

    const hasOption = Array.from(roleSelect.options).some(opt => opt.value === normalized);
    if (!hasOption && normalized) {
        const option = document.createElement('option');
        option.value = normalized;
        option.textContent = raw.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        roleSelect.appendChild(option);
    }

    roleSelect.value = normalized || '';
}
