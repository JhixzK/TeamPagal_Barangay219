<?php
/**
 * E-Barangay Information Management System
 * Footer Component
 */
?>
    <?php if (isLoggedIn()): ?>
    </div> <!-- End main-content -->
    <?php endif; ?>
    
    <!-- Bootstrap JS (Local) -->
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <?php if (isLoggedIn()): ?>
    <script src="<?php echo ASSETS_URL; ?>css/js/app-notifications.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
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

    function debounce(fn, wait) {
        let timer = null;
        return function debounced() {
            const ctx = this;
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    function findSearchTrigger() {
        return document.querySelector('.search-bar button[onclick*="search" i]');
    }

    function triggerSearchAction() {
        const trigger = findSearchTrigger();
        if (trigger) {
            trigger.click();
        }
    }

    function persistModuleUiState() {
        const baseKey = 'module-ui:' + window.location.pathname;
        const searchInput = document.getElementById('searchInput');
        const tabs = document.querySelectorAll('#statusTabs .nav-link[data-status]');

        if (searchInput) {
            const storedSearch = sessionStorage.getItem(baseKey + ':search') || '';
            if (!searchInput.value && storedSearch) {
                searchInput.value = storedSearch;
            }

            const debouncedSearch = debounce(function() {
                const value = (searchInput.value || '').trim();
                sessionStorage.setItem(baseKey + ':search', value);
                triggerSearchAction();
            }, 320);

            searchInput.addEventListener('input', debouncedSearch);
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = (searchInput.value || '').trim();
                    sessionStorage.setItem(baseKey + ':search', value);
                    triggerSearchAction();
                }
            });
        }

        if (tabs.length) {
            const storedStatus = sessionStorage.getItem(baseKey + ':status');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    sessionStorage.setItem(baseKey + ':status', tab.getAttribute('data-status') || '');
                });
            });

            if (storedStatus !== null) {
                const target = Array.from(tabs).find(function(tab) {
                    return (tab.getAttribute('data-status') || '') === storedStatus;
                });
                if (target && !target.classList.contains('active')) {
                    target.click();
                }
            }
        }
    }

    function enhanceActionButtonAccessibility() {
        document.querySelectorAll('.action-icon-btn').forEach(function(btn) {
            if (!btn.getAttribute('aria-label')) {
                const fallbackLabel = btn.getAttribute('title') || 'Action';
                btn.setAttribute('aria-label', fallbackLabel);
            }
        });
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

        persistModuleUiState();
        enhanceActionButtonAccessibility();
    });
    </script>
</body>
</html>
