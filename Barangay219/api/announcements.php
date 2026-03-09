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
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * List all active announcements for residents
 * Ordered by date_posted DESC (most recent first)
 * Limited to recent announcements
 */
function listAnnouncements() {
    try {
        $db = Database::getInstance();
        
        // Residents can only view active announcements that haven't expired
        $sql = "SELECT 
                    id,
                    title,
                    content,
                    date_posted,
                    expiration_date,
                    created_at,
                    updated_at
                FROM announcements
                WHERE status = 'active'
                AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                ORDER BY date_posted DESC
                LIMIT 50";
        
        $announcements = $db->fetchAll($sql);
        
        sendResponse(true, 'Announcements retrieved', $announcements);
    } catch (Exception $e) {
        error_log('[Announcements API] Error listing: ' . $e->getMessage());
        sendResponse(false, 'Error retrieving announcements', null, 500);
    }
}

/**
 * Get a single announcement by ID
 * Residents can view any active, non-expired announcement
 */
function getAnnouncement() {
    try {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            sendResponse(false, 'Announcement ID is required', null, 400);
            return;
        }
        
        $db = Database::getInstance();
        
        // Residents can only view active, non-expired announcements
        $sql = "SELECT 
                    id,
                    title,
                    content,
                    date_posted,
                    expiration_date,
                    created_at,
                    updated_at
                FROM announcements
                WHERE id = ?
                AND status = 'active'
                AND (expiration_date IS NULL OR expiration_date >= CURDATE())";
        
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
