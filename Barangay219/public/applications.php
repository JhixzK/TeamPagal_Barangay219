<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!canAccessModule('applications') && !canAccessModule('certificates')) {
    header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
    exit();
}

$page_title = 'Certificate Applications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page apps-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Services Module</p>
                    <h2 class="mb-1"><i class="bi bi-file-earmark-person me-2"></i>Certificate Applications</h2>
                    <p class="module-subtitle mb-0">Manage requests, approvals, releases, and certificate records.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg"></i> New Application
                </button>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by resident, ref #, or purpose...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchApplications()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetApplications()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs app-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="under_review">Under Review</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="approved">Approved</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="issued">Released</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="rejected">Rejected</a></li>
        </ul>

        <div class="table-responsive data-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="text-center">Ref #</th>
                        <th class="text-center">Resident</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Purpose</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsTableBody">
                    <tr><td colspan="7" class="text-center py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <nav class="mt-3"><ul class="pagination justify-content-center" id="pagination"></ul></nav>
    </div>
</div>

<style>
.apps-page .module-hero {
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
    border: 1px solid rgba(37, 99, 235, 0.18) !important;
}

.apps-page .module-kicker {
    letter-spacing: 0.08em;
    color: #475569;
    font-weight: 700;
}

.apps-page .module-subtitle {
    color: #64748b;
}

.apps-page .search-bar {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.35);
}

.apps-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    border-bottom: 0;
    gap: 0.45rem;
}

.apps-page .app-tabs .nav-item {
    margin: 0;
}

.apps-page .app-tabs .nav-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.55rem 0.85rem;
    background: #ffffff;
    white-space: nowrap;
}

.apps-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

.apps-page .data-table .table th,
.apps-page .data-table .table td {
    vertical-align: middle;
}

.apps-page .data-table code {
    background: #f1f5f9;
    color: #0f172a;
    padding: 0.1rem 0.35rem;
    border-radius: 6px;
}

