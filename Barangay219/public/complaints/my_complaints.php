<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/resident-complaints.php';

residentComplaintsRequireResident();

$db = Database::getInstance();
$residentId = residentComplaintsGetResidentId();
$residentName = residentComplaintsGetResidentName();

$moduleReady = residentComplaintsTableExists($db);
$complaints = [];

if ($moduleReady) {
    $selectCols = [
        'id',
        residentComplaintsSelectExpr($db, ['reference_number'], 'reference_number'),
        residentComplaintsSelectExpr($db, ['title', 'complaint_title'], 'title'),
        residentComplaintsSelectExpr($db, ['category', 'complaint_type'], 'category'),
        residentComplaintsSelectExpr($db, ['date_submitted', 'filing_date', 'created_at'], 'date_submitted'),
        residentComplaintsSelectExpr($db, ['jurisdiction_status'], 'jurisdiction_status', "'Valid'"),
        residentComplaintsSelectExpr($db, ['status'], 'status', "'Pending Review'")
    ];

    // Pick a safe order column that exists.
    $orderCol = residentComplaintsPickComplaintColumn($db, ['date_submitted', 'filing_date', 'created_at']);
    if (!$orderCol) {
        $orderCol = 'id';
    }

    $whereSql = '';
    $whereParams = [];

    if (residentComplaintsHasComplaintColumn($db, 'resident_id')) {
        $whereSql = 'resident_id = ?';
        $whereParams = [$residentId];
    } elseif (residentComplaintsHasComplaintColumn($db, 'complainant_name')) {
        // Fallback for older schemas: match by complainant name.
        // This is not as strong as resident_id, but prevents leaking all complaints.
        $whereSql = 'complainant_name = ?';
        $whereParams = [$residentName];
    } else {
        // No safe way to scope complaints to the logged in resident.
        $moduleReady = false;
    }

    if ($moduleReady) {
        $complaints = $db->fetchAll(
            "SELECT " . implode(', ', $selectCols) . "
             FROM complaints
             WHERE {$whereSql}
             ORDER BY {$orderCol} DESC, id DESC",
            $whereParams
        );
    }
}

$summary = [
    'total' => count($complaints),
    'active' => 0,
    'resolved' => 0,
    'referred' => 0
];

foreach ($complaints as $complaint) {
    $displayStatus = residentComplaintsDisplayStatus($complaint['status'] ?? '');
    if (in_array($displayStatus, ['Pending Review', 'Under Investigation', 'Scheduled for Mediation'], true)) {
        $summary['active']++;
    }
    if ($displayStatus === 'Resolved') {
        $summary['resolved']++;
    }
    if ($displayStatus === 'Referred to Other Barangay') {
        $summary['referred']++;
    }
}
?>
<?php
$page_title = 'My Complaints';
require_once __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content module-page resident-complaints-page" id="mainContent">
  <div class="container-fluid">
    <section class="dashboard-head mb-3">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>My Complaints</h2>
        <p class="dashboard-subtitle">Track the handling status of every complaint you submitted to the barangay.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <?php if (!$moduleReady): ?>
      <div class="alert alert-warning shadow-sm border-0 mb-4"><?php echo htmlspecialchars(residentComplaintsMissingTableMessage()); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Total Complaints</p><h3 class="mb-0"><?php echo (int)$summary['total']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Active Cases</p><h3 class="mb-0"><?php echo (int)$summary['active']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Referred</p><h3 class="mb-0"><?php echo (int)$summary['referred']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Resolved</p><h3 class="mb-0"><?php echo (int)$summary['resolved']; ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <?php if (!$complaints): ?>
          <div class="p-5 text-center">
            <h4 class="mb-2">No complaints submitted yet</h4>
            <p class="text-muted mb-4">When you report an incident, it will appear here for status tracking.</p>
            <a href="<?php echo BASE_URL; ?>complaints/submit_complaint.php" class="btn btn-primary">Submit Your First Complaint</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Reference Number</th>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Date Submitted</th>
                  <th>Jurisdiction Status</th>
                  <th>Complaint Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($complaints as $complaint): ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars(residentComplaintsDisplayReference($complaint)); ?></td>
                    <td><?php echo htmlspecialchars($complaint['title'] ?: 'Untitled Complaint'); ?></td>
                    <td><?php echo htmlspecialchars($complaint['category'] ?: 'Others'); ?></td>
                    <td><?php echo !empty($complaint['date_submitted']) ? htmlspecialchars(date('F d, Y h:i A', strtotime($complaint['date_submitted']))) : '-'; ?></td>
                    <td><span class="badge text-bg-<?php echo ($complaint['jurisdiction_status'] ?? 'Valid') === 'Valid' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($complaint['jurisdiction_status'] ?: 'Valid'); ?></span></td>
                    <td><?php $displayStatus = residentComplaintsDisplayStatus($complaint['status'] ?? ''); ?><span class="badge text-bg-<?php echo residentComplaintsStatusClass($displayStatus); ?>"><?php echo htmlspecialchars($displayStatus); ?></span></td>
                    <td><a href="<?php echo BASE_URL; ?>complaints/complaint_details.php?id=<?php echo (int)$complaint['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/view-mode-switch.js?v=<?php echo time(); ?>"></script>
</body>
</html>
