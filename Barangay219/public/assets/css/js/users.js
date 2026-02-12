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
    { value: 'barangay_captain', label: 'Barangay Captain' },
    { value: 'secretary', label: 'Secretary' },
    { value: 'treasurer', label: 'Treasurer' },
    { value: 'kagawad', label: 'Kagawad' },
    { value: 'sk_chairman', label: 'SK Chairman' }
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

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    initPermissionsUI();
    
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
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${escapeHtml(user.username)}</td>
            <td>${escapeHtml(user.full_name || '-')}</td>
            <td>${escapeHtml(user.email || '-')}</td>
            <td><span class="badge bg-info">${formatRole(user.role)}</span></td>
            <td><span class="badge ${getStatusClass(user.status)}">${formatStatus(user.status)}</span></td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})" title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
                ${user.status === 'active' 
                    ? `<button class="btn btn-sm btn-warning" onclick="suspendUser(${user.id})" title="Suspend">
                        <i class="bi bi-pause-circle"></i>
                       </button>`
                    : `<button class="btn btn-sm btn-success" onclick="activateUser(${user.id})" title="Activate">
                        <i class="bi bi-play-circle"></i>
                       </button>`
                }
                ${user.id !== (window.CURRENT_USER_ID || 0) 
                    ? `<button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})" title="Delete">
                        <i class="bi bi-trash"></i>
                       </button>`
                    : ''
                }
            </td>
        </tr>
    `).join('');
}

/**
 * Edit user
 */
function editUser(id) {
    fetch(`${window.API_URL}users.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.data;
                const currentRole = String(user.role || '').trim().toLowerCase();
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email || '';
                document.getElementById('role').value = user.role;
                document.getElementById('resident_id').value = user.resident_id || '';
                document.getElementById('status').value = user.status;
                document.getElementById('password').required = false;
                document.getElementById('userModalTitle').textContent = 'Edit User';

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
    const tableBody = document.getElementById('permissionsTableBody');
    if (!roleSelect || !tableBody) {
        return;
    }

    roleSelect.innerHTML = ROLE_OPTIONS.map(role =>
        `<option value="${role.value}">${role.label}</option>`
    ).join('');

    renderPermissionsTable();
    roleSelect.addEventListener('change', loadRolePermissions);

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
                <input type="checkbox" class="form-check-input perm-checkbox" data-module="${module.key}" data-perm="can_access">
            </td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox" data-module="${module.key}" data-perm="can_create">
            </td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox" data-module="${module.key}" data-perm="can_edit">
            </td>
            <td class="text-center">
                <input type="checkbox" class="form-check-input perm-checkbox" data-module="${module.key}" data-perm="can_delete">
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
