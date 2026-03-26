<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('reports');

$page_title = 'Reports';

/**
 * Normalize YYYY-MM-DD input.
 */
function reportsNormalizeDate(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return '';
    }

    return $value;
}

/**
 * Reusable created_at filter.
 * If one date is missing, use an open-ended fallback while still applying BETWEEN.
 */
function reportsBuildDateFilter(string $fromDate, string $toDate, string $column = 'created_at'): array
{
    if ($fromDate === '' && $toDate === '') {
        return [
            'sql' => '',
            'params' => [],
            'label' => 'All records'
        ];
    }

    $effectiveFrom = $fromDate !== '' ? $fromDate : '1000-01-01';
    $effectiveTo = $toDate !== '' ? $toDate : '9999-12-31';

    return [
        'sql' => " WHERE DATE({$column}) BETWEEN :from_date AND :to_date",
        'params' => [
            ':from_date' => $effectiveFrom,
            ':to_date' => $effectiveTo
        ],
        'label' => $effectiveFrom . ' to ' . $effectiveTo
    ];
}

function reportsFetchOne(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function reportsFetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function reportsTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
    ");
    $stmt->execute([':table_name' => $table]);
    return (bool)$stmt->fetchColumn();
}

function reportsGetColumns(PDO $pdo, string $table): array
{
    if (!reportsTableExists($pdo, $table)) {
        return [];
    }

    $rows = reportsFetchAll(
        $pdo,
        "
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
        ",
        [':table_name' => $table]
    );

    $columns = [];
    foreach ($rows as $row) {
        $columns[(string)$row['column_name']] = true;
    }
    return $columns;
}

function reportsHasColumn(array $columns, string $column): bool
{
    return isset($columns[$column]);
}

function reportsFetchValue(PDO $pdo, string $sql, array $params = [], string $key = 'total'): int
{
    $row = reportsFetchOne($pdo, $sql, $params);
    return (int)($row[$key] ?? 0);
}

function reportsFetchSimpleRows(PDO $pdo, string $sql, array $params = []): array
{
    return reportsFetchAll($pdo, $sql, $params);
}

function reportsFetchGroupedCounts(PDO $pdo, string $table, string $groupColumn, string $filterSql, array $filterParams): array
{
    $sql = "
        SELECT COALESCE(NULLIF(TRIM({$groupColumn}), ''), 'Unspecified') AS label, COUNT(*) AS total
        FROM {$table}
        {$filterSql}
        GROUP BY {$groupColumn}
        ORDER BY label ASC
    ";

    return reportsFetchAll($pdo, $sql, $filterParams);
}

function reportsFetchTwoLevelCounts(PDO $pdo, string $table, string $primaryColumn, string $secondaryColumn, string $filterSql, array $filterParams): array
{
    $sql = "
        SELECT
            COALESCE(NULLIF(TRIM({$primaryColumn}), ''), 'Unspecified') AS primary_label,
            COALESCE(NULLIF(TRIM({$secondaryColumn}), ''), 'Unspecified') AS secondary_label,
            COUNT(*) AS total
        FROM {$table}
        {$filterSql}
        GROUP BY {$primaryColumn}, {$secondaryColumn}
        ORDER BY primary_label ASC, secondary_label ASC
    ";

    return reportsFetchAll($pdo, $sql, $filterParams);
}

