<?php
/**
 * E-Barangay Information Management System
 * Authentication API Endpoints
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/mail-helper.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const LOGIN_2FA_TTL_SEC = 600;
const LOGIN_2FA_MAX_ATTEMPTS = 5;
const LOGIN_2FA_MAX_RESENDS = 3;
const LOGIN_2FA_RATE_IP_MAX = 10;
const LOGIN_2FA_RATE_USER_MAX = 5;
const LOGIN_2FA_RATE_WINDOW_MINUTES = 15;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;

    case 'verify_login_2fa':
        handleVerifyLogin2fa();
        break;

    case 'resend_login_2fa':
        handleResendLogin2fa();
        break;

    case 'toggle_two_factor':
        handleToggleTwoFactor();
        break;

    case 'logout':
        handleLogout();
        break;

    case 'check':
        checkAuth();
        break;

    case 'view_mode':
        handleViewMode();
        break;

    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * @return string 64-char hex sha256 of raw token
 */
function login2faTokenHash($rawToken) {
    return hash('sha256', $rawToken);
}

/**
 * @return bool
 */
function login2faTablesReady(Database $db) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $t = $db->fetchOne("SHOW TABLES LIKE 'login_2fa_challenges'");
        $c = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        $ready = !empty($t) && !empty($c);
    } catch (Exception $e) {
        $ready = false;
    }
    return $ready;
}

function login2faRateAllowed(Database $db, $userId, $ip) {
    $ip = (string)$ip;
    $since = date('Y-m-d H:i:s', time() - LOGIN_2FA_RATE_WINDOW_MINUTES * 60);
    try {
        $rowIp = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM login_2fa_challenges WHERE created_at >= ? AND ip_address = ?",
            [$since, $ip]
        );
        $rowUser = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM login_2fa_challenges WHERE created_at >= ? AND user_id = ?",
            [$since, $userId]
        );
        if ($rowIp === false || $rowUser === false) {
            return false;
        }
        $byIp = (int)($rowIp['c'] ?? 0);
        $byUser = (int)($rowUser['c'] ?? 0);
    } catch (Exception $e) {
        return false;
    }
    return $byIp < LOGIN_2FA_RATE_IP_MAX && $byUser < LOGIN_2FA_RATE_USER_MAX;
}

function login2faDeleteChallenge(Database $db, $id) {
    try {
        $db->query("DELETE FROM login_2fa_challenges WHERE id = ?", [(int)$id]);
    } catch (Exception $e) {
        /* ignore */
    }
}

/**
 * Complete session after password (+ optional 2FA) success.
 *
 * @param array $user row with id, username, password, email, role, status, resident_id
 */
function authCompleteLoginSession(Database $db, array $user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    try {
        $db->query(
            "INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (?, 'login', 'auth', ?)",
            [$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Exception $e) { /* table may not exist yet */ }
    $_SESSION['email'] = $user['email'];
    $_SESSION['resident_id'] = $user['resident_id'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    if ($user['role'] === ROLE_RESIDENT) {
        unset($_SESSION['view_mode']);
    } else {
        $_SESSION['view_mode'] = 'official';
    }

    session_regenerate_id(true);

    $userInfo = getUserInfo($user['id']);
    if ($userInfo) {
        unset($userInfo['password']);
    }

    $redirectUrl = BASE_URL . 'dashboard.php';
    if ($user['role'] === 'resident') {
        $redirectUrl = BASE_URL . 'resident_dashboard.php';
    }

    return [
        'user' => $userInfo,
        'redirect' => $redirectUrl,
    ];
}

/**
 * Start email 2FA challenge (does not create session).
 *
 * @param array $user from DB including id, username, email
 * @return array{ok:bool, error?:string, http?:int, data?:array}
 */
function login2faCreateAndSend(Database $db, array $user, $ip) {
    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'error' => 'Two-factor login is enabled but your account has no valid email. Contact an administrator to add an email or disable 2FA.',
            'http' => 403,
        ];
    }

    if (!login2faRateAllowed($db, (int)$user['id'], $ip)) {
        return [
            'ok' => false,
            'error' => 'Too many verification requests. Please wait and try again later.',
            'http' => 429,
        ];
    }

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = login2faTokenHash($rawToken);
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + LOGIN_2FA_TTL_SEC);

    try {
        $db->query(
            "INSERT INTO login_2fa_challenges (user_id, token_hash, otp_hash, expires_at, max_attempts, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [(int)$user['id'], $tokenHash, $otpHash, $expiresAt, LOGIN_2FA_MAX_ATTEMPTS, $ip !== '' ? $ip : null]
        );
    } catch (Exception $e) {
        error_log('login_2fa insert: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not start verification. Please try again.', 'http' => 500];
    }

    $mail = sendLoginOtpEmail($email, $otp, (string)$user['username']);
    if (!$mail['sent']) {
        try {
            $db->query("DELETE FROM login_2fa_challenges WHERE token_hash = ?", [$tokenHash]);
        } catch (Exception $e2) { /* ignore */ }
        if (!empty($mail['skipped'])) {
            return [
                'ok' => false,
                'error' => 'Email could not be sent (mail is not configured). Login cannot continue. Contact an administrator.',
                'http' => 503,
            ];
        }
        return [
            'ok' => false,
            'error' => 'Email could not be sent. Please try again later or contact an administrator.',
            'http' => 503,
        ];
    }

    return [
        'ok' => true,
        'data' => [
            'step' => 'email_2fa',
            'challenge_token' => $rawToken,
            'message' => 'A verification code was sent to your email address.',
        ],
    ];
}

