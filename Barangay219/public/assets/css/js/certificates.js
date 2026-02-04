document.addEventListener('DOMContentLoaded', function() {
    loadCertificates();
});

function loadCertificates() {
    fetch('<?php echo API_URL; ?>certificates.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const tbody = document.getElementById('certTableBody');
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
            }
        });
}

function updateStatus(id, status) {
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('status', status);
    fetch('<?php echo API_URL; ?>certificates.php', { method: 'POST', body: fd })
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