function reportsRenderSimpleTable(array $rows, array $headers, array $keys, string $emptyMessage = 'No records found.', array $alignRightKeys = []): void
{
    ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo htmlspecialchars($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($keys as $key): ?>
                                <?php $value = $row[$key] ?? ''; ?>
                                <td class="<?php echo in_array($key, $alignRightKeys, true) ? 'text-end' : ''; ?>">
                                    <?php
                                    if (is_numeric($value) && in_array($key, $alignRightKeys, true)) {
                                        $numericValue = (float)$value;
                                        echo number_format($numericValue, floor($numericValue) == $numericValue ? 0 : 2);
                                    } else {
                                        echo htmlspecialchars((string)$value);
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo count($headers); ?>" class="text-center text-muted"><?php echo htmlspecialchars($emptyMessage); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$fromDate = reportsNormalizeDate($_GET['from_date'] ?? '');
$toDate = reportsNormalizeDate($_GET['to_date'] ?? '');
$dateFilter = reportsBuildDateFilter($fromDate, $toDate);

$pdo = Database::getInstance()->getConnection();
$residentColumns = reportsGetColumns($pdo, 'residents');
$householdColumns = reportsGetColumns($pdo, 'households');
$certificateColumns = reportsGetColumns($pdo, 'certificate_requests');
$blotterColumns = reportsGetColumns($pdo, 'blotters');
$complaintColumns = reportsGetColumns($pdo, 'complaints');
$announcementColumns = reportsGetColumns($pdo, 'announcements');
$activityLogColumns = reportsGetColumns($pdo, 'activity_logs');
$userColumns = reportsGetColumns($pdo, 'users');

$hasResidentsTable = !empty($residentColumns);
$hasHouseholdsTable = !empty($householdColumns);
$hasCertificateRequestsTable = !empty($certificateColumns);
$hasBlottersTable = !empty($blotterColumns);
$hasComplaintsTable = !empty($complaintColumns);
$hasAnnouncementsTable = !empty($announcementColumns);
$hasActivityLogsTable = !empty($activityLogColumns);
$hasUsersTable = !empty($userColumns);

$populationTotal = 0;
$populationByGender = [];
$populationByCivilStatus = [];
$populationByAgeGroup = [];
$populationByPurok = [];
$registeredVoters = null;
$seniorCitizens = null;
$minors = null;
$citizenshipBreakdown = [];
$lengthOfStayBreakdown = [];

if ($hasResidentsTable) {
    $ageExpr = "TIMESTAMPDIFF(YEAR, birth_date, CURDATE())";
    $populationTotal = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM residents" . $dateFilter['sql'], $dateFilter['params']);
    $populationByGender = reportsFetchGroupedCounts($pdo, 'residents', 'gender', $dateFilter['sql'], $dateFilter['params']);
    $populationByCivilStatus = reportsFetchGroupedCounts($pdo, 'residents', 'civil_status', $dateFilter['sql'], $dateFilter['params']);
    $populationByAgeGroup = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT
                CASE
                    WHEN {$ageExpr} BETWEEN 0 AND 12 THEN '0-12'
                    WHEN {$ageExpr} BETWEEN 13 AND 17 THEN '13-17'
                    WHEN {$ageExpr} BETWEEN 18 AND 59 THEN '18-59'
                    WHEN {$ageExpr} >= 60 THEN '60+'
                    ELSE 'Unspecified'
                END AS age_group,
                COUNT(*) AS total
            FROM residents
            {$dateFilter['sql']}
            GROUP BY age_group
            ORDER BY FIELD(age_group, '0-12', '13-17', '18-59', '60+', 'Unspecified')
        ",
        $dateFilter['params']
    );

    if (reportsHasColumn($residentColumns, 'purok_sitio')) {
        $populationByPurok = reportsFetchGroupedCounts($pdo, 'residents', 'purok_sitio', $dateFilter['sql'], $dateFilter['params']);
    }

    $seniorCitizens = reportsFetchValue(
        $pdo,
        "SELECT COUNT(*) AS total FROM residents" . ($dateFilter['sql'] === '' ? " WHERE {$ageExpr} >= 60" : str_replace(' WHERE ', " WHERE {$ageExpr} >= 60 AND ", $dateFilter['sql'])),
        $dateFilter['params']
    );
    $minors = reportsFetchValue(
        $pdo,
        "SELECT COUNT(*) AS total FROM residents" . ($dateFilter['sql'] === '' ? " WHERE {$ageExpr} < 18" : str_replace(' WHERE ', " WHERE {$ageExpr} < 18 AND ", $dateFilter['sql'])),
        $dateFilter['params']
    );

    if (reportsHasColumn($residentColumns, 'citizenship')) {
        $citizenshipBreakdown = reportsFetchSimpleRows(
            $pdo,
            "
                SELECT
                    CASE
                        WHEN LOWER(COALESCE(TRIM(citizenship), '')) = 'filipino' THEN 'Filipino'
                        WHEN COALESCE(TRIM(citizenship), '') = '' THEN 'Unspecified'
                        ELSE 'Non-Filipino'
                    END AS category,
                    COUNT(*) AS total
                FROM residents
                {$dateFilter['sql']}
                GROUP BY category
                ORDER BY FIELD(category, 'Filipino', 'Non-Filipino', 'Unspecified')
            ",
            $dateFilter['params']
        );
    }

    $residencyYearsColumn = reportsHasColumn($residentColumns, 'length_of_residency_years')
        ? 'length_of_residency_years'
        : (reportsHasColumn($residentColumns, 'years_of_residency') ? 'years_of_residency' : '');
    if ($residencyYearsColumn !== '') {
        $lengthOfStayBreakdown = reportsFetchSimpleRows(
            $pdo,
            "
                SELECT
                    CASE
                        WHEN COALESCE({$residencyYearsColumn}, 0) < 1 THEN '<1 year'
                        WHEN COALESCE({$residencyYearsColumn}, 0) BETWEEN 1 AND 5 THEN '1-5 years'
                        ELSE '5+ years'
                    END AS stay_range,
                    COUNT(*) AS total
                FROM residents
                {$dateFilter['sql']}
                GROUP BY stay_range
                ORDER BY FIELD(stay_range, '<1 year', '1-5 years', '5+ years')
            ",
            $dateFilter['params']
        );
    }

    if (reportsHasColumn($residentColumns, 'voter_status')) {
        $registeredVoters = reportsFetchValue(
            $pdo,
            "
                SELECT COUNT(*) AS total
                FROM residents
                " . ($dateFilter['sql'] === ''
                    ? "WHERE COALESCE(TRIM(voter_status), '') <> '' AND LOWER(TRIM(voter_status)) NOT IN ('not registered', 'unregistered', 'no', 'n/a')"
                    : str_replace(' WHERE ', " WHERE COALESCE(TRIM(voter_status), '') <> '' AND LOWER(TRIM(voter_status)) NOT IN ('not registered', 'unregistered', 'no', 'n/a') AND ", $dateFilter['sql'])),
            $dateFilter['params']
        );
    }
}

$totalHouseholds = 0;
$averageHouseholdMembers = null;
$householdsPerPurok = [];

if ($hasHouseholdsTable) {
    $totalHouseholds = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM households" . $dateFilter['sql'], $dateFilter['params']);
    if (reportsHasColumn($householdColumns, 'total_members')) {
        $avgRow = reportsFetchOne($pdo, "SELECT AVG(total_members) AS average_members FROM households" . $dateFilter['sql'], $dateFilter['params']);
        $averageHouseholdMembers = isset($avgRow['average_members']) ? round((float)$avgRow['average_members'], 2) : null;
    }
}

if ($hasResidentsTable && reportsHasColumn($residentColumns, 'household_id') && reportsHasColumn($residentColumns, 'purok_sitio')) {
    $householdsPerPurok = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT COALESCE(NULLIF(TRIM(purok_sitio), ''), 'Unspecified') AS purok, COUNT(DISTINCT household_id) AS total
            FROM residents
            " . ($dateFilter['sql'] === '' ? "WHERE household_id IS NOT NULL" : str_replace(' WHERE ', ' WHERE household_id IS NOT NULL AND ', $dateFilter['sql'])) . "
            GROUP BY purok
            ORDER BY purok ASC
        ",
        $dateFilter['params']
    );
}

