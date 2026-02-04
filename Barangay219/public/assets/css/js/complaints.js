// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

document.addEventListener('DOMContentLoaded', function() {
    loadComplaints();
});

function loadComplaints() {
    fetch(window.API_URL + 'complaints.php?action=list')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('complaintsTableBody');
            if (d.success) {
                tbody.innerHTML = d.data.map(c => `
                    <tr>
                        <td>${c.id}</td>
                        <td>${c.complaint_title}</td>
                        <td>${c.complainant_name}</td>
                        <td>${c.respondent_name || '-'}</td>
                        <td>${formatDate(c.filing_date)}</td>
                        <td><span class="badge bg-${getStatusColor(c.status)}">${c.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewComplaint(${c.id})">View</button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No complaints found or access denied</td></tr>';
                console.warn('Complaints API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading complaints:', err);
            const tbody = document.getElementById('complaintsTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading complaints</td></tr>';
        });
}

function viewComplaint(id) {
    fetch(`${window.API_URL}complaints.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(`Complaint: ${d.data.complaint_title}\nNarrative: ${d.data.narrative}`);
            }
        });
}

function getStatusColor(status) {
    const colors = { 'pending': 'warning', 'resolved': 'success', 'dismissed': 'danger' };
    return colors[status] || 'secondary';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
