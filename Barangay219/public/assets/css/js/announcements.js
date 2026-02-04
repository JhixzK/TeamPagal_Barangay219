document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
});

function loadAnnouncements() {
    fetch('<?php echo API_URL; ?>announcement.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const tbody = document.getElementById('announcementsTableBody');
                tbody.innerHTML = d.data.map(a => `
                    <tr>
                        <td>${a.id}</td>
                        <td>${a.title}</td>
                        <td>${a.posted_by_name || '-'}</td>
                        <td>${formatDate(a.date_posted)}</td>
                        <td><span class="badge bg-${a.status === 'active' ? 'success' : 'secondary'}">${a.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewAnnouncement(${a.id})">View</button>
                        </td>
                    </tr>
                `).join('');
            }
        });
}

function viewAnnouncement(id) {
    fetch(`<?php echo API_URL; ?>announcement.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(`Title: ${d.data.title}\n\nContent: ${d.data.content}`);
            }
        });
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