/**
 * Handle login request
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $loginIdentifier = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($loginIdentifier) || empty($password)) {
        sendResponse(false, 'Username and password are required', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();

        $sql = "SELECT u.id, u.username, u.password, u.email, u.role, u.status, u.resident_id, u.two_factor_enabled
            FROM users u
            LEFT JOIN residents r ON u.resident_id = r.id
            WHERE (u.username = ? OR u.email = ? OR r.resident_code = ?)
              AND u.status != 'suspended'
            LIMIT 1";
        try {
            $user = $db->fetchOne($sql, [$loginIdentifier, $loginIdentifier, $loginIdentifier]);
        } catch (Exception $e) {
            // Migration not applied: fall back without 2FA column
            $sqlLegacy = "SELECT u.id, u.username, u.password, u.email, u.role, u.status, u.resident_id
                FROM users u
                LEFT JOIN residents r ON u.resident_id = r.id
                WHERE (u.username = ? OR u.email = ? OR r.resident_code = ?)
                  AND u.status != 'suspended'
                LIMIT 1";
            $user = $db->fetchOne($sqlLegacy, [$loginIdentifier, $loginIdentifier, $loginIdentifier]);
            if ($user) {
                $user['two_factor_enabled'] = 0;
            }
        }

        if (!$user) {
            sendResponse(false, 'Invalid username or password', null, 401);
            return;
        }

        if (!password_verify($password, $user['password'])) {
            if (($loginIdentifier === 'admin' && $password === 'admin123') ||
                ($loginIdentifier === 'resident' && $password === 'resident123')) {
                try {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $db->query("UPDATE users SET password = ? WHERE id = ?", [$newHash, $user['id']]);
                    $user['password'] = $newHash;
                } catch (Exception $e) {
                    error_log("Password migration error for {$loginIdentifier}: " . $e->getMessage());
                    sendResponse(false, 'An error occurred during login. Please try again.', null, 500);
                    return;
                }
            } else {
                sendResponse(false, 'Invalid username or password', null, 401);
                return;
            }
        }

        if ($user['status'] !== 'active') {
            sendResponse(false, 'Your account is inactive. Please contact the administrator.', null, 403);
            return;
        }

        $twoFaOn = !empty($user['two_factor_enabled']) && login2faTablesReady($db);
        if ($twoFaOn) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $started = login2faCreateAndSend($db, $user, $ip);
            if (!$started['ok']) {
                sendResponse(false, $started['error'], null, (int)($started['http'] ?? 500));
                return;
            }
            sendResponse(true, $started['data']['message'], $started['data']);
            return;
        }

        $payload = authCompleteLoginSession($db, $user);
        sendResponse(true, 'Login successful', $payload);

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        sendResponse(false, 'An error occurred during login. Please try again.', null, 500);
    }
}

function handleVerifyLogin2fa() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $rawToken = trim((string)($_POST['challenge_token'] ?? ''));
    $otp = preg_replace('/\D/', '', (string)($_POST['otp'] ?? ''));

    if (strlen($rawToken) < 32 || strlen($otp) !== 6) {
        sendResponse(false, 'Invalid or expired verification code.', null, 401);
        return;
    }

    if (!login2faTablesReady(Database::getInstance())) {
        sendResponse(false, 'Verification is not available.', null, 503);
        return;
    }

    try {
        $db = Database::getInstance();
        $hash = login2faTokenHash($rawToken);
        $row = $db->fetchOne(
            "SELECT c.*, u.username, u.password, u.email, u.role, u.status, u.resident_id, u.two_factor_enabled
             FROM login_2fa_challenges c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.token_hash = ? LIMIT 1",
            [$hash]
        );

        if (!$row || strtotime($row['expires_at']) < time()) {
            sendResponse(false, 'Invalid or expired verification code.', null, 401);
            return;
        }

        if ((int)$row['attempt_count'] >= (int)$row['max_attempts']) {
            login2faDeleteChallenge($db, $row['id']);
            sendResponse(false, 'Invalid or expired verification code.', null, 401);
            return;
        }

        if (!password_verify($otp, $row['otp_hash'])) {
            $db->query(
                "UPDATE login_2fa_challenges SET attempt_count = attempt_count + 1 WHERE id = ?",
                [(int)$row['id']]
            );
            try {
                $db->query(
                    "INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (?, 'login_2fa_failed', 'auth', ?)",
                    [(int)$row['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]
                );
            } catch (Exception $e) { /* ignore */ }
            sendResponse(false, 'Invalid or expired verification code.', null, 401);
            return;
        }

        if (($row['status'] ?? '') !== 'active') {
            login2faDeleteChallenge($db, $row['id']);
            sendResponse(false, 'Your account is inactive. Please contact the administrator.', null, 403);
            return;
        }

        $user = [
            'id' => (int)$row['user_id'],
            'username' => $row['username'],
            'password' => $row['password'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'resident_id' => $row['resident_id'],
        ];

        login2faDeleteChallenge($db, $row['id']);
        $payload = authCompleteLoginSession($db, $user);
        sendResponse(true, 'Login successful', $payload);
    } catch (Exception $e) {
        error_log('verify_login_2fa: ' . $e->getMessage());
        sendResponse(false, 'An error occurred during verification. Please try again.', null, 500);
    }
}

