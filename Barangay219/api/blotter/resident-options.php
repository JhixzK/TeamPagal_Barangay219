<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    blotterJson(false, 'GET required', null, 405);
}

try {
    $residentId = requireResidentSessionForBlotter();

    $q = trim((string)($_GET['q'] ?? ''));
    $limit = min(300, max(30, (int)($_GET['limit'] ?? 150)));

    $where = 'WHERE id <> ?';
    $params = [$residentId];

    if ($q !== '') {
        $term = '%' . $q . '%';
        $where .= ' AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ? OR CONCAT(last_name, ", ", first_name) LIKE ?)';
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    $db = Database::getInstance();
    $rows = $db->fetchAll(
        "SELECT id, first_name, middle_name, last_name
         FROM residents
         {$where}
         ORDER BY last_name ASC, first_name ASC
         LIMIT {$limit}",
        $params
    );

    blotterJson(true, 'Respondent options loaded.', ['residents' => $rows]);
} catch (Exception $e) {
    error_log('Resident respondent options error: ' . $e->getMessage());
    blotterJson(false, 'Unable to load respondent options.', null, 500);
}
