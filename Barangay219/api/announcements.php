<?php
/**
 * E-Barangay Information Management System
 * Announcements API - Resident Module
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

// Require login for all operations
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listAnnouncements();
        break;
    
    case 'get':
        getAnnouncement();
        break;

    case 'increment-views':
        incrementViews();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * List all published announcements for residents
 * Ordered by pinned first, then most recent
 */
function listAnnouncements() {
    try {
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $expiryExpr = getAnnouncementExpiryExpression($columns);
        $createdExpr = getAnnouncementCreatedExpression($columns);
        $search = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($columns['status'])) {
            $where .= " AND status IN ('published', 'active')";
        }

        if ($expiryExpr !== null) {
            $where .= " AND ($expiryExpr IS NULL OR $expiryExpr >= CURDATE())";
        }
        
        if (!empty($search)) {
            $where .= " AND title LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $sql = "SELECT 
                    id,
                    title,
                    content,
                    " . (!empty($columns['image_path']) ? "image_path" : "NULL") . " AS image_path,
                    " . (!empty($columns['category']) ? "category" : "'General'") . " AS category,
                    " . (!empty($columns['priority']) ? "priority" : "'normal'") . " AS priority,
                    " . (!empty($columns['is_pinned']) ? "is_pinned" : "0") . " AS is_pinned,
                    " . (!empty($columns['views']) ? "views" : "0") . " AS views,
                    $createdExpr AS created_at,
                    " . ($expiryExpr !== null ? $expiryExpr : "NULL") . " AS expires_at
                FROM announcements
                $where
                ORDER BY " . (!empty($columns['is_pinned']) ? "is_pinned DESC, " : "") . "$createdExpr DESC
                LIMIT 50";
        
        $announcements = $db->fetchAll($sql, $params);
        
        sendResponse(true, 'Announcements retrieved', $announcements);
    } catch (Exception $e) {
        error_log('[Announcements API] Error listing: ' . $e->getMessage());
        sendResponse(false, 'Error retrieving announcements', null, 500);
    }
}

/**
 * Get a single announcement by ID
 * Residents can view any published, non-expired announcement
 */
function getAnnouncement() {
    try {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            sendResponse(false, 'Announcement ID is required', null, 400);
            return;
        }
        
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $expiryExpr = getAnnouncementExpiryExpression($columns);
        $createdExpr = getAnnouncementCreatedExpression($columns);
        
        $sql = "SELECT 
                    id,
                    title,
                    content,
                " . (!empty($columns['image_path']) ? "image_path" : "NULL") . " AS image_path,
                " . (!empty($columns['category']) ? "category" : "'General'") . " AS category,
                " . (!empty($columns['priority']) ? "priority" : "'normal'") . " AS priority,
                " . (!empty($columns['is_pinned']) ? "is_pinned" : "0") . " AS is_pinned,
                " . (!empty($columns['views']) ? "views" : "0") . " AS views,
                $createdExpr AS created_at,
                " . ($expiryExpr !== null ? $expiryExpr : "NULL") . " AS expires_at
                FROM announcements
                WHERE id = ?
            " . (!empty($columns['status']) ? "AND status IN ('published', 'active')" : "") . "
            " . ($expiryExpr !== null ? "AND ($expiryExpr IS NULL OR $expiryExpr >= CURDATE())" : "") . "";
        
        $announcement = $db->fetchOne($sql, [$id]);
        
        if (!$announcement) {
            sendResponse(false, 'Announcement not found', null, 404);
            return;
        }
        
        sendResponse(true, 'Announcement retrieved', $announcement);
    } catch (Exception $e) {
        error_log('[Announcements API] Error getting single: ' . $e->getMessage());
        sendResponse(false, 'Error retrieving announcement', null, 500);
    }
}

/**
 * Increment view count for an announcement
 */
function incrementViews() {
    try {
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            sendResponse(false, 'Announcement ID is required', null, 400);
            return;
        }
        
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $expiryExpr = getAnnouncementExpiryExpression($columns);

        if (empty($columns['views'])) {
            // Older schema has no views column; treat as a successful no-op.
            sendResponse(true, 'Views tracking not enabled');
            return;
        }
        
        // Verify announcement exists and is published
        $where = "id = ?";
        if (!empty($columns['status'])) {
            $where .= " AND status IN ('published', 'active')";
        }
        if ($expiryExpr !== null) {
            $where .= " AND ($expiryExpr IS NULL OR $expiryExpr >= CURDATE())";
        }

        $announcement = $db->fetchOne("SELECT id FROM announcements WHERE $where", [$id]);
        
        if (!$announcement) {
            sendResponse(false, 'Announcement not found', null, 404);
            return;
        }
        
        // Increment views
        $db->query("UPDATE announcements SET views = views + 1 WHERE id = ?", [$id]);
        
        sendResponse(true, 'Views incremented');
    } catch (Exception $e) {
        error_log('[Announcements API] Error incrementing views: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

/**
 * Send JSON response
 * @param bool $success
 * @param string $message
 * @param mixed $data
 * @param int $httpCode
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

/**
 * Return announcements table column map (lowercased).
 * Supports mixed schemas across deployments.
 */
function getAnnouncementColumns($db) {
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $rows = $db->fetchAll("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!empty($row['COLUMN_NAME'])) {
                $columns[strtolower($row['COLUMN_NAME'])] = true;
            }
        }
    }

    return $columns;
}

/**
 * Build expiration SQL expression only from existing columns.
 */
function getAnnouncementExpiryExpression($columns) {
    $hasExpiresAt = !empty($columns['expires_at']);
    $hasExpirationDate = !empty($columns['expiration_date']);

    if ($hasExpiresAt && $hasExpirationDate) {
        return 'COALESCE(expires_at, expiration_date)';
    }
    if ($hasExpiresAt) {
        return 'expires_at';
    }
    if ($hasExpirationDate) {
        return 'expiration_date';
    }

    return null;
}

/**
 * Choose a compatible created date field.
 */
function getAnnouncementCreatedExpression($columns) {
    if (!empty($columns['created_at'])) {
        return 'created_at';
    }
    if (!empty($columns['date_posted'])) {
        return 'date_posted';
    }

    return 'NOW()';
}