@media (max-width: 992px) {
    .apps-page .app-tabs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 576px) {
    .apps-page .app-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<!-- Create Application Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Certificate Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Resident <span class="text-danger">*</span></label>
                    <select class="form-select" id="createResidentId" required>
                        <option value="">-- Select Resident --</option>
                    </select>
                    <small class="text-muted">Or <a href="<?php echo BASE_URL; ?>residents.php">add new resident</a> first</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Certificate Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="createCertType" required>
                        <option value="">-- Select Type --</option>
                        <option value="barangay_clearance">Barangay Clearance</option>
                        <option value="certificate_residency">Certificate of Residency</option>
                        <option value="certificate_indigency">Certificate of Indigency</option>
                        <option value="certificate_good_moral">Certificate of Good Moral</option>
                        <option value="transfer_request">Transfer Request</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose</label>
                    <input type="text" class="form-control" id="createPurpose" placeholder="e.g., Employment, Scholarship">
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" id="createRemarks" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnCreate"><i class="bi bi-check"></i> Create Application</button>
            </div>
        </div>
    </div>
</div>

<!-- View/Edit Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details - <span id="viewAppRef"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
            <div class="modal-footer" id="viewModalFooter"></div>
        </div>
    </div>
</div>

<!-- Release Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Release Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="releaseId">
                <div class="mb-2 small text-muted">Edit certificate content before issuing.</div>
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="releaseCertName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="releaseCertAddress" rows="2" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="releaseCertPurpose" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Certificate Body <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="releaseCertBody" rows="4" required></textarea>
                    <small class="text-muted">You may edit the certificate wording if necessary. Placeholders will automatically be replaced.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date Issued <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="releaseDateIssued" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Control Number</label>
                    <input type="text" class="form-control" id="releaseControlNumber" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnRelease"><i class="bi bi-box-arrow-up"></i> Release</button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Applications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Certificate Type</label>
                    <select class="form-select" id="filterType">
                        <option value="">All</option>
                        <option value="barangay_clearance">Barangay Clearance</option>
                        <option value="certificate_residency">Certificate of Residency</option>
                        <option value="certificate_indigency">Certificate of Indigency</option>
                        <option value="certificate_good_moral">Certificate of Good Moral</option>
                        <option value="transfer_request">Transfer Request</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterFrom">
                </div>
                <div class="mb-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" id="filterTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyApplicationFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script>
(function() {
    let currentStatus = '';
    let currentPage = 1;
    let applicationFilters = { q: '', type: '', from: '', to: '' };
    let releaseAutoBodyEnabled = true;
    let releaseLastAutoBody = '';
    const APP_PERMS = {
        canCreate: window.canModulePermission
            ? (window.canModulePermission('applications', 'can_create') || window.canModulePermission('certificates', 'can_create'))
            : true,
        canEdit: window.canModulePermission
            ? (window.canModulePermission('applications', 'can_edit') || window.canModulePermission('certificates', 'can_edit'))
            : true
    };

    function applyApplicationPermissions() {
        if (!APP_PERMS.canCreate) {
            const openBtn = document.getElementById('btnOpenCreate');
            if (openBtn) openBtn.style.display = 'none';
            const createBtn = document.getElementById('btnCreate');
            if (createBtn) createBtn.style.display = 'none';
        }
        if (!APP_PERMS.canEdit) {
            const releaseBtn = document.getElementById('btnRelease');
            if (releaseBtn) releaseBtn.style.display = 'none';
        }
    }

    function loadApplications() {
        const tbody = document.getElementById('applicationsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border"></div></td></tr>';
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage.toString()
        });
        if (currentStatus) params.append('status', currentStatus);
        if (applicationFilters.q) params.append('q', applicationFilters.q);
        if (applicationFilters.type) params.append('type', applicationFilters.type);
        if (applicationFilters.from) params.append('from', applicationFilters.from);
        if (applicationFilters.to) params.append('to', applicationFilters.to);

        fetch(API_URL + 'certificates.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-danger">' + (data.message || 'Error') + '</td></tr>';
                    return;
                }
                const apps = data.data.certificates || data.data || [];
                if (apps.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No applications found.</td></tr>';
                } else {
                      tbody.innerHTML = apps.map(a => `
                          <tr>
                              <td class="text-center"><code>${esc(a.application_ref || 'APP-'+a.id)}</code></td>
                              <td class="text-center">${esc(toTitleCase(a.resident_name || '-'))}</td>
                              <td class="text-center">${esc(toTitleCase((a.certificate_type || '').replace(/_/g, ' ')))}</td>
                              <td class="text-center">${esc(toTitleCase((a.purpose || '').substring(0,30)))}${(a.purpose||'').length>30?'...':''}</td>
                              <td class="text-center">${formatDate(a.created_at)}</td>
                              <td class="text-center"><span class="badge bg-${getStatusColor(a.status)}">${a.status}</span></td>
                              <td class="text-center">
                                ${a.status === 'issued' ? `<a href="<?php echo BASE_URL; ?>certificate-print.php?id=${a.id}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print / PDF" aria-label="Print / PDF"><i class="bi bi-printer"></i></a>` : ''}
                                <button class="btn btn-sm btn-primary" title="View" aria-label="View" onclick="viewApp(${a.id})"><i class="bi bi-eye"></i></button>
                                  ${APP_PERMS.canEdit && a.status === 'pending' ? `
                                      <button class="btn btn-sm btn-secondary" title="Move to Under Review" aria-label="Move to Under Review" onclick="updateStatus(${a.id}, 'under_review')"><i class="bi bi-search"></i></button>
                                  ` : ''}
                                  ${APP_PERMS.canEdit && a.status === 'under_review' ? `
                                      <button class="btn btn-sm btn-success" title="Approve" aria-label="Approve" onclick="updateStatus(${a.id}, 'approved')"><i class="bi bi-check-lg"></i></button>
                                      <button class="btn btn-sm btn-outline-danger" title="Reject" aria-label="Reject" onclick="rejectApp(${a.id})"><i class="bi bi-x-lg"></i></button>
                                  ` : ''}
                                  ${APP_PERMS.canEdit && a.status === 'approved' ? `<button class="btn btn-sm btn-info" title="Release" aria-label="Release" onclick="openRelease(${a.id})"><i class="bi bi-box-arrow-up-right"></i></button>` : ''}
                                  ${a.status === 'issued' ? (a.control_number ? `<small>${esc(a.control_number)}</small>` : '') : ''}
                              </td>
                          </tr>
                      `).join('');
                }
                const totalPages = data.data.total_pages || 1;
                renderPagination(totalPages, data.data.page || 1);
            })
            .catch(() => { tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Failed to load.</td></tr>'; });
    }

    function renderPagination(totalPages, page) {
        const ul = document.getElementById('pagination');
        if (totalPages <= 1) { ul.innerHTML = ''; return; }
        let html = '';
        if (page > 1) html += '<li class="page-item"><a class="page-link" href="#" data-p="' + (page - 1) + '">Prev</a></li>';
        for (let i = 1; i <= Math.min(totalPages, 10); i++) {
            html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-p="' + i + '">' + i + '</a></li>';
        }
        if (page < totalPages) html += '<li class="page-item"><a class="page-link" href="#" data-p="' + (page + 1) + '">Next</a></li>';
        ul.innerHTML = html;
        ul.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', e => { e.preventDefault(); currentPage = parseInt(a.dataset.p); loadApplications(); });
        });
    }

    window.searchApplications = function() {
        const query = document.getElementById('searchInput')?.value.trim() || '';
        applicationFilters.q = query;
        currentPage = 1;
        loadApplications();
    };

    window.applyApplicationFilters = function() {
        applicationFilters.type = document.getElementById('filterType')?.value || '';
        applicationFilters.from = document.getElementById('filterFrom')?.value || '';
        applicationFilters.to = document.getElementById('filterTo')?.value || '';
        currentPage = 1;
        loadApplications();
        const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
        if (modal) modal.hide();
    };

    window.resetApplications = function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';
        applicationFilters = { q: '', type: '', from: '', to: '' };
        const typeSel = document.getElementById('filterType');
        const fromInput = document.getElementById('filterFrom');
        const toInput = document.getElementById('filterTo');
        if (typeSel) typeSel.value = '';
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
        currentStatus = '';
        document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
        const firstTab = document.querySelector('#statusTabs .nav-link');
        if (firstTab) firstTab.classList.add('active');
        currentPage = 1;
        loadApplications();
    };

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
      function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function getStatusColor(s) {
        const c = { 'pending':'warning','under_review':'secondary','approved':'info','issued':'success','rejected':'danger','released':'success' };
        return c[s] || 'secondary';
    }

    function buildDefaultCertificateTemplate() {
        return [
            'TO WHOM IT MAY CONCERN:',
            '',
            'This is to certify that [NAME], residing at [ADDRESS], is a bona fide resident of Barangay 219, Tondo, Manila.',
            '',
            'This certification is issued upon request for [PURPOSE].',
            '',
            'Issued this [DATE_ISSUED] at Barangay 219, Tondo, Manila.',
            '',
            'Control No: [CONTROL_NUMBER]'
        ].join('\n');
    }

    function formatIssueDateForBody(dateValue) {
        if (!dateValue) return '';
        const parsed = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(parsed.getTime())) return dateValue;
        return parsed.toLocaleDateString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }

    function replaceBodyPlaceholders(template, values) {
        return String(template || '')
            .replace(/\[NAME\]/g, values.name)
            .replace(/\[ADDRESS\]/g, values.address)
            .replace(/\[PURPOSE\]/g, values.purpose)
            .replace(/\[DATE_ISSUED\]/g, values.dateIssued)
            .replace(/\[CONTROL_NUMBER\]/g, values.controlNumber);
    }

    function updateReleaseBodyAuto(force = false) {
        if (!force && !releaseAutoBodyEnabled) return;

        const name = (document.getElementById('releaseCertName')?.value || '').trim();
        const address = (document.getElementById('releaseCertAddress')?.value || '').trim();
        const purpose = (document.getElementById('releaseCertPurpose')?.value || '').trim();
        const dateIssuedRaw = document.getElementById('releaseDateIssued')?.value || '';
        const controlRaw = (document.getElementById('releaseControlNumber')?.value || '').trim();

        const values = {
            name: name || '[NAME]',
            address: address || '[ADDRESS]',
            purpose: purpose || '[PURPOSE]',
            dateIssued: formatIssueDateForBody(dateIssuedRaw) || '[DATE_ISSUED]',
            controlNumber: (controlRaw && !/^Auto-generated/i.test(controlRaw)) ? controlRaw : '[CONTROL_NUMBER]'
        };

        const body = replaceBodyPlaceholders(buildDefaultCertificateTemplate(), values);
        const bodyInput = document.getElementById('releaseCertBody');
        if (bodyInput) {
            bodyInput.value = body;
            releaseLastAutoBody = body;
            releaseAutoBodyEnabled = true;
        }
    }

    function initApplicationStatFilters() {
        const container = document.querySelector('.module-stats[data-module="applications"]');
        if (!container) return;
        container.querySelectorAll('[data-status]').forEach(card => {
            const handleClick = () => {
                const status = card.getAttribute('data-status') || '';
                currentStatus = status;
                currentPage = 1;
                document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
                const activeTab = Array.from(document.querySelectorAll('#statusTabs .nav-link'))
                    .find(l => (l.getAttribute('data-status') || '') === status);
                if (activeTab) activeTab.classList.add('active');
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

    window.viewApp = function(id) {
        fetch(API_URL + 'certificates.php?action=get&id=' + id)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return alert(data.message);
                const a = data.data;
                document.getElementById('viewAppRef').textContent = a.application_ref || 'APP-' + a.id;
                const html = `
                    <table class="table table-sm">
                        <tr><td><strong>Resident</strong></td><td>${esc(toTitleCase(a.resident_name || '-'))}</td></tr>
                        <tr><td><strong>Certificate Type</strong></td><td>${esc(toTitleCase((a.certificate_type||'').replace(/_/g,' ')))}</td></tr>
                        <tr><td><strong>Purpose</strong></td><td>${esc(toTitleCase(a.purpose || '-'))}</td></tr>
                        <tr><td><strong>Status</strong></td><td><span class="badge bg-${getStatusColor(a.status)}">${a.status}</span></td></tr>
                        <tr><td><strong>Certificate Name</strong></td><td>${esc(a.cert_name) || '-'}</td></tr>
                        <tr><td><strong>Certificate Address</strong></td><td>${esc(a.cert_address) || '-'}</td></tr>
                        <tr><td><strong>Certificate Purpose</strong></td><td>${esc(a.cert_purpose) || '-'}</td></tr>
                        <tr><td><strong>Date Issued</strong></td><td>${formatDate(a.date_issued || a.issued_date) || '-'}</td></tr>
                        <tr><td><strong>Control Number</strong></td><td>${esc(a.control_number) || '-'}</td></tr>
                        <tr><td><strong>Created</strong></td><td>${formatDate(a.created_at)}</td></tr>
                        <tr><td><strong>Approved At</strong></td><td>${formatDate(a.approved_at) || '-'}</td></tr>
                    </table>
                `;
                document.getElementById('viewModalBody').innerHTML = html;
                let footer = '';
                if (APP_PERMS.canEdit && a.status === 'pending') {
                    footer = '<button class="btn btn-secondary" onclick="updateStatus('+a.id+',\'under_review\'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Mark Under Review</button>';
                } else if (APP_PERMS.canEdit && a.status === 'under_review') {
                    footer = '<button class="btn btn-success" onclick="updateStatus('+a.id+',\'approved\'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Approve</button>' +
                        '<button class="btn btn-danger" onclick="rejectApp('+a.id+'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Reject</button>';
                } else if (APP_PERMS.canEdit && a.status === 'approved') {
                    footer = '<button class="btn btn-info" onclick="openRelease('+a.id+'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Release</button>';
                }
                if (a.status === 'issued') {
                    footer += ' <a href="<?php echo BASE_URL; ?>certificate-print.php?id='+a.id+'" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print / PDF</a>';
                }
                document.getElementById('viewModalFooter').innerHTML = footer;
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            });
    };

    window.updateStatus = function(id, status) {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', id);
        fd.append('status', status);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) { loadApplications(); } else alert(d.message || 'Error'); });
    };

    window.rejectApp = function(id) {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        const reason = prompt('Rejection reason (optional):');
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('id', id);
        if (reason) fd.append('reason', reason);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) loadApplications(); else alert(d.message || 'Error'); });
    };

    window.openRelease = function(id) {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        fetch(API_URL + 'certificates.php?action=get&id=' + id)
            .then(r => r.json())
            .then(async d => {
                if (!d.success || !d.data) { alert(d.message || 'Unable to load application'); return; }
                const a = d.data;
                document.getElementById('releaseId').value = id;
                document.getElementById('releaseCertName').value = a.cert_name || toTitleCase(a.resident_name || '');
                document.getElementById('releaseCertAddress').value = a.cert_address || a.address || '';
                document.getElementById('releaseCertPurpose').value = a.cert_purpose || a.purpose || '';
                document.getElementById('releaseDateIssued').value = (a.date_issued || new Date().toISOString().slice(0,10));

                if (a.control_number) {
                    document.getElementById('releaseControlNumber').value = a.control_number;
                } else {
                    try {
                        const ctrlRes = await fetch(API_URL + 'certificates.php?action=generate_control');
                        const ctrl = await ctrlRes.json();
                        document.getElementById('releaseControlNumber').value = ctrl?.data?.control_number || 'Auto-generated on issue';
                    } catch (e) {
                        document.getElementById('releaseControlNumber').value = 'Auto-generated on issue';
                    }
                }

                const existingBody = (a.cert_body || '').trim();
                if (existingBody !== '') {
                    const dateIssuedPretty = formatIssueDateForBody(document.getElementById('releaseDateIssued').value || '');
                    const resolvedExisting = replaceBodyPlaceholders(existingBody, {
                        name: (document.getElementById('releaseCertName').value || '').trim(),
                        address: (document.getElementById('releaseCertAddress').value || '').trim(),
                        purpose: (document.getElementById('releaseCertPurpose').value || '').trim(),
                        dateIssued: dateIssuedPretty,
                        controlNumber: (document.getElementById('releaseControlNumber').value || '').trim()
                    });
                    document.getElementById('releaseCertBody').value = resolvedExisting;
                    releaseAutoBodyEnabled = false;
                    releaseLastAutoBody = '';
                } else {
                    updateReleaseBodyAuto(true);
                }

                new bootstrap.Modal(document.getElementById('releaseModal')).show();
            });
    };

    ['releaseCertName', 'releaseCertAddress', 'releaseCertPurpose'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => updateReleaseBodyAuto());
        }
    });

    const releaseDateInput = document.getElementById('releaseDateIssued');
    if (releaseDateInput) {
        releaseDateInput.addEventListener('change', () => updateReleaseBodyAuto());
    }

    const releaseBodyInput = document.getElementById('releaseCertBody');
    if (releaseBodyInput) {
        releaseBodyInput.addEventListener('input', () => {
            if (!releaseAutoBodyEnabled) return;
            if (releaseBodyInput.value !== releaseLastAutoBody) {
                releaseAutoBodyEnabled = false;
            }
        });
    }

    document.getElementById('btnRelease').addEventListener('click', function() {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        const id = document.getElementById('releaseId').value;
        const certName = document.getElementById('releaseCertName').value.trim();
        const certAddress = document.getElementById('releaseCertAddress').value.trim();
        const certPurpose = document.getElementById('releaseCertPurpose').value.trim();
        const certBody = document.getElementById('releaseCertBody').value.trim();
        const dateIssued = document.getElementById('releaseDateIssued').value;

        if (!certName || !certAddress || !certPurpose || !certBody || !dateIssued) {
            alert('Name, address, purpose, certificate body, and date issued are required before issuing.');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'release');
        fd.append('id', id);
        fd.append('cert_name', certName);
        fd.append('cert_address', certAddress);
        fd.append('cert_purpose', certPurpose);
        fd.append('cert_body', certBody);
        fd.append('date_issued', dateIssued);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                bootstrap.Modal.getInstance(document.getElementById('releaseModal')).hide();
                if (d.success) {
                    alert('Released. Control #: ' + (d.data?.control_number || ''));
                    loadApplications();
                } else alert(d.message || 'Error');
            });
    });

    // Load residents for create dropdown
    function loadResidents() {
        fetch(API_URL + 'resident.php?action=list&limit=500')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data && d.data.residents) {
                    const sel = document.getElementById('createResidentId');
                    sel.innerHTML = '<option value="">-- Select Resident --</option>' +
                        d.data.residents.map(r => `<option value="${r.id}">${esc(r.last_name + ', ' + r.first_name + ' ' + (r.middle_name||''))}</option>`).join('');
                }
            });
    }

    document.getElementById('btnCreate').addEventListener('click', function() {
        if (!APP_PERMS.canCreate) { alert('Access denied'); return; }
        const residentId = document.getElementById('createResidentId').value;
        const certType = document.getElementById('createCertType').value;
        const purpose = document.getElementById('createPurpose').value;
        const remarks = document.getElementById('createRemarks').value;
        if (!residentId || !certType) { alert('Resident and certificate type required'); return; }
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('resident_id', residentId);
        fd.append('certificate_type', certType);
        fd.append('purpose', toTitleCase(purpose));
        fd.append('remarks', toTitleCase(remarks));
        this.disabled = true;
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                this.disabled = false;
                if (d.success) {
                    bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
                    document.getElementById('createResidentId').value = '';
                    document.getElementById('createCertType').value = '';
                    document.getElementById('createPurpose').value = '';
                    document.getElementById('createRemarks').value = '';
                    alert('Application created. Ref: ' + (d.data?.application_ref || d.data?.id));
                    loadApplications();
                } else alert(d.message || 'Error');
            })
            .catch(() => { this.disabled = false; alert('Error'); });
    });

    document.querySelectorAll('#statusTabs .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.dataset.status || '';
            currentPage = 1;
            loadApplications();
        });
    });

    applyApplicationPermissions();
    initApplicationStatFilters();
    loadResidents();
    loadApplications();
})();
</script>
