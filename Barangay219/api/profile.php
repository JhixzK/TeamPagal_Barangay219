<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload':
        uploadAvatar();
        break;
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

function sendResponse($success = false, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function uploadAvatar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'No file uploaded or upload error', null, 400);
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        sendResponse(false, 'Not authenticated', null, 401);
    }

    // CSRF token check if provided
    $token = $_POST[CSRF_TOKEN_NAME] ?? null;
    if ($token && !verifyCSRFToken($token)) {
        sendResponse(false, 'Invalid CSRF token', null, 403);
    }

    $file = $_FILES['avatar'];
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        sendResponse(false, 'File too large', null, 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) {
        sendResponse(false, 'Invalid file type', null, 400);
    }

    // Determine extension
    $ext = '';
    switch ($mime) {
        case 'image/png': $ext = 'png'; break;
        case 'image/gif': $ext = 'gif'; break;
        default: $ext = 'jpg'; break;
    }

    $uploadDir = PUBLIC_PATH . '/uploads/profile';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $target = $uploadDir . '/' . $userId . '.' . $ext;

    // Remove other existing extensions for this user
    foreach (['png','jpg','jpeg','gif'] as $e) {
        $old = $uploadDir . '/' . $userId . '.' . $e;
        if (file_exists($old) && $old !== $target) {
            @unlink($old);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        sendResponse(false, 'Failed to save file', null, 500);
    }

    $publicUrl = BASE_URL . 'uploads/profile/' . $userId . '.' . $ext;

    sendResponse(true, 'Avatar uploaded', ['url' => $publicUrl]);
}
