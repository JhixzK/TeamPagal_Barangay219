<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'dashboard_bundle':
        requireModuleAccess('dashboard');
        getDashboardBundle();
        break;
    case 'statistics': 
        requireModuleAccess('dashboard');
        getStatistics(); 
        break;
    case 'recent_activities':
        requireModuleAccess('dashboard');
        getRecentActivities();
        break;
    case 'dashboard_charts':
        requireModuleAccess('dashboard');
        getDashboardCharts();
        break;
    case 'module_stats':
        $module = sanitizeInput($_GET['module'] ?? $_POST['module'] ?? '');
        getModuleStats($module);
        break;
    case 'population': 
    case 'certificates': 
    case 'blotters': 
    case 'complaints':
    case 'announcements':
    case 'applications':
    case 'activity_logs':
        requireModuleAccess('reports');
        if ($action === 'population') getPopulationReport();
        elseif ($action === 'certificates') getCertificatesReport();
        elseif ($action === 'blotters') getBlottersReport();
        elseif ($action === 'complaints') getComplaintsReport();
        elseif ($action === 'announcements') getAnnouncementsReport();
        elseif ($action === 'applications') getApplicationsReport();
        elseif ($action === 'activity_logs') getActivityLogsReport();
        break;
    default: 
        sendResponse(false, 'Invalid action', null, 400);
}

/**
 * KPI counts in one DB round trip (dashboard was doing 10 sequential COUNTs).
 *
 * @return array<string,int>|null
 */
