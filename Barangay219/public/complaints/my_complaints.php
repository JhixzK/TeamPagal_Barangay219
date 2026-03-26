<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/resident-complaints.php';
require_once __DIR__ . '/../../includes/complaint-categories.php';

residentComplaintsRequireResident();

$db = Database::getInstance();
$residentId = residentComplaintsGetResidentId();
$residentName = residentComplaintsGetResidentName();
$systemBarangay = residentComplaintsSystemBarangay();
$moduleReady = residentComplaintsTableExists($db);
$categories = complaintCategoriesList();

$formData = [
    'category' => '',
    'title' => '',
    'description' => '',
    'incident_date' => date('Y-m-d'),
    'location' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$moduleReady) {
        $errors[] = residentComplaintsMissingTableMessage();
    }
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session token is invalid. Please refresh the page and try again.';
    }
    foreach ($formData as $key => $value) {
        $formData[$key] = sanitizeInput($_POST[$key] ?? $value);
    }
    if (!complaintCategoriesIsValid($formData['category'])) {
        $errors[] = 'Please choose a valid complaint category.';
    }
    if ($formData['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($formData['description'] === '') {
        $errors[] = 'Description is required.';
    }
    if ($formData['incident_date'] === '') {
        $errors[] = 'Incident date is required.';
    }
    if ($formData['location'] === '') {
        $errors[] = 'Location is required.';
    }

    list($evidencePath, $uploadError) = residentComplaintsHandleUpload($_FILES['evidence_file'] ?? null);
    if ($uploadError !== null) {
        $errors[] = $uploadError;
    }

    if (!$errors) {
        $jurisdiction = residentComplaintsResolveJurisdiction($systemBarangay);
        $submittedAt = date('Y-m-d H:i:s');
        $filingDate = date('Y-m-d');
        $incidentTime = null;
        $landmark = null;
        $respondentName = null;
        $respondentAddress = null;
        $respondentBarangay = null;
        $respondentCity = null;

        try {
            $referenceNumber = null;
            $inserted = false;
            $supportsReferenceNumber = residentComplaintsHasComplaintColumn($db, 'reference_number');
            $supportsResidentId = residentComplaintsHasComplaintColumn($db, 'resident_id');

            for ($attempt = 0; $attempt < 3 && !$inserted; $attempt++) {
                $db->beginTransaction();
                try {
                    $referenceNumber = $supportsReferenceNumber ? residentComplaintsGenerateReference($db) : null;

                    if ($supportsReferenceNumber && $supportsResidentId) {
                        $db->query(
                            "INSERT INTO complaints (
                                reference_number, resident_id, complaint_title, complainant_name,
                                respondent_name, complaint_type, narrative, filing_date,
                                category, title, description, incident_date, incident_time,
                                incident_house_street, incident_landmark, incident_barangay,
                                respondent_address, respondent_barangay, respondent_city, respondent_residency,
                                evidence_file, jurisdiction_status, status, assigned_officer,
                                resolution_notes, referral_notes, date_submitted
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )",
                            [
                                $referenceNumber,
                                $residentId,
                                $formData['title'],
                                $residentName,
                                $respondentName,
                                $formData['category'],
                                $formData['description'],
                                $filingDate,
                                $formData['category'],
                                $formData['title'],
                                $formData['description'],
                                $formData['incident_date'],
                                $incidentTime,
                                $formData['location'],
                                $landmark,
                                $systemBarangay,
                                $respondentAddress,
                                $respondentBarangay,
                                $respondentCity,
                                'Unknown',
                                $evidencePath,
                                $jurisdiction['jurisdiction_status'],
                                $jurisdiction['status'],
                                null,
                                null,
                                $jurisdiction['jurisdiction_status'] === 'Outside Jurisdiction' ? 'This incident was reported outside the covered barangay jurisdiction.' : null,
                                $submittedAt,
                            ]
                        );
                    } elseif ($supportsResidentId) {
                        $db->query(
                            "INSERT INTO complaints (
                                resident_id, complaint_title, complainant_name,
                                respondent_name, complaint_type, narrative, filing_date,
                                category, title, description, incident_date, incident_time,
                                incident_house_street, incident_landmark, incident_barangay,
                                respondent_address, respondent_barangay, respondent_city, respondent_residency,
                                evidence_file, jurisdiction_status, status, assigned_officer,
                                resolution_notes, referral_notes, date_submitted
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )",
                            [
                                $residentId,
                                $formData['title'],
                                $residentName,
                                $respondentName,
                                $formData['category'],
                                $formData['description'],
                                $filingDate,
                                $formData['category'],
                                $formData['title'],
                                $formData['description'],
                                $formData['incident_date'],
                                $incidentTime,
                                $formData['location'],
                                $landmark,
                                $systemBarangay,
                                $respondentAddress,
                                $respondentBarangay,
                                $respondentCity,
                                'Unknown',
                                $evidencePath,
                                $jurisdiction['jurisdiction_status'],
                                $jurisdiction['status'],
                                null,
                                null,
                                $jurisdiction['jurisdiction_status'] === 'Outside Jurisdiction' ? 'This incident was reported outside the covered barangay jurisdiction.' : null,
                                $submittedAt,
                            ]
                        );
                    } else {
                        $db->query(
                            "INSERT INTO complaints (
                                complaint_title, complainant_name,
                                respondent_name, complaint_type, narrative, filing_date,
                                category, title, description, incident_date, incident_time,
                                incident_house_street, incident_landmark, incident_barangay,
                                respondent_address, respondent_barangay, respondent_city, respondent_residency,
                                evidence_file, jurisdiction_status, status, assigned_officer,
                                resolution_notes, referral_notes, date_submitted
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )",
                            [
                                $formData['title'],
                                $residentName,
                                $respondentName,
                                $formData['category'],
                                $formData['description'],
                                $filingDate,
                                $formData['category'],
                                $formData['title'],
                                $formData['description'],
                                $formData['incident_date'],
                                $incidentTime,
                                $formData['location'],
                                $landmark,
                                $systemBarangay,
                                $respondentAddress,
                                $respondentBarangay,
                                $respondentCity,
                                'Unknown',
                                $evidencePath,
                                $jurisdiction['jurisdiction_status'],
                                $jurisdiction['status'],
                                null,
                                null,
                                $jurisdiction['jurisdiction_status'] === 'Outside Jurisdiction' ? 'This incident was reported outside the covered barangay jurisdiction.' : null,
                                $submittedAt,
                            ]
                        );
                    }
                    $db->commit();
                    $complaintId = (int)$db->lastInsertId();
                    try {
                        require_once __DIR__ . '/../../includes/notifications-store.php';
                        $refLabel = $referenceNumber ?: ('ID ' . $complaintId);
                        notificationsNotifyStaffForModule(
                            'complaints',
                            'New complaint',
                            'A resident submitted a complaint: ' . $formData['title'] . ' (Ref: ' . $refLabel . ').',
                            'info',
                            'complaint_submitted_resident',
                            BASE_URL . 'complaints.php',
                            json_encode(['complaint_id' => $complaintId], JSON_UNESCAPED_UNICODE),
                            0
                        );
                        notificationsInsertForResident(
                            $residentId,
                            'Complaint received',
                            'Your complaint was submitted successfully. Reference: ' . $refLabel,
                            'success',
                            'complaint_submitted',
                            BASE_URL . 'complaints/my_complaints.php',
                            json_encode(['complaint_id' => $complaintId], JSON_UNESCAPED_UNICODE)
                        );
                    } catch (Throwable $notifyEx) {
                        error_log('Resident complaint notifications: ' . $notifyEx->getMessage());
                    }
                    $inserted = true;
                } catch (Exception $exception) {
                    if ($db->getConnection()->inTransaction()) {
                        $db->rollback();
                    }
                    if ($exception instanceof PDOException && ($exception->getCode() === '23000' || ($exception->errorInfo[1] ?? 0) === 1062)) {
                        continue;
                    }
                    throw $exception;
                }
            }

            if (!$inserted) {
                throw new RuntimeException('Unable to submit your complaint right now. Please try again.');
            }

            if (!$supportsReferenceNumber || $referenceNumber === null) {
                $lastId = (int)$db->lastInsertId();
                $referenceNumber = $lastId > 0 ? ('CMP-' . date('Y') . '-' . str_pad((string)$lastId, 4, '0', STR_PAD_LEFT)) : 'Pending';
            }

            header('Location: ' . BASE_URL . 'complaints/my_complaints.php?submitted=' . urlencode((string)$referenceNumber));
            exit();
        } catch (Exception $exception) {
            if ($evidencePath && file_exists(PUBLIC_PATH . '/' . $evidencePath)) {
                @unlink(PUBLIC_PATH . '/' . $evidencePath);
            }
            $errors[] = DEBUG_MODE ? $exception->getMessage() : 'Unable to submit your complaint right now. Please try again later.';
        }
    }
}

