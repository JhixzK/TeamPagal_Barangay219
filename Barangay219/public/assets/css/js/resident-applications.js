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

const ACTIVATION_LINK_STORAGE_KEY = 'resident_app_activation_links_v1';
const ACTIVATION_LINK_STORAGE_LIMIT = 20;

let currentStatus = 'pending';
let currentPage = 1;
let appFilters = { q: '', sex: '', from: '', to: '' };

document.addEventListener('DOMContentLoaded', function() {
    bindTabs();
    bindActions();
    initResidentApplicationStatFilters();
    renderStoredActivationLinks();
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

function initResidentApplicationStatFilters() {
    const container = document.querySelector('.module-stats[data-module="resident_applications"]');
    if (!container) return;
    container.querySelectorAll('[data-status]').forEach(card => {
        const handleClick = () => {
            const status = card.getAttribute('data-status') || 'pending';
            currentStatus = status;
            currentPage = 1;
            document.querySelectorAll('#statusTabs .nav-link').forEach(t => t.classList.remove('active'));
            const tab = Array.from(document.querySelectorAll('#statusTabs .nav-link'))
                .find(t => (t.getAttribute('data-status') || '') === status);
            if (tab) tab.classList.add('active');
            loadApplications();
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

function loadApplications() {
    const tbody = document.getElementById('applicationsTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border"></div></td></tr>';

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
        .then(async r => {
            const contentType = r.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('NON_JSON_RESPONSE');
            }
            return r.json();
        })
        .then(data => {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-danger">' + esc(data.message || 'Error') + '</td></tr>';
                return;
            }
            const apps = data.data.applications || [];
            renderApplications(apps);
            renderPagination(data.data.total_pages || 1);
        })
        .catch(err => {
            console.error(err);
            const message = err && err.message === 'NON_JSON_RESPONSE'
                ? 'Failed to load applications (session/API host mismatch).'
                : 'Failed to load applications';
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger">' + esc(message) + '</td></tr>';
        });
}

function renderApplications(apps) {
    const tbody = document.getElementById('applicationsTableBody');
    if (!apps.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No applications found.</td></tr>';
        return;
    }

    tbody.innerHTML = apps.map(app => {
        const fullName = [app.first_name, app.middle_name, app.last_name].filter(Boolean).join(' ');
        const statusBadge = getStatusBadge(app.record_status);
        const actions = renderActions(app);
        const roleInfo = getHouseholdRoleInfo(app);
        return `
            <tr>
                <td><code>${esc(app.application_ref || 'APP-' + app.id)}</code></td>
                <td>${esc(fullName || '-')}</td>
                <td>${esc(app.sex || '-')}</td>
                <td>${esc(app.mobile_number || '-')}</td>
                <td>${formatDate(app.created_at)}</td>
                <td>${roleInfo.badge}</td>
                <td>${statusBadge}</td>
                <td>${actions}</td>
            </tr>
        `;
    }).join('');
}

function renderActions(app) {
    const viewBtn = `<button class="btn btn-sm btn-primary" title="View" aria-label="View" onclick="viewApplication(${app.id})"><i class="bi bi-eye"></i></button>`;
    if (!RES_APP_PERMS.canEdit) {
        return viewBtn;
    }
    if (app.record_status === 'pending') {
        return `${viewBtn}
            <button class="btn btn-sm btn-success" title="Approve" aria-label="Approve" onclick="openApprove(${app.id})"><i class="bi bi-check-lg"></i></button>
            <button class="btn btn-sm btn-outline-danger" title="Reject" aria-label="Reject" onclick="openReject(${app.id})"><i class="bi bi-x-lg"></i></button>`;
    }
    if (app.record_status === 'approved') {
        return `${viewBtn}
            <button class="btn btn-sm btn-outline-info" title="Get Activation Link" aria-label="Get Activation Link" onclick="fetchActivationLink(${app.id})"><i class="bi bi-link-45deg"></i></button>`;
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

            const roleInfo = getHouseholdRoleInfo(app);
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
                    <div class="col-md-6"><strong>Household Role:</strong> ${roleInfo.label}</div>
                    <div class="col-md-6"><strong>Relationship to Head:</strong> ${esc(roleInfo.relationship || '-')}</div>
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
            const activationLink = data.data?.activation_link || '';
            if (activationLink) {
                saveActivationLink({
                    applicationId: Number(id),
                    residentCode: data.data?.resident_code || '',
                    activationLink,
                    createdAt: new Date().toISOString()
                });
                renderStoredActivationLinks();
            }

            showAlert('success', data.message || 'Application approved');
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

function fetchActivationLink(id) {
    const params = new URLSearchParams({ action: 'activation_link', id: String(id) });
    fetch(window.API_URL + 'applications.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                const fallbackMsg = 'Failed to retrieve activation link';
                showAlert('error', (data.message || fallbackMsg));
                return;
            }

            const activationLink = data.data?.activation_link || '';
            if (!activationLink) {
                showAlert('error', 'No activation link returned by server.');
                return;
            }

            saveActivationLink({
                applicationId: Number(id),
                residentCode: data.data?.resident_code || '',
                activationLink,
                createdAt: new Date().toISOString()
            });
            renderStoredActivationLinks();

            const exp = data.data?.activation_expires ? ` Expires: ${data.data.activation_expires}` : '';
            showAlert('success', 'Activation link retrieved and saved locally.' + exp);
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to retrieve activation link');
        });
}

function buildFileLink(path, label) {
    if (!path) return '<span class="text-muted">None</span>';
    const trimmed = path.trim();
    const base = window.RESIDENT_APPLICATIONS_BASE_URL || '';
    let resolvedPath = trimmed.replace(/^\/+/, '');

    // Backward compatibility:
    // old records stored as "applications/..." (outside public before fix)
    // new records stored as "uploads/applications/..."
    if (!resolvedPath.startsWith('uploads/')) {
        if (resolvedPath.startsWith('applications/')) {
            resolvedPath = 'uploads/' + resolvedPath;
        } else {
            resolvedPath = 'uploads/applications/' + resolvedPath;
        }
    }

    const url = trimmed.startsWith('http') ? trimmed : (base + resolvedPath);
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

function getHouseholdRoleInfo(app) {
    const raw = (app.relationship_to_head || app.household_role || '').toString().trim();
    const lower = raw.toLowerCase();
    if (!raw) {
        return {
            label: '-',
            relationship: '',
            badge: '<span class="text-muted">-</span>'
        };
    }
    const isHead = lower === 'head' || lower.includes('head') || lower.includes('single');
    return {
        label: isHead ? 'Head' : 'Member',
        relationship: (app.relationship_to_head || '').toString().trim() || raw,
        badge: isHead ? '<span class="badge bg-primary">Head</span>' : '<span class="badge bg-light text-dark border">Member</span>'
    };
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

function showAlert(type, message) {
    const container = document.querySelector('.main-content .container-fluid') || document.body;
    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
    alert.innerHTML = `${esc(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.insertBefore(alert, container.firstChild);
    setTimeout(() => {
        try { alert.remove(); } catch (e) {}
    }, 5000);
}

function getStoredActivationLinks() {
    try {
        const raw = localStorage.getItem(ACTIVATION_LINK_STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

function saveActivationLink(entry) {
    const existing = getStoredActivationLinks();
    const filtered = existing.filter(item => item.activationLink !== entry.activationLink);
    filtered.unshift(entry);
    const limited = filtered.slice(0, ACTIVATION_LINK_STORAGE_LIMIT);
    localStorage.setItem(ACTIVATION_LINK_STORAGE_KEY, JSON.stringify(limited));
}

function renderStoredActivationLinks() {
    const container = document.querySelector('.main-content .container-fluid');
    if (!container) return;

    const stored = getStoredActivationLinks();
    const existingPanel = document.getElementById('activationLinksPanel');
    if (!stored.length) {
        if (existingPanel) existingPanel.remove();
        return;
    }

    const latest = stored[0];
    const itemsHtml = stored.slice(0, 5).map((item, idx) => {
        const code = item.residentCode ? `Resident ID: ${esc(item.residentCode)}` : `Application #${esc(item.applicationId || '-')}`;
        const safeLink = esc(item.activationLink || '');
        return `
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold">${code}</span>
                <input class="form-control form-control-sm" style="min-width:260px;max-width:560px" value="${safeLink}" readonly>
                <a class="btn btn-sm btn-outline-primary" href="${safeLink}" target="_blank" rel="noopener">Open</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyActivationLinkFromInput(this)">Copy</button>
            </li>
        `;
    }).join('');

    const panelHtml = `
        <div id="activationLinksPanel" class="alert alert-info border-info-subtle shadow-sm mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <strong>Recent Activation Links</strong>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearActivationLinks()">Clear</button>
            </div>
            <div class="small mb-2">Latest link is saved locally in this browser so it will not disappear after approval.</div>
            <ul class="list-group">${itemsHtml}</ul>
            <div class="small text-muted mt-2">Newest: ${esc(latest.createdAt || '')}</div>
        </div>
    `;

    if (existingPanel) {
        existingPanel.outerHTML = panelHtml;
    } else {
        container.insertAdjacentHTML('afterbegin', panelHtml);
    }
}

function clearActivationLinks() {
    localStorage.removeItem(ACTIVATION_LINK_STORAGE_KEY);
    renderStoredActivationLinks();
}

function copyActivationLink(value) {
    if (!value) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value)
            .then(() => showAlert('success', 'Activation link copied to clipboard.'))
            .catch(() => fallbackCopyText(value));
        return;
    }
    fallbackCopyText(value);
}

function copyActivationLinkFromInput(btn) {
    const input = btn ? btn.parentElement.querySelector('input') : null;
    copyActivationLink(input ? input.value : '');
}

function fallbackCopyText(value) {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showAlert('success', 'Activation link copied to clipboard.');
    } catch (e) {
        showAlert('error', 'Unable to copy activation link automatically.');
    } finally {
        textarea.remove();
    }
}
