<?php
/**
 * E-Barangay Information Management System
 * Footer Component
 */
?>
    <?php if (isLoggedIn()): ?>
    </div> <!-- End main-content -->
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - <?php echo BARANGAY_NAME; ?>
                    </span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">
                        Version <?php echo APP_VERSION; ?>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS (Local) -->
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (if needed) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
    // Ensure API_URL is available and valid
    if (typeof window.API_URL === 'undefined' || window.API_URL === null) {
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
    }

    // If API_URL looks like unprocessed PHP or contains '<', use fallback computed from current location
    (function() {
        function looksInvalid(u) {
            if (!u || typeof u !== 'string') return true;
            return u.indexOf('<?') !== -1 || u.indexOf('%3C') !== -1 || u.trim() === '' || u.charAt(0) === '<';
        }

        if (looksInvalid(window.API_URL)) {
            // Fallback: construct API path relative to current origin and app root
            var fallback = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
            console.warn('API_URL contained unprocessed PHP; using fallback API_URL:', fallback);
            window.API_URL = fallback;
        }
    })();

    // Logout function
    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            const apiUrl = window.API_URL || '<?php echo addslashes(API_URL); ?>';
            fetch(apiUrl + 'auth.php?action=logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.data.redirect;
                } else {
                    alert('Error logging out. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error logging out. Please try again.');
            });
        }
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    </script>
</body>
</html>
