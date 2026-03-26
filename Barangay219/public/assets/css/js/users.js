/**
 * E-Barangay Information Management System
 * User Management JavaScript
 */

// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

let userFilters = { q: '', role: '', status: '' };
let usersPage = 1;
const USER_MANAGEMENT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('users', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('users', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('users', 'can_delete') : true
};

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
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
    const params = new URLSearchParams({ action: 'list', page: String(usersPage) });
    if (userFilters.q) params.append('q', userFilters.q);
    if (userFilters.role) params.append('role', userFilters.role);
    if (userFilters.status) params.append('status', userFilters.status);

    fetch(window.API_URL + 'users.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const payload = data.data;
                const list = payload && Array.isArray(payload.users)
                    ? payload.users
                    : (Array.isArray(payload) ? payload : []);
                usersPage = (payload && payload.page) ? Number(payload.page) : usersPage;
                displayUsers(list);
                if (typeof window.renderModuleBtnPagination === 'function') {
                    const total = payload && payload.total != null ? Number(payload.total) : list.length;
                    const totalPages = payload && payload.total_pages != null ? Number(payload.total_pages) : 1;
                    window.renderModuleBtnPagination({
                        containerId: 'usersPagination',
                        outerWrapId: 'usersPaginationOuter',
                        currentPage: usersPage,
                        total,
                        totalPages,
                        onPage: pg => {
                            usersPage = pg;
                            loadUsers();
                        }
                    });
                }
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
    usersPage = 1;
    const query = document.getElementById('searchInput')?.value.trim() || '';
    userFilters.q = query;
    loadUsers();
}

function applyUserFilters() {
    usersPage = 1;
    userFilters.role = document.getElementById('filterRole')?.value || '';
    userFilters.status = document.getElementById('filterStatus')?.value || '';
    loadUsers();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetUsers() {
    usersPage = 1;
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
        tbody.innerHTML = '<tr><td colspan="7" class="text-center users-subtext">No users found</td></tr>';
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
            <td class="text-center"><span class="users-code-badge">${escapeHtml(user.username)}</span></td>
            <td class="text-center"><span class="users-main-text">${escapeHtml(user.full_name || '-')}</span></td>
            <td class="text-center"><span class="users-subtext">${escapeHtml(user.email || '-')}</span></td>
            <td class="text-center"><span class="user-pill role-pill ${getRoleClass(user.role)}">${formatRole(user.role)}</span></td>
            <td class="text-center"><span class="user-pill ${getStatusClass(user.status)}">${formatStatus(user.status)}</span></td>
            <td class="text-center"><span class="users-subtext">${formatDate(user.created_at)}</span></td>
            <td class="text-center users-actions-col users-actions actions-col-wide">
                ${USER_MANAGEMENT_PERMS.canEdit ? `
                    <button class="action-icon-btn" title="Edit" aria-label="Edit" onclick="editUser(${user.id})">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    ${user.id !== (window.CURRENT_USER_ID || 0) ? (
                        user.status === 'active'
                            ? `<button class="action-icon-btn btn-suspend" title="Suspend" aria-label="Suspend" onclick="suspendUser(${user.id})">
                                <i class="bi bi-pause-circle"></i>
                               </button>`
                            : `<button class="action-icon-btn btn-activate" title="Activate" aria-label="Activate" onclick="activateUser(${user.id})">
                                <i class="bi bi-play-circle"></i>
                               </button>`
                    ) : ''}
                ` : ''}
                ${USER_MANAGEMENT_PERMS.canDelete && user.id !== (window.CURRENT_USER_ID || 0)
                    ? `<button class="action-icon-btn btn-delete" title="Delete" aria-label="Delete" onclick="deleteUser(${user.id})">
                        <i class="bi bi-trash"></i>
                       </button>`
                    : ''}
                ${!USER_MANAGEMENT_PERMS.canEdit && !USER_MANAGEMENT_PERMS.canDelete ? '<span class="users-subtext">View only</span>' : ''}
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
                document.getElementById('status').value = user.status;
                const pwInput = document.getElementById('password');
                if (pwInput) {
                    pwInput.value = '';
                    pwInput.required = false;
                    pwInput.disabled = true;
                }
                const np = document.getElementById('new_password');
                const cp = document.getElementById('confirm_password');
                if (np) np.value = '';
                if (cp) cp.value = '';
                togglePasswordField(false);
                document.getElementById('userModalTitle').textContent = 'Edit User';

                const tfRow = document.getElementById('adminTwoFactorRow');
                const tfCb = document.getElementById('two_factor_enabled');
                if (tfRow && window.CAN_MANAGE_USER_2FA) {
                    tfRow.style.display = '';
                    if (tfCb) {
                        tfCb.checked = !!user.two_factor_enabled;
                    }
                } else if (tfRow) {
                    tfRow.style.display = 'none';
                }

                toggleEditFieldLocks(true);

                const roleSelect = document.getElementById('role');
                if (roleSelect) {
                    roleSelect.disabled = currentRole === 'barangay_captain' || currentRole === 'super_admin';
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

    if (userId) {
        formData.delete('password');
        const newPw = (document.getElementById('new_password') && document.getElementById('new_password').value) || '';
        const confirmPw = (document.getElementById('confirm_password') && document.getElementById('confirm_password').value) || '';
        if (newPw || confirmPw) {
            if (newPw !== confirmPw) {
                showAlert('error', 'New password and confirmation do not match.');
                return;
            }
            formData.append('password', newPw);
        }
    } else if (!formData.get('password')) {
        showAlert('error', 'Password is required for new users.');
        return;
    }

    if (userId && form && (form.dataset.currentRole === 'barangay_captain' || form.dataset.currentRole === 'super_admin')) {
        formData.delete('role');
    }

    if (userId && window.CAN_MANAGE_USER_2FA) {
        formData.append('two_factor_field_present', '1');
        const tfCb = document.getElementById('two_factor_enabled');
        formData.append('two_factor_enabled', tfCb && tfCb.checked ? '1' : '0');
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
    const pwInput = document.getElementById('password');
    if (pwInput) {
        pwInput.required = true;
        pwInput.disabled = false;
    }
    const np = document.getElementById('new_password');
    const cp = document.getElementById('confirm_password');
    if (np) np.value = '';
    if (cp) cp.value = '';
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

    const tfRow = document.getElementById('adminTwoFactorRow');
    const tfCb = document.getElementById('two_factor_enabled');
    if (tfRow) {
        tfRow.style.display = 'none';
    }
    if (tfCb) {
        tfCb.checked = false;
    }
}

/**
 * Helper functions
 */
function formatRole(role) {
    return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getRoleClass(role) {
    const r = String(role || '').toLowerCase();
    const map = {
        'super_admin': 'role-super-admin',
        'barangay_captain': 'role-captain',
        'secretary': 'role-secretary',
        'treasurer': 'role-treasurer',
        'kagawad': 'role-kagawad',
        'sk_chairman': 'role-sk',
        'resident': 'role-resident'
    };
    return map[r] || 'role-pill';
}

function formatStatus(status) {
    const normalized = String(status || '').toLowerCase();
    if (!normalized) return 'Unknown';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function getStatusClass(status) {
    const normalized = String(status || '').toLowerCase();
    const classes = {
        'active': 'status-active',
        'inactive': 'status-inactive',
        'suspended': 'status-suspended'
    };
    return classes[normalized] || 'status-unknown';
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
    const changeSection = document.getElementById('changePasswordSection');
    if (field) {
        field.classList.toggle('d-none', !show);
    }
    if (changeSection) {
        changeSection.classList.toggle('d-none', show);
    }
}

function toggleEditFieldLocks(isEditing) {
    const lockIds = ['username', 'email'];
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