$certificateTotalIssued = 0;
$certificateByType = [];
$certificateByStatus = [];
$certificateDailyIssued = [];
$certificateMonthlyIssued = [];
$applicationTotal = 0;
$applicationByStatus = [];
$averageProcessingDays = null;

if ($hasCertificateRequestsTable) {
    $applicationTotal = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM certificate_requests" . $dateFilter['sql'], $dateFilter['params']);
    $applicationByStatus = reportsFetchGroupedCounts($pdo, 'certificate_requests', 'status', $dateFilter['sql'], $dateFilter['params']);
    $certificateByStatus = $applicationByStatus;

    $issuedStatusSql = ($dateFilter['sql'] === '')
        ? " WHERE LOWER(status) IN ('released', 'issued')"
        : str_replace(' WHERE ', " WHERE LOWER(status) IN ('released', 'issued') AND ", $dateFilter['sql']);

    $certificateTotalIssued = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM certificate_requests" . $issuedStatusSql, $dateFilter['params']);
    $certificateByType = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT
                CASE
                    WHEN LOWER(COALESCE(certificate_type, '')) IN ('certificate_residency', 'certificate_of_residency') THEN 'Residency'
                    WHEN LOWER(COALESCE(certificate_type, '')) IN ('barangay_indigency', 'certificate_indigency', 'certificate_of_indigency') THEN 'Indigency'
                    WHEN LOWER(COALESCE(certificate_type, '')) = 'barangay_clearance' THEN 'Clearance'
                    WHEN LOWER(COALESCE(certificate_type, '')) LIKE '%business%' THEN 'Business'
                    ELSE 'Others'
                END AS certificate_group,
                COUNT(*) AS total
            FROM certificate_requests
            {$issuedStatusSql}
            GROUP BY certificate_group
            ORDER BY certificate_group ASC
        ",
        $dateFilter['params']
    );

    $issueDateColumns = [];
    foreach (['released_at', 'approved_at', 'ready_for_pickup_at', 'date_issued', 'issued_date', 'created_at'] as $column) {
        if (reportsHasColumn($certificateColumns, $column)) {
            $issueDateColumns[] = $column;
        }
    }
    $issueDateExpr = 'DATE(COALESCE(' . implode(', ', $issueDateColumns) . '))';

    $issueDateParams = [];
    $issueDateFilterSql = '';
    if ($fromDate !== '' || $toDate !== '') {
        $issueDateFilterSql = " AND {$issueDateExpr} BETWEEN :issued_from AND :issued_to";
        $issueDateParams = [
            ':issued_from' => $fromDate !== '' ? $fromDate : '1000-01-01',
            ':issued_to' => $toDate !== '' ? $toDate : '9999-12-31'
        ];
    }

    $certificateDailyIssued = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT {$issueDateExpr} AS issued_day, COUNT(*) AS total
            FROM certificate_requests
            WHERE LOWER(status) IN ('released', 'issued')
            {$issueDateFilterSql}
            GROUP BY issued_day
            ORDER BY issued_day DESC
        ",
        $issueDateParams
    );
    $certificateMonthlyIssued = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT DATE_FORMAT({$issueDateExpr}, '%Y-%m') AS issued_month, COUNT(*) AS total
            FROM certificate_requests
            WHERE LOWER(status) IN ('released', 'issued')
            {$issueDateFilterSql}
            GROUP BY issued_month
            ORDER BY issued_month DESC
        ",
        $issueDateParams
    );

    $processingDateParts = [];
    foreach (['released_at', 'approved_at', 'ready_for_pickup_at', 'rejected_at', 'date_issued', 'issued_date'] as $column) {
        if (reportsHasColumn($certificateColumns, $column)) {
            $processingDateParts[] = $column;
        }
    }
    if ($processingDateParts) {
        $processingExpr = 'COALESCE(' . implode(', ', $processingDateParts) . ')';
        $avgRow = reportsFetchOne(
            $pdo,
            "SELECT AVG(DATEDIFF(DATE({$processingExpr}), DATE(created_at))) AS avg_days FROM certificate_requests" . ($dateFilter['sql'] === '' ? " WHERE {$processingExpr} IS NOT NULL" : str_replace(' WHERE ', " WHERE {$processingExpr} IS NOT NULL AND ", $dateFilter['sql'])),
            $dateFilter['params']
        );
        $averageProcessingDays = isset($avgRow['avg_days']) && $avgRow['avg_days'] !== null ? round((float)$avgRow['avg_days'], 2) : null;
    }
}

