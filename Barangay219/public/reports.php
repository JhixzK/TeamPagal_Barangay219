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

$fromDate = reportsNormalizeDate($_GET['from_date'] ?? '');
$toDate = reportsNormalizeDate($_GET['to_date'] ?? '');
$dateFilter = reportsBuildDateFilter($fromDate, $toDate);

$pdo = Database::getInstance()->getConnection();
$hasResidentsTable = reportsTableExists($pdo, 'residents');
$hasCertificateRequestsTable = reportsTableExists($pdo, 'certificate_requests');
$hasBlottersTable = reportsTableExists($pdo, 'blotters');
$hasComplaintsTable = reportsTableExists($pdo, 'complaints');
$hasAnnouncementsTable = reportsTableExists($pdo, 'announcements');
$hasActivityLogsTable = reportsTableExists($pdo, 'activity_logs');

/* SQL: residents total count */
$populationTotalRow = $hasResidentsTable
    ? reportsFetchOne(
        $pdo,
        "SELECT COUNT(*) AS total FROM residents" . $dateFilter['sql'],
        $dateFilter['params']
    )
    : ['total' => 0];
$populationTotal = (int)($populationTotalRow['total'] ?? 0);

/* SQL: residents grouped by gender */
$populationByGender = $hasResidentsTable
    ? reportsFetchGroupedCounts(
        $pdo,
        'residents',
        'gender',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: residents grouped by civil_status */
$populationByCivilStatus = $hasResidentsTable
    ? reportsFetchGroupedCounts(
        $pdo,
        'residents',
        'civil_status',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: certificate_requests grouped by certificate_type and status */
$certificateCounts = $hasCertificateRequestsTable
    ? reportsFetchTwoLevelCounts(
        $pdo,
        'certificate_requests',
        'certificate_type',
        'status',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: certificate_requests grouped by status */
$applicationCounts = $hasCertificateRequestsTable
    ? reportsFetchGroupedCounts(
        $pdo,
        'certificate_requests',
        'status',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: blotters grouped by status */
$blotterCounts = $hasBlottersTable
    ? reportsFetchGroupedCounts(
        $pdo,
        'blotters',
        'status',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: complaints grouped by status */
$complaintCounts = $hasComplaintsTable
    ? reportsFetchGroupedCounts(
        $pdo,
        'complaints',
        'status',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: announcements grouped by status */
$announcementCounts = $hasAnnouncementsTable
    ? reportsFetchGroupedCounts(
        $pdo,
        'announcements',
        'status',
        $dateFilter['sql'],
        $dateFilter['params']
    )
    : [];

/* SQL: activity_logs list ordered by latest first */
$activityLogs = $hasActivityLogsTable
    ? reportsFetchAll(
        $pdo,
        "
            SELECT id, description, created_at
            FROM activity_logs
            {$dateFilter['sql']}
            ORDER BY created_at DESC, id DESC
        ",
        $dateFilter['params']
    )
    : [];

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
    .reports-page .table-responsive {
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
                <div class="mb-3">
                    <div class="small text-muted">Total Residents</div>
                    <div class="fs-4 fw-bold"><?php echo number_format($populationTotal); ?></div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6>By Gender</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Gender</th>
                                        <th class="text-end">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($populationByGender): ?>
                                        <?php foreach ($populationByGender as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)$row['label']); ?></td>
                                                <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">No records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <h6>By Civil Status</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Civil Status</th>
                                        <th class="text-end">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($populationByCivilStatus): ?>
                                        <?php foreach ($populationByCivilStatus as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)$row['label']); ?></td>
                                                <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">No records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Certificates</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($certificateCounts): ?>
                                <?php foreach ($certificateCounts as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['primary_label']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['secondary_label']); ?></td>
                                        <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card report-card mb-4">
            <div class="card-header"><strong>Applications</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($applicationCounts): ?>
                                <?php foreach ($applicationCounts as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['label']); ?></td>
                                        <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center text-muted">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card report-card h-100">
                    <div class="card-header"><strong>Blotters</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($blotterCounts): ?>
                                        <?php foreach ($blotterCounts as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)$row['label']); ?></td>
                                                <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">No records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card report-card h-100">
                    <div class="card-header"><strong>Complaints</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($complaintCounts): ?>
                                        <?php foreach ($complaintCounts as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)$row['label']); ?></td>
                                                <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">No records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card report-card h-100">
                    <div class="card-header"><strong>Announcements</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($announcementCounts): ?>
                                        <?php foreach ($announcementCounts as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)$row['label']); ?></td>
                                                <td class="text-end"><?php echo number_format((int)$row['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">No records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card report-card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Activity Logs</strong>
                <span class="text-muted small"><?php echo number_format(reportsTableRowsCount($activityLogs)); ?> record(s)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th style="width: 100px;">ID</th>
                                <th>Description</th>
                                <th style="width: 220px;">Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($activityLogs): ?>
                                <?php foreach ($activityLogs as $row): ?>
                                    <tr>
                                        <td><?php echo (int)$row['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['description']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