function fetchDashboardStatisticsRow($db) {
    try {
        $row = $db->fetchOne(
            'SELECT
                (SELECT COUNT(*) FROM residents WHERE status = \'active\') AS total_residents,
                (SELECT COUNT(*) FROM households) AS total_households,
                (SELECT COUNT(*) FROM certificate_requests WHERE status = \'pending\') AS pending_certificates,
                (SELECT COUNT(*) FROM blotters WHERE status = \'pending\') AS pending_blotters,
                (SELECT COUNT(*) FROM complaints WHERE status IN (\'pending\', \'Pending Review\', \'Under Investigation\', \'Scheduled for Mediation\')) AS pending_complaints,
                (SELECT COUNT(*) FROM certificate_requests WHERE status IN (\'released\', \'issued\')) AS issued_certificates,
                (SELECT COUNT(*) FROM blotters WHERE status IN (\'resolved\', \'settled\')) AS resolved_blotters,
                (SELECT COUNT(*) FROM complaints WHERE status IN (\'resolved\', \'Resolved\')) AS resolved_complaints,
                (SELECT COUNT(*) FROM announcements WHERE status = \'active\') AS active_announcements'
        );
    } catch (Exception $e) {
        return null;
    }
    if (!$row) {
        return null;
    }
    $pending = (int)$row['pending_certificates'];
    return [
        'total_residents' => (int)$row['total_residents'],
        'total_households' => (int)$row['total_households'],
        'pending_certificates' => $pending,
        'pending_applications' => $pending,
        'pending_blotters' => (int)$row['pending_blotters'],
        'pending_complaints' => (int)$row['pending_complaints'],
        'issued_certificates' => (int)$row['issued_certificates'],
        'resolved_blotters' => (int)$row['resolved_blotters'],
        'resolved_complaints' => (int)$row['resolved_complaints'],
        'active_announcements' => (int)$row['active_announcements'],
    ];
}

function getStatistics() {
    try {
        $db = Database::getInstance();
        $stats = fetchDashboardStatisticsRow($db);
        if ($stats === null) {
            sendResponse(false, 'Error', null, 500);
        }
        sendResponse(true, 'Statistics retrieved', $stats);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

/**
 * @return list<array<string,mixed>>
 */
function fetchRecentActivitiesForDashboard($db, $limit) {
    if (!activityLogsTableExists($db)) {
        return [];
    }
    $limit = min(50, max(5, (int)$limit));
    $exclude = activityLogsExcludeLoginSql('al');
    $rows = $db->fetchAll(
        "SELECT al.*, u.username FROM activity_logs al 
         LEFT JOIN users u ON al.user_id = u.id 
         WHERE $exclude
         ORDER BY al.created_at DESC LIMIT " . (int)$limit
    );
    return activityLogsWithSummary($rows);
}

function getRecentActivities() {
    try {
        $db = Database::getInstance();
        $limit = (int)($_GET['limit'] ?? 10);
        sendResponse(true, 'Recent activities', fetchRecentActivitiesForDashboard($db, $limit));
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getDashboardBundle() {
    try {
        $db = Database::getInstance();
        $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 10);
        $stats = fetchDashboardStatisticsRow($db);
        if ($stats === null) {
            sendResponse(false, 'Error', null, 500);
        }
        sendResponse(true, 'Dashboard data retrieved', [
            'statistics' => $stats,
            'charts' => buildDashboardChartsPayload($db),
            'recent_activities' => fetchRecentActivitiesForDashboard($db, $limit),
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getModuleStats($module) {
    $allowed = ['residents', 'households', 'applications', 'certificates', 'complaints', 'blotters', 'announcements', 'officials', 'users', 'reports', 'profile', 'resident_applications'];
    if (!in_array($module, $allowed, true)) {
        sendResponse(false, 'Invalid module', null, 400);
    }

    if ($module === 'users') {
        requireAdmin();
    } elseif ($module === 'officials') {
        requireModuleAccess('officials');
    } elseif ($module !== 'profile') {
        requireModuleAccess($module);
    }

    try {
        $db = Database::getInstance();
        $stats = [];

        switch ($module) {
            case 'residents':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status = 'active'), 0) AS active,\n" .
                    "COALESCE(SUM(status = 'inactive'), 0) AS inactive,\n" .
                    "COALESCE(SUM(status = 'deceased'), 0) AS deceased,\n" .
                    "COALESCE(SUM(status = 'transferred'), 0) AS transferred\n" .
                    "FROM residents"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'active' => (int)$row['active'],
                    'inactive' => (int)$row['inactive'],
                    'deceased' => (int)$row['deceased'],
                    'transferred' => (int)$row['transferred']
                ];
                break;
            case 'households':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total_households,\n" .
                    "COALESCE(SUM(total_members), 0) AS total_members,\n" .
                    "COALESCE(SUM(registration_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS new_this_month,\n" .
                    "COALESCE(SUM(registration_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')), 0) AS new_this_year\n" .
                    "FROM households"
                );
                $stats = [
                    'total_households' => (int)$row['total_households'],
                    'total_members' => (int)$row['total_members'],
                    'new_this_month' => (int)$row['new_this_month'],
                    'new_this_year' => (int)$row['new_this_year']
                ];
                break;
            case 'applications':
            case 'certificates':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status = 'pending'), 0) AS pending,\n" .
                    "COALESCE(SUM(status = 'approved'), 0) AS approved,\n" .
                    "COALESCE(SUM(status = 'rejected'), 0) AS rejected,\n" .
                    "COALESCE(SUM(status IN ('released', 'issued')), 0) AS issued\n" .
                    "FROM certificate_requests"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'pending' => (int)$row['pending'],
                    'approved' => (int)$row['approved'],
                    'rejected' => (int)$row['rejected'],
                    'issued' => (int)$row['issued']
                ];
                break;
            case 'complaints':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status IN ('pending', 'Pending Review')), 0) AS pending,\n" .
                    "COALESCE(SUM(status IN ('under_review', 'Under Investigation', 'Scheduled for Mediation')), 0) AS under_review,\n" .
                    "COALESCE(SUM(status IN ('resolved', 'Resolved')), 0) AS resolved,\n" .
                    "COALESCE(SUM(status IN ('dismissed', 'Dismissed')), 0) AS dismissed\n" .
                    "FROM complaints"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'pending' => (int)$row['pending'],
                    'under_review' => (int)$row['under_review'],
                    'resolved' => (int)$row['resolved'],
                    'dismissed' => (int)$row['dismissed']
                ];
                break;
            case 'blotters':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status = 'pending'), 0) AS pending,\n" .
                    "COALESCE(SUM(status = 'resolved'), 0) AS resolved,\n" .
                    "COALESCE(SUM(status = 'settled'), 0) AS settled\n" .
                    "FROM blotters"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'pending' => (int)$row['pending'],
                    'resolved' => (int)$row['resolved'],
                    'settled' => (int)$row['settled']
                ];
                break;
            case 'announcements':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status = 'active'), 0) AS active,\n" .
                    "COALESCE(SUM(status = 'inactive'), 0) AS inactive,\n" .
                    "COALESCE(SUM(status = 'expired'), 0) AS expired,\n" .
                    "COALESCE(SUM(status = 'archived'), 0) AS archived\n" .
                    "FROM announcements"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'active' => (int)$row['active'],
                    'inactive' => (int)$row['inactive'],
                    'expired' => (int)$row['expired'],
                    'archived' => (int)$row['archived']
                ];
                break;
            case 'users':
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status = 'active'), 0) AS active,\n" .
                    "COALESCE(SUM(status = 'inactive'), 0) AS inactive,\n" .
                    "COALESCE(SUM(status = 'suspended'), 0) AS suspended\n" .
                    "FROM users"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'active' => (int)$row['active'],
                    'inactive' => (int)$row['inactive'],
                    'suspended' => (int)$row['suspended']
                ];
                break;
            case 'officials':
                $tableRow = $db->fetchOne("SHOW TABLES LIKE 'officials'");
                if (empty($tableRow)) {
                    $stats = [
                        'total' => 0,
                        'active' => 0,
                        'inactive' => 0,
                        'kagawad_active' => 0
                    ];
                    break;
                }
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(status = 'active'), 0) AS active,\n" .
                    "COALESCE(SUM(status = 'inactive'), 0) AS inactive,\n" .
                    "COALESCE(SUM(status = 'active' AND position = 'kagawad'), 0) AS kagawad_active\n" .
                    "FROM officials"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'active' => (int)$row['active'],
                    'inactive' => (int)$row['inactive'],
                    'kagawad_active' => (int)$row['kagawad_active']
                ];
                break;
            case 'reports':
                $stats = [
                    'total_residents' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM residents WHERE status = 'active'")['count'],
                    'total_households' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM households")['count'],
                    'issued_certificates' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status IN ('released', 'issued')")['count'],
                    'pending_applications' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'pending'")['count'],
                    'pending_complaints' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status IN ('pending', 'Pending Review', 'Under Investigation', 'Scheduled for Mediation')")['count'],
                    'active_announcements' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM announcements WHERE status = 'active'")['count']
                ];
                break;
            case 'resident_applications':
                $tableRow = $db->fetchOne("SHOW TABLES LIKE 'resident_applications'");
                if (empty($tableRow)) {
                    $stats = [
                        'total' => 0,
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                    break;
                }
                $row = $db->fetchOne(
                    "SELECT COUNT(*) AS total,\n" .
                    "COALESCE(SUM(record_status = 'pending'), 0) AS pending,\n" .
                    "COALESCE(SUM(record_status = 'approved'), 0) AS approved,\n" .
                    "COALESCE(SUM(record_status = 'rejected'), 0) AS rejected\n" .
                    "FROM resident_applications"
                );
                $stats = [
                    'total' => (int)$row['total'],
                    'pending' => (int)$row['pending'],
                    'approved' => (int)$row['approved'],
                    'rejected' => (int)$row['rejected']
                ];
                break;
            case 'profile':
                $userId = (int)getCurrentUserId();
                $residentRow = $db->fetchOne("SELECT resident_id FROM users WHERE id = ?", [$userId]);
                $residentId = (int)($residentRow['resident_id'] ?? 0);
                if ($residentId) {
                    $certRow = $db->fetchOne(
                        "SELECT COUNT(*) AS total,\n" .
                        "COALESCE(SUM(status IN ('released', 'issued')), 0) AS issued,\n" .
                        "COALESCE(SUM(status = 'pending'), 0) AS pending\n" .
                        "FROM certificate_requests WHERE resident_id = ?",
                        [$residentId]
                    );
                    $hasResidentCol = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'resident_id'")->fetchAll());
                    if ($hasResidentCol) {
                        $compRow = $db->fetchOne(
                            "SELECT COUNT(*) AS total, COALESCE(SUM(status IN ('pending', 'Pending Review', 'Under Investigation', 'Scheduled for Mediation')), 0) AS pending FROM complaints WHERE resident_id = ?",
                            [$residentId]
                        );
                        $complaintsTotal = (int)$compRow['total'];
                    } else {
                        $complaintsTotal = 0;
                    }
                    $stats = [
                        'my_certificates_total' => (int)$certRow['total'],
                        'my_certificates_issued' => (int)$certRow['issued'],
                        'my_certificates_pending' => (int)$certRow['pending'],
                        'my_complaints_total' => (int)$complaintsTotal
                    ];
                } else {
                    $stats = [
                        'my_certificates_total' => 0,
                        'my_certificates_issued' => 0,
                        'my_certificates_pending' => 0,
                        'my_complaints_total' => 0
                    ];
                }
                break;
        }

        sendResponse(true, 'Module statistics retrieved', $stats);
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
        $data = $db->fetchAll(
            "SELECT
                CASE
                    WHEN status IN ('pending', 'Pending Review') THEN 'Pending Review'
                    WHEN status IN ('under_review', 'Under Investigation') THEN 'Under Investigation'
                    WHEN status = 'Scheduled for Mediation' THEN 'Scheduled for Mediation'
                    WHEN status = 'Referred to Other Barangay' THEN 'Referred to Other Barangay'
                    WHEN status IN ('resolved', 'Resolved') THEN 'Resolved'
                    WHEN status IN ('dismissed', 'Dismissed') THEN 'Dismissed'
                    ELSE status
                END AS status,
                COUNT(*) as count
             FROM complaints
             WHERE $where
             GROUP BY status",
            $params
        );
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

function getActivityLogsReport() {
    try {
        $db = Database::getInstance();
        if (!activityLogsTableExists($db)) {
            sendResponse(true, 'Activity logs report', []);
        }

        list($from, $to) = getDateFilter();
        $where = "1=1 AND " . activityLogsExcludeLoginSql('al');
        $params = [];
        if ($from) { $where .= " AND DATE(al.created_at) >= ?"; $params[] = $from; }
        if ($to) { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $to; }

        $sql = "SELECT al.created_at, u.username, al.action, al.module, al.details
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE $where
                ORDER BY al.created_at DESC";
        $data = $db->fetchAll($sql, $params);
        sendResponse(true, 'Activity logs report', activityLogsWithSummary($data));
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

/**
 * Active residents per street; labels match config/barangay219_streets.php when values normalize to the same name.
 *
 * @return list<array{label: string, value: int}>
 */
function fetchPopulationByStreetSeries($db) {
    $registryPath = __DIR__ . '/../config/barangay219_streets.php';
    $registry = is_file($registryPath) ? require $registryPath : [];
    if (!is_array($registry)) {
        $registry = [];
    }

    $norm = static function ($s) {
        $s = trim((string)$s);
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/\s+/u', ' ', $s);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    };

    $canonicalForNorm = [];
    foreach ($registry as $name) {
        $canonicalForNorm[$norm($name)] = $name;
    }

    $aggregated = [];
    foreach ($registry as $name) {
        $aggregated[$name] = 0;
    }

    $rows = [];
    try {
        $rows = $db->fetchAll(
            'SELECT st, COUNT(*) AS cnt FROM (
                SELECT TRIM(COALESCE(
                    NULLIF(TRIM(r.street), \'\'),
                    NULLIF(TRIM(h.street), \'\')
                )) AS st
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                WHERE r.status = \'active\'
            ) x
            WHERE st IS NOT NULL AND st <> \'\'
            GROUP BY st'
        );
    } catch (Exception $e) {
        try {
            $rows = $db->fetchAll(
                "SELECT TRIM(street) AS st, COUNT(*) AS cnt FROM residents
                 WHERE status = 'active' AND street IS NOT NULL AND TRIM(street) <> ''
                 GROUP BY TRIM(street)"
            );
        } catch (Exception $e2) {
            $rows = [];
        }
    }

    foreach ($rows as $r) {
        $st = (string)($r['st'] ?? '');
        $n = $norm($st);
        if ($n === '') {
            continue;
        }
        $label = $canonicalForNorm[$n] ?? $st;
        $aggregated[$label] = ($aggregated[$label] ?? 0) + (int)($r['cnt'] ?? 0);
    }

    // Always include every official street (0 residents still shown).
    $series = [];
    foreach ($registry as $name) {
        $series[] = ['label' => $name, 'value' => (int)($aggregated[$name] ?? 0)];
    }

    $extras = [];
    foreach ($aggregated as $label => $v) {
        if (in_array($label, $registry, true)) {
            continue;
        }
        if ($v > 0) {
            $extras[] = ['label' => $label, 'value' => $v];
        }
    }
    usort($extras, static function ($a, $b) {
        return $b['value'] <=> $a['value'];
    });

    return array_merge($series, $extras);
}

function reportsColumnExists($db, $table, $column) {
    static $cache = [];
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)$table);
    $column = preg_replace('/[^a-z0-9_]/i', '', (string)$column);
    if ($table === '' || $column === '') {
        return false;
    }
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $q = $db->getConnection()->quote($column);
        $row = $db->fetchOne("SHOW COLUMNS FROM `$table` LIKE $q");
        $cache[$key] = !empty($row);
    } catch (Exception $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function dashboardCertificateStatusLabel($status) {
    $s = strtolower(trim((string)$status));
    $map = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'ready_for_pickup' => 'Ready for pickup',
        'rejected' => 'Rejected',
        'released' => 'Released',
        'issued' => 'Released',
    ];
    return $map[$s] ?? ucwords(str_replace('_', ' ', $s));
}

function dashboardCertificateTypeLabel($type) {
    $t = strtolower(trim((string)$type));
    $map = [
        CERT_BARANGAY_CLEARANCE => 'Barangay Clearance',
        CERT_INDIGENCY => 'Certificate of Indigency',
        CERT_RESIDENCY => 'Certificate of Residency',
        CERT_GOOD_MORAL => 'Certificate of Good Moral',
        CERT_TRANSFER_REQUEST => 'Transfer Request',
    ];
    return $map[$t] ?? ucwords(str_replace('_', ' ', $t));
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardGenderDistribution($db) {
    $counts = ['male' => 0, 'female' => 0, 'other' => 0];
    try {
        $rows = $db->fetchAll(
            "SELECT LOWER(TRIM(gender)) AS g, COUNT(*) AS cnt FROM residents WHERE status = 'active' GROUP BY LOWER(TRIM(gender))"
        );
        foreach ($rows as $r) {
            $g = (string)($r['g'] ?? '');
            if (isset($counts[$g])) {
                $counts[$g] = (int)($r['cnt'] ?? 0);
            } elseif ($g !== '') {
                $counts['other'] += (int)($r['cnt'] ?? 0);
            }
        }
    } catch (Exception $e) {
        // keep zeros
    }
    return [
        ['label' => 'Male', 'value' => $counts['male']],
        ['label' => 'Female', 'value' => $counts['female']],
        ['label' => 'Other', 'value' => $counts['other']],
    ];
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardCivilStatusDistribution($db) {
    $order = ['single', 'married', 'widowed', 'divorced', 'separated'];
    $labels = [
        'single' => 'Single',
        'married' => 'Married',
        'widowed' => 'Widowed',
        'divorced' => 'Divorced',
        'separated' => 'Separated',
    ];
    $counts = array_fill_keys($order, 0);
    $counts['unknown'] = 0;
    try {
        $rows = $db->fetchAll(
            "SELECT civil_status, COUNT(*) AS cnt FROM residents WHERE status = 'active' GROUP BY civil_status"
        );
        foreach ($rows as $r) {
            $raw = $r['civil_status'] ?? null;
            if ($raw === null || $raw === '') {
                $counts['unknown'] += (int)($r['cnt'] ?? 0);
                continue;
            }
            $k = strtolower(trim((string)$raw));
            if (isset($counts[$k])) {
                $counts[$k] = (int)($r['cnt'] ?? 0);
            } else {
                $counts['unknown'] += (int)($r['cnt'] ?? 0);
            }
        }
    } catch (Exception $e) {
        return [];
    }
    $series = [];
    foreach ($order as $k) {
        $series[] = ['label' => $labels[$k], 'value' => $counts[$k]];
    }
    if ($counts['unknown'] > 0) {
        $series[] = ['label' => 'Unspecified', 'value' => $counts['unknown']];
    }
    return $series;
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardAgeGroups($db) {
    $order = ['0-17', '18-30', '31-45', '46-60', '60+'];
    $init = array_fill_keys($order, 0);
    try {
        $rows = $db->fetchAll(
            "SELECT age_bucket, COUNT(*) AS cnt FROM (
                SELECT
                    CASE
                        WHEN birth_date IS NULL THEN 'unknown'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 18 THEN '0-17'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 31 AND 45 THEN '31-45'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 46 AND 60 THEN '46-60'
                        ELSE '60+'
                    END AS age_bucket
                FROM residents
                WHERE status = 'active'
            ) x
            WHERE age_bucket <> 'unknown'
            GROUP BY age_bucket"
        );
        foreach ($rows as $r) {
            $b = (string)($r['age_bucket'] ?? '');
            if (isset($init[$b])) {
                $init[$b] = (int)($r['cnt'] ?? 0);
            }
        }
    } catch (Exception $e) {
        // zeros
    }
    $series = [];
    foreach ($order as $k) {
        $series[] = ['label' => $k, 'value' => $init[$k]];
    }
    return $series;
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardRequestStatus($db) {
    $map = [];
    try {
        $rows = $db->fetchAll('SELECT status, COUNT(*) AS cnt FROM certificate_requests GROUP BY status');
        foreach ($rows as $r) {
            $st = strtolower(trim((string)($r['status'] ?? '')));
            if ($st === '') {
                continue;
            }
            $map[$st] = (int)($r['cnt'] ?? 0);
        }
    } catch (Exception $e) {
        return [];
    }

    $released = (int)($map['released'] ?? 0) + (int)($map['issued'] ?? 0);

    $ordered = [
        ['pending', 'Pending'],
        ['approved', 'Approved'],
        ['ready_for_pickup', 'Ready for pickup'],
        ['released', 'Released'],
        ['rejected', 'Rejected'],
    ];
    $series = [];
    foreach ($ordered as [$key, $label]) {
        $v = $key === 'released' ? $released : (int)($map[$key] ?? 0);
        $series[] = ['label' => $label, 'value' => $v];
    }

    $known = ['pending', 'approved', 'ready_for_pickup', 'released', 'rejected', 'issued'];
    foreach ($map as $st => $cnt) {
        if (in_array($st, $known, true)) {
            continue;
        }
        $series[] = ['label' => dashboardCertificateStatusLabel($st), 'value' => $cnt];
    }

    return $series;
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardRequestTypes($db) {
    try {
        $rows = $db->fetchAll(
            'SELECT certificate_type, COUNT(*) AS cnt FROM certificate_requests GROUP BY certificate_type ORDER BY cnt DESC'
        );
    } catch (Exception $e) {
        return [];
    }
    $series = [];
    foreach ($rows as $r) {
        $series[] = [
            'label' => dashboardCertificateTypeLabel($r['certificate_type'] ?? ''),
            'value' => (int)($r['cnt'] ?? 0),
        ];
    }
    return $series;
}

/**
 * Last 12 calendar months, certificate request counts by created_at.
 *
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardRequestsOverTime($db) {
    $series = [];
    $map = [];
    try {
        $rows = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
             FROM certificate_requests
             WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
             GROUP BY ym
             ORDER BY ym"
        );
        foreach ($rows as $r) {
            $map[(string)($r['ym'] ?? '')] = (int)($r['cnt'] ?? 0);
        }
    } catch (Exception $e) {
        // empty map
    }
    for ($i = 11; $i >= 0; $i--) {
        $t = strtotime('first day of today -' . $i . ' months');
        $ym = date('Y-m', $t);
        $series[] = ['label' => date('M Y', $t), 'value' => $map[$ym] ?? 0];
    }
    return $series;
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardHouseholdTypes($db) {
    if (!reportsColumnExists($db, 'households', 'household_type')) {
        try {
            $n = (int)($db->fetchOne('SELECT COUNT(*) AS c FROM households')['c'] ?? 0);
        } catch (Exception $e) {
            $n = 0;
        }
        return $n > 0 ? [['label' => 'All households', 'value' => $n]] : [];
    }
    try {
        $rows = $db->fetchAll(
            "SELECT IFNULL(NULLIF(TRIM(household_type), ''), 'Unspecified') AS ht, COUNT(*) AS cnt
             FROM households
             GROUP BY ht
             ORDER BY cnt DESC"
        );
    } catch (Exception $e) {
        return [];
    }
    $series = [];
    foreach ($rows as $r) {
        $series[] = ['label' => (string)($r['ht'] ?? 'Unspecified'), 'value' => (int)($r['cnt'] ?? 0)];
    }
    return $series;
}

/**
 * @return array{new_registrations_today: int, approved_today: int}
 */
function fetchDashboardTodayCounts($db) {
    $out = ['new_registrations_today' => 0, 'approved_today' => 0];
    try {
        $row = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM residents WHERE DATE(created_at) = CURDATE()"
        );
        $out['new_registrations_today'] = (int)($row['c'] ?? 0);
    } catch (Exception $e) {
        // 0
    }
    try {
        $row = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM certificate_requests
             WHERE DATE(updated_at) = CURDATE()
             AND status IN ('approved', 'ready_for_pickup', 'released')"
        );
        $out['approved_today'] = (int)($row['c'] ?? 0);
    } catch (Exception $e) {
        // 0
    }
    return $out;
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardDailyLogins($db) {
    $series = [];
    $map = [];
    if (activityLogsTableExists($db)) {
        try {
            $rows = $db->fetchAll(
                "SELECT DATE(created_at) AS d, COUNT(*) AS cnt
                 FROM activity_logs
                 WHERE module = 'auth' AND action = 'login'
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY d"
            );
            foreach ($rows as $r) {
                $map[(string)($r['d'] ?? '')] = (int)($r['cnt'] ?? 0);
            }
        } catch (Exception $e) {
            // zeros
        }
    }
    for ($i = 29; $i >= 0; $i--) {
        $t = strtotime('-' . $i . ' days');
        $d = date('Y-m-d', $t);
        $series[] = ['label' => date('M d', $t), 'value' => $map[$d] ?? 0];
    }
    return $series;
}

/**
 * New residents per month (last 12 months).
 *
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardResidentRegistrationsByMonth($db) {
    $series = [];
    $map = [];
    try {
        $rows = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
             FROM residents
             WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
             GROUP BY ym
             ORDER BY ym"
        );
        foreach ($rows as $r) {
            $map[(string)($r['ym'] ?? '')] = (int)($r['cnt'] ?? 0);
        }
    } catch (Exception $e) {
        // empty
    }
    for ($i = 11; $i >= 0; $i--) {
        $t = strtotime('first day of today -' . $i . ' months');
        $ym = date('Y-m', $t);
        $series[] = ['label' => date('M Y', $t), 'value' => $map[$ym] ?? 0];
    }
    return $series;
}

/**
 * @return list<array{label: string, value: int}>
 */
function fetchDashboardSpecialCategories($db) {
    try {
        $row = $db->fetchOne(
            "SELECT
                COALESCE(SUM(is_senior_citizen = 1), 0) AS senior,
                COALESCE(SUM(is_pwd = 1), 0) AS pwd,
                COALESCE(SUM(is_solo_parent = 1), 0) AS solo,
                COALESCE(SUM(is_4ps_beneficiary = 1), 0) AS fourps,
                COALESCE(SUM(is_ip_member = 1), 0) AS ipm
             FROM residents
             WHERE status = 'active'"
        );
    } catch (Exception $e) {
        $row = [];
    }
    return [
        ['label' => 'Senior Citizens', 'value' => (int)($row['senior'] ?? 0)],
        ['label' => 'PWD', 'value' => (int)($row['pwd'] ?? 0)],
        ['label' => 'Solo Parents', 'value' => (int)($row['solo'] ?? 0)],
        ['label' => '4Ps Beneficiaries', 'value' => (int)($row['fourps'] ?? 0)],
        ['label' => 'IP Members', 'value' => (int)($row['ipm'] ?? 0)],
    ];
}

function buildDashboardChartsPayload($db) {
    $today = fetchDashboardTodayCounts($db);
    return [
        'gender_distribution' => fetchDashboardGenderDistribution($db),
        'civil_status_distribution' => fetchDashboardCivilStatusDistribution($db),
        'age_groups' => fetchDashboardAgeGroups($db),
        'population_by_street' => fetchPopulationByStreetSeries($db),
        'request_status' => fetchDashboardRequestStatus($db),
        'request_types' => fetchDashboardRequestTypes($db),
        'requests_over_time' => fetchDashboardRequestsOverTime($db),
        'household_types' => fetchDashboardHouseholdTypes($db),
        'new_registrations_today' => $today['new_registrations_today'],
        'approved_today' => $today['approved_today'],
        'daily_logins' => fetchDashboardDailyLogins($db),
        'user_registrations' => fetchDashboardResidentRegistrationsByMonth($db),
        'special_categories' => fetchDashboardSpecialCategories($db),
    ];
}

function getDashboardCharts() {
    try {
        $db = Database::getInstance();
        sendResponse(true, 'Dashboard charts data', buildDashboardChartsPayload($db));
    } catch (Exception $e) {
        sendResponse(false, 'Error loading dashboard charts', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
