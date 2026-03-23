<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!canAccessModule('applications') && !canAccessModule('certificates')) {
    redirectStaffPortalAccessDenied();
}

header('Location: ' . BASE_URL . 'applications.php');
exit();

