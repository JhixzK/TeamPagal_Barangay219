document.addEventListener('DOMContentLoaded', function() {
    loadComplaints();
});

function loadComplaints() {
    fetch('<?php echo API_URL; ?>complaints.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const tbody = document.getElementById('complaintsTableBody');
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
            }
        });
}

function viewComplaint(id) {
    fetch(`<?php echo API_URL; ?>complaints.php?action=get&id=${id}`)
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
