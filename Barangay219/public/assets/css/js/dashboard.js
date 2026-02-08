document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();
    loadRecentActivities();
});

function loadStatistics() {
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined.');
        setDefaultStats();
        return;
    }

    fetch(apiUrl + 'reports.php?action=statistics')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data) {
                const s = d.data;
                setEl('totalResidents', s.total_residents ?? 0);
                setEl('totalHouseholds', s.total_households ?? 0);
                setEl('issuedCertificates', s.issued_certificates ?? 0);
                setEl('pendingApplications', s.pending_certificates ?? s.pending_applications ?? 0);
                setEl('pendingComplaints', s.pending_complaints ?? 0);
                setEl('activeAnnouncements', s.active_announcements ?? 0);
                if (s.total_residents !== undefined) {
                    initOverviewChart(d.data);
                }
            } else {
                setDefaultStats();
            }
        })
        .catch(e => {
            console.error('Error loading statistics:', e);
            setDefaultStats();
        });
}

function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function setDefaultStats() {
    setEl('totalResidents', '0');
    setEl('totalHouseholds', '0');
    setEl('issuedCertificates', '0');
    setEl('pendingApplications', '0');
    setEl('pendingComplaints', '0');
    setEl('activeAnnouncements', '0');
}

function initOverviewChart(stats) {
    const canvas = document.getElementById('overviewChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const ctx = canvas.getContext('2d');
    if (window.overviewChartInstance) window.overviewChartInstance.destroy();

    window.overviewChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Residents', 'Households', 'Issued Certs', 'Pending Apps', 'Complaints'],
            datasets: [{
                label: 'Count',
                data: [
                    stats.total_residents || 0,
                    stats.total_households || 0,
                    stats.issued_certificates || 0,
                    stats.pending_certificates || stats.pending_applications || 0,
                    stats.pending_complaints || 0
                ],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.7)',
                    'rgba(25, 135, 84, 0.7)',
                    'rgba(13, 202, 240, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(220, 53, 69, 0.7)'
                ],
                borderColor: ['#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

function loadRecentActivities() {
    const apiUrl = window.API_URL;
    const list = document.getElementById('recentActivitiesList');
    if (!list) return;

    fetch(apiUrl + 'reports.php?action=recent_activities&limit=8')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data && d.data.length > 0) {
                list.innerHTML = d.data.map(a => `
                    <div class="list-group-item">
                        <small class="text-muted">${escapeHtml(a.username || 'System')}</small>
                        <div>${escapeHtml(a.action)} - ${escapeHtml(a.module)}</div>
                        <small class="text-muted">${formatDateTime(a.created_at)}</small>
                    </div>
                `).join('');
            } else {
                list.innerHTML = '<div class="list-group-item text-center text-muted py-4">No recent activities</div>';
            }
        })
        .catch(() => {
            list.innerHTML = '<div class="list-group-item text-center text-muted py-4">Unable to load activities</div>';
        });
}

function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
function formatDateTime(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString() + ' ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
