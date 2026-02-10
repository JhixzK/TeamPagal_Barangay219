<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'statistics': 
        requireModuleAccess('dashboard');
        getStatistics(); 
        break;
    case 'recent_activities':
        requireModuleAccess('dashboard');
        getRecentActivities();
        break;
    case 'population': 
    case 'certificates': 
    case 'blotters': 
    case 'complaints':
    case 'announcements':
    case 'applications':
        requireModuleAccess('reports');
        if ($action === 'population') getPopulationReport();
        elseif ($action === 'certificates') getCertificatesReport();
        elseif ($action === 'blotters') getBlottersReport();
        elseif ($action === 'complaints') getComplaintsReport();
        elseif ($action === 'announcements') getAnnouncementsReport();
        elseif ($action === 'applications') getApplicationsReport();
        break;
    default: 
        sendResponse(false, 'Invalid action', null, 400);
}

function getStatistics() {
    try {
        $db = Database::getInstance();
        $stats = [
            'total_residents' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM residents WHERE status = 'active'")['count'],
            'total_households' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM households")['count'],
            'pending_certificates' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'pending'")['count'],
            'pending_applications' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'pending'")['count'],
            'pending_blotters' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM blotters WHERE status = 'pending'")['count'],
            'pending_complaints' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'")['count'],
            'issued_certificates' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'issued'")['count'],
            'resolved_blotters' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM blotters WHERE status IN ('resolved', 'settled')")['count'],
            'resolved_complaints' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status = 'resolved'")['count'],
            'active_announcements' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM announcements WHERE status = 'active'")['count']
        ];
        sendResponse(true, 'Statistics retrieved', $stats);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getRecentActivities() {
    try {
        $db = Database::getInstance();
        if (!activityLogsTableExists($db)) {
            sendResponse(true, 'Recent activities', []);
        }
        $limit = (int)($_GET['limit'] ?? 10);
        $limit = min(50, max(5, $limit));
        $rows = $db->fetchAll(
            "SELECT al.*, u.username FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC LIMIT ?",
            [$limit]
        );
        sendResponse(true, 'Recent activities', $rows);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function activityLogsTableExists($db) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $result = $db->fetchOne("SHOW TABLES LIKE 'activity_logs'");
        $exists = !empty($result);
    } catch (Exception $e) {
        $exists = false;
    }
    return $exists;
}

function getDateFilter() {
    $from = $_GET['from'] ?? $_POST['from'] ?? null;
    $to = $_GET['to'] ?? $_POST['to'] ?? null;
    return [$from ?: null, $to ?: null];
}

function getPopulationReport() {
    try {
        $db = Database::getInstance();
        $data = [
            'by_gender' => $db->fetchAll("SELECT gender, COUNT(*) as count FROM residents WHERE status = 'active' GROUP BY gender"),
            'by_civil_status' => $db->fetchAll("SELECT civil_status, COUNT(*) as count FROM residents WHERE status = 'active' GROUP BY civil_status"),
            'total' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM residents WHERE status = 'active'")['count']
        ];
        sendResponse(true, 'Population report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getCertificatesReport() {
    try {
        $db = Database::getInstance();
        list($from, $to) = getDateFilter();
        $where = "1=1";
        $params = [];
        if ($from) { $where .= " AND DATE(c.created_at) >= ?"; $params[] = $from; }
        if ($to) { $where .= " AND DATE(c.created_at) <= ?"; $params[] = $to; }
        $sql = "SELECT c.certificate_type, c.status, COUNT(*) as count FROM certificate_requests c WHERE $where GROUP BY c.certificate_type, c.status";
        $data = $db->fetchAll($sql, $params);
        sendResponse(true, 'Certificates report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getBlottersReport() {
    try {
        $db = Database::getInstance();
        list($from, $to) = getDateFilter();
        $where = "1=1";
        $params = [];
        if ($from) { $where .= " AND DATE(incident_date) >= ?"; $params[] = $from; }
        if ($to) { $where .= " AND DATE(incident_date) <= ?"; $params[] = $to; }
        $data = $db->fetchAll("SELECT status, COUNT(*) as count FROM blotters WHERE $where GROUP BY status", $params);
        sendResponse(true, 'Blotters report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getComplaintsReport() {
    try {
        $db = Database::getInstance();
        list($from, $to) = getDateFilter();
        $where = "1=1";
        $params = [];
        if ($from) { $where .= " AND DATE(filing_date) >= ?"; $params[] = $from; }
        if ($to) { $where .= " AND DATE(filing_date) <= ?"; $params[] = $to; }
        $data = $db->fetchAll("SELECT status, COUNT(*) as count FROM complaints WHERE $where GROUP BY status", $params);
        sendResponse(true, 'Complaints report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getAnnouncementsReport() {
    try {
        $db = Database::getInstance();
        list($from, $to) = getDateFilter();
        $where = "1=1";
        $params = [];
        if ($from) { $where .= " AND DATE(date_posted) >= ?"; $params[] = $from; }
        if ($to) { $where .= " AND DATE(date_posted) <= ?"; $params[] = $to; }
        $data = $db->fetchAll("SELECT status, COUNT(*) as count FROM announcements WHERE $where GROUP BY status", $params);
        sendResponse(true, 'Announcements report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getApplicationsReport() {
    try {
        $db = Database::getInstance();
        list($from, $to) = getDateFilter();
        $where = "1=1";
        $params = [];
        if ($from) { $where .= " AND DATE(created_at) >= ?"; $params[] = $from; }
        if ($to) { $where .= " AND DATE(created_at) <= ?"; $params[] = $to; }
        $data = $db->fetchAll("SELECT status, certificate_type, COUNT(*) as count FROM certificate_requests WHERE $where GROUP BY status, certificate_type", $params);
        sendResponse(true, 'Applications report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