$complaints = [];
if ($moduleReady) {
    $selectCols = [
        'id',
        residentComplaintsSelectExpr($db, ['reference_number'], 'reference_number'),
        residentComplaintsSelectExpr($db, ['title', 'complaint_title'], 'title'),
        residentComplaintsSelectExpr($db, ['category', 'complaint_type'], 'category'),
        residentComplaintsSelectExpr($db, ['date_submitted', 'filing_date', 'created_at'], 'date_submitted'),
        residentComplaintsSelectExpr($db, ['jurisdiction_status'], 'jurisdiction_status', "'Valid'"),
        residentComplaintsSelectExpr($db, ['status'], 'status', "'pending'"),
        residentComplaintsSelectExpr($db, ['incident_house_street'], 'location_line', "''"),
        residentComplaintsSelectExpr($db, ['description', 'narrative'], 'body_text', "''"),
        residentComplaintsSelectExpr($db, ['assigned_officer'], 'assigned_officer', "NULL"),
    ];
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
        $whereSql = 'complainant_name = ?';
        $whereParams = [$residentName];
    } else {
        $moduleReady = false;
    }
    if ($moduleReady) {
        $complaints = $db->fetchAll(
            'SELECT ' . implode(', ', $selectCols) . ' FROM complaints WHERE ' . $whereSql . ' ORDER BY ' . $orderCol . ' DESC, id DESC',
            $whereParams
        );
    }
}

