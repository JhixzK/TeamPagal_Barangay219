if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}
const BASE_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/public/';

const CERT_PERMS = {
    canEdit: window.canModulePermission ? window.canModulePermission('certificates', 'can_edit') : true,
    canCreateApplication: window.canModulePermission ? window.canModulePermission('applications', 'can_create') : true
};

let certificateFilters = { q: '', status: '', type: '', from: '', to: '' };

document.addEventListener('DOMContentLoaded', function() {
    loadCertificates();
    applyCertificatePermissions();
    initCertificateStatFilters();
});

function applyCertificatePermissions() {
    if (!CERT_PERMS.canCreateApplication) {
        const btn = document.getElementById('btnOpenApplications');
        if (btn) btn.style.display = 'none';
    }
}

function loadCertificates() {
    const params = new URLSearchParams({ action: 'list' });
    if (certificateFilters.q) params.append('q', certificateFilters.q);
    if (certificateFilters.status) params.append('status', certificateFilters.status);
    if (certificateFilters.type) params.append('type', certificateFilters.type);
    if (certificateFilters.from) params.append('from', certificateFilters.from);
    if (certificateFilters.to) params.append('to', certificateFilters.to);

    fetch(window.API_URL + 'certificates.php?' + params.toString())
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('certTableBody');
            if (d.success) {
                const certs = d.data.certificates || d.data || [];
                tbody.innerHTML = certs.map(c => `
                    <tr>
                        <td>${c.id}</td>
                        <td>${escapeHtml(c.resident_name || '-')}</td>
                        <td>${escapeHtml((c.certificate_type || '').replace(/_/g, ' '))}</td>
                        <td><code>${escapeHtml(c.application_ref || 'APP-'+c.id)}</code></td>
                        <td><span class="badge bg-${getStatusColor(c.status)}">${c.status}</span></td>
                        <td>${formatDate(c.created_at)}</td>
                        <td>${c.control_number ? escapeHtml(c.control_number) : '-'}</td>
                        <td>
                            ${c.status === 'issued' ? `
                                <a href="${BASE_URL}certificate-print.php?id=${c.id}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print / PDF" aria-label="Print / PDF">
                                    <i class="bi bi-printer"></i>
                                </a>
                            ` : ''}
                            ${CERT_PERMS.canEdit && c.status === 'pending' ? `
                                <button class="btn btn-sm btn-success" title="Approve" aria-label="Approve" onclick="updateStatus(${c.id}, 'approved')"><i class="bi bi-check-lg"></i></button>
                                <button class="btn btn-sm btn-outline-danger" title="Reject" aria-label="Reject" onclick="rejectCert(${c.id})"><i class="bi bi-x-lg"></i></button>
                            ` : ''}
                            ${CERT_PERMS.canEdit && c.status === 'approved' ? `
                                <button class="btn btn-sm btn-info" title="Release" aria-label="Release" onclick="releaseCert(${c.id})"><i class="bi bi-box-arrow-up-right"></i></button>
                            ` : ''}
                            <button class="btn btn-sm btn-primary" title="View" aria-label="View" onclick="viewCert(${c.id})"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No certificates found</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            const tbody = document.getElementById('certTableBody');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading certificates</td></tr>';
        });
}

function searchCertificates() {
    const query = document.getElementById('searchInput')?.value.trim() || '';
    certificateFilters.q = query;
    loadCertificates();
}

function applyCertificateFilters() {
    certificateFilters.status = document.getElementById('filterStatus')?.value || '';
    certificateFilters.type = document.getElementById('filterType')?.value || '';
    certificateFilters.from = document.getElementById('filterFrom')?.value || '';
    certificateFilters.to = document.getElementById('filterTo')?.value || '';
    loadCertificates();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetCertificates() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    certificateFilters = { q: '', status: '', type: '', from: '', to: '' };
    const statusSel = document.getElementById('filterStatus');
    const typeSel = document.getElementById('filterType');
    const fromInput = document.getElementById('filterFrom');
    const toInput = document.getElementById('filterTo');
    if (statusSel) statusSel.value = '';
    if (typeSel) typeSel.value = '';
    if (fromInput) fromInput.value = '';
    if (toInput) toInput.value = '';
    loadCertificates();
}

function initCertificateStatFilters() {
    const container = document.querySelector('.module-stats[data-module="certificates"]');
    if (!container) return;
    container.querySelectorAll('[data-status]').forEach(card => {
        const handleClick = () => {
            const status = card.getAttribute('data-status') || '';
            certificateFilters.status = status;
            const statusSelect = document.getElementById('filterStatus');
            if (statusSelect) statusSelect.value = status;
            loadCertificates();
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

function updateStatus(id, status) {
    if (!CERT_PERMS.canEdit) { alert('Access denied'); return; }
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('status', status);
    fetch(window.API_URL + 'certificates.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadCertificates(); else alert(d.message || 'Error'); });
}

function rejectCert(id) {
    if (!CERT_PERMS.canEdit) { alert('Access denied'); return; }
    const reason = prompt('Rejection reason (optional):');
    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('id', id);
    if (reason) fd.append('reason', reason);
    fetch(window.API_URL + 'certificates.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadCertificates(); else alert(d.message || 'Error'); });
}

function releaseCert(id) {
    if (!CERT_PERMS.canEdit) { alert('Access denied'); return; }
    if (!confirm('Release certificate and assign control number?')) return;
    const fd = new FormData();
    fd.append('action', 'release');
    fd.append('id', id);
    fetch(window.API_URL + 'certificates.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Released. Control #: ' + (d.data?.control_number || ''));
                loadCertificates();
            } else alert(d.message || 'Error');
        });
}

function viewCert(id) {
    window.location.href = BASE_URL + 'certificate-print.php?id=' + id;
}

function getStatusColor(status) {
    const colors = { 'pending': 'warning', 'approved': 'info', 'rejected': 'danger', 'issued': 'success' };
    return colors[status] || 'secondary';
}

function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
