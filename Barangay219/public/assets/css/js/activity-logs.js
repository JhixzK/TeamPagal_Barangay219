/**
 * E-Barangay Information Management System
 * Activity Logs JavaScript
 */

if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    const limitEl = document.getElementById('activityLogLimit');
    if (limitEl) {
        limitEl.addEventListener('change', reloadActivityLogs);
    }
    reloadActivityLogs();
});

function reloadActivityLogs() {
    const tbody = document.getElementById('activityLogsBody');
    const limitEl = document.getElementById('activityLogLimit');
    const limit = limitEl ? Number(limitEl.value || 50) : 50;

    if (!tbody) {
        return;
    }

    tbody.innerHTML = '<tr><td colspan="3" class="text-center">Loading...</td></tr>';
    fetch(window.API_URL + `activity_logs.php?action=list&limit=${encodeURIComponent(limit)}`)
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
            if (!ok || !d.success) {
                const msg = (d && d.message) ? d.message : 'Could not load activity logs';
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">' + escapeHtml(msg) + '</td></tr>';
                return;
            }

            if (d.data && d.data.length) {
                tbody.innerHTML = d.data.map(l => `
                    <tr>
                        <td>${escapeHtml(l.username || '-')}</td>
                        <td>${escapeHtml(l.summary || (l.action + ' — ' + l.module))}</td>
                        <td>${l.created_at ? new Date(l.created_at).toLocaleString() : '-'}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No activity logs</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading activity logs</td></tr>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
