if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

function setModuleStatValue(el, value) {
    if (!el) return;
    const v = value === null || value === undefined ? 0 : value;
    el.textContent = v;
}

function loadModuleStats(moduleKey) {
    const container = document.querySelector('.module-stats[data-module]');
    if (!container) return;
    const apiUrl = window.API_URL || '';
    if (!apiUrl) return;

    fetch(apiUrl + 'reports.php?action=module_stats&module=' + encodeURIComponent(moduleKey))
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.data) {
                container.querySelectorAll('[data-stat]').forEach(el => setModuleStatValue(el, 0));
                return;
            }
            container.querySelectorAll('[data-stat]').forEach(el => {
                const key = el.getAttribute('data-stat');
                setModuleStatValue(el, d.data[key]);
            });
        })
        .catch(() => {
            container.querySelectorAll('[data-stat]').forEach(el => setModuleStatValue(el, 0));
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.module-stats[data-module]');
    if (!container) return;
    const moduleKey = container.getAttribute('data-module');
    if (!moduleKey) return;
    loadModuleStats(moduleKey);
});
