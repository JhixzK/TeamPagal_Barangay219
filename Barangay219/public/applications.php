<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY]);

$page_title = 'Certificate Applications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-person"></i> Certificate Applications</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-plus-lg"></i> New Application
            </button>
        </div>

        <ul class="nav nav-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="approved">Approved</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="issued">Released</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="rejected">Rejected</a></li>
        </ul>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Resident</th>
                        <th>Type</th>
                        <th>Purpose</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                <p>Assign control number and mark as released?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnRelease"><i class="bi bi-box-arrow-up"></i> Release</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
(function() {
    let currentStatus = '';
    let currentPage = 1;

    function loadApplications() {
        const tbody = document.getElementById('applicationsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border"></div></td></tr>';
        let url = 'certificates.php?action=list&page=' + currentPage;
        if (currentStatus) url += '&status=' + currentStatus;
        fetch(API_URL + url)
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
                            <td><code>${esc(a.application_ref || 'APP-'+a.id)}</code></td>
                            <td>${esc(a.resident_name || '-')}</td>
                            <td>${esc((a.certificate_type || '').replace(/_/g, ' '))}</td>
                            <td>${esc((a.purpose || '').substring(0,30))}${(a.purpose||'').length>30?'...':''}</td>
                            <td>${formatDate(a.created_at)}</td>
                            <td><span class="badge bg-${getStatusColor(a.status)}">${a.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewApp(${a.id})"><i class="bi bi-eye"></i></button>
                                ${a.status === 'pending' ? `
                                <button class="btn btn-sm btn-success" onclick="updateStatus(${a.id}, 'approved')">Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="rejectApp(${a.id})">Reject</button>
                                ` : ''}
                                ${a.status === 'approved' ? `<button class="btn btn-sm btn-info" onclick="openRelease(${a.id})">Release</button>` : ''}
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

    function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function getStatusColor(s) {
        const c = { 'pending':'warning','approved':'info','issued':'success','rejected':'danger','released':'success' };
        return c[s] || 'secondary';
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
                        <tr><td><strong>Resident</strong></td><td>${esc(a.resident_name)}</td></tr>
                        <tr><td><strong>Certificate Type</strong></td><td>${esc((a.certificate_type||'').replace(/_/g,' '))}</td></tr>
                        <tr><td><strong>Purpose</strong></td><td>${esc(a.purpose) || '-'}</td></tr>
                        <tr><td><strong>Status</strong></td><td><span class="badge bg-${getStatusColor(a.status)}">${a.status}</span></td></tr>
                        <tr><td><strong>Control Number</strong></td><td>${esc(a.control_number) || '-'}</td></tr>
                        <tr><td><strong>Created</strong></td><td>${formatDate(a.created_at)}</td></tr>
                        <tr><td><strong>Issued Date</strong></td><td>${formatDate(a.issued_date) || '-'}</td></tr>
                    </table>
                `;
                document.getElementById('viewModalBody').innerHTML = html;
                let footer = '';
                if (a.status === 'pending') {
                    footer = '<button class="btn btn-success" onclick="updateStatus('+a.id+',\'approved\'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Approve</button>' +
                        '<button class="btn btn-danger" onclick="rejectApp('+a.id+'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Reject</button>';
                } else if (a.status === 'approved') {
                    footer = '<button class="btn btn-info" onclick="openRelease('+a.id+'); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();">Release</button>';
                }
                footer += '<a href="<?php echo BASE_URL; ?>certificates.php?id='+a.id+'" class="btn btn-primary">View in Certificates</a>';
                document.getElementById('viewModalFooter').innerHTML = footer;
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            });
    };

    window.updateStatus = function(id, status) {
        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', id);
        fd.append('status', status);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) { loadApplications(); } else alert(d.message || 'Error'); });
    };

    window.rejectApp = function(id) {
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
        document.getElementById('releaseId').value = id;
        new bootstrap.Modal(document.getElementById('releaseModal')).show();
    };

    document.getElementById('btnRelease').addEventListener('click', function() {
        const id = document.getElementById('releaseId').value;
        const fd = new FormData();
        fd.append('action', 'release');
        fd.append('id', id);
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
        const residentId = document.getElementById('createResidentId').value;
        const certType = document.getElementById('createCertType').value;
        const purpose = document.getElementById('createPurpose').value;
        const remarks = document.getElementById('createRemarks').value;
        if (!residentId || !certType) { alert('Resident and certificate type required'); return; }
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('resident_id', residentId);
        fd.append('certificate_type', certType);
        fd.append('purpose', purpose);
        fd.append('remarks', remarks);
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

    loadResidents();
    loadApplications();
})();
</script>
