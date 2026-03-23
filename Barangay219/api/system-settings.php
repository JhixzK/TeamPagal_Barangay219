<?php
/**
 * System-wide settings (e.g. indigent income threshold).
 */
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/indigent-classification.php';

requireLogin();
requireAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]);

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

switch ($action) {
    case 'get':
        getSystemSettings();
        break;
    case 'save':
        saveSystemSettings();
        break;
    default:
        sendResponse(false, 'Invalid action', null, 400);
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

function getSystemSettings() {
    try {
        $db = Database::getInstance();
        ensureIndigentClassificationSchema($db);
        $threshold = getIndigentThresholdMonthly($db);
        sendResponse(true, 'OK', [
            'indigent_threshold_monthly' => $threshold,
        ]);
    } catch (Throwable $e) {
        error_log('getSystemSettings: ' . $e->getMessage());
        sendResponse(false, 'Error loading settings', null, 500);
    }
}

function saveSystemSettings() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $raw = trim((string)($_POST['indigent_threshold_monthly'] ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        sendResponse(false, 'Indigent threshold must be a non-negative number.', null, 400);
        return;
    }
    $val = (float)$raw;
    if ($val < 0) {
        sendResponse(false, 'Indigent threshold cannot be negative.', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        ensureIndigentClassificationSchema($db);

        $db->query(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES ('indigent_threshold_monthly', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [(string)round($val, 2)]
        );

        sendResponse(true, 'Settings saved. Household classifications use the new threshold on next view.', [
            'indigent_threshold_monthly' => round($val, 2),
        ]);
    } catch (Throwable $e) {
        error_log('saveSystemSettings: ' . $e->getMessage());
        sendResponse(false, 'Error saving settings', null, 500);
    }
}
