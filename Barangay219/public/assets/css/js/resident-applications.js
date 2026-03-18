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
let isApproveSubmitting = false;
let isRejectSubmitting = false;

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

    const assignBtn = document.getElementById('btnAssignHousehold');
    if (assignBtn) {
        assignBtn.addEventListener('click', submitAssignHousehold);
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
                <td class="text-center"><code>${esc(app.application_ref || 'APP-' + app.id)}</code></td>
                <td class="text-center">${esc(toTitleCase(fullName || '-'))}</td>
                <td class="text-center">${esc(toTitleCase(app.sex || '-'))}</td>
                <td class="text-center">${esc(formatPhoneNumber(app.mobile_number) || '-')}</td>
                <td class="text-center">${formatDate(app.created_at)}</td>
                <td class="text-center"><div class="d-flex justify-content-center">${roleInfo.badge}</div></td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-center">${actions}</td>
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
        // For pending applications, only show the View button in the table.
        // Approve / Reject actions are now exposed inside the Application Details modal.
        return viewBtn;
    }
    if (app.record_status === 'approved') {
        const canAssign = app.approved_resident_id && Number(app.approved_resident_id) > 0;
        const roleInfo = getHouseholdRoleInfo(app);
        const isHead = (roleInfo.label || '').toLowerCase() === 'head';
        const assignBtn = canAssign
            ? `<button class="btn btn-sm btn-outline-secondary" title="Assign Household" aria-label="Assign Household" onclick="openAssignHousehold(${app.id}, ${Number(app.approved_resident_id)}, ${isHead ? 1 : 0})"><i class="bi bi-house-check"></i></button>`
            : '';
        return `${viewBtn}
            ${assignBtn}
            <button class="btn btn-sm btn-outline-info" title="Get Activation Link" aria-label="Get Activation Link" onclick="fetchActivationLink(${app.id})"><i class="bi bi-link-45deg"></i></button>`;
    }
    return viewBtn;
}

function openAssignHousehold(applicationId, residentId, isHead) {
    if (!RES_APP_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }
    document.getElementById('assignApplicationId').value = applicationId;
    document.getElementById('assignResidentId').value = residentId;
    const hint = document.getElementById('assignHouseholdRoleHint');
    if (hint) {
        hint.textContent = isHead ? 'Detected role: Head of Family (HH/FH codes will generate if missing)' : 'Detected role: Member';
    }

    const sel = document.getElementById('assignHouseholdId');
    if (!sel) return;
    sel.innerHTML = '<option value="">Loading households...</option>';

    fetch(`${window.API_URL}households.php?action=list`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !Array.isArray(d.data)) {
                sel.innerHTML = '<option value="">Failed to load households</option>';
                return;
            }
            const opts = ['<option value="">-- Select household --</option>']
                .concat(d.data.map(h => {
                    const id = Number(h.id);
                    const head = toTitleCase(h.family_head_name || '');
                    const label = head ? `Household ${id} • ${head}` : `Household ${id}`;
                    return `<option value="${id}">${esc(label)}</option>`;
                }));
            sel.innerHTML = opts.join('');
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Failed to load households</option>';
        });

    const modal = new bootstrap.Modal(document.getElementById('assignHouseholdModal'));
    modal.show();
}