$searchQ = trim(sanitizeInput($_GET['q'] ?? ''));
$perPage = max(5, min(50, (int)($_GET['per_page'] ?? 5)));
$listPage = max(1, (int)($_GET['page'] ?? 1));
$submittedRef = sanitizeInput($_GET['submitted'] ?? '');

$summary = ['total' => count($complaints), 'active' => 0, 'resolved' => 0, 'rejected' => 0];
foreach ($complaints as $row) {
    $code = strtolower(trim((string)($row['status'] ?? 'pending')));
    if (in_array($code, ['pending', 'approved', 'assigned', 'in_progress'], true)) {
        $summary['active']++;
    }
    if ($code === 'resolved') {
        $summary['resolved']++;
    }
    if ($code === 'rejected') {
        $summary['rejected']++;
    }
}

$filtered = $complaints;
if ($searchQ !== '') {
    $needle = strtolower($searchQ);
    $filtered = array_values(array_filter($filtered, function ($row) use ($needle) {
        $hay = strtolower(
            ($row['title'] ?? '') . ' ' .
            ($row['category'] ?? '') . ' ' .
            ($row['location_line'] ?? '') . ' ' .
            ($row['body_text'] ?? '')
        );
        return strpos($hay, $needle) !== false;
    }));
}
$listTotal = count($filtered);
$totalPages = max(1, (int)ceil($listTotal / $perPage));
if ($listPage > $totalPages) {
    $listPage = $totalPages;
}
$offset = ($listPage - 1) * $perPage;
$complaintsPage = array_slice($filtered, $offset, $perPage);

