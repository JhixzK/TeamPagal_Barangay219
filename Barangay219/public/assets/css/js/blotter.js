document.addEventListener('DOMContentLoaded', function() {
    loadBlotters();
});

function loadBlotters() {
    fetch('<?php echo API_URL; ?>blotter.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const tbody = document.getElementById('blotterTableBody');
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
            }
        });
}

function viewBlotter(id) {
    fetch(`<?php echo API_URL; ?>blotter.php?action=get&id=${id}`)
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
