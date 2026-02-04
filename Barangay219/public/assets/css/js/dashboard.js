document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();
});

function loadStatistics() {
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        return;
    }
    
    fetch(apiUrl + 'reports.php?action=statistics')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById('totalResidents').textContent = d.data.total_residents || 0;
                document.getElementById('totalHouseholds').textContent = d.data.total_households || 0;
                document.getElementById('pendingCertificates').textContent = d.data.pending_certificates || 0;
                document.getElementById('pendingComplaints').textContent = d.data.pending_complaints || 0;
            } else {
                console.error('Failed to load statistics:', d.message);
                // Set default values on error
                document.getElementById('totalResidents').textContent = '0';
                document.getElementById('totalHouseholds').textContent = '0';
                document.getElementById('pendingCertificates').textContent = '0';
                document.getElementById('pendingComplaints').textContent = '0';
            }
        })
        .catch(e => {
            console.error('Error loading statistics:', e);
            // Set default values on error
            document.getElementById('totalResidents').textContent = '0';
            document.getElementById('totalHouseholds').textContent = '0';
            document.getElementById('pendingCertificates').textContent = '0';
            document.getElementById('pendingComplaints').textContent = '0';
        });
}