$page_title = 'Complaints';
require_once __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content module-page resident-complaints-page resident-theme resident-complaints-portal" id="mainContent">
  <div class="container-fluid py-3">
    <section class="dashboard-head mb-4">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Complaints / Reports</h2>
        <p class="dashboard-subtitle">Submit a new report and track status in one place.</p>
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
      <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2 small">Total</p><h3 class="mb-0"><?php echo (int)$summary['total']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2 small">Active</p><h3 class="mb-0"><?php echo (int)$summary['active']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2 small">Resolved</p><h3 class="mb-0"><?php echo (int)$summary['resolved']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2 small">Rejected</p><h3 class="mb-0"><?php echo (int)$summary['rejected']; ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4 complaint-submit-card">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <h5 class="mb-0">Submit Complaint</h5>
          <span class="text-muted small">Status starts as Pending</span>
        </div>
        <?php if ($submittedRef !== ''): ?>
          <div class="alert alert-success border-0 mb-3">Complaint submitted. Reference: <strong><?php echo htmlspecialchars($submittedRef); ?></strong></div>
        <?php endif; ?>
        <?php if ($errors): ?>
          <div class="alert alert-danger border-0 mb-3"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="complaint-submit-form" <?php echo !$moduleReady ? 'onsubmit="return false;"' : ''; ?>>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-uppercase small text-muted">Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="Brief complaint title" maxlength="255" value="<?php echo htmlspecialchars($formData['title']); ?>" required <?php echo !$moduleReady ? 'disabled' : ''; ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small text-muted">Category <span class="text-danger">*</span></label>
              <select name="category" class="form-select" required <?php echo !$moduleReady ? 'disabled' : ''; ?>>
                <option value="">Select complaint category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $formData['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small text-muted">Location <span class="text-danger">*</span></label>
              <input type="text" name="location" class="form-control" placeholder="Street, Purok, or Sitio" maxlength="255" value="<?php echo htmlspecialchars($formData['location']); ?>" required <?php echo !$moduleReady ? 'disabled' : ''; ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small text-muted">Date of incident <span class="text-danger">*</span></label>
              <input type="date" name="incident_date" class="form-control" value="<?php echo htmlspecialchars($formData['incident_date']); ?>" required <?php echo !$moduleReady ? 'disabled' : ''; ?>>
            </div>
            <div class="col-12">
              <label class="form-label text-uppercase small text-muted">Upload image <span class="text-muted fw-normal">(optional)</span></label>
              <input type="file" name="evidence_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" <?php echo !$moduleReady ? 'disabled' : ''; ?>>
            </div>
            <div class="col-12">
              <label class="form-label text-uppercase small text-muted">Description <span class="text-danger">*</span></label>
              <textarea name="description" class="form-control" rows="4" placeholder="Describe the complaint in detail" required <?php echo !$moduleReady ? 'disabled' : ''; ?>><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary px-4" <?php echo !$moduleReady ? 'disabled' : ''; ?>><i class="bi bi-check2-circle me-1"></i> Submit Complaint</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm complaint-history-card">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>My Complaints</h5>
          <span class="text-muted small"><?php echo (int)$listTotal; ?> total</span>
        </div>
        <form method="get" class="row g-2 align-items-end mb-3 complaint-filter-form">
          <input type="hidden" name="submitted" value="">
          <div class="col-lg-5">
            <label class="form-label text-uppercase small text-muted mb-1">Search</label>
            <input type="text" name="q" class="form-control" placeholder="Search title, category, location, or description" value="<?php echo htmlspecialchars($searchQ); ?>">
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label text-uppercase small text-muted mb-1">Show</label>
            <select name="per_page" class="form-select">
              <?php foreach ([5, 10, 25, 50] as $n): ?>
                <option value="<?php echo $n; ?>" <?php echo $perPage === $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 col-6">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Apply</button>
          </div>
          <div class="col-md-2">
            <a href="<?php echo BASE_URL; ?>complaints/my_complaints.php" class="btn btn-link text-decoration-none p-0"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
          </div>
        </form>

        <?php if (!$complaints): ?>
          <div class="text-center text-muted py-5">No complaints submitted yet.</div>
        <?php elseif (!$complaintsPage): ?>
          <div class="text-center text-muted py-5">No matches for your search.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Location</th>
                  <th>Submitted</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($complaintsPage as $c): ?>
                  <?php
                    $displayStatus = residentComplaintsDisplayStatus($c['status'] ?? '');
                    $ref = residentComplaintsDisplayReference($c);
                  ?>
                  <tr>
                    <td class="fw-semibold">#<?php echo (int)$c['id']; ?></td>
                    <td><?php echo htmlspecialchars($c['title'] ?: 'Untitled'); ?></td>
                    <td><?php echo htmlspecialchars($c['category'] ?: '—'); ?></td>
                    <td class="text-muted small"><?php echo htmlspecialchars($c['location_line'] ?: '—'); ?></td>
                    <td class="small"><?php echo !empty($c['date_submitted']) ? htmlspecialchars(date('n/j/Y', strtotime($c['date_submitted']))) : '—'; ?></td>
                    <td><span class="badge text-bg-<?php echo residentComplaintsStatusClass($c['status'] ?? ''); ?>"><?php echo htmlspecialchars($displayStatus); ?></span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-circle" href="<?php echo BASE_URL; ?>complaints/complaint_details.php?id=<?php echo (int)$c['id']; ?>" title="View"><i class="bi bi-eye"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
            <nav class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small">Page <?php echo (int)$listPage; ?> of <?php echo (int)$totalPages; ?></span>
              <div class="btn-group">
                <?php
                $qparams = ['q' => $searchQ, 'per_page' => $perPage];
                $prev = max(1, $listPage - 1);
                $next = min($totalPages, $listPage + 1);
                $mk = function ($p) use ($qparams) {
                    $qparams['page'] = $p;
                    return BASE_URL . 'complaints/my_complaints.php?' . http_build_query(array_filter($qparams));
                };
                ?>
                <a class="btn btn-outline-secondary btn-sm <?php echo $listPage <= 1 ? 'disabled' : ''; ?>" href="<?php echo $listPage <= 1 ? '#' : htmlspecialchars($mk($prev)); ?>">Prev</a>
                <a class="btn btn-outline-secondary btn-sm <?php echo $listPage >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $listPage >= $totalPages ? '#' : htmlspecialchars($mk($next)); ?>">Next</a>
              </div>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
.resident-complaints-portal .complaint-submit-card .form-label { letter-spacing: 0.02em; }
.resident-complaints-portal .complaint-history-card table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
</style>

<script src="<?php echo ASSETS_URL; ?>css/js/view-mode-switch.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
