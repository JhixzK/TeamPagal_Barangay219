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
        $search = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        
        $where = "WHERE status = 'published' AND (COALESCE(expires_at, expiration_date) IS NULL OR COALESCE(expires_at, expiration_date) >= CURDATE())";
        $params = [];
        
        if (!empty($search)) {
            $where .= " AND title LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $sql = "SELECT 
                    id,
                    title,
                    content,
                    image_path,
                    category,
                    priority,
                    is_pinned,
                    views,
                    created_at,
                    COALESCE(expires_at, expiration_date) AS expires_at
                FROM announcements
                $where
                ORDER BY is_pinned DESC, created_at DESC
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
        
        $sql = "SELECT 
                    id,
                    title,
                    content,
                    image_path,
                    category,
                    priority,
                    is_pinned,
                    views,
                    created_at,
                    COALESCE(expires_at, expiration_date) AS expires_at
                FROM announcements
                WHERE id = ?
                AND status = 'published'
                AND (COALESCE(expires_at, expiration_date) IS NULL OR COALESCE(expires_at, expiration_date) >= CURDATE())";
        
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
        
        // Verify announcement exists and is published
        $announcement = $db->fetchOne(
            "SELECT id FROM announcements WHERE id = ? AND status = 'published' AND (COALESCE(expires_at, expiration_date) IS NULL OR COALESCE(expires_at, expiration_date) >= CURDATE())",
            [$id]
        );
        
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
