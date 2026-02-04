// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

function loadReport(type) {
    fetch(`${window.API_URL}reports.php?action=${type}`)
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            if (d.success) {
                console.log('Report Data:', d.data);
                alert('Report data loaded. Check console for details.');
            } else {
                console.warn('Reports API returned error:', d.message);
                alert('Error loading report: ' + (d.message || 'Unknown'));
            }
        })
        .catch(err => {
            console.error('Error loading report:', err);
            alert('Error loading report. Check console for details.');
        });
}