$blotterTotal = 0;
$blotterByStatus = [];
$blotterByType = [];
if ($hasBlottersTable) {
    $blotterTotal = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM blotters" . $dateFilter['sql'], $dateFilter['params']);
    $blotterByStatus = reportsFetchGroupedCounts($pdo, 'blotters', 'status', $dateFilter['sql'], $dateFilter['params']);
    if (reportsHasColumn($blotterColumns, 'incident_type')) {
        $blotterByType = reportsFetchGroupedCounts($pdo, 'blotters', 'incident_type', $dateFilter['sql'], $dateFilter['params']);
    }
}

$complaintTotal = 0;
$complaintByStatus = [];
$complaintResolutionRate = null;
if ($hasComplaintsTable) {
    $complaintTotal = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM complaints" . $dateFilter['sql'], $dateFilter['params']);
    $complaintByStatus = reportsFetchGroupedCounts($pdo, 'complaints', 'status', $dateFilter['sql'], $dateFilter['params']);
    if ($complaintTotal > 0) {
        $resolvedCount = reportsFetchValue(
            $pdo,
            "SELECT COUNT(*) AS total FROM complaints" . ($dateFilter['sql'] === '' ? " WHERE LOWER(status) = 'resolved'" : str_replace(' WHERE ', " WHERE LOWER(status) = 'resolved' AND ", $dateFilter['sql'])),
            $dateFilter['params']
        );
        $complaintResolutionRate = round(($resolvedCount / $complaintTotal) * 100, 2);
    }
}

