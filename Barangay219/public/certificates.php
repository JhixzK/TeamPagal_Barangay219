<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!canAccessModule('applications') && !canAccessModule('certificates')) {
    header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
    exit();
}

header('Location: ' . BASE_URL . 'applications.php');
exit();

