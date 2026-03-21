<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// All actions require login and module access
requireLogin();
requireModuleAccess('announcements');

switch ($action) {
    case 'list': listAnnouncements(); break;
    case 'get': getAnnouncement(); break;
    case 'create':
        if (!canPerformModulePermission('announcements', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createAnnouncement();
        break;
    case 'update':
        if (!canPerformModulePermission('announcements', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateAnnouncement();
        break;
    case 'delete':
        if (!canPerformModulePermission('announcements', 'can_delete')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        deleteAnnouncement();
        break;
    case 'pin':
        if (!canPerformModulePermission('announcements', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        pinAnnouncement();
        break;
    case 'publish':
        if (!canPerformModulePermission('announcements', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateStatus('published');
        break;
    case 'unpublish':
        if (!canPerformModulePermission('announcements', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateStatus('draft');
        break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listAnnouncements() {
    try {
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $status = $_GET['status'] ?? '';
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $where = "1=1";
        $params = [];
        
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $params = array_merge($params, [$term, $term]);
        }
        
        if (in_array($status, ['draft', 'published'])) {
            $where .= " AND a.status = ?";
            $params[] = mapApiStatusToDbStatus($status, $columns);
        }

        $authorColumn = !empty($columns['created_by']) ? 'created_by' : (!empty($columns['posted_by']) ? 'posted_by' : null);
        $authorJoin = $authorColumn ? "LEFT JOIN users u ON a.$authorColumn = u.id" : "";
        $authorSelect = $authorColumn ? ", u.username as created_by_name" : ", NULL as created_by_name";
        $orderBy = !empty($columns['is_pinned']) ? "a.is_pinned DESC, " : "";
        $orderBy .= !empty($columns['created_at']) ? "a.created_at DESC" : "a.date_posted DESC";
        
        $sql = "SELECT a.* $authorSelect
                FROM announcements a 
                $authorJoin
                WHERE $where 
                ORDER BY $orderBy";

        $rows = $db->fetchAll($sql, $params);
        $rows = array_map(function ($row) use ($columns) {
            return normalizeAnnouncementRowForApi($row, $columns);
        }, $rows ?: []);

        sendResponse(true, 'Retrieved', $rows);
    } catch (Exception $e) {
        error_log('[Announcements] Error listing: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function listAnnouncementsPublic() {
    try {
        $db = Database::getInstance();
        $sql = "SELECT id, title, content, date_posted, expiration_date FROM announcements WHERE status = 'active' AND (expiration_date IS NULL OR expiration_date >= CURDATE()) ORDER BY date_posted DESC LIMIT 20";
        $list = $db->fetchAll($sql);
        sendResponse(true, 'Retrieved', $list);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getAnnouncement() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { 
        sendResponse(false, 'ID required', null, 400); 
        return; 
    }
    try {
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $authorColumn = !empty($columns['created_by']) ? 'created_by' : (!empty($columns['posted_by']) ? 'posted_by' : null);
        $authorJoin = $authorColumn ? "LEFT JOIN users u ON a.$authorColumn = u.id" : "";
        $authorSelect = $authorColumn ? ", u.username as created_by_name" : ", NULL as created_by_name";
        $a = $db->fetchOne(
            "SELECT a.* $authorSelect
             FROM announcements a 
             $authorJoin
             WHERE a.id = ?", 
            [$id]
        );
        if ($a) {
            $a = normalizeAnnouncementRowForApi($a, $columns);
        }
        sendResponse($a ? true : false, $a ? 'Found' : 'Not found', $a);
    } catch (Exception $e) {
        error_log('[Announcements] Error getting: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function createAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        sendResponse(false, 'POST required', null, 405); 
        return; 
    }
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? 'General');
    $priority = in_array($_POST['priority'] ?? 'normal', ['normal', 'urgent']) ? $_POST['priority'] : 'normal';
    $expires_at = normalizeAnnouncementDate($_POST['expires_at'] ?? null);
    $status = in_array($_POST['status'] ?? 'draft', ['draft', 'published']) ? $_POST['status'] : 'draft';
    
    if (!$title || !$content) { 
        sendResponse(false, 'Title and content required', null, 400); 
        return; 
    }

    if ($status === 'published' && isPastDate($expires_at)) {
        sendResponse(false, 'Expiration date cannot be in the past for published announcements', null, 400);
        return;
    }
    
    try {
        $imagePath = processAnnouncementImageUpload('photo');

        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $dbStatus = mapApiStatusToDbStatus($status, $columns);

        $insertColumns = ['title', 'content'];
        $insertValues = [$title, $content];

        if (!empty($columns['category'])) {
            $insertColumns[] = 'category';
            $insertValues[] = $category;
        }
        if (!empty($columns['priority'])) {
            $insertColumns[] = 'priority';
            $insertValues[] = $priority;
        }
        if (!empty($columns['status'])) {
            $insertColumns[] = 'status';
            $insertValues[] = $dbStatus;
        }
        if (!empty($columns['expires_at'])) {
            $insertColumns[] = 'expires_at';
            $insertValues[] = $expires_at;
        }
        if (!empty($columns['expiration_date'])) {
            $insertColumns[] = 'expiration_date';
            $insertValues[] = $expires_at;
        }
        if (!empty($columns['image_path'])) {
            $insertColumns[] = 'image_path';
            $insertValues[] = $imagePath;
        }
        if (!empty($columns['created_by'])) {
            $insertColumns[] = 'created_by';
            $insertValues[] = getCurrentUserId();
        }
        if (!empty($columns['posted_by'])) {
            $insertColumns[] = 'posted_by';
            $insertValues[] = getCurrentUserId();
        }
        if (!empty($columns['date_posted'])) {
            $insertColumns[] = 'date_posted';
            $insertValues[] = date('Y-m-d');
        }

        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
        $db->query(
            "INSERT INTO announcements (" . implode(', ', $insertColumns) . ") VALUES (" . $placeholders . ")",
            $insertValues
        );
        logActivity('create', 'announcements', $db->lastInsertId());
        sendResponse(true, 'Created', ['id' => $db->lastInsertId()]);
    } catch (Throwable $e) {
        error_log('[Announcements] Error creating: ' . $e->getMessage());
        sendResponse(false, 'Error creating announcement', null, 500);
    }
}

function updateAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        sendResponse(false, 'POST required', null, 405); 
        return; 
    }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { 
        sendResponse(false, 'ID required', null, 400); 
        return; 
    }
    
    $db = Database::getInstance();
    $columns = getAnnouncementColumns($db);
    $updates = [];
    $params = [];

    $current = $db->fetchOne("SELECT status, expiration_date" . (!empty($columns['expires_at']) ? ", expires_at" : "") . " FROM announcements WHERE id = ?", [$id]);
    if (!$current) {
        sendResponse(false, 'Announcement not found', null, 404);
        return;
    }

    $requestedStatus = isset($_POST['status']) && in_array($_POST['status'], ['draft', 'published'])
        ? $_POST['status']
        : mapDbStatusToApiStatus($current['status'] ?? 'draft');

    $currentExpires = $current['expires_at'] ?? ($current['expiration_date'] ?? null);
    $requestedExpires = array_key_exists('expires_at', $_POST)
        ? normalizeAnnouncementDate($_POST['expires_at'])
        : $currentExpires;

    if ($requestedStatus === 'published' && isPastDate($requestedExpires)) {
        sendResponse(false, 'Expiration date cannot be in the past for published announcements', null, 400);
        return;
    }
    
    $allowedFields = ['title', 'content', 'category', 'priority', 'expires_at', 'status'];
    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $value = $_POST[$field];
            
            // Validate specific fields
            if ($field === 'priority' && !in_array($value, ['normal', 'urgent'])) continue;
            if ($field === 'status' && !in_array($value, ['draft', 'published'])) continue;
            
            if ($field === 'expires_at') {
                $normalizedDate = normalizeAnnouncementDate($value);
                if (!empty($columns['expires_at'])) {
                    $updates[] = "expires_at = ?";
                    $params[] = $normalizedDate;
                }
                if (!empty($columns['expiration_date'])) {
                    $updates[] = "expiration_date = ?";
                    $params[] = $normalizedDate;
                }
                continue;
            }

            if ($field === 'status') {
                if (!empty($columns['status'])) {
                    $updates[] = "status = ?";
                    $params[] = mapApiStatusToDbStatus($value, $columns);
                }
                continue;
            }

            if (empty($columns[$field])) {
                continue;
            }

            $updates[] = "$field = ?";
            $params[] = $field === 'priority' || $field === 'status' ? $value : sanitizeInput($value);
        }
    }

    $existingImagePath = null;
    $newImagePath = null;
    try {
        if (!empty($columns['image_path'])) {
            $existingRow = $db->fetchOne("SELECT image_path FROM announcements WHERE id = ?", [$id]);
            $existingImagePath = $existingRow['image_path'] ?? null;
        }

        $newImagePath = processAnnouncementImageUpload('photo');
        if (!empty($newImagePath) && !empty($columns['image_path'])) {
            $updates[] = "image_path = ?";
            $params[] = $newImagePath;
        }
    } catch (Throwable $e) {
        error_log('[Announcements] Error preparing update image: ' . $e->getMessage());
        sendResponse(false, 'Error processing image upload', null, 500);
    }
    
    if (empty($updates)) { 
        sendResponse(false, 'Nothing to update', null, 400); 
        return; 
    }
    
    $params[] = $id;
    
    try {
        $db->query("UPDATE announcements SET " . implode(', ', $updates) . " WHERE id = ?", $params);

        if (!empty($newImagePath) && !empty($existingImagePath) && $newImagePath !== $existingImagePath) {
            deleteAnnouncementImageFile($existingImagePath);
        }

        sendResponse(true, 'Updated');
    } catch (Throwable $e) {
        error_log('[Announcements] Error updating: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function deleteAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        sendResponse(false, 'POST required', null, 405); 
        return; 
    }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { 
        sendResponse(false, 'ID required', null, 400); 
        return; 
    }
    try {
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $existing = !empty($columns['image_path'])
            ? $db->fetchOne("SELECT image_path FROM announcements WHERE id = ?", [$id])
            : ['image_path' => null];
        $db->query("DELETE FROM announcements WHERE id = ?", [$id]);
        if (!empty($existing['image_path'])) {
            deleteAnnouncementImageFile($existing['image_path']);
        }
        logActivity('delete', 'announcements', $id);
        sendResponse(true, 'Deleted');
    } catch (Exception $e) {
        error_log('[Announcements] Error deleting: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function pinAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        sendResponse(false, 'POST required', null, 405); 
        return; 
    }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { 
        sendResponse(false, 'ID required', null, 400); 
        return; 
    }
    
    try {
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);

        if (empty($columns['is_pinned'])) {
            sendResponse(true, 'Pinning is not enabled in this database schema');
            return;
        }
        
        // Check if announcement exists
        $announcement = $db->fetchOne("SELECT is_pinned FROM announcements WHERE id = ?", [$id]);
        if (!$announcement) {
            sendResponse(false, 'Announcement not found', null, 404);
            return;
        }
        
        // If already pinned, unpin it
        if ($announcement['is_pinned']) {
            $db->query("UPDATE announcements SET is_pinned = 0 WHERE id = ?", [$id]);
            sendResponse(true, 'Announcement unpinned');
            return;
        }
        
        // Unpin all other announcements
        $db->query("UPDATE announcements SET is_pinned = 0 WHERE is_pinned = 1");
        
        // Pin this one
        $db->query("UPDATE announcements SET is_pinned = 1 WHERE id = ?", [$id]);
        
        sendResponse(true, 'Announcement pinned');
    } catch (Exception $e) {
        error_log('[Announcements] Error pinning: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function updateStatus($newStatus) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        sendResponse(false, 'POST required', null, 405); 
        return; 
    }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { 
        sendResponse(false, 'ID required', null, 400); 
        return; 
    }
    
    if (!in_array($newStatus, ['draft', 'published'])) {
        sendResponse(false, 'Invalid status', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        $columns = getAnnouncementColumns($db);
        $dbStatus = mapApiStatusToDbStatus($newStatus, $columns);
        $db->query("UPDATE announcements SET status = ? WHERE id = ?", [$dbStatus, $id]);
        sendResponse(true, 'Status updated to ' . $newStatus);
    } catch (Exception $e) {
        error_log('[Announcements] Error updating status: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

function normalizeAnnouncementDate($rawDate) {
    $rawDate = trim((string)($rawDate ?? ''));
    if ($rawDate === '') {
        return null;
    }

    // HTML date input format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
        return $rawDate;
    }

    // Allow manually entered dates from mixed locales
    $formats = ['m/d/Y', 'n/j/Y', 'd/m/Y', 'j/n/Y', 'Y/m/d'];
    foreach ($formats as $format) {
        $parsed = DateTime::createFromFormat($format, $rawDate);
        if ($parsed instanceof DateTime) {
            return $parsed->format('Y-m-d');
        }
    }

    return null;
}

function processAnnouncementImageUpload($fieldName) {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed');
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Image must be 5MB or less');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload source');
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        throw new RuntimeException('Uploaded file is not a valid image');
    }

    $mime = strtolower((string)$imageInfo['mime']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Allowed image formats are JPG, PNG, WEBP');
    }

    $gdAvailable = function_exists('imagecreatetruecolor') && function_exists('getimagesize');
    if (!$gdAvailable) {
        // Fallback path when GD is not enabled: store validated file as-is.
        return storeUploadedImageWithoutProcessing($tmpPath, $mime);
    }

    switch ($mime) {
        case 'image/jpeg':
            $source = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $source = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                throw new RuntimeException('WEBP is not supported on this server');
            }
            $source = @imagecreatefromwebp($tmpPath);
            break;
        default:
            $source = false;
            break;
    }

    if (!$source) {
        // If format readers are unavailable, keep upload working by storing original file.
        return storeUploadedImageWithoutProcessing($tmpPath, $mime);
    }

    $originalWidth = imagesx($source);
    $originalHeight = imagesy($source);
    $maxWidth = 1200;
    $maxHeight = 800;

    $scale = min($maxWidth / max($originalWidth, 1), $maxHeight / max($originalHeight, 1), 1);
    $newWidth = max(1, (int)round($originalWidth * $scale));
    $newHeight = max(1, (int)round($originalHeight * $scale));

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    if (in_array($mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    $uploadDir = ROOT_PATH . '/uploads/announcements';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        imagedestroy($source);
        imagedestroy($resized);
        throw new RuntimeException('Unable to create upload directory');
    }

    $baseName = 'announcement_' . time() . '_' . bin2hex(random_bytes(4));
    $relativePath = 'uploads/announcements/' . $baseName;

    if (function_exists('imagewebp')) {
        $relativePath .= '.webp';
        $saved = imagewebp($resized, ROOT_PATH . '/' . $relativePath, 75);
    } elseif ($mime === 'image/png') {
        $relativePath .= '.png';
        $saved = imagepng($resized, ROOT_PATH . '/' . $relativePath, 6);
    } else {
        $relativePath .= '.jpg';
        $saved = imagejpeg($resized, ROOT_PATH . '/' . $relativePath, 75);
    }

    imagedestroy($source);
    imagedestroy($resized);

    if (!$saved) {
        return storeUploadedImageWithoutProcessing($tmpPath, $mime);
    }

    return $relativePath;
}

function storeUploadedImageWithoutProcessing($tmpPath, $mime) {
    $uploadDir = ROOT_PATH . '/uploads/announcements';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory');
    }

    $extension = 'jpg';
    if ($mime === 'image/png') {
        $extension = 'png';
    } elseif ($mime === 'image/webp') {
        $extension = 'webp';
    }

    $fileName = 'announcement_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $relativePath = 'uploads/announcements/' . $fileName;
    $targetPath = ROOT_PATH . '/' . $relativePath;

    if (!@move_uploaded_file($tmpPath, $targetPath)) {
        // Final fallback for environments where move_uploaded_file may fail unexpectedly.
        if (!@copy($tmpPath, $targetPath)) {
            throw new RuntimeException('Unable to save uploaded image');
        }
    }

    return $relativePath;
}

function deleteAnnouncementImageFile($path) {
    $value = trim((string)$path);
    if ($value === '') {
        return;
    }

    $normalized = str_replace('\\', '/', ltrim($value, '/'));
    if (strpos($normalized, 'uploads/announcements/') !== 0) {
        return;
    }

    $fullPath = ROOT_PATH . '/' . $normalized;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

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

function mapApiStatusToDbStatus($status, $columns) {
    $status = ($status === 'published') ? 'published' : 'draft';

    // Old schemas typically use active/inactive.
    if (!empty($columns['status']) && isset($columns['created_by'])) {
        return $status;
    }

    return $status === 'published' ? 'active' : 'inactive';
}

function mapDbStatusToApiStatus($status) {
    $normalized = strtolower((string)$status);
    if ($normalized === 'published' || $normalized === 'active') {
        return 'published';
    }
    return 'draft';
}

function normalizeAnnouncementRowForApi($row, $columns) {
    $row['category'] = $row['category'] ?? 'General';
    $row['priority'] = $row['priority'] ?? 'normal';
    $row['is_pinned'] = isset($row['is_pinned']) ? (int)$row['is_pinned'] : 0;
    $row['views'] = isset($row['views']) ? (int)$row['views'] : 0;

    if (empty($row['created_at']) && !empty($row['date_posted'])) {
        $row['created_at'] = $row['date_posted'];
    }

    if (empty($row['expires_at']) && !empty($row['expiration_date'])) {
        $row['expires_at'] = $row['expiration_date'];
    }

    $row['status'] = mapDbStatusToApiStatus($row['status'] ?? 'draft');

    return $row;
}

function isPastDate($dateValue) {
    if ($dateValue === null || trim((string)$dateValue) === '') {
        return false;
    }

    $dateOnly = substr((string)$dateValue, 0, 10);
    $today = date('Y-m-d');
    return $dateOnly < $today;
}
