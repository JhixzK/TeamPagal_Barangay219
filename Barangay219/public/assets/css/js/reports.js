function loadReport(type) {
    fetch(`<?php echo API_URL; ?>reports.php?action=${type}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                console.log('Report Data:', d.data);
                alert('Report data loaded. Check console for details.');
            }
        });
}
