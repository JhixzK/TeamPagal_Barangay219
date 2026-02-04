<?php
/**
 * E-Barangay Information Management System
 * Constants and Configuration
 */

// Prevent direct access
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not allowed');
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'barangay219_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'E-Barangay Information Management System');
define('APP_VERSION', '1.0.0');
define('BARANGAY_NAME', 'Barangay 195, Tondo, Manila');

// Base URLs
// Auto-detect the correct path based on the current script location

// Hardcoded base URLs for reliability
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/TeamPagal_Barangay219/Barangay219/public/');
    define('API_URL', 'http://localhost/TeamPagal_Barangay219/Barangay219/api/');
}

define('ASSETS_URL', BASE_URL . 'assets/');

// File Paths
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('API_PATH', ROOT_PATH . '/api');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// User Roles
define('ROLE_BARANGAY_CAPTAIN', 'barangay_captain');
define('ROLE_SECRETARY', 'secretary');
define('ROLE_TREASURER', 'treasurer');
define('ROLE_KAGAWA', 'kagawad');
define('ROLE_SK_CHAIRMAN', 'sk_chairman');

// Certificate Types
define('CERT_BARANGAY_CLEARANCE', 'barangay_clearance');
define('CERT_INDIGENCY', 'certificate_indigency');
define('CERT_RESIDENCY', 'certificate_residency');
define('CERT_TRANSFER_REQUEST', 'transfer_request');

// Status Values
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_ISSUED', 'issued');
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_SUSPENDED', 'suspended');
define('STATUS_RESOLVED', 'resolved');
define('STATUS_SETTLED', 'settled');
define('STATUS_UNDER_INVESTIGATION', 'under_investigation');
define('STATUS_UNDER_REVIEW', 'under_review');
define('STATUS_DISMISSED', 'dismissed');
define('STATUS_REFERRED', 'referred');
define('STATUS_EXPIRED', 'expired');

// User Status
define('USER_ACTIVE', 'active');
define('USER_INACTIVE', 'inactive');
define('USER_SUSPENDED', 'suspended');

// Resident Status
define('RESIDENT_ACTIVE', 'active');
define('RESIDENT_INACTIVE', 'inactive');
define('RESIDENT_DECEASED', 'deceased');
define('RESIDENT_TRANSFERRED', 'transferred');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'EBARANGAY_SESSION');

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'F d, Y');
define('DISPLAY_DATETIME_FORMAT', 'F d, Y h:i A');

// File Upload (if needed in future)
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Error Reporting (set to 0 in production)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
