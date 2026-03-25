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
    $cleanQ = sanitizeInput($q);
    $term = '%' . $cleanQ . '%';
    $nameParts = preg_split('/\s+/', $cleanQ, 2);

    $where = [
        'first_name LIKE ?',
        'middle_name LIKE ?',
        'last_name LIKE ?',
        'resident_code LIKE ?'
    ];
    $params = [$term, $term, $term, $term];

    if (count($nameParts) === 2 && $nameParts[0] !== '' && $nameParts[1] !== '') {
        $firstLike = $nameParts[0] . '%';
        $lastLike = $nameParts[1] . '%';
        $where[] = '(first_name LIKE ? AND last_name LIKE ?)';
        $where[] = '(first_name LIKE ? AND middle_name LIKE ?)';
        $params[] = $firstLike;
        $params[] = $lastLike;
        $params[] = $firstLike;
        $params[] = $lastLike;
    }

    $sql = "SELECT id,
                   CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name,
                   COALESCE(purok_sitio, '') AS purok_sitio
            FROM residents
            WHERE " . implode(' OR ', $where) . "
            ORDER BY last_name ASC, first_name ASC
            LIMIT 20";

    $rows = $db->fetchAll($sql, $params);

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
