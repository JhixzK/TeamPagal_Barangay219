if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}
const BASE_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/public/';

document.addEventListener('DOMContentLoaded', function() {
    loadCertificates();
});

function loadCertificates() {
    fetch(window.API_URL + 'certificates.php?action=list')
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
                                <a href="${BASE_URL}certificate-print.php?id=${c.id}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-printer"></i> Print/PDF
                                </a>
                            ` : ''}
                            ${c.status === 'pending' ? `
                                <button class="btn btn-sm btn-success" onclick="updateStatus(${c.id}, 'approved')">Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="rejectCert(${c.id})">Reject</button>
                            ` : ''}
                            ${c.status === 'approved' ? `
                                <button class="btn btn-sm btn-info" onclick="releaseCert(${c.id})">Release</button>
                            ` : ''}
                            <button class="btn btn-sm btn-outline-secondary" onclick="viewCert(${c.id})">View</button>
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

function updateStatus(id, status) {
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('status', status);
    fetch(window.API_URL + 'certificates.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadCertificates(); else alert(d.message || 'Error'); });
}

function rejectCert(id) {
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
