/**
 * E-Barangay Information Management System
 * Residents Management JavaScript
 */

let currentPage = 1;
let residentFilters = { q: '', status: '', gender: '', age_from: '', age_to: '' };

const RESIDENT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('residents', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('residents', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('residents', 'can_delete') : true
};

document.addEventListener('DOMContentLoaded', function() {
    loadResidents();
    loadHouseholdsForDropdown();

    applyResidentPermissions();
    initResidentStatFilters();

    initResidentFormValidation();
    
    document.getElementById('residentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveResident();
    });
    
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchResidents();
    });
    
    document.getElementById('residentModal').addEventListener('show.bs.modal', function() {
        loadHouseholdsForDropdown();
    });
    
    document.getElementById('btnEditFromView').addEventListener('click', function() {
        const id = this.dataset.residentId;
        if (id) { bootstrap.Modal.getInstance(document.getElementById('viewResidentModal')).hide(); editResident(parseInt(id)); }
    });
});

function initResidentStatFilters() {
    const tabs = document.querySelectorAll('#statusTabs .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const status = this.getAttribute('data-status') || '';
            residentFilters.status = status;
            const statusSel = document.getElementById('filterStatus');
            if (statusSel) statusSel.value = status;
            loadResidents(1);
        });
    });
}

function initResidentFormValidation() {
    const firstName = document.getElementById('first_name');
    const middleName = document.getElementById('middle_name');
    const lastName = document.getElementById('last_name');
    const suffix = document.getElementById('suffix');
    const contact = document.getElementById('contact_number');
    const address = document.getElementById('address');
    const citizenship = document.getElementById('citizenship');

    if (firstName) validateNameInput(firstName);
    if (middleName) validateNameInput(middleName);
    if (lastName) validateNameInput(lastName);
    if (suffix) validateNameInput(suffix, true);
    if (contact) validatePhoneInput(contact);
    if (address) attachTitleCaseOnBlur(address);
    if (citizenship) attachTitleCaseOnBlur(citizenship);
}

// Name input validation - only allow letters, spaces, and dots (for suffix)
function validateNameInput(input, allowDot = false) {
    input.addEventListener('input', function() {
        const regex = allowDot ? /[^a-zA-Z\s.]/g : /[^a-zA-Z\s]/g;
        this.value = this.value.replace(regex, '');
    });
    input.addEventListener('blur', function() {
        this.value = toTitleCase(this.value);
    });
}

// Phone number input validation - enforce +63 prefix with space and 10 digits
function validatePhoneInput(input) {
    const ensurePrefix = () => {
        if (!input.value.startsWith('+63')) {
            input.value = '+63 ';
        } else if (input.value === '+63') {
            input.value = '+63 ';
        }
    };

    ensurePrefix();

    input.addEventListener('input', function() {
        const digits = normalizePhoneDigits(this.value);
        this.value = '+63 ' + digits;
    });

    input.addEventListener('blur', function() {
        const digits = normalizePhoneDigits(this.value);
        this.value = digits ? ('+63 ' + digits) : '+63 ';
    });
}

function applyResidentPermissions() {
    if (!RESIDENT_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
    }
    if (!RESIDENT_PERMS.canEdit) {
        const editBtn = document.getElementById('btnEditFromView');
        if (editBtn) editBtn.style.display = 'none';
    }
}

