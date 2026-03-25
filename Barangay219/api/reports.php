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

function getStatistics() {
    try {
        $db = Database::getInstance();
        $stats = [
            'total_residents' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM residents WHERE status = 'active'")['count'],
            'total_households' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM households")['count'],
            'pending_certificates' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'pending'")['count'],
            'pending_applications' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status = 'pending'")['count'],
            'pending_blotters' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM blotters WHERE status = 'pending'")['count'],
            'pending_complaints' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status IN ('pending', 'Pending Review', 'Under Investigation', 'Scheduled for Mediation')")['count'],
            'issued_certificates' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM certificate_requests WHERE status IN ('released', 'issued')")['count'],
            'resolved_blotters' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM blotters WHERE status IN ('resolved', 'settled')")['count'],
            'resolved_complaints' => (int)$db->fetchOne("SELECT COUNT(*) as count FROM complaints WHERE status IN ('resolved', 'Resolved')")['count'],
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
        $exclude = activityLogsExcludeLoginSql('al');
        $rows = $db->fetchAll(
            "SELECT al.*, u.username FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             WHERE $exclude
             ORDER BY al.created_at DESC LIMIT " . (int)$limit
        );
        sendResponse(true, 'Recent activities', activityLogsWithSummary($rows));
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

function getDashboardCharts() {
    // ── DUMMY DATA MODE ──
    // Replace this function body with real DB queries when ready.
    // Each dataset uses the {label, value} format the frontend expects.

    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $months[] = date('M Y', strtotime("-$i months"));
    }

    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $days[] = date('M d', strtotime("-$i days"));
    }

    $charts = [
        'gender_distribution' => [
            ['label' => 'Male',   'value' => 1245],
            ['label' => 'Female', 'value' => 1382],
            ['label' => 'Other',  'value' => 18],
        ],

        'civil_status_distribution' => [
            ['label' => 'Single',    'value' => 980],
            ['label' => 'Married',   'value' => 1120],
            ['label' => 'Widowed',   'value' => 185],
            ['label' => 'Separated', 'value' => 95],
            ['label' => 'Divorced',  'value' => 32],
        ],

        'age_groups' => [
            ['label' => '0-17',  'value' => 620],
            ['label' => '18-30', 'value' => 845],
            ['label' => '31-45', 'value' => 560],
            ['label' => '46-60', 'value' => 390],
            ['label' => '60+',   'value' => 230],
        ],

        'population_by_purok' => [
            ['label' => 'Purok 1',  'value' => 342],
            ['label' => 'Purok 2',  'value' => 285],
            ['label' => 'Purok 3',  'value' => 410],
            ['label' => 'Purok 4',  'value' => 198],
            ['label' => 'Purok 5',  'value' => 265],
            ['label' => 'Purok 6',  'value' => 320],
            ['label' => 'Purok 7',  'value' => 175],
            ['label' => 'Zone A',   'value' => 150],
        ],

        'request_status' => [
            ['label' => 'Pending',          'value' => 42],
            ['label' => 'Approved',         'value' => 156],
            ['label' => 'Released',         'value' => 310],
            ['label' => 'Rejected',         'value' => 18],
            ['label' => 'Ready for pickup', 'value' => 25],
        ],

        'request_types' => [
            ['label' => 'Barangay Clearance',       'value' => 215],
            ['label' => 'Certificate of Indigency',  'value' => 128],
            ['label' => 'Certificate of Residency',  'value' => 97],
            ['label' => 'Transfer Request',           'value' => 34],
            ['label' => 'Business Permit',            'value' => 62],
        ],

        'requests_over_time' => array_map(function ($m) {
            return ['label' => $m, 'value' => rand(18, 65)];
        }, $months),

        'household_types' => [
            ['label' => 'Family Household',                         'value' => 320],
            ['label' => 'Single Inhabitant',                        'value' => 85],
            ['label' => 'Couple Only',                              'value' => 62],
            ['label' => 'Non-Relative Household (Shared / Boarders)', 'value' => 48],
            ['label' => 'Unspecified',                              'value' => 30],
        ],

        'household_trends' => array_map(function ($m) {
            return ['label' => $m, 'value' => rand(5, 28)];
        }, $months),

        'new_registrations_today' => 7,
        'approved_today' => 12,

        'daily_logins' => array_map(function ($d) {
            return ['label' => $d, 'value' => rand(3, 22)];
        }, $days),

        'user_registrations' => array_map(function ($m) {
            return ['label' => $m, 'value' => rand(2, 15)];
        }, $months),

        'special_categories' => [
            ['label' => 'Senior Citizens',   'value' => 186],
            ['label' => 'PWD',               'value' => 54],
            ['label' => 'Solo Parents',      'value' => 73],
            ['label' => '4Ps Beneficiaries', 'value' => 142],
            ['label' => 'IP Members',        'value' => 29],
        ],
    ];

    sendResponse(true, 'Dashboard charts data (dummy)', $charts);
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
