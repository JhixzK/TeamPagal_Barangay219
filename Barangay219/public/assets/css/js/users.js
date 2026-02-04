/**
 * E-Barangay Information Management System
 * User Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    
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
    fetch('<?php echo API_URL; ?>users.php?action=list')
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
                ${user.id !== <?php echo getCurrentUserId(); ?> 
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
    fetch(`<?php echo API_URL; ?>users.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.data;
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email || '';
                document.getElementById('role').value = user.role;
                document.getElementById('resident_id').value = user.resident_id || '';
                document.getElementById('status').value = user.status;
                document.getElementById('password').required = false;
                document.getElementById('userModalTitle').textContent = 'Edit User';
                
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
    
    fetch('<?php echo API_URL; ?>users.php', {
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
        
        fetch('<?php echo API_URL; ?>users.php', {
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
    
    fetch('<?php echo API_URL; ?>users.php', {
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
        
        fetch('<?php echo API_URL; ?>users.php', {
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