function loadHouseholdsForDropdown() {
    const sel = document.getElementById('household_id');
    if (!sel) return;
    const currentVal = sel.value;
    fetch((window.API_URL || '') + 'households.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data) {
                sel.innerHTML = '<option value="">-- None --</option>' + 
                    d.data.map(h => `<option value="${h.id}">${escapeHtml(h.family_head_name || 'Household #'+h.id)} - ${escapeHtml((h.address||'').substring(0,40))}...</option>`).join('');
                if (currentVal) sel.value = currentVal;
            }
        })
        .catch(() => {});
}

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
    const params = new URLSearchParams({
        action: 'list',
        page: page.toString(),
        limit: itemsPerPage.toString()
    });
    if (residentFilters.q) params.append('q', residentFilters.q);
    if (residentFilters.status) params.append('status', residentFilters.status);
    if (residentFilters.gender) params.append('gender', residentFilters.gender);
    if (residentFilters.age_from) params.append('age_from', residentFilters.age_from);
    if (residentFilters.age_to) params.append('age_to', residentFilters.age_to);

    fetch(`${apiUrl}resident.php?${params.toString()}`)
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
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">No residents found</td></tr>';
        return;
    }

    const statusRank = status => {
        const s = String(status || '').toLowerCase();
        if (s === 'active') return 0;
        if (s === 'inactive') return 2;
        return 1;
    };

    const sortedResidents = residents
        .map((resident, idx) => ({ resident, idx }))
        .sort((a, b) => {
            const ra = statusRank(a.resident.status);
            const rb = statusRank(b.resident.status);
            if (ra !== rb) return ra - rb;
            return a.idx - b.idx;
        })
        .map(item => item.resident);

    tbody.innerHTML = sortedResidents.map(resident => {
        const rawFullName = `${resident.first_name || ''} ${resident.middle_name || ''} ${resident.last_name || ''} ${resident.suffix || ''}`.trim();
        const fullName = escapeHtml(toTitleCase(rawFullName));
        const age = calculateAge(resident.birth_date);
        const residentCode = resident.resident_code ? escapeHtml(resident.resident_code) : '<span class="text-muted">N/A</span>';
        const verificationStatus = normalizeVerificationStatus(resident);
        const hasIdUpload = !!(resident.id_document_path && String(resident.id_document_path).trim() !== '');
        const canVerifyNow = RESIDENT_PERMS.canEdit && hasIdUpload && verificationStatus !== 'verified';
        const canRejectNow = RESIDENT_PERMS.canEdit && hasIdUpload && verificationStatus === 'pending';
        
        const isHead = String(resident.is_household_head) === '1';
        const householdCode = resident.household_code
            ? `<span class="badge bg-light text-dark border">${escapeHtml(String(resident.household_code))}</span>`
            : '<span class="text-muted">-</span>';
        const familyHeadCode = resident.family_head_code
            ? `<span class="badge bg-light text-dark border">${escapeHtml(String(resident.family_head_code))}</span>`
            : '<span class="text-muted">-</span>';
        return `
            <tr>
                <td class="text-center">${residentCode}</td>
                <td class="text-center">${fullName}</td>
                <td class="text-center">${formatDate(resident.birth_date)} (${age} yrs)</td>
                <td class="text-center">${formatGender(resident.gender)}</td>
                <td class="text-center">${escapeHtml(formatTitleCaseTruncate(resident.address || '', 40))}${(resident.address||'').length>40?'...':''}</td>
                <td class="text-center">${escapeHtml(formatPhoneNumber(resident.contact_number) || '-')}</td>
                <td class="text-center">${householdCode}</td>
                <td class="text-center">${familyHeadCode}</td>
                <td class="text-center">${getVerificationBadge(verificationStatus)}</td>
                <td class="text-center"><span class="badge ${getStatusClass(resident.status)}">${formatStatus(resident.status)}</span></td>
                <td class="text-center">
                    ${RESIDENT_PERMS.canEdit ? `<button class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit" onclick="editResident(${resident.id})"><i class="bi bi-pencil-square"></i></button>` : ''}
                    <button class="btn btn-sm btn-primary" title="View" aria-label="View" onclick="viewResident(${resident.id})"><i class="bi bi-eye"></i></button>
                    ${canVerifyNow ? `<button class="btn btn-sm btn-success" title="Verify ID" aria-label="Verify ID" onclick="verifyResidentId(${resident.id}, 'verified')"><i class="bi bi-patch-check"></i></button>` : ''}
                    ${canRejectNow ? `<button class="btn btn-sm btn-warning" title="Reject ID" aria-label="Reject ID" onclick="verifyResidentId(${resident.id}, 'rejected')"><i class="bi bi-patch-exclamation"></i></button>` : ''}
                    ${RESIDENT_PERMS.canDelete ? `<button class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete" onclick="deleteResident(${resident.id})"><i class="bi bi-trash"></i></button>` : ''}
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

    residentFilters.q = query;
    loadResidents(1);
}

function applyFilters() {
    residentFilters.status = document.getElementById('filterStatus')?.value || '';
    residentFilters.gender = document.getElementById('filterGender')?.value || '';
    residentFilters.age_from = document.getElementById('filterAgeFrom')?.value || '';
    residentFilters.age_to = document.getElementById('filterAgeTo')?.value || '';
    syncResidentStatusTabs();
    loadResidents(1);
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetResidents() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    residentFilters = { q: '', status: '', gender: '', age_from: '', age_to: '' };
    const statusSel = document.getElementById('filterStatus');
    const genderSel = document.getElementById('filterGender');
    const ageFrom = document.getElementById('filterAgeFrom');
    const ageTo = document.getElementById('filterAgeTo');
    if (statusSel) statusSel.value = '';
    if (genderSel) genderSel.value = '';
    if (ageFrom) ageFrom.value = '';
    if (ageTo) ageTo.value = '';
    syncResidentStatusTabs();
    loadResidents(1);
}

function syncResidentStatusTabs() {
    document.querySelectorAll('#statusTabs .nav-link').forEach(tab => {
        const tabStatus = tab.getAttribute('data-status') || '';
        tab.classList.toggle('active', tabStatus === (residentFilters.status || ''));
    });
}

/**
 * Edit resident
 */
function editResident(id) {
    if (!RESIDENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }
    const apiUrl = window.API_URL;
    if (!apiUrl) { showAlert('error', 'Configuration error.'); return; }
    Promise.all([
        fetch(apiUrl + 'households.php?action=list').then(r => r.json()),
        fetch(apiUrl + 'resident.php?action=get&id=' + id).then(r => r.json())
    ]).then(([householdsData, residentData]) => {
        if (!residentData.success) { showAlert('error', residentData.message); return; }
        const resident = residentData.data;
        const sel = document.getElementById('household_id');
        if (householdsData.success && householdsData.data) {
            sel.innerHTML = '<option value="">-- None --</option>' +
                householdsData.data.map(h => `<option value="${h.id}">${escapeHtml(h.family_head_name || 'Household #'+h.id)}</option>`).join('');
        }
        document.getElementById('residentId').value = resident.id;
        document.getElementById('first_name').value = toTitleCase(resident.first_name || '');
        document.getElementById('middle_name').value = toTitleCase(resident.middle_name || '');
        document.getElementById('last_name').value = toTitleCase(resident.last_name || '');
        document.getElementById('suffix').value = toTitleCase(resident.suffix || '');
        document.getElementById('birth_date').value = resident.birth_date;
        document.getElementById('gender').value = resident.gender;
        document.getElementById('civil_status').value = resident.civil_status || '';
        const occupationSelect = document.getElementById('occupation');
        if (occupationSelect) {
            const occupationValue = resident.occupation || '';
            if (occupationValue && !occupationSelect.querySelector(`option[value="${occupationValue.replace(/"/g, '&quot;')}"]`)) {
                const opt = document.createElement('option');
                opt.value = occupationValue;
                opt.textContent = occupationValue;
                occupationSelect.appendChild(opt);
            }
            occupationSelect.value = occupationValue;
        }
        document.getElementById('citizenship').value = toTitleCase(resident.citizenship || 'Filipino');
        document.getElementById('address').value = toTitleCase(resident.address || '');
        document.getElementById('contact_number').value = formatPhoneForInput(resident.contact_number) || '+63 ';
        document.getElementById('household_id').value = resident.household_id || '';
        document.getElementById('status').value = resident.status;
        document.getElementById('residentModalTitle').textContent = 'Edit Resident';
        initResidentFormValidation();
        new bootstrap.Modal(document.getElementById('residentModal')).show();
    }).catch(() => showAlert('error', 'Error loading resident'));
}

/**
 * View resident details
 */
function viewResident(id) {
    const apiUrl = window.API_URL;
    if (!apiUrl) { showAlert('error', 'Configuration error.'); return; }
    fetch(`${apiUrl}resident.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showAlert('error', data.message); return; }
            const r = data.data;
            const fullName = `${r.first_name || ''} ${r.middle_name || ''} ${r.last_name || ''} ${r.suffix || ''}`.trim();
            const age = calculateAge(r.birth_date);
            const residentCode = r.resident_code ? escapeHtml(r.resident_code) : '-';
            const verificationStatus = normalizeVerificationStatus(r);
            const idDocLink = buildResidentFileLink(r.id_document_path, 'View Uploaded ID');
            document.getElementById('viewResidentBody').innerHTML = `
                <table class="table table-sm">
                    <tr><td><strong>Resident ID</strong></td><td>${residentCode}</td></tr>
                    <tr><td><strong>Full Name</strong></td><td>${escapeHtml(toTitleCase(fullName))}</td></tr>
                    <tr><td><strong>Birth Date</strong></td><td>${formatDate(r.birth_date)} (${age} yrs)</td></tr>
                    <tr><td><strong>Gender</strong></td><td>${formatGender(r.gender)}</td></tr>
                    <tr><td><strong>Civil Status</strong></td><td>${escapeHtml(toTitleCase(r.civil_status || '-'))}</td></tr>
                    <tr><td><strong>Contact</strong></td><td>${escapeHtml(formatPhoneNumber(r.contact_number) || '-')}</td></tr>
                    <tr><td><strong>Address</strong></td><td>${escapeHtml(toTitleCase(r.address || '-'))}</td></tr>
                    <tr><td><strong>Occupation</strong></td><td>${escapeHtml(toTitleCase(r.occupation || '-'))}</td></tr>
                    <tr><td><strong>Citizenship</strong></td><td>${escapeHtml(toTitleCase(r.citizenship || '-'))}</td></tr>
                    <tr><td><strong>Household</strong></td><td>${r.household_address ? 'Household #'+r.household_id+' ('+r.total_members+' members)' : 'None'}</td></tr>
                    <tr><td><strong>Household Code</strong></td><td>${r.household_code ? (escapeHtml(String(r.household_code)) + (String(r.is_household_head) === '1' ? (String(r.household_role || '').toLowerCase() === 'landlord' ? ' <span class="badge bg-info ms-1">Landlord</span>' : ' <i class="bi bi-patch-check-fill text-success ms-1" title="Family Head" aria-label="Family Head"></i>') : '')) : '-'}</td></tr>
                    <tr><td><strong>Family Head Code</strong></td><td>${String(r.is_household_head) === '1' ? (r.family_head_code ? escapeHtml(String(r.family_head_code)) : '-') : ''}</td></tr>
                    <tr><td><strong>Verification</strong></td><td>${getVerificationBadge(verificationStatus)}</td></tr>
                    <tr><td><strong>Uploaded ID</strong></td><td>${idDocLink}</td></tr>
                    <tr><td><strong>Certificates</strong></td><td>${r.certificates_count || 0} issued</td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="badge ${getStatusClass(r.status)}">${formatStatus(r.status)}</span></td></tr>
                </table>
                ${RESIDENT_PERMS.canEdit && r.id_document_path ? `
                    <div class="d-flex gap-2 justify-content-end">
                        ${verificationStatus !== 'verified' ? `<button class="btn btn-success btn-sm" onclick="verifyResidentId(${r.id}, 'verified')"><i class="bi bi-patch-check"></i> Verify Uploaded ID</button>` : ''}
                        ${verificationStatus === 'pending' ? `<button class="btn btn-warning btn-sm" onclick="verifyResidentId(${r.id}, 'rejected')"><i class="bi bi-patch-exclamation"></i> Reject ID</button>` : ''}
                    </div>
                ` : ''}
            `;
            document.getElementById('btnEditFromView').dataset.residentId = id;
            document.getElementById('linkCertificates').href = (window.BASE_URL || '') + 'certificates.php';
            new bootstrap.Modal(document.getElementById('viewResidentModal')).show();
        })
        .catch(() => showAlert('error', 'Error loading resident'));
}

/**
 * Save resident (create or update)
 */
function saveResident() {
    applyTitleCaseToForm();
    const form = document.getElementById('residentForm');
    const formData = new FormData(form);
    const residentId = document.getElementById('residentId').value;

    if (!residentId) {
        showAlert('error', 'Creating new residents from Residents Management is disabled. Use approved resident applications instead.');
        return;
    }

    if (residentId && !RESIDENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }
    if (!residentId && !RESIDENT_PERMS.canCreate) {
        showAlert('error', 'Access denied');
        return;
    }
    
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
    if (!RESIDENT_PERMS.canDelete) {
        showAlert('error', 'Access denied');
        return;
    }
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
    document.getElementById('contact_number').value = '+63 ';
    document.getElementById('residentModalTitle').textContent = 'Edit Resident';
    initResidentFormValidation();
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

function normalizeVerificationStatus(row) {
    const raw = String(row?.verification_status || row?.record_status || '').toLowerCase().trim();
    if (raw === 'verified' || raw === 'approved' || raw === 'active') return 'verified';
    if (raw === 'rejected') return 'rejected';
    return 'pending';
}

function getVerificationBadge(status) {
    if (status === 'verified') {
        return '<span class="badge bg-success">Verified</span>';
    }
    if (status === 'rejected') {
        return '<span class="badge bg-danger">Rejected</span>';
    }
    return '<span class="badge bg-warning text-dark">Pending</span>';
}

function buildResidentFileLink(path, label) {
    if (!path) return '<span class="text-muted">No ID uploaded</span>';
    const trimmed = String(path).trim();
    if (!trimmed) return '<span class="text-muted">No ID uploaded</span>';
    const base = window.BASE_URL || '';
    const normalized = trimmed.replace(/^\/+/, '');
    const url = trimmed.startsWith('http') ? trimmed : (base + normalized);
    return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(label || 'View file')}</a>`;
}

function verifyResidentId(id, nextStatus) {
    if (!RESIDENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }

    const isVerify = nextStatus === 'verified';
    let remarks = '';
    if (isVerify) {
        if (!confirm('Mark this resident ID as verified?')) return;
    } else {
        remarks = prompt('Enter rejection reason (required):', 'ID photo is unclear or invalid. Please upload a clearer, valid government ID.') || '';
        remarks = remarks.trim();
        if (!remarks) {
            showAlert('error', 'Rejection reason is required.');
            return;
        }
        if (!confirm('Reject this resident ID upload?')) return;
    }

    const formData = new FormData();
    formData.append('action', isVerify ? 'verify_id' : 'reject_id');
    formData.append('id', String(id));
    if (remarks) formData.append('remarks', remarks);

    fetch((window.API_URL || '') + 'resident.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Failed to update verification status.');
                return;
            }
            showAlert('success', data.message || 'Verification status updated.');

            const viewModalEl = document.getElementById('viewResidentModal');
            const viewModal = viewModalEl ? bootstrap.Modal.getInstance(viewModalEl) : null;
            if (viewModal) {
                viewModal.hide();
            }

            loadResidents(currentPage);
        })
        .catch(error => {
            console.error('Verification update error:', error);
            showAlert('error', 'Failed to update verification status.');
        });
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function toTitleCase(text) {
    if (!text) return '';
    return String(text)
        .trim()
        .split(/\s+/)
        .map(word => {
            if (!word) return '';
            const clean = word.replace(/[^a-zA-Z]/g, '');
            if (clean.length > 0 && clean === clean.toUpperCase() && clean.length <= 3) {
                return word;
            }
            const first = word.charAt(0).toUpperCase();
            const rest = word.slice(1).toLowerCase();
            return first + rest;
        })
        .join(' ');
}

function formatTitleCaseTruncate(text, maxLen) {
    const titled = toTitleCase(text || '');
    return titled.substring(0, maxLen);
}

function attachTitleCaseOnBlur(input) {
    input.addEventListener('blur', function() {
        this.value = toTitleCase(this.value);
    });
}

function applyTitleCaseToForm() {
    const fields = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'address',
        'citizenship'
    ];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = toTitleCase(el.value);
    });
}

function normalizePhoneDigits(raw) {
    if (!raw) return '';
    let digits = String(raw).replace(/\D/g, '');
    if (digits.startsWith('63')) digits = digits.slice(2);
    if (digits.startsWith('0')) digits = digits.slice(1);
    return digits.slice(0, 10);
}

function formatPhoneNumber(raw) {
    if (!raw) return '';
    const digits = normalizePhoneDigits(raw);
    if (!digits) return String(raw).trim();
    if (digits.length < 10) return String(raw).trim();
    return '+63 ' + digits;
}

function formatPhoneForInput(raw) {
    const digits = normalizePhoneDigits(raw);
    return '+63 ' + digits;
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
