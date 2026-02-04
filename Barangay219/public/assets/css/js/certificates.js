// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

document.addEventListener('DOMContentLoaded', function() {
    loadCertificates();
});

function loadCertificates() {
    fetch(window.API_URL + 'certificates.php?action=list')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('certTableBody');
            if (d.success) {
                tbody.innerHTML = d.data.map(c => `
                    <tr>
                        <td>${c.id}</td>
                        <td>${c.resident_name || '-'}</td>
                        <td>${c.certificate_type.replace(/_/g, ' ')}</td>
                        <td><span class="badge bg-${getStatusColor(c.status)}">${c.status}</span></td>
                        <td>${formatDate(c.created_at)}</td>
                        <td>
                            ${c.status === 'pending' ? `
                                <button class="btn btn-sm btn-success" onclick="updateStatus(${c.id}, 'approved')">Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="updateStatus(${c.id}, 'rejected')">Reject</button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No certificates found or access denied</td></tr>';
                console.warn('Certificates API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading certificates:', err);
            const tbody = document.getElementById('certTableBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading certificates</td></tr>';
        });
}

function updateStatus(id, status) {
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('status', status);
    fetch(window.API_URL + 'certificates.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadCertificates();
            }
        });
}

function getStatusColor(status) {
    const colors = { 'pending': 'warning', 'approved': 'success', 'rejected': 'danger', 'issued': 'info' };
    return colors[status] || 'secondary';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
