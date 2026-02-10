/**
 * E-Barangay Information Management System
 * Resident Applications JavaScript
 */

// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

const RES_APP_PERMS = {
    canEdit: window.canModulePermission ? window.canModulePermission('resident_applications', 'can_edit') : true
};

let currentStatus = 'pending';
let currentPage = 1;
let appFilters = { q: '', sex: '', from: '', to: '' };

document.addEventListener('DOMContentLoaded', function() {
    bindTabs();
    bindActions();
    loadApplications();
});

function bindTabs() {
    document.querySelectorAll('#statusTabs .nav-link').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#statusTabs .nav-link').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.getAttribute('data-status') || 'pending';
            currentPage = 1;
            loadApplications();
        });
    });
}

function bindActions() {
    const approveBtn = document.getElementById('btnApprove');
    if (approveBtn) {
        approveBtn.addEventListener('click', submitApprove);
    }

    const rejectBtn = document.getElementById('btnReject');
    if (rejectBtn) {
        rejectBtn.addEventListener('click', submitReject);
    }
}

function searchApplications() {
    appFilters.q = document.getElementById('searchInput')?.value.trim() || '';
    currentPage = 1;
    loadApplications();
}

function applyApplicationFilters() {
    appFilters.sex = document.getElementById('filterSex')?.value || '';
    appFilters.from = document.getElementById('filterFrom')?.value || '';
    appFilters.to = document.getElementById('filterTo')?.value || '';
    currentPage = 1;
    loadApplications();

    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetApplications() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    appFilters = { q: '', sex: '', from: '', to: '' };
    const sex = document.getElementById('filterSex');
    const from = document.getElementById('filterFrom');
    const to = document.getElementById('filterTo');
    if (sex) sex.value = '';
    if (from) from.value = '';
    if (to) to.value = '';
    currentPage = 1;
    loadApplications();
}

function loadApplications() {
    const tbody = document.getElementById('applicationsTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border"></div></td></tr>';

    const params = new URLSearchParams({
        action: 'list',
        status: currentStatus,
        page: currentPage.toString()
    });

    if (appFilters.q) params.append('q', appFilters.q);
    if (appFilters.sex) params.append('sex', appFilters.sex);
    if (appFilters.from) params.append('from', appFilters.from);
    if (appFilters.to) params.append('to', appFilters.to);

    fetch(window.API_URL + 'applications.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-danger">' + esc(data.message || 'Error') + '</td></tr>';
                return;
            }
            const apps = data.data.applications || [];
            renderApplications(apps);
            renderPagination(data.data.total_pages || 1);
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Failed to load applications</td></tr>';
        });
}

function renderApplications(apps) {
    const tbody = document.getElementById('applicationsTableBody');
    if (!apps.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No applications found.</td></tr>';
        return;
    }

    tbody.innerHTML = apps.map(app => {
        const fullName = [app.first_name, app.middle_name, app.last_name].filter(Boolean).join(' ');
        const statusBadge = getStatusBadge(app.record_status);
        const actions = renderActions(app);
        return `
            <tr>
                <td><code>${esc(app.application_ref || 'APP-' + app.id)}</code></td>
                <td>${esc(fullName || '-')}</td>
                <td>${esc(app.sex || '-')}</td>
                <td>${esc(app.mobile_number || '-')}</td>
                <td>${formatDate(app.created_at)}</td>
                <td>${statusBadge}</td>
                <td>${actions}</td>
            </tr>
        `;
    }).join('');
}

function renderActions(app) {
    const viewBtn = `<button class="btn btn-sm btn-outline-primary" onclick="viewApplication(${app.id})"><i class="bi bi-eye"></i></button>`;
    if (!RES_APP_PERMS.canEdit) {
        return viewBtn;
    }
    if (app.record_status === 'pending') {
        return `${viewBtn}
            <button class="btn btn-sm btn-success" onclick="openApprove(${app.id})">Approve</button>
            <button class="btn btn-sm btn-danger" onclick="openReject(${app.id})">Reject</button>`;
    }
    return viewBtn;
}