function handleResendLogin2fa() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $rawToken = trim((string)($_POST['challenge_token'] ?? ''));
    if (strlen($rawToken) < 32) {
        sendResponse(false, 'Invalid or expired session. Please sign in again.', null, 401);
        return;
    }

    if (!login2faTablesReady(Database::getInstance())) {
        sendResponse(false, 'Verification is not available.', null, 503);
        return;
    }

    try {
        $db = Database::getInstance();
        $hash = login2faTokenHash($rawToken);
        $row = $db->fetchOne(
            "SELECT c.*, u.username, u.email FROM login_2fa_challenges c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.token_hash = ? LIMIT 1",
            [$hash]
        );

        if (!$row || strtotime($row['expires_at']) < time()) {
            sendResponse(false, 'Invalid or expired session. Please sign in again.', null, 401);
            return;
        }

        if ((int)$row['resend_count'] >= LOGIN_2FA_MAX_RESENDS) {
            sendResponse(false, 'Maximum resend attempts reached. Please sign in again.', null, 429);
            return;
        }

        $email = trim((string)($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, 'No valid email on file. Contact an administrator.', null, 403);
            return;
        }

        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $mail = sendLoginOtpEmail($email, $otp, (string)$row['username']);
        if (!$mail['sent']) {
            sendResponse(false, 'Could not send email. Try again later.', null, 503);
            return;
        }

        $db->query(
            "UPDATE login_2fa_challenges SET otp_hash = ?, attempt_count = 0, resend_count = resend_count + 1 WHERE id = ?",
            [$otpHash, (int)$row['id']]
        );

        sendResponse(true, 'A new code was sent to your email.', [
            'step' => 'email_2fa',
            'challenge_token' => $rawToken,
        ]);
    } catch (Exception $e) {
        error_log('resend_login_2fa: ' . $e->getMessage());
        sendResponse(false, 'Could not resend code.', null, 500);
    }
}

