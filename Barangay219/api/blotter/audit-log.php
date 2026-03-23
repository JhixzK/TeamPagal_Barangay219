<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

try {
    $caseId = (int)($_GET['case_id'] ?? 0);
    
    if ($caseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Case ID required']);
        exit;
    }

    $db = Database::getInstance();
    
    // Fetch audit logs for this case, ordered by timestamp
    $logs = $db->fetchAll(
        'SELECT id, blotter_id, action, old_value, new_value, changed_by, admin_name, timestamp, notes 
         FROM blotter_logs 
         WHERE blotter_id = ? 
         ORDER BY timestamp DESC',
        [$caseId]
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Audit logs retrieved',
        'data' => $logs ?? []
    ]);

} catch (Exception $e) {
    error_log('Blotter audit-log error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