function renderPagination(totalPages) {
    const pagination = document.getElementById('pagination');
    if (!pagination) return;

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
        </li>`;
    }
    pagination.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    loadApplications();
}

function viewApplication(id) {
    fetch(`${window.API_URL}applications.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Failed to load application');
                return;
            }
            const app = data.data;
            const fullName = [app.first_name, app.middle_name, app.last_name, app.suffix].filter(Boolean).join(' ');
            const address = [app.house_number, app.street, app.purok_sitio, app.barangay, app.city, app.province].filter(Boolean).join(', ');
            document.getElementById('viewAppRef').textContent = app.application_ref || ('APP-' + app.id);

            const idDoc = buildFileLink(app.id_document_path, 'Valid ID');
            const proofDoc = buildFileLink(app.proof_of_residency_path, 'Proof of Residency');

            document.getElementById('viewModalBody').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6"><strong>Name:</strong> ${esc(fullName || '-')}</div>
                    <div class="col-md-6"><strong>Sex:</strong> ${esc(app.sex || '-')}</div>
                    <div class="col-md-6"><strong>Birth Date:</strong> ${formatDate(app.birth_date)}</div>
                    <div class="col-md-6"><strong>Place of Birth:</strong> ${esc(app.place_of_birth || '-')}</div>
                    <div class="col-md-6"><strong>Civil Status:</strong> ${esc(app.civil_status || '-')}</div>
                    <div class="col-md-6"><strong>Citizenship:</strong> ${esc(app.citizenship || '-')}</div>
                    <div class="col-md-12"><strong>Address:</strong> ${esc(address || '-')}</div>
                    <div class="col-md-6"><strong>Mobile:</strong> ${esc(app.mobile_number || '-')}</div>
                    <div class="col-md-6"><strong>Email:</strong> ${esc(app.email || '-')}</div>
                    <div class="col-md-6"><strong>Emergency Contact:</strong> ${esc(app.emergency_contact_name || '-')}</div>
                    <div class="col-md-6"><strong>Emergency Number:</strong> ${esc(app.emergency_contact_number || '-')}</div>
                    <div class="col-md-6"><strong>Relationship:</strong> ${esc(app.emergency_contact_relationship || '-')}</div>
                    <div class="col-md-6"><strong>Employment:</strong> ${esc(app.employment_status || '-')}</div>
                    <div class="col-md-6"><strong>Education:</strong> ${esc(app.educational_attainment || '-')}</div>
                    <div class="col-md-6"><strong>Submitted:</strong> ${formatDate(app.created_at)}</div>
                    <div class="col-md-6"><strong>Status:</strong> ${getStatusBadge(app.record_status)}</div>
                    <div class="col-md-6"><strong>Valid ID:</strong> ${idDoc}</div>
                    <div class="col-md-6"><strong>Proof of Residency:</strong> ${proofDoc}</div>
                </div>
            `;

            const footer = document.getElementById('viewModalFooter');
            if (footer) {
                footer.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
            }

            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to load application');
        });
}

function openApprove(id) {
    if (!RES_APP_PERMS.canEdit) return;
    document.getElementById('approveId').value = id;
    document.getElementById('approveRemarks').value = '';
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function submitApprove() {
    const id = document.getElementById('approveId').value;
    const remarks = document.getElementById('approveRemarks').value.trim();
    const formData = new FormData();
    formData.append('action', 'approve');
    formData.append('id', id);
    if (remarks) formData.append('remarks', remarks);

    fetch(window.API_URL + 'applications.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Approval failed');
                return;
            }
            const link = data.data?.activation_link ? ` Activation link: ${data.data.activation_link}` : '';
            showAlert('success', (data.message || 'Application approved') + link);
            bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
            loadApplications();
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Approval failed');
        });
}

function openReject(id) {
    if (!RES_APP_PERMS.canEdit) return;
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function submitReject() {
    const id = document.getElementById('rejectId').value;
    const reason = document.getElementById('rejectReason').value.trim();
    const formData = new FormData();
    formData.append('action', 'reject');
    formData.append('id', id);
    if (reason) formData.append('rejection_reason', reason);

    fetch(window.API_URL + 'applications.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Rejection failed');
                return;
            }
            showAlert('success', data.message || 'Application rejected');
            bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
            loadApplications();
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Rejection failed');
        });
}

function buildFileLink(path, label) {
    if (!path) return '<span class="text-muted">None</span>';
    const trimmed = path.trim();
    const url = trimmed.startsWith('http') ? trimmed : (window.RESIDENT_APPLICATIONS_BASE_URL || '') + trimmed.replace(/^\/+/, '');
    return `<a href="${esc(url)}" target="_blank" rel="noopener">${esc(label)}</a>`;
}

function getStatusBadge(status) {
    const map = {
        pending: 'warning',
        approved: 'success',
        rejected: 'danger'
    };
    const color = map[status] || 'secondary';
    return `<span class="badge bg-${color}">${esc(status || 'unknown')}</span>`;
}

function formatDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return esc(value);
    return date.toLocaleDateString();
}

function esc(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