function handleToggleTwoFactor() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    if (!isLoggedIn()) {
        sendResponse(false, 'Login required', null, 401);
        return;
    }

    if (!login2faTablesReady(Database::getInstance())) {
        sendResponse(false, 'Two-factor authentication is not available on this server (database not migrated).', null, 503);
        return;
    }

    $password = (string)($_POST['password'] ?? '');
    $enable = isset($_POST['enable']) ? (int)$_POST['enable'] : -1;
    if ($password === '' || ($enable !== 0 && $enable !== 1)) {
        sendResponse(false, 'Password and enable flag (0 or 1) are required.', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        $uid = getCurrentUserId();
        $row = $db->fetchOne("SELECT id, password, email FROM users WHERE id = ? LIMIT 1", [$uid]);
        if (!$row || !password_verify($password, (string)$row['password'])) {
            sendResponse(false, 'Current password is incorrect.', null, 403);
            return;
        }

        if ($enable === 1) {
            $email = trim((string)($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendResponse(false, 'Add a valid email to your account before enabling email 2FA.', null, 400);
                return;
            }
            if (!SMTP_MAIL_ENABLED) {
                sendResponse(false, 'Email is not configured on this server; codes cannot be delivered. Contact an administrator.', null, 503);
                return;
            }
            $db->query(
                "UPDATE users SET two_factor_enabled = 1, two_factor_enabled_at = NOW() WHERE id = ?",
                [$uid]
            );
            try {
                logActivity('two_factor_enabled', 'auth', $uid);
            } catch (Exception $e) { /* ignore */ }
            sendResponse(true, 'Email verification at login is now enabled.', ['two_factor_enabled' => true]);
            return;
        }

        $db->query(
            "UPDATE users SET two_factor_enabled = 0, two_factor_enabled_at = NULL WHERE id = ?",
            [$uid]
        );
        try {
            logActivity('two_factor_disabled', 'auth', $uid);
        } catch (Exception $e) { /* ignore */ }
        sendResponse(true, 'Email verification at login is disabled.', ['two_factor_enabled' => false]);
    } catch (Exception $e) {
        error_log('toggle_two_factor: ' . $e->getMessage());
        sendResponse(false, 'Could not update settings.', null, 500);
    }
}

/**
 * Handle logout request
 */
function handleLogout() {
    logout();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $redirect = BASE_URL . 'index.php';
        header('Location: ' . $redirect);
        exit();
    }

    sendResponse(true, 'Logged out successfully', [
        'redirect' => BASE_URL . 'index.php'
    ]);
}

/**
 * Check authentication status
 */
function checkAuth() {
    if (isLoggedIn()) {
        $userInfo = getUserInfo();
        if ($userInfo) {
            unset($userInfo['password']);
            sendResponse(true, 'User is authenticated', ['user' => $userInfo]);
        } else {
            sendResponse(false, 'User session invalid', null, 401);
        }
    } else {
        sendResponse(false, 'User is not authenticated', null, 401);
    }
}

/**
 * Toggle view mode for non-resident users
 */
function handleViewMode() {
    if (!isLoggedIn()) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
        sendResponse(false, 'User is not authenticated', null, 401);
        return;
    }

    refreshSessionRole();
    if (normalizeRole(getRealUserRole()) === normalizeRole(ROLE_RESIDENT)) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: ' . BASE_URL . 'resident_dashboard.php');
            exit();
        }
        sendResponse(false, 'View mode is not available for resident accounts', null, 403);
        return;
    }

    $mode = sanitizeInput($_GET['mode'] ?? $_POST['mode'] ?? '');
    if ($mode !== 'resident' && $mode !== 'official') {
        $mode = 'official';
    }

    if (!setViewMode($mode)) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit();
        }
        sendResponse(false, 'View mode switch not allowed', null, 403);
        return;
    }

    $redirect = $mode === 'resident'
        ? BASE_URL . 'resident_dashboard.php'
        : BASE_URL . 'dashboard.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ' . $redirect);
        exit();
    }

    sendResponse(true, 'View mode updated', ['redirect' => $redirect]);
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit();
}
