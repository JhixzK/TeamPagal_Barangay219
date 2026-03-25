document.addEventListener('DOMContentLoaded', function () {
    loadDashboardBundle();
});

/* ============================================================
   Color palette
   ============================================================ */
const COLORS = {
    blue:   { bg: 'rgba(37,99,235,.65)',  border: '#2563eb' },
    teal:   { bg: 'rgba(15,118,110,.65)', border: '#0f766e' },
    sky:    { bg: 'rgba(2,132,199,.65)',   border: '#0284c7' },
    amber:  { bg: 'rgba(217,119,6,.65)',   border: '#d97706' },
    rose:   { bg: 'rgba(190,18,60,.65)',   border: '#be123c' },
    green:  { bg: 'rgba(22,163,74,.65)',   border: '#16a34a' },
    purple: { bg: 'rgba(124,58,237,.65)',  border: '#7c3aed' },
    slate:  { bg: 'rgba(71,85,105,.65)',   border: '#475569' },
    cyan:   { bg: 'rgba(6,182,212,.60)',   border: '#06b6d4' },
    pink:   { bg: 'rgba(236,72,153,.60)',  border: '#ec4899' },
};
const PALETTE_BG     = [COLORS.blue.bg, COLORS.teal.bg, COLORS.amber.bg, COLORS.rose.bg, COLORS.green.bg, COLORS.purple.bg, COLORS.sky.bg, COLORS.cyan.bg, COLORS.pink.bg, COLORS.slate.bg];
const PALETTE_BORDER = [COLORS.blue.border, COLORS.teal.border, COLORS.amber.border, COLORS.rose.border, COLORS.green.border, COLORS.purple.border, COLORS.sky.border, COLORS.cyan.border, COLORS.pink.border, COLORS.slate.border];

/* ============================================================
   Shared chart defaults
   ============================================================ */
const CHART_FONT = { family: "'Inter','Segoe UI',sans-serif", size: 10 };
const GRID_COLOR = 'rgba(226,232,240,.6)';

Chart.defaults.font.family = CHART_FONT.family;
Chart.defaults.font.size = CHART_FONT.size;
Chart.defaults.color = '#64748b';

function chartDefaults(extraPlugins) {
    return {
        responsive: true,
        maintainAspectRatio: true,
        plugins: Object.assign({ legend: { display: false } }, extraPlugins || {})
    };
}

/* ============================================================
   One request: KPIs + charts + recent activity (faster than 3 calls)
   ============================================================ */
function loadDashboardBundle() {
    fetch(window.API_URL + 'reports.php?action=dashboard_bundle&limit=10')
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.data) {
                setDefaultStats();
                renderRecentActivities([]);
                return;
            }
            const p = d.data;
            applyStatistics(p.statistics);
            applyChartsData(p.charts);
            renderRecentActivities(p.recent_activities || []);
        })
        .catch(() => {
            setDefaultStats();
            renderRecentActivities(null);
        });
}

function applyStatistics(s) {
    if (!s) return setDefaultStats();
    setEl('totalResidents', s.total_residents ?? 0);
    setEl('totalHouseholds', s.total_households ?? 0);
    setEl('issuedCertificates', s.issued_certificates ?? 0);
    setEl('pendingApplications', s.pending_certificates ?? s.pending_applications ?? 0);
    setEl('pendingComplaints', s.pending_complaints ?? 0);
    setEl('activeAnnouncements', s.active_announcements ?? 0);
}

/* ============================================================
   1. KPI Cards
   ============================================================ */
function loadStatistics() {
    fetch(window.API_URL + 'reports.php?action=statistics')
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.data) return setDefaultStats();
            applyStatistics(d.data);
        })
        .catch(() => setDefaultStats());
}

function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setDefaultStats() {
    ['totalResidents','totalHouseholds','issuedCertificates','pendingApplications','pendingComplaints','activeAnnouncements'].forEach(id => setEl(id, '0'));
}

/* ============================================================
   2. Chart Data
   ============================================================ */
const chartInstances = {};

function applyChartsData(c) {
    if (!c) return;
    setEl('approvedToday', c.approved_today ?? 0);
    setEl('newRegistrationsToday', c.new_registrations_today ?? 0);

    initLineChart('chartRequestsTime', c.requests_over_time, 'Requests', COLORS.blue);
    initDoughnutChart('chartRequestStatus', c.request_status);
    initDoughnutChart('chartGender', c.gender_distribution);
    initBarChart('chartStreet', c.population_by_street, 'Residents', COLORS.teal, true);
    initBarChart('chartAge', c.age_groups, 'Residents', COLORS.amber, false);
    initSpecialCategories(c.special_categories);
}

function loadCharts() {
    fetch(window.API_URL + 'reports.php?action=dashboard_charts')
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.data) return;
            applyChartsData(d.data);
        })
        .catch(err => console.error('Dashboard charts error:', err));
}

/* ============================================================
   Chart builders
   ============================================================ */
function extract(data) {
    if (!Array.isArray(data) || data.length === 0) return null;
    return { labels: data.map(d => d.label), values: data.map(d => d.value) };
}

function getCanvas(id) {
    const canvas = document.getElementById(id);
    if (!canvas) return null;
    if (chartInstances[id]) chartInstances[id].destroy();
    return canvas;
}