$announcementTotal = 0;
$announcementByActiveArchived = [];
if ($hasAnnouncementsTable) {
    $announcementTotal = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM announcements" . $dateFilter['sql'], $dateFilter['params']);
    $announcementByActiveArchived = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT CASE WHEN LOWER(COALESCE(status, '')) = 'active' THEN 'Active' ELSE 'Archived' END AS category, COUNT(*) AS total
            FROM announcements
            {$dateFilter['sql']}
            GROUP BY category
            ORDER BY FIELD(category, 'Active', 'Archived')
        ",
        $dateFilter['params']
    );
}

$activityTotal = 0;
$recentActivities = [];
$activityByUser = [];
if ($hasActivityLogsTable) {
    $activityTotal = reportsFetchValue($pdo, "SELECT COUNT(*) AS total FROM activity_logs" . $dateFilter['sql'], $dateFilter['params']);
    $activitySummaryParts = [];
    if (reportsHasColumn($activityLogColumns, 'action')) {
        $activitySummaryParts[] = "COALESCE(al.action, '')";
    }
    if (reportsHasColumn($activityLogColumns, 'module')) {
        $activitySummaryParts[] = "CASE WHEN COALESCE(al.module, '') <> '' THEN CONCAT(' - ', al.module) ELSE '' END";
    }
    $activitySummaryExpr = $activitySummaryParts
        ? 'CONCAT(' . implode(', ', $activitySummaryParts) . ')'
        : (reportsHasColumn($activityLogColumns, 'details') ? "COALESCE(CAST(al.details AS CHAR), 'Activity')" : "'Activity'");

    $activityTextExpr = reportsHasColumn($activityLogColumns, 'description')
        ? "COALESCE(NULLIF(TRIM(al.description), ''), {$activitySummaryExpr})"
        : $activitySummaryExpr;
    $recentActivities = reportsFetchSimpleRows(
        $pdo,
        "
            SELECT al.created_at, {$activityTextExpr} AS activity_text
            FROM activity_logs al
            {$dateFilter['sql']}
            ORDER BY al.created_at DESC
            LIMIT 25
        ",
        $dateFilter['params']
    );
    if ($hasUsersTable && reportsHasColumn($activityLogColumns, 'user_id') && reportsHasColumn($userColumns, 'username')) {
        $activityByUser = reportsFetchSimpleRows(
            $pdo,
            "
                SELECT COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User #', al.user_id)) AS username, COUNT(*) AS total
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                " . ($dateFilter['sql'] === '' ? '' : str_replace('created_at', 'al.created_at', $dateFilter['sql'])) . "
                GROUP BY username
                ORDER BY total DESC, username ASC
            ",
            $dateFilter['params']
        );
    }
}

function reportsTableRowsCount(array $rows): int
{
    return count($rows);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.reports-page .report-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}
.reports-page .report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}
.reports-page .metric-tile {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    padding: 1rem;
}
.reports-page .metric-label {
    font-size: 0.85rem;
    color: #6b7280;
}
.reports-page .metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1.1;
}
.reports-page .table-responsive {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
}
.reports-page .table {
    margin-bottom: 0;
}
.reports-page .table th {
    background: #f8f9fa;
    font-size: 0.85rem;
}
.reports-page .print-header {
    display: none;
}

