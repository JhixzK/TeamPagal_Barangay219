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

const MODULES = [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'applications', label: 'Certificate Applications' },
    { key: 'resident_applications', label: 'Resident Applications' },
    { key: 'residents', label: 'Residents' },
    { key: 'households', label: 'Households' },
    { key: 'certificates', label: 'Certificates' },
    { key: 'blotters', label: 'Blotters' },
    { key: 'complaints', label: 'Complaints' },
    { key: 'announcements', label: 'Announcements' },
    { key: 'reports', label: 'Reports' },
    { key: 'users', label: 'Users' }
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
    initUserStatFilters();
    applyUsersPagePermissions();
    
    // Form submission
    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveUser();
    });
});

function initUserStatFilters() {
    const container = document.querySelector('.module-stats[data-module="users"]');
    if (!container) return;
    container.querySelectorAll('[data-status]').forEach(card => {
        const handleClick = () => {
            const status = card.getAttribute('data-status') || '';
            userFilters.status = status;
            const statusSel = document.getElementById('filterStatus');
            if (statusSel) statusSel.value = status;
            loadUsers();
        };
        card.addEventListener('click', handleClick);
        card.addEventListener('keypress', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleClick();
            }
        });
    });
}

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
    const tableBody = document.getElementById('permissionsTableBody');
    if (!roleSelect || !roleIcons || !tableBody) {
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

    renderPermissionsTable();
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

    document.getElementById('permissionsTableBody').addEventListener('change', function(e) {
        const target = e.target;
        if (!target.classList.contains('perm-checkbox')) {
            return;
        }

        const moduleKey = target.getAttribute('data-module');
        const perm = target.getAttribute('data-perm');

        if (perm !== 'can_access' && target.checked) {
            const accessBox = document.querySelector(`input[data-module="${moduleKey}"][data-perm="can_access"]`);
            if (accessBox) {
                accessBox.checked = true;
            }
        }

        if (perm === 'can_access' && !target.checked) {
            ['can_create', 'can_edit', 'can_delete'].forEach(p => {
                const box = document.querySelector(`input[data-module="${moduleKey}"][data-perm="${p}"]`);
                if (box) {
                    box.checked = false;
                }
            });
        }
    });

    loadRolePermissions();
}

function renderPermissionsTable() {
    const tbody = document.getElementById('permissionsTableBody');
    if (!tbody) {
        return;
    }

    tbody.innerHTML = MODULES.map(module => `
        <tr>
            <td>${module.label}</td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox perm-toggle" data-module="${module.key}" data-perm="can_access">
            </td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox perm-toggle" data-module="${module.key}" data-perm="can_create">
            </td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox perm-toggle" data-module="${module.key}" data-perm="can_edit">
            </td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox perm-toggle" data-module="${module.key}" data-perm="can_delete">
            </td>
        </tr>
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
                applyPermissionsToTable(data.data.permissions || {});
                applyPermissionsLock(role);
            } else {
                showAlert('error', data.message || 'Failed to load permissions');
            }
        })
        .catch(() => {
            showAlert('error', 'Error loading permissions');
        });
}

function applyPermissionsToTable(permissions) {
    MODULES.forEach(module => {
        const perm = permissions[module.key] || {};
        ['can_access', 'can_create', 'can_edit', 'can_delete'].forEach(key => {
            const box = document.querySelector(`input[data-module="${module.key}"][data-perm="${key}"]`);
            if (box) {
                box.checked = !!perm[key];
            }
        });
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
        const getVal = perm => {
            const box = document.querySelector(`input[data-module="${module.key}"][data-perm="${perm}"]`);
            return box ? box.checked : false;
        };

        permissions[module.key] = {
            can_access: getVal('can_access'),
            can_create: getVal('can_create'),
            can_edit: getVal('can_edit'),
            can_delete: getVal('can_delete')
        };
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
    const boxes = document.querySelectorAll('#permissionsTableBody .perm-checkbox');
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
