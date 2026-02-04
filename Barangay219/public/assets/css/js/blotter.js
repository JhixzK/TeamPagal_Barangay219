// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

document.addEventListener('DOMContentLoaded', function() {
    loadBlotters();
});

function loadBlotters() {
    fetch(window.API_URL + 'blotter.php?action=list')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('blotterTableBody');
            if (d.success) {
                tbody.innerHTML = d.data.map(b => `
                    <tr>
                        <td>${b.id}</td>
                        <td>${b.case_title}</td>
                        <td>${b.complainant_name}</td>
                        <td>${b.respondent_name || '-'}</td>
                        <td>${formatDate(b.incident_date)}</td>
                        <td><span class="badge bg-${getStatusColor(b.status)}">${b.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewBlotter(${b.id})">View</button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No blotter records found or access denied</td></tr>';
                console.warn('Blotter API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading blotters:', err);
            const tbody = document.getElementById('blotterTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading blotters</td></tr>';
        });
}

function viewBlotter(id) {
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(`Case: ${d.data.case_title}\nDescription: ${d.data.description}`);
            }
        });
}

function getStatusColor(status) {
    const colors = { 'pending': 'warning', 'resolved': 'success', 'settled': 'info' };
    return colors[status] || 'secondary';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
