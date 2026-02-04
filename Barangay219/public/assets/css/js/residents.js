/**
 * E-Barangay Information Management System
 * Residents Management JavaScript
 */

let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadResidents();
    
    // Form submission
    document.getElementById('residentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveResident();
    });
    
    // Search on Enter key
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchResidents();
        }
    });
});

/**
 * Load all residents
 */
function loadResidents(page = 1) {
    currentPage = page;
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    const itemsPerPage = window.ITEMS_PER_PAGE || 20;
    fetch(`${apiUrl}resident.php?action=list&page=${page}&limit=${itemsPerPage}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayResidents(data.data.residents);
                displayPagination(data.data);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error loading residents');
        });
}

/**
 * Display residents in table
 */
function displayResidents(residents) {
    const tbody = document.getElementById('residentsTableBody');
    
    if (residents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No residents found</td></tr>';
        return;
    }
    
    tbody.innerHTML = residents.map(resident => {
        const fullName = `${escapeHtml(resident.first_name)} ${escapeHtml(resident.middle_name || '')} ${escapeHtml(resident.last_name)} ${escapeHtml(resident.suffix || '')}`.trim();
        const age = calculateAge(resident.birth_date);
        
        return `
            <tr>
                <td>${resident.id}</td>
                <td>${fullName}</td>
                <td>${formatDate(resident.birth_date)} (${age} yrs)</td>
                <td>${formatGender(resident.gender)}</td>
                <td>${escapeHtml(resident.address)}</td>
                <td>${escapeHtml(resident.contact_number || '-')}</td>
                <td><span class="badge ${getStatusClass(resident.status)}">${formatStatus(resident.status)}</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editResident(${resident.id})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-info" onclick="viewResident(${resident.id})" title="View">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteResident(${resident.id})" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Display pagination
 */
function displayPagination(data) {
    const pagination = document.getElementById('pagination');
    const totalPages = data.total_pages;
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadResidents(${currentPage - 1}); return false;">Previous</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadResidents(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next button
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadResidents(${currentPage + 1}); return false;">Next</a>
    </li>`;
    
    pagination.innerHTML = html;
}

/**
 * Search residents
 */
function searchResidents() {
    const query = document.getElementById('searchInput').value.trim();
    
    if (!query) {
        loadResidents();
        return;
    }
    
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    fetch(`${apiUrl}resident.php?action=search&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayResidents(data.data);
                document.getElementById('pagination').innerHTML = '';
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error searching residents');
        });
}

/**
 * Edit resident
 */
function editResident(id) {
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    fetch(`${apiUrl}resident.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const resident = data.data;
                document.getElementById('residentId').value = resident.id;
                document.getElementById('first_name').value = resident.first_name;
                document.getElementById('middle_name').value = resident.middle_name || '';
                document.getElementById('last_name').value = resident.last_name;
                document.getElementById('suffix').value = resident.suffix || '';
                document.getElementById('birth_date').value = resident.birth_date;
                document.getElementById('gender').value = resident.gender;
                document.getElementById('civil_status').value = resident.civil_status || '';
                document.getElementById('occupation').value = resident.occupation || '';
                document.getElementById('citizenship').value = resident.citizenship || 'Filipino';
                document.getElementById('address').value = resident.address;
                document.getElementById('contact_number').value = resident.contact_number || '';
                document.getElementById('household_id').value = resident.household_id || '';
                document.getElementById('status').value = resident.status;
                document.getElementById('residentModalTitle').textContent = 'Edit Resident';
                
                const modal = new bootstrap.Modal(document.getElementById('residentModal'));
                modal.show();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error loading resident');
        });
}

/**
 * View resident details
 */
function viewResident(id) {
    // Redirect to profile page or show in modal
    const baseUrl = window.BASE_URL;
    if (!baseUrl) {
        console.error('BASE_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    window.location.href = `${baseUrl}profile.php?resident_id=${id}`;
}

/**
 * Save resident (create or update)
 */
function saveResident() {
    const form = document.getElementById('residentForm');
    const formData = new FormData(form);
    const residentId = document.getElementById('residentId').value;
    
    formData.append('action', residentId ? 'update' : 'create');
    if (residentId) {
        formData.append('id', residentId);
    }
    
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    fetch(`${apiUrl}resident.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('residentModal')).hide();
            resetForm();
            loadResidents(currentPage);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Error saving resident');
    });
}

/**
 * Delete resident
 */
function deleteResident(id) {
    if (confirm('Are you sure you want to delete this resident?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const apiUrl = window.API_URL;
        if (!apiUrl) {
            console.error('API_URL is not defined. Please check your configuration.');
            showAlert('error', 'Configuration error. Please refresh the page.');
            return;
        }
        fetch(`${apiUrl}resident.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadResidents(currentPage);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error deleting resident');
        });
    }
}

/**
 * Reset form
 */
function resetForm() {
    document.getElementById('residentForm').reset();
    document.getElementById('residentId').value = '';
    document.getElementById('citizenship').value = 'Filipino';
    document.getElementById('status').value = 'active';
    document.getElementById('residentModalTitle').textContent = 'Add New Resident';
}

/**
 * Helper functions
 */
function calculateAge(birthDate) {
    if (!birthDate) return '-';
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

function formatGender(gender) {
    return gender ? gender.charAt(0).toUpperCase() + gender.slice(1) : '-';
}

function formatStatus(status) {
    return status ? status.charAt(0).toUpperCase() + status.slice(1) : '-';
}

function getStatusClass(status) {
    const classes = {
        'active': 'bg-success',
        'inactive': 'bg-secondary',
        'deceased': 'bg-dark',
        'transferred': 'bg-info'
    };
    return classes[status] || 'bg-secondary';
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    if (!text) return '';
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
