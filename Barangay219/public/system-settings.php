<?php
/**
 * System-wide settings (admin).
 */
define('ACCESS_ALLOWED', true);
$page_title = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]);

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page system-settings-page">
    <div class="container-fluid" style="max-width: 640px;">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="module-kicker text-uppercase small mb-1">Administration</p>
                <h2 class="mb-1"><i class="bi bi-sliders me-2"></i>System Settings</h2>
                <p class="module-subtitle mb-0">Configure thresholds used across the system.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <strong>Indigent classification</strong>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Households are classified as <strong>Indigent</strong> when total monthly income of all members is at or below this threshold, and <strong>Non-Indigent</strong> when above.
                    Missing per-member income is treated as zero. Changes apply the next time household details are loaded.
                </p>
                <div class="mb-3">
                    <label for="indigentThresholdInput" class="form-label">Indigent threshold (monthly household income, PHP)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" class="form-control" id="indigentThresholdInput" min="0" step="0.01" inputmode="decimal" required>
                    </div>
                    <div class="form-text">Default: ₱<?php echo number_format((float)DEFAULT_INDIGENT_THRESHOLD_MONTHLY, 2); ?></div>
                </div>
                <div id="systemSettingsAlert" class="alert d-none" role="alert"></div>
                <button type="button" class="btn btn-primary" id="btnSaveIndigentThreshold">
                    <i class="bi bi-check2-circle me-1"></i> Save threshold
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
if (typeof window.API_URL === 'undefined') window.API_URL = '<?php echo addslashes(API_URL); ?>';
(function() {
    const input = document.getElementById('indigentThresholdInput');
    const alertEl = document.getElementById('systemSettingsAlert');
    const btn = document.getElementById('btnSaveIndigentThreshold');

    function showAlert(type, msg) {
        if (!alertEl) return;
        alertEl.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
        alertEl.textContent = msg;
        alertEl.classList.remove('d-none');
    }

    function loadSettings() {
        fetch(window.API_URL + 'system-settings.php?action=get', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success || !d.data) {
                    showAlert('error', d.message || 'Could not load settings.');
                    return;
                }
                const v = d.data.indigent_threshold_monthly;
                if (input && v != null) input.value = String(v);
            })
            .catch(function() { showAlert('error', 'Could not load settings.'); });
    }

    if (btn) {
        btn.addEventListener('click', function() {
            const raw = input ? String(input.value || '').trim() : '';
            if (raw === '' || isNaN(Number(raw)) || Number(raw) < 0) {
                showAlert('error', 'Enter a valid non-negative amount.');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('indigent_threshold_monthly', raw);
            btn.disabled = true;
            fetch(window.API_URL + 'system-settings.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    btn.disabled = false;
                    if (!d.success) {
                        showAlert('error', d.message || 'Save failed.');
                        return;
                    }
                    showAlert('success', d.message || 'Saved.');
                })
                .catch(function() {
                    btn.disabled = false;
                    showAlert('error', 'Save failed.');
                });
        });
    }

    loadSettings();
})();
</script>