function showNoData(id) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const parent = canvas.parentElement;
    canvas.style.display = 'none';
    const div = document.createElement('div');
    div.className = 'chart-no-data';
    div.textContent = 'No data available';
    parent.appendChild(div);
}

function initLineChart(id, data, label, color) {
    const parsed = extract(data);
    if (!parsed) return showNoData(id);
    const canvas = getCanvas(id);
    if (!canvas) return;

    chartInstances[id] = new Chart(canvas, {
        type: 'line',
        data: {
            labels: parsed.labels,
            datasets: [{
                label: label,
                data: parsed.values,
                borderColor: color.border,
                backgroundColor: color.bg,
                fill: true,
                tension: .35,
                pointRadius: 2,
                pointHoverRadius: 4,
                pointBackgroundColor: color.border,
                borderWidth: 1.5
            }]
        },
        options: Object.assign({}, chartDefaults({ legend: { display: false } }), {
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 9 }, maxTicksLimit: 8 } },
                y: { beginAtZero: true, grid: { color: GRID_COLOR }, ticks: { stepSize: 1, font: { size: 9 } } }
            }
        })
    });
}

function initBarChart(id, data, label, color, horizontal) {
    const parsed = extract(data);
    if (!parsed) return showNoData(id);
    const canvas = getCanvas(id);
    if (!canvas) return;

    const bgColors = parsed.labels.map((_, i) => PALETTE_BG[i % PALETTE_BG.length]);
    const borderColors = parsed.labels.map((_, i) => PALETTE_BORDER[i % PALETTE_BORDER.length]);

    chartInstances[id] = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: parsed.labels,
            datasets: [{
                label: label,
                data: parsed.values,
                backgroundColor: bgColors,
                borderColor: borderColors,
                borderWidth: 1,
                borderRadius: 4,
                maxBarThickness: 26
            }]
        },
        options: Object.assign({}, chartDefaults(), {
            indexAxis: horizontal ? 'y' : 'x',
            scales: {
                x: { beginAtZero: true, grid: { color: horizontal ? GRID_COLOR : 'transparent' }, ticks: { font: { size: 9 }, maxRotation: horizontal ? 0 : 45, maxTicksLimit: horizontal ? 12 : 8 } },
                y: { beginAtZero: true, grid: { color: horizontal ? 'transparent' : GRID_COLOR }, ticks: { font: { size: 9 } } }
            }
        })
    });
}

function initDoughnutChart(id, data) {
    const parsed = extract(data);
    if (!parsed) return showNoData(id);
    const canvas = getCanvas(id);
    if (!canvas) return;

    const bgColors = parsed.labels.map((_, i) => PALETTE_BG[i % PALETTE_BG.length]);
    const borderColors = parsed.labels.map((_, i) => PALETTE_BORDER[i % PALETTE_BORDER.length]);

    chartInstances[id] = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: parsed.labels,
            datasets: [{
                data: parsed.values,
                backgroundColor: bgColors,
                borderColor: borderColors,
                borderWidth: 2,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '58%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { padding: 6, boxWidth: 10, usePointStyle: true, pointStyle: 'circle', font: { size: 9 } }
                }
            }
        }
    });
}

/* ============================================================
   Special categories
   ============================================================ */
const SC_ICONS = {
    'Senior Citizens': 'bi-person-wheelchair',
    'PWD': 'bi-universal-access',
    'Solo Parents': 'bi-person-hearts',
    '4Ps Beneficiaries': 'bi-cash-stack',
    'IP Members': 'bi-globe-americas'
};

function initSpecialCategories(data) {
    const container = document.getElementById('specialCategoriesCards');
    if (!container || !Array.isArray(data)) return;

    if (data.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-3">No special category data</div>';
        return;
    }

    container.innerHTML = data.map(d => {
        const icon = SC_ICONS[d.label] || 'bi-star';
        return `
            <div class="col-6 col-md-4 col-lg">
                <div class="special-cat-card">
                    <div class="sc-icon"><i class="bi ${icon}"></i></div>
                    <div class="sc-value">${d.value}</div>
                    <div class="sc-label">${escapeHtml(d.label)}</div>
                </div>
            </div>`;
    }).join('');
}

/* ============================================================
   3. Recent Activities
   ============================================================ */
function renderRecentActivities(rows) {
    const list = document.getElementById('recentActivitiesList');
    if (!list) return;

    if (rows === null) {
        list.innerHTML = '<div class="list-group-item text-center text-muted py-4">Unable to load activities</div>';
        return;
    }
    if (rows.length > 0) {
        list.innerHTML = rows.map(a => `
                    <div class="list-group-item">
                        <div class="act-user">${escapeHtml(a.username || 'System')}</div>
                        <div class="act-action">${escapeHtml(a.summary || (a.action + ' — ' + a.module))}</div>
                        <div class="act-time">${formatDateTime(a.created_at)}</div>
                    </div>
                `).join('');
    } else {
        list.innerHTML = '<div class="list-group-item text-center text-muted py-4">No recent activities</div>';
    }
}

function loadRecentActivities() {
    const list = document.getElementById('recentActivitiesList');
    if (!list) return;

    fetch(window.API_URL + 'reports.php?action=recent_activities&limit=10')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data) {
                renderRecentActivities(d.data);
            } else {
                renderRecentActivities([]);
            }
        })
        .catch(() => renderRecentActivities(null));
}

/* ============================================================
   Utilities
   ============================================================ */
function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function formatDateTime(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
