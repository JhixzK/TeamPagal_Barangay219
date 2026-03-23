<?php
header('Content-Type: application/json; charset=UTF-8');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

requireLogin();

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied',
        'data' => null,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'GET required',
        'data' => null,
    ]);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) < 3) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Search keyword must be at least 3 characters.',
        'data' => [],
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    $term = '%' . sanitizeInput($q) . '%';

    $rows = $db->fetchAll(
        "SELECT id,
                CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name,
                COALESCE(purok_sitio, '') AS purok_sitio
         FROM residents
         WHERE first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?
            OR resident_code LIKE ?
         ORDER BY last_name ASC, first_name ASC
         LIMIT 20",
        [$term, $term, $term, $term, $term]
    );

    $data = array_map(static function ($row) {
        return [
            'id' => (int)($row['id'] ?? 0),
            'full_name' => trim((string)($row['full_name'] ?? '')),
            'purok_sitio' => trim((string)($row['purok_sitio'] ?? '')),
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'message' => 'Residents found',
        'data' => $data,
    ]);
} catch (Exception $e) {
    error_log('Residents search API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to search residents',
        'data' => null,
    ]);
}
