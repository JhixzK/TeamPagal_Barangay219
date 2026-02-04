<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'statistics': 
        // Statistics available to all logged-in users for dashboard
        getStatistics(); 
        break;
    case 'population': 
    case 'certificates': 
    case 'blotters': 
    case 'complaints': 
        // Other reports require specific roles
        requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER]);
        if ($action === 'population') getPopulationReport();
        elseif ($action === 'certificates') getCertificatesReport();
        elseif ($action === 'blotters') getBlottersReport();
        elseif ($action === 'complaints') getComplaintsReport();
        break;
    default: 
        sendResponse(false, 'Invalid action', null, 400);
}

function getStatistics() {
    try {
        $db = Database::getInstance();
        $stats = [
            'total_residents' => $db->fetchOne("SELECT COUNT(*) as count FROM residents WHERE status = 'active'")['count'],
            'total_households' => $db->fetchOne("SELECT COUNT(*) as count FROM households")['count'],
            'pending_certificates' => $db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'pending'")['count'],
            'pending_blotters' => $db->fetchOne("SELECT COUNT(*) as count FROM blotters WHERE status = 'pending'")['count'],
            'pending_complaints' => $db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'")['count'],
            'issued_certificates' => $db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'issued'")['count'],
            'resolved_blotters' => $db->fetchOne("SELECT COUNT(*) as count FROM blotters WHERE status IN ('resolved', 'settled')")['count'],
            'resolved_complaints' => $db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status = 'resolved'")['count']
        ];
        sendResponse(true, 'Statistics retrieved', $stats);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getPopulationReport() {
    try {
        $db = Database::getInstance();
        $data = [
            'by_gender' => $db->fetchAll("SELECT gender, COUNT(*) as count FROM residents WHERE status = 'active' GROUP BY gender"),
            'by_civil_status' => $db->fetchAll("SELECT civil_status, COUNT(*) as count FROM residents WHERE status = 'active' GROUP BY civil_status"),
            'total' => $db->fetchOne("SELECT COUNT(*) as count FROM residents WHERE status = 'active'")['count']
        ];
        sendResponse(true, 'Population report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getCertificatesReport() {
    try {
        $db = Database::getInstance();
        $data = $db->fetchAll("SELECT certificate_type, status, COUNT(*) as count FROM certificate_requests GROUP BY certificate_type, status");
        sendResponse(true, 'Certificates report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getBlottersReport() {
    try {
        $db = Database::getInstance();
        $data = $db->fetchAll("SELECT status, COUNT(*) as count FROM blotters GROUP BY status");
        sendResponse(true, 'Blotters report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getComplaintsReport() {
    try {
        $db = Database::getInstance();
        $data = $db->fetchAll("SELECT status, COUNT(*) as count FROM complaints GROUP BY status");
        sendResponse(true, 'Complaints report', $data);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
