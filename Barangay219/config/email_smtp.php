<?php
/**
 * SMTP settings for outbound mail (account activation, etc.)
 *
 * PHP always reads the saved file on disk — after editing, press Save (Ctrl+S).
 *
 * Gmail: use an App Password (Google Account → Security → 2-Step Verification → App passwords),
 * not your normal Gmail password. Paste the 16 characters with or without spaces.
 */
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not allowed');
}

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/constants.php';
}

define('SMTP_MAIL_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'barangay219official@gmail.com');
define('SMTP_PASSWORD', 'odpr gxju ltmo rbql');
/** 'tls' (port 587) | 'ssl' (port 465) | '' */
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_FROM_EMAIL', 'barangay219official@gmail.com');
define('SMTP_FROM_NAME', 'Barangay 219 Officials');
/**
 * XAMPP on Windows often fails TLS with "certificate verify failed".
 * Set to false only on local machines to test; use true in production.
 */
define('SMTP_VERIFY_SSL', false);
define('SMTP_DEBUG', false);
