<?php
/**
 * E-Barangay - Resident Applications Review (Barangay Staff)
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY]);

$page_title = 'Resident Applications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-person"></i> Resident Applications</h2>
        </div>

        <ul class="nav nav-tabs mb-3" id="statusTabs">
            <li class="nav-item">
                <a class="nav-link active" href="#" data-status="pending">Pending</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-status="approved">Approved</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-status="rejected">Rejected</a>
            </li>
        </ul>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Name</th>
                        <th>Birth Date</th>
                        <th>Sex</th>
                        <th>Contact</th>
                        <th>Submitted</th>
                        <th>Reviewed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsTableBody">
                    <tr><td colspan="9" class="text-center py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <nav class="mt-3"><ul class="pagination justify-content-center" id="pagination"></ul></nav>
    </div>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
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

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approveId">
                <div class="mb-3">
                    <label class="form-label">Remarks (optional)</label>
                    <textarea class="form-control" id="approveRemarks" rows="2"></textarea>
                </div>
                <p class="text-muted small">Upon approval, a Resident ID will be generated and the applicant may activate their account.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnApprove"><i class="bi bi-check"></i> Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectId">
                <div class="mb-3">
                    <label class="form-label">Rejection Reason (optional)</label>
                    <textarea class="form-control" id="rejectReason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnReject"><i class="bi bi-x"></i> Reject</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
(function() {
    let currentStatus = 'pending';
    let currentPage = 1;

    function loadApplications() {
        const tbody = document.getElementById('applicationsTableBody');
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border"></div></td></tr>';
        fetch(API_URL + 'applications.php?action=list&status=' + currentStatus + '&page=' + currentPage)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-danger">' + data.message + '</td></tr>';
                    return;
                }
                const apps = data.data.applications || [];
                if (apps.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No applications found.</td></tr>';
                } else {
                    tbody.innerHTML = apps.map(a => `
                        <tr>
                            <td><code>${escapeHtml(a.application_ref)}</code></td>
                            <td>${escapeHtml(a.last_name + ', ' + a.first_name)}</td>
                            <td>${escapeHtml(a.birth_date)}</td>
                            <td>${escapeHtml(a.sex)}</td>
                            <td>${escapeHtml(a.mobile_number)}</td>
                            <td>${escapeHtml(a.created_at)}</td>
                            <td>${a.reviewed_at ? escapeHtml(a.reviewed_at) : '-'}</td>
                            <td><span class="badge bg-${a.record_status === 'approved' ? 'success' : a.record_status === 'rejected' ? 'danger' : 'warning'}">${a.record_status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewApp(${a.id})"><i class="bi bi-eye"></i></button>
                                ${a.record_status === 'pending' ? `
                                <button class="btn btn-sm btn-success" onclick="openApprove(${a.id})"><i class="bi bi-check"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="openReject(${a.id})"><i class="bi bi-x"></i></button>
                                ` : ''}
                            </td>
                        </tr>
                    `).join('');
                }
                renderPagination(data.data.total_pages, data.data.page);
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="9" class="text-danger">Failed to load.</td></tr>';
            });
    }

    function renderPagination(totalPages, page) {
        const ul = document.getElementById('pagination');
        if (totalPages <= 1) { ul.innerHTML = ''; return; }
        let html = '';
        if (page > 1) html += '<li class="page-item"><a class="page-link" href="#" data-p="' + (page - 1) + '">Prev</a></li>';
        for (let i = 1; i <= totalPages; i++) {
            html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-p="' + i + '">' + i + '</a></li>';
        }
        if (page < totalPages) html += '<li class="page-item"><a class="page-link" href="#" data-p="' + (page + 1) + '">Next</a></li>';
        ul.innerHTML = html;
        ul.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', e => { e.preventDefault(); currentPage = parseInt(a.dataset.p); loadApplications(); });
        });
    }

    function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

    window.viewApp = function(id) {
        fetch(API_URL + 'applications.php?action=get&id=' + id)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return alert(data.message);
                const a = data.data;
                document.getElementById('viewAppRef').textContent = a.application_ref;
                const sections = [
                    ['Personal', { 'Full Name': (a.first_name + ' ' + (a.middle_name || '') + ' ' + a.last_name + ' ' + (a.suffix || '')).trim(), 'Sex': a.sex, 'Birth Date': a.birth_date, 'Place of Birth': a.place_of_birth, 'Civil Status': a.civil_status, 'Citizenship': a.citizenship }],
                    ['Family', { 'Family Code': a.family_code, 'Relationship to Head': a.relationship_to_head }],
                    ['Address', { 'House/Street': (a.house_number || '') + ' ' + (a.street || ''), 'Purok/Sitio': a.purok_sitio, 'Barangay': a.barangay, 'City': a.city, 'Province': a.province, 'Length of Residency': a.length_of_residency_years ? a.length_of_residency_years + ' years' : '' }],
                    ['Contact', { 'Mobile': a.mobile_number, 'Email': a.email, 'Emergency': a.emergency_contact_name + ' - ' + a.emergency_contact_number + ' (' + a.emergency_contact_relationship + ')' }],
                    ['Education & Employment', { 'Educational Attainment': a.educational_attainment, 'Employment Status': a.employment_status, 'Occupation': a.occupation }],
                    ['Special', { 'Senior Citizen': a.is_senior_citizen ? 'Yes' : 'No', 'PWD': a.is_pwd ? 'Yes' : 'No', 'Solo Parent': a.is_solo_parent ? 'Yes' : 'No', 'IP Member': a.is_ip_member ? 'Yes' : 'No', '4Ps': a.is_4ps_beneficiary ? 'Yes' : 'No' }],
                    ['ID', { 'Valid ID Type': a.valid_id_type, 'Valid ID Number': a.valid_id_number }]
                ];
                let html = '';
                sections.forEach(([title, kv]) => {
                    html += '<h6 class="text-primary mt-3">' + title + '</h6><table class="table table-sm"><tbody>';
                    Object.entries(kv).forEach(([k, v]) => { if (v) html += '<tr><td>' + escapeHtml(k) + '</td><td>' + escapeHtml(v) + '</td></tr>'; });
                    html += '</tbody></table>';
                });
                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModalFooter').innerHTML = a.record_status === 'pending' ?
                    '<button class="btn btn-success" onclick="openApprove(' + a.id + '); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();"><i class="bi bi-check"></i> Approve</button>' +
                    '<button class="btn btn-danger" onclick="openReject(' + a.id + '); bootstrap.Modal.getInstance(document.getElementById(\'viewModal\')).hide();"><i class="bi bi-x"></i> Reject</button>' : '';
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            });
    };

    window.openApprove = function(id) {
        document.getElementById('approveId').value = id;
        document.getElementById('approveRemarks').value = '';
        new bootstrap.Modal(document.getElementById('approveModal')).show();
    };

    window.openReject = function(id) {
        document.getElementById('rejectId').value = id;
        document.getElementById('rejectReason').value = '';
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    };

    document.getElementById('btnApprove').addEventListener('click', function() {
        const id = document.getElementById('approveId').value;
        const remarks = document.getElementById('approveRemarks').value;
        const btn = this;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'approve');
        fd.append('id', id);
        fd.append('remarks', remarks);
        fetch(API_URL + 'applications.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
                if (data.success) {
                    alert('Approved! Resident ID: ' + (data.data?.resident_code || '') + '\nActivation link: ' + (data.data?.activation_link || ''));
                    loadApplications();
                } else {
                    alert(data.message);
                }
            })
            .catch(() => { btn.disabled = false; alert('Error'); });
    });

    document.getElementById('btnReject').addEventListener('click', function() {
        const id = document.getElementById('rejectId').value;
        const reason = document.getElementById('rejectReason').value;
        const btn = this;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('id', id);
        fd.append('rejection_reason', reason);
        fetch(API_URL + 'applications.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
                if (data.success) { loadApplications(); } else { alert(data.message); }
            })
            .catch(() => { btn.disabled = false; alert('Error'); });
    });

    document.querySelectorAll('#statusTabs .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.dataset.status;
            currentPage = 1;
            loadApplications();
        });
    });

    loadApplications();
})();
</script>