@media print {
    body {
        background: #fff !important;
    }
    .navbar,
    .sidebar,
    .module-kicker,
    .module-subtitle,
    .report-actions,
    .btn,
    form {
        display: none !important;
    }
    .main-content,
    .container-fluid {
        margin: 0 !important;
        padding: 0 !important;
    }
    .reports-page .card,
    .reports-page .report-card,
    .reports-page .table-responsive,
    .reports-page .metric-tile {
        border: none !important;
        box-shadow: none !important;
    }
    .reports-page .print-header {
        display: block;
        margin-bottom: 1rem;
    }
    .reports-page .print-header h3 {
        margin-bottom: 0.25rem;
    }
    .reports-page .table th {
        background: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<div class="main-content module-page reports-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Reports Module</p>
                    <h2 class="mb-1"><i class="bi bi-graph-up me-2"></i>Reports</h2>
                    <p class="module-subtitle mb-0">System statistics with optional date filtering.</p>
                </div>
                <div class="report-actions">
                    <button type="button" class="btn btn-danger" onclick="window.print()">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Generate PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="print-header">
            <h3>Barangay Reports</h3>
            <div><strong>Date Range:</strong> <?php echo htmlspecialchars($dateFilter['label']); ?></div>
            <div><strong>Generated:</strong> <?php echo htmlspecialchars(date('F d, Y h:i A')); ?></div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="from_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="to_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                        <a href="<?php echo htmlspecialchars(BASE_URL); ?>reports.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <div class="small text-muted">Date Range</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($dateFilter['label']); ?></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Population</strong></div>
            <div class="card-body">
                <div class="report-grid mb-4">
                    <div class="metric-tile">
                        <div class="metric-label">Total Residents</div>
                        <div class="metric-value"><?php echo number_format($populationTotal); ?></div>
                    </div>
                    <div class="metric-tile">
                        <div class="metric-label">Senior Citizens (60+)</div>
                        <div class="metric-value"><?php echo $seniorCitizens !== null ? number_format($seniorCitizens) : 'N/A'; ?></div>
                    </div>
                    <div class="metric-tile">
                        <div class="metric-label">Minors</div>
                        <div class="metric-value"><?php echo $minors !== null ? number_format($minors) : 'N/A'; ?></div>
                    </div>
                    <?php if ($registeredVoters !== null): ?>
                    <div class="metric-tile">
                        <div class="metric-label">Registered Voters</div>
                        <div class="metric-value"><?php echo number_format($registeredVoters); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6>By Gender</h6>
                        <?php reportsRenderSimpleTable($populationByGender, ['Gender', 'Count'], ['label', 'total'], 'No resident data found.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>By Civil Status</h6>
                        <?php reportsRenderSimpleTable($populationByCivilStatus, ['Civil Status', 'Count'], ['label', 'total'], 'No resident data found.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>By Age Group</h6>
                        <?php reportsRenderSimpleTable($populationByAgeGroup, ['Age Group', 'Count'], ['age_group', 'total'], 'No resident data found.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>By Purok / Zone</h6>
                        <?php reportsRenderSimpleTable($populationByPurok, ['Purok / Zone', 'Count'], ['label', 'total'], 'Purok / zone is not available.', ['total']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Residency / Citizenship</strong></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6>Filipino vs Non-Filipino</h6>
                        <?php reportsRenderSimpleTable($citizenshipBreakdown, ['Category', 'Count'], ['category', 'total'], 'Citizenship is not available.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>Length of Stay</h6>
                        <?php reportsRenderSimpleTable($lengthOfStayBreakdown, ['Length of Stay', 'Count'], ['stay_range', 'total'], 'Length of stay is not available.', ['total']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Household</strong></div>
            <div class="card-body">
                <div class="report-grid mb-4">
                    <div class="metric-tile">
                        <div class="metric-label">Total Households</div>
                        <div class="metric-value"><?php echo number_format($totalHouseholds); ?></div>
                    </div>
                    <div class="metric-tile">
                        <div class="metric-label">Average Members per Household</div>
                        <div class="metric-value"><?php echo $averageHouseholdMembers !== null ? number_format($averageHouseholdMembers, 2) : 'N/A'; ?></div>
                    </div>
                </div>
                <h6>Households per Purok</h6>
                <?php reportsRenderSimpleTable($householdsPerPurok, ['Purok', 'Households'], ['purok', 'total'], 'Household purok data is not available.', ['total']); ?>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Certificates</strong></div>
            <div class="card-body">
                <div class="report-grid mb-4">
                    <div class="metric-tile">
                        <div class="metric-label">Total Issued</div>
                        <div class="metric-value"><?php echo number_format($certificateTotalIssued); ?></div>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6>By Type</h6>
                        <?php reportsRenderSimpleTable($certificateByType, ['Type', 'Count'], ['certificate_group', 'total'], 'No certificate data found.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>By Status</h6>
                        <?php reportsRenderSimpleTable($certificateByStatus, ['Status', 'Count'], ['label', 'total'], 'No certificate data found.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>Daily Issuance Count</h6>
                        <?php reportsRenderSimpleTable($certificateDailyIssued, ['Date', 'Count'], ['issued_day', 'total'], 'No issued certificates found.', ['total']); ?>
                    </div>
                    <div class="col-lg-6">
                        <h6>Monthly Issuance Count</h6>
                        <?php reportsRenderSimpleTable($certificateMonthlyIssued, ['Month', 'Count'], ['issued_month', 'total'], 'No issued certificates found.', ['total']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Applications</strong></div>
            <div class="card-body">
                <div class="report-grid mb-4">
                    <div class="metric-tile">
                        <div class="metric-label">Total Requests</div>
                        <div class="metric-value"><?php echo number_format($applicationTotal); ?></div>
                    </div>
                    <div class="metric-tile">
                        <div class="metric-label">Average Processing Time (Days)</div>
                        <div class="metric-value"><?php echo $averageProcessingDays !== null ? number_format($averageProcessingDays, 2) : 'N/A'; ?></div>
                    </div>
                </div>
                <h6>By Status</h6>
                <?php reportsRenderSimpleTable($applicationByStatus, ['Status', 'Count'], ['label', 'total'], 'No application data found.', ['total']); ?>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card report-card h-100">
                    <div class="card-header"><strong>Blotters</strong></div>
                    <div class="card-body">
                        <div class="report-grid mb-4">
                            <div class="metric-tile">
                                <div class="metric-label">Total Cases</div>
                                <div class="metric-value"><?php echo number_format($blotterTotal); ?></div>
                            </div>
                        </div>
                        <h6>By Status</h6>
                        <?php reportsRenderSimpleTable($blotterByStatus, ['Status', 'Count'], ['label', 'total'], 'No blotter data found.', ['total']); ?>
                        <div class="mt-4">
                            <h6>By Type</h6>
                            <?php reportsRenderSimpleTable($blotterByType, ['Type', 'Count'], ['label', 'total'], 'Blotter type is not available.', ['total']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card report-card h-100">
                    <div class="card-header"><strong>Complaints</strong></div>
                    <div class="card-body">
                        <div class="report-grid mb-4">
                            <div class="metric-tile">
                                <div class="metric-label">Total Complaints</div>
                                <div class="metric-value"><?php echo number_format($complaintTotal); ?></div>
                            </div>
                            <div class="metric-tile">
                                <div class="metric-label">Resolution Rate</div>
                                <div class="metric-value"><?php echo $complaintResolutionRate !== null ? number_format($complaintResolutionRate, 2) . '%' : 'N/A'; ?></div>
                            </div>
                        </div>
                        <h6>By Status</h6>
                        <?php reportsRenderSimpleTable($complaintByStatus, ['Status', 'Count'], ['label', 'total'], 'No complaint data found.', ['total']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card report-card mt-4 mb-4">
            <div class="card-header"><strong>Announcements</strong></div>
            <div class="card-body">
                <div class="report-grid mb-4">
                    <div class="metric-tile">
                        <div class="metric-label">Total Posted</div>
                        <div class="metric-value"><?php echo number_format($announcementTotal); ?></div>
                    </div>
                </div>
                <h6>Active vs Archived</h6>
                <?php reportsRenderSimpleTable($announcementByActiveArchived, ['Category', 'Count'], ['category', 'total'], 'No announcement data found.', ['total']); ?>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Activity Logs</strong></div>
            <div class="card-body">
                <div class="report-grid mb-4">
                    <div class="metric-tile">
                        <div class="metric-label">Total Actions</div>
                        <div class="metric-value"><?php echo number_format($activityTotal); ?></div>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h6>Recent Activity List</h6>
                        <?php reportsRenderSimpleTable($recentActivities, ['Created At', 'Activity'], ['created_at', 'activity_text'], 'No activity logs found.'); ?>
                    </div>
                    <div class="col-lg-5">
                        <h6>Actions by User</h6>
                        <?php reportsRenderSimpleTable($activityByUser, ['User', 'Actions'], ['username', 'total'], 'Per-user activity is not available.', ['total']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
