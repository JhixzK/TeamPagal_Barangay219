// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
});

function loadAnnouncements() {
    fetch(window.API_URL + 'announcement.php?action=list')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('announcementsTableBody');
            if (d.success) {
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
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No announcements found or access denied</td></tr>';
                console.warn('Announcements API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading announcements:', err);
            const tbody = document.getElementById('announcementsTableBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading announcements</td></tr>';
        });
}

function viewAnnouncement(id) {
    fetch(`${window.API_URL}announcement.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(`Title: ${d.data.title}\n\nContent: ${d.data.content}`);
            }
        });
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
