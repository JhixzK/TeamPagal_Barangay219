<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

if (!canPerformModulePermission('blotters', 'can_edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $caseId = (int)($_POST['case_id'] ?? 0);
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $respondentId = !empty($_POST['respondent_id']) ? (int)$_POST['respondent_id'] : null;
    $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');

    if ($caseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Case ID required']);
        exit;
    }

    if (!in_array($newStatus, ['pending', 'investigation', 'mediation', 'settled', 'dismissed'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $db = Database::getInstance();
    $userId = getCurrentUserId();

    // Get current case data
    $currentCase = $db->fetchOne(
        'SELECT id, status, respondent_id FROM blotter_records WHERE id = ?',
        [$caseId]
    );

    if (!$currentCase) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Case not found']);
        exit;
    }

    // Begin transaction for data consistency
    $db->query('START TRANSACTION');

    // Prepare update query
    $updates = [];
    $params = [];

    if ($newStatus !== (string)($currentCase['status'] ?? '')) {
        $updates[] = 'status = ?';
        $params[] = $newStatus;
    }

    if ($respondentId !== null && $respondentId !== (int)($currentCase['respondent_id'] ?? 0)) {
        $updates[] = 'respondent_id = ?';
        $params[] = $respondentId;

        // Also fetch and update respondent_name JSON field
        $resident = $db->fetchOne(
            'SELECT id, first_name, middle_name, last_name, address, contact_number FROM residents WHERE id = ?',
            [$respondentId]
        );
        if ($resident) {
            $respondentNameData = [
                [
                    'name' => trim(($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ?? '') . ' ' . ($resident['last_name'] ?? '')),
                    'address' => $resident['address'] ?? '',
                    'contact' => $resident['contact_number'] ?? '',
                    'residency' => 'resident',
                    'resident_id' => $respondentId
                ]
            ];
            $updates[] = 'respondent_name = ?';
            $params[] = json_encode($respondentNameData, JSON_UNESCAPED_UNICODE);
        }
    }

    if ($adminNotes !== '') {
        $updates[] = 'admin_updates = ?';
        $params[] = $adminNotes;
    }

    if (!empty($updates)) {
        $params[] = $caseId;
        $db->query(
            'UPDATE blotter_records SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?',
            $params
        );
    }

    // Get admin name for audit log
    $adminUser = $db->fetchOne('SELECT username FROM users WHERE id = ?', [$userId]);
    $adminName = $adminUser['username'] ?? 'Unknown Admin';

    // Log status change if it happened
    if ($newStatus !== (string)($currentCase['status'] ?? '')) {
        $oldStatus = (string)($currentCase['status'] ?? 'pending');
        $db->query(
            'INSERT INTO blotter_logs (blotter_id, action, old_value, new_value, changed_by, admin_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $caseId,
                'status_change',
                $oldStatus,
                $newStatus,
                $userId,
                $adminName,
                'Status changed from ' . ucfirst($oldStatus) . ' to ' . ucfirst($newStatus)
            ]
        );
    }

    // Log respondent linkage if it happened
    if ($respondentId !== null && $respondentId !== (int)($currentCase['respondent_id'] ?? 0)) {
        $oldRespondentId = (int)($currentCase['respondent_id'] ?? 0);
        
        // Get resident name if linked
        $respondent = $db->fetchOne(
            'SELECT CONCAT(first_name, " ", last_name) as name FROM residents WHERE id = ?',
            [$respondentId]
        );
        $respondentName = $respondent['name'] ?? 'Unknown Resident';

        $db->query(
            'INSERT INTO blotter_logs (blotter_id, action, old_value, new_value, changed_by, admin_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $caseId,
                'respondent_link',
                (string)$oldRespondentId,
                (string)$respondentId,
                $userId,
                $adminName,
                'Respondent linked to: ' . $respondentName
            ]
        );
    }

    // If admin notes were added, log that too
    if ($adminNotes !== '') {
        $db->query(
            'INSERT INTO blotter_logs (blotter_id, action, changed_by, admin_name, notes) VALUES (?, ?, ?, ?, ?)',
            [
                $caseId,
                'admin_notes',
                $userId,
                $adminName,
                'Admin notes updated: ' . substr($adminNotes, 0, 100) . (strlen($adminNotes) > 100 ? '...' : '')
            ]
        );
    }

    // Commit transaction
    $db->query('COMMIT');

    // Fetch updated case
    $updatedCase = $db->fetchOne(
        'SELECT br.* FROM blotter_records br WHERE br.id = ?',
        [$caseId]
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Case updated successfully',
        'data' => [
            'id' => (int)$updatedCase['id'],
            'status' => $updatedCase['status'],
            'respondent_id' => $updatedCase['respondent_id'],
            'admin_updates' => $updatedCase['admin_updates']
        ]
    ]);

} catch (Exception $e) {
    $db->query('ROLLBACK');
    error_log('Blotter update_case error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
