<?php
/**
 * E-Barangay - Account Activation API
 * Resident sets password after barangay approval.
 * Requires valid activation token.
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(false, 'Method not allowed', null, 405);
}

function sendJson($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (!$token) {
    sendJson(false, 'Activation token is required.', null, 400);
}

// Password validation
if (strlen($password) < PASSWORD_MIN_LENGTH || strlen($password) > PASSWORD_MAX_LENGTH) {
    sendJson(false, 'Password must be ' . PASSWORD_MIN_LENGTH . '-' . PASSWORD_MAX_LENGTH . ' characters.', null, 400);
}
if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{' . PASSWORD_MIN_LENGTH . ',' . PASSWORD_MAX_LENGTH . '}$/', $password)) {
    sendJson(false, 'Password must contain both letters and numbers.', null, 400);
}
if ($password !== $password_confirm) {
    sendJson(false, 'Passwords do not match.', null, 400);
}

try {
    $db = Database::getInstance();
    $user = $db->fetchOne(
        "SELECT id, username, activation_token FROM users WHERE activation_token = ? AND activation_expires > CURRENT_TIMESTAMP AND role = ?",
        [$token, ROLE_RESIDENT]
    );

    if (!$user) {
        sendJson(false, 'Invalid or expired activation link. Please contact the barangay office.', null, 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->query(
        "UPDATE users SET password = ?, activation_token = NULL, activation_expires = NULL WHERE id = ?",
        [$hash, $user['id']]
    );

    sendJson(true, 'Account activated successfully. You may now login with your Resident ID and password.', [
        'username' => $user['username'],
        'redirect' => BASE_URL . 'login.php'
    ]);
} catch (Exception $e) {
    error_log('Activate account: ' . $e->getMessage());
    sendJson(false, 'Activation failed. Please try again.', null, 500);
}