function submitAssignHousehold() {
    const applicationId = Number(document.getElementById('assignApplicationId')?.value || 0);
    const residentId = Number(document.getElementById('assignResidentId')?.value || 0);
    const householdId = Number(document.getElementById('assignHouseholdId')?.value || 0);

    if (!applicationId || !residentId || !householdId) {
        showAlert('error', 'Please select a household.');
        return;
    }

    const fd = new FormData();
    fd.append('application_id', applicationId);
    fd.append('resident_id', residentId);
    fd.append('household_id', householdId);

    fetch(`${window.API_URL}applications.php?action=assign_household`, { method: 'POST', body: fd })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Assign household non-JSON response:', text);
                return { success: false, message: 'Server returned an invalid response. Check PHP error output/logs.' };
            }
        })
        .then(d => {
            if (d && d.success) {
                const modalEl = document.getElementById('assignHouseholdModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                showAlert('success', d.message || 'Household assigned');
                loadApplications();
            } else {
                showAlert('error', (d && d.message) ? d.message : 'Failed to assign household');
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to assign household');
        });
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

            const idDoc = buildFilePreview(app.id_document_path, 'Valid ID');
            // If proof upload is missing, show the Valid ID document in the Proof section
            // (requested behavior to avoid empty/incorrect Proof of Residency display).
            const proofPath = app.proof_of_residency_path ? app.proof_of_residency_path : app.id_document_path;
            const proofDoc = buildFilePreview(proofPath, 'Proof of Residency');

            const roleInfo = getHouseholdRoleInfo(app);
            document.getElementById('viewModalBody').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6"><strong>Name:</strong> ${esc(toTitleCase(fullName || '-'))}</div>
                    <div class="col-md-6"><strong>Sex:</strong> ${esc(toTitleCase(app.sex || '-'))}</div>
                    <div class="col-md-6"><strong>Birth Date:</strong> ${formatDate(app.birth_date)}</div>
                    <div class="col-md-6"><strong>Place of Birth:</strong> ${esc(toTitleCase(app.place_of_birth || '-'))}</div>
                    <div class="col-md-6"><strong>Civil Status:</strong> ${esc(toTitleCase(app.civil_status || '-'))}</div>
                    <div class="col-md-6"><strong>Citizenship:</strong> ${esc(toTitleCase(app.citizenship || '-'))}</div>
                    <div class="col-md-12"><strong>Address:</strong> ${esc(toTitleCase(address || '-'))}</div>
                    <div class="col-md-6"><strong>Mobile:</strong> ${esc(formatPhoneNumber(app.mobile_number) || '-')}</div>
                    <div class="col-md-6"><strong>Email:</strong> ${esc(app.email || '-')}</div>
                    <div class="col-md-6"><strong>Household Role:</strong> ${esc(toTitleCase(roleInfo.label || '-'))}</div>
                    <div class="col-md-6"><strong>Relationship to Head:</strong> ${esc(toTitleCase(roleInfo.relationship || '-'))}</div>
                    <div class="col-md-6"><strong>Emergency Contact:</strong> ${esc(toTitleCase(app.emergency_contact_name || '-'))}</div>
                    <div class="col-md-6"><strong>Emergency Number:</strong> ${esc(formatPhoneNumber(app.emergency_contact_number) || '-')}</div>
                    <div class="col-md-6"><strong>Relationship:</strong> ${esc(toTitleCase(app.emergency_contact_relationship || '-'))}</div>
                    <div class="col-md-6"><strong>Employment:</strong> ${esc(toTitleCase(app.employment_status || '-'))}</div>
                    <div class="col-md-6"><strong>Education:</strong> ${esc(toTitleCase(app.educational_attainment || '-'))}</div>
                    <div class="col-md-6"><strong>Submitted:</strong> ${formatDate(app.created_at)}</div>
                    <div class="col-md-6"><strong>Status:</strong> ${getStatusBadge(app.record_status)}</div>
                    <div class="col-md-6"><strong>Valid ID:</strong> ${idDoc}</div>
                    <div class="col-md-6"><strong>Proof of Residency:</strong> ${proofDoc}</div>
                </div>
            `;

            const footer = document.getElementById('viewModalFooter');
            if (footer) {
                let footerButtons = '';
                if (RES_APP_PERMS.canEdit && (app.record_status === 'pending')) {
                    footerButtons += `
                        <button type="button" class="btn btn-success me-2" onclick="openApprove(${app.id})">
                            <i class="bi bi-check-lg"></i> Approve
                        </button>
                        <button type="button" class="btn btn-outline-danger me-auto" onclick="openReject(${app.id})">
                            <i class="bi bi-x-lg"></i> Reject
                        </button>
                    `;
                }
                footerButtons += '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
                footer.innerHTML = footerButtons;
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
    const display = document.getElementById('approveIdDisplay');
    if (display) {
        display.value = id;
    }
    document.getElementById('approveRemarks').value = '';
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function submitApprove() {
    if (isApproveSubmitting) return;
    const id = document.getElementById('approveId').value;
    const remarks = document.getElementById('approveRemarks').value.trim();
    const approveBtn = document.getElementById('btnApprove');
    isApproveSubmitting = true;
    if (approveBtn) approveBtn.disabled = true;
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
        })
        .finally(() => {
            isApproveSubmitting = false;
            if (approveBtn) approveBtn.disabled = false;
        });
}

function openReject(id) {
    if (!RES_APP_PERMS.canEdit) return;
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function submitReject() {
    if (isRejectSubmitting) return;
    const id = document.getElementById('rejectId').value;
    const reason = document.getElementById('rejectReason').value.trim();
    const rejectBtn = document.getElementById('btnReject');
    isRejectSubmitting = true;
    if (rejectBtn) rejectBtn.disabled = true;
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
        })
        .finally(() => {
            isRejectSubmitting = false;
            if (rejectBtn) rejectBtn.disabled = false;
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

function buildFilePreview(path, label) {
    if (!path) return '<span class="text-muted">None</span>';
    const trimmed = path.trim();
    const base = window.RESIDENT_APPLICATIONS_BASE_URL || '';
    let resolvedPath = trimmed.replace(/^\/+/, '');

    // Backward compatibility: old records may store "applications/..." while newer store "uploads/applications/...".
    if (!resolvedPath.startsWith('uploads/')) {
        if (resolvedPath.startsWith('applications/')) {
            resolvedPath = 'uploads/' + resolvedPath;
        } else {
            resolvedPath = 'uploads/applications/' + resolvedPath;
        }
    }

    const url = trimmed.startsWith('http') ? trimmed : (base + resolvedPath);
    const ext = (resolvedPath.split('.').pop() || '').toLowerCase();
    const safeUrl = esc(url);
    const fallbackLink = buildFileLink(path, label);

    if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) {
        // Inline preview for images.
        return `
            <div>
                <img src="${safeUrl}" alt="${esc(label)}" class="img-fluid rounded border" style="max-height:260px; object-fit:contain;">
                <div class="mt-1 small text-muted">${fallbackLink}</div>
            </div>
        `;
    }

    if (ext === 'pdf') {
        // Inline preview for PDFs.
        return `
            <div>
                <iframe src="${safeUrl}" title="${esc(label)}" style="width:100%; height:420px; border:1px solid #e2e8f0; border-radius:10px;"></iframe>
                <div class="mt-1 small text-muted">${fallbackLink}</div>
            </div>
        `;
    }

    // Fallback: show clickable link if we can't preview.
    return buildFileLink(path, label);
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
