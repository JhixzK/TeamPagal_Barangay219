<?php
/**
 * E-Barangay Information Management System
 * Activity Logs Page
 */

define('ACCESS_ALLOWED', true);
$page_title = 'Activity Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('reports');

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Administration Module</p>
                    <h2 class="mb-1"><i class="bi bi-clock-history me-2"></i>Activity Logs</h2>
                    <p class="module-subtitle mb-0">View and filter system activity logs.</p>
                </div>
                <div>
                    <button class="btn btn-outline-secondary" onclick="reloadActivityLogs()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> User Activity Logs</h5>
                <div class="d-flex align-items-center gap-2">
                    <label class="small mb-0" for="activityLogLimit">Limit</label>
                    <select id="activityLogLimit" class="form-select form-select-sm" style="width: 100px;">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Summary</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="activityLogsBody">
                            <tr><td colspan="3" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/activity-logs.js?v=<?php echo time(); ?>"></script>
