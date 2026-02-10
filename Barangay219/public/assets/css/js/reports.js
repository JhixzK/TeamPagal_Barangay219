if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

let currentFrom = '';
let currentTo = '';
let currentReportType = '';
let currentReportTitle = '';

function getFilterParams() {
    const from = document.getElementById('filterFrom')?.value || '';
    const to = document.getElementById('filterTo')?.value || '';
    currentFrom = from;
    currentTo = to;
    let q = '';
    if (from) q += '&from=' + encodeURIComponent(from);
    if (to) q += '&to=' + encodeURIComponent(to);
    return q;
}

function applyFilter() {
    getFilterParams();
    if (currentReportType) {
        loadReport(currentReportType, currentReportTitle);
    }
}

function loadReport(type, title) {
    const params = getFilterParams();
    currentReportType = type;
    currentReportTitle = title;
    fetch(window.API_URL + 'reports.php?action=' + type + params)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const rangeText = currentFrom || currentTo ? (currentFrom || '...') + ' to ' + (currentTo || '...') : 'All Dates';
                document.getElementById('reportTitle').textContent = title + (currentFrom || currentTo ? ' (' + rangeText + ')' : '');
                document.getElementById('reportBody').innerHTML = formatReportData(type, d.data);
                document.getElementById('reportResult').style.display = 'block';
                updatePrintHeader(title, rangeText);
            } else {
                alert('Error: ' + (d.message || 'Unknown'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error loading report');
        });
}

function updatePrintHeader(title, rangeText) {
    const titleEl = document.getElementById('printReportTitle');
    const rangeEl = document.getElementById('printReportRange');
    const genEl = document.getElementById('printGeneratedAt');
    if (titleEl) titleEl.textContent = title || 'Report';
    if (rangeEl) rangeEl.textContent = rangeText || 'All Dates';
    if (genEl) genEl.textContent = new Date().toLocaleString();
}

function formatReportData(type, data) {
    if (type === 'population') {
        let html = '<div class="mb-3"><strong>Total Residents:</strong> ' + (data.total || 0) + '</div>';
        if (data.by_gender && data.by_gender.length) {
            html += '<h6 class="mt-3">By Gender</h6><table class="table table-sm table-striped report-table"><thead><tr><th>Gender</th><th>Count</th></tr></thead><tbody>';
            data.by_gender.forEach(r => { html += '<tr><td>' + r.gender + '</td><td>' + r.count + '</td></tr>'; });
            html += '</tbody></table>';
        }
        if (data.by_civil_status && data.by_civil_status.length) {
            html += '<h6 class="mt-3">By Civil Status</h6><table class="table table-sm table-striped report-table"><thead><tr><th>Civil Status</th><th>Count</th></tr></thead><tbody>';
            data.by_civil_status.forEach(r => { html += '<tr><td>' + (r.civil_status || '-') + '</td><td>' + r.count + '</td></tr>'; });
            html += '</tbody></table>';
        }
        return html;
    }
    if (Array.isArray(data) && data.length > 0) {
        const keys = Object.keys(data[0]);
        let html = '<table class="table table-sm table-striped report-table"><thead><tr>';
        keys.forEach(k => { html += '<th>' + k.replace(/_/g, ' ') + '</th>'; });
        html += '</tr></thead><tbody>';
        data.forEach(row => {
            html += '<tr>';
            keys.forEach(k => { html += '<td>' + (row[k] ?? '-') + '</td>'; });
            html += '</tr>';
        });
        html += '</tbody></table>';
        return html;
    }
    return '<p class="text-muted">No data found for the selected filters.</p>';
}
