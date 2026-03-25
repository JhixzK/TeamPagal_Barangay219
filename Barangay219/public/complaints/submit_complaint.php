<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/resident-complaints.php';

residentComplaintsRequireResident();

$db = Database::getInstance();
$residentId = residentComplaintsGetResidentId();
$residentName = residentComplaintsGetResidentName();
$systemBarangay = residentComplaintsSystemBarangay();
$moduleReady = residentComplaintsTableExists($db);

$categories = [
    'Noise Complaint',
    'Neighbor Dispute',
    'Public Disturbance',
    'Sanitation / Garbage',
    'Infrastructure Issue',
    'Illegal Parking',
    'Suspicious Activity',
    'Others'
];
$residencyOptions = [
    'Resident of this Barangay',
    'Resident of another Barangay',
    'Unknown'
];

$formData = [
    'category' => '',
    'title' => '',
    'description' => '',
    'incident_date' => date('Y-m-d'),
    'incident_time' => '',
    'incident_house_street' => '',
    'incident_purok' => '',
    'incident_landmark' => '',
    'incident_barangay' => $systemBarangay,
    'respondent_name' => '',
    'respondent_address' => '',
    'respondent_barangay' => '',
    'respondent_city' => '',
    'respondent_residency' => 'Unknown'
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

    if (!in_array($formData['category'], $categories, true)) $errors[] = 'Please choose a valid complaint category.';
    if ($formData['title'] === '') $errors[] = 'Incident Title is required.';
    if ($formData['description'] === '') $errors[] = 'Description is required.';
    if ($formData['incident_date'] === '') $errors[] = 'Incident Date is required.';
    if ($formData['incident_time'] === '') $errors[] = 'Incident Time is required.';
    if ($formData['incident_house_street'] === '') $errors[] = 'House Number / Street is required.';
    if ($formData['respondent_address'] === '') $errors[] = 'Respondent Address is required.';
    if ($formData['respondent_barangay'] === '') $errors[] = 'Respondent Barangay is required.';
    if ($formData['respondent_city'] === '') $errors[] = 'Respondent City / Municipality is required.';
    if (!in_array($formData['respondent_residency'], $residencyOptions, true)) $errors[] = 'Please choose a valid respondent residency option.';

    list($evidencePath, $uploadError) = residentComplaintsHandleUpload($_FILES['evidence_file'] ?? null);
    if ($uploadError !== null) {
        $errors[] = $uploadError;
    }

    if (!$errors) {
        $jurisdiction = residentComplaintsResolveJurisdiction($formData['incident_barangay']);
        $submittedAt = date('Y-m-d H:i:s');
        $filingDate = date('Y-m-d');

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
                                incident_house_street, incident_purok, incident_landmark, incident_barangay,
                                respondent_address, respondent_barangay, respondent_city, respondent_residency,
                                evidence_file, jurisdiction_status, status, assigned_officer,
                                resolution_notes, referral_notes, date_submitted
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )",
                            [
                                $referenceNumber,
                                $residentId,
                                $formData['title'],
                                $residentName,
                                $formData['respondent_name'] !== '' ? $formData['respondent_name'] : null,
                                $formData['category'],
                                $formData['description'],
                                $filingDate,
                                $formData['category'],
                                $formData['title'],
                                $formData['description'],
                                $formData['incident_date'],
                                $formData['incident_time'],
                                $formData['incident_house_street'],
                                $formData['incident_purok'],
                                $formData['incident_landmark'] !== '' ? $formData['incident_landmark'] : null,
                                $formData['incident_barangay'],
                                $formData['respondent_address'],
                                $formData['respondent_barangay'],
                                $formData['respondent_city'],
                                $formData['respondent_residency'],
                                $evidencePath,
                                $jurisdiction['jurisdiction_status'],
                                $jurisdiction['status'],
                                null,
                                null,
                                $jurisdiction['jurisdiction_status'] === 'Outside Jurisdiction' ? 'This incident was reported outside the covered barangay jurisdiction.' : null,
                                $submittedAt
                            ]
                        );
                    } elseif ($supportsResidentId) {
                        $db->query(
                            "INSERT INTO complaints (
                                resident_id, complaint_title, complainant_name,
                                respondent_name, complaint_type, narrative, filing_date,
                                category, title, description, incident_date, incident_time,
                                incident_house_street, incident_purok, incident_landmark, incident_barangay,
                                respondent_address, respondent_barangay, respondent_city, respondent_residency,
                                evidence_file, jurisdiction_status, status, assigned_officer,
                                resolution_notes, referral_notes, date_submitted
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )",
                            [
                                $residentId,
                                $formData['title'],
                                $residentName,
                                $formData['respondent_name'] !== '' ? $formData['respondent_name'] : null,
                                $formData['category'],
                                $formData['description'],
                                $filingDate,
                                $formData['category'],
                                $formData['title'],
                                $formData['description'],
                                $formData['incident_date'],
                                $formData['incident_time'],
                                $formData['incident_house_street'],
                                $formData['incident_purok'],
                                $formData['incident_landmark'] !== '' ? $formData['incident_landmark'] : null,
                                $formData['incident_barangay'],
                                $formData['respondent_address'],
                                $formData['respondent_barangay'],
                                $formData['respondent_city'],
                                $formData['respondent_residency'],
                                $evidencePath,
                                $jurisdiction['jurisdiction_status'],
                                $jurisdiction['status'],
                                null,
                                null,
                                $jurisdiction['jurisdiction_status'] === 'Outside Jurisdiction' ? 'This incident was reported outside the covered barangay jurisdiction.' : null,
                                $submittedAt
                            ]
                        );
                    } else {
                        // Oldest schema fallback: no resident_id column.
                        // Store only complainant_name so residents can still list their own records by name.
                        $db->query(
                            "INSERT INTO complaints (
                                complaint_title, complainant_name,
                                respondent_name, complaint_type, narrative, filing_date,
                                category, title, description, incident_date, incident_time,
                                incident_house_street, incident_purok, incident_landmark, incident_barangay,
                                respondent_address, respondent_barangay, respondent_city, respondent_residency,
                                evidence_file, jurisdiction_status, status, assigned_officer,
                                resolution_notes, referral_notes, date_submitted
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )",
                            [
                                $formData['title'],
                                $residentName,
                                $formData['respondent_name'] !== '' ? $formData['respondent_name'] : null,
                                $formData['category'],
                                $formData['description'],
                                $filingDate,
                                $formData['category'],
                                $formData['title'],
                                $formData['description'],
                                $formData['incident_date'],
                                $formData['incident_time'],
                                $formData['incident_house_street'],
                                $formData['incident_purok'],
                                $formData['incident_landmark'] !== '' ? $formData['incident_landmark'] : null,
                                $formData['incident_barangay'],
                                $formData['respondent_address'],
                                $formData['respondent_barangay'],
                                $formData['respondent_city'],
                                $formData['respondent_residency'],
                                $evidencePath,
                                $jurisdiction['jurisdiction_status'],
                                $jurisdiction['status'],
                                null,
                                null,
                                $jurisdiction['jurisdiction_status'] === 'Outside Jurisdiction' ? 'This incident was reported outside the covered barangay jurisdiction.' : null,
                                $submittedAt
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
                    if ($exception instanceof PDOException && ($exception->getCode() === '23000' || $exception->errorInfo[1] === 1062)) {
                        continue;
                    }
                    throw $exception;
                }
            }

            if (!$inserted) {
                throw new RuntimeException('Unable to submit your complaint right now. Please try again.');
            }

            if (!$supportsReferenceNumber || $referenceNumber === null) {
                // Fallback for older schemas: show a computed reference based on inserted ID.
                $lastId = (int)$db->lastInsertId();
                $referenceNumber = $lastId > 0 ? ('CMP-' . date('Y') . '-' . str_pad((string)$lastId, 4, '0', STR_PAD_LEFT)) : 'Pending';
            }

            header('Location: ' . BASE_URL . 'complaints/submit_complaint.php?submitted=' . urlencode($referenceNumber));
            exit();
        } catch (Exception $exception) {
            if ($evidencePath && file_exists(PUBLIC_PATH . '/' . $evidencePath)) {
                @unlink(PUBLIC_PATH . '/' . $evidencePath);
            }
            $errors[] = DEBUG_MODE ? $exception->getMessage() : 'Unable to submit your complaint right now. Please try again later.';
        }
    }
}

$submittedReference = sanitizeInput($_GET['submitted'] ?? '');
?>
<?php
$page_title = 'Submit Complaint';
require_once __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content module-page resident-complaints-page resident-theme" id="mainContent">
  <div class="container-fluid">
    <section class="dashboard-head mb-3">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Submit Complaint</h2>
        <p class="dashboard-subtitle">Report an incident and track it in the resident portal.</p>
      </div>
      <div class="head-meta"><span class="view-badge">Resident View</span><span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span></div>
    </section>

    <?php if (!$moduleReady): ?><div class="alert alert-warning shadow-sm border-0"><?php echo htmlspecialchars(residentComplaintsMissingTableMessage()); ?></div><?php endif; ?>
    <?php if ($submittedReference !== ''): ?><div class="alert alert-success shadow-sm border-0"><strong>Complaint submitted successfully.</strong><br>Reference Number: <?php echo htmlspecialchars($submittedReference); ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger shadow-sm border-0"><strong>Please correct the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form class="card border-0 shadow-sm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <div class="card-body p-4">
        <h5 class="mb-3">Incident Information</h5>
        <div class="row g-3 mb-4">
          <div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?php echo htmlspecialchars($category); ?>" <?php echo $formData['category'] === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-8"><label class="form-label">Incident Title</label><input type="text" class="form-control" name="title" maxlength="255" value="<?php echo htmlspecialchars($formData['title']); ?>" required></div>
          <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars($formData['description']); ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Incident Date</label><input type="date" class="form-control" name="incident_date" value="<?php echo htmlspecialchars($formData['incident_date']); ?>" required></div>
          <div class="col-md-6"><label class="form-label">Incident Time</label><input type="time" class="form-control" name="incident_time" value="<?php echo htmlspecialchars($formData['incident_time']); ?>" required></div>
        </div>

        <h5 class="mb-3">Incident Location</h5>
        <div class="row g-3 mb-4">
                    <div class="col-md-9"><label class="form-label">House Number / Street</label><input type="text" class="form-control" name="incident_house_street" maxlength="255" value="<?php echo htmlspecialchars($formData['incident_house_street']); ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Landmark</label><input type="text" class="form-control" name="incident_landmark" maxlength="255" value="<?php echo htmlspecialchars($formData['incident_landmark']); ?>"></div>
          <div class="col-md-6"><label class="form-label">Barangay</label><input type="text" class="form-control" name="incident_barangay" value="<?php echo htmlspecialchars($formData['incident_barangay']); ?>" readonly required></div>
        </div>

        <h5 class="mb-3">Respondent Information</h5>
        <div class="row g-3 mb-4">
          <div class="col-md-6"><label class="form-label">Respondent Name (optional)</label><input type="text" class="form-control" name="respondent_name" maxlength="255" value="<?php echo htmlspecialchars($formData['respondent_name']); ?>"></div>
          <div class="col-md-6"><label class="form-label">Respondent Residency</label><select class="form-select" name="respondent_residency" required><?php foreach ($residencyOptions as $option): ?><option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formData['respondent_residency'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Respondent Address</label><input type="text" class="form-control" name="respondent_address" maxlength="255" value="<?php echo htmlspecialchars($formData['respondent_address']); ?>" required></div>
          <div class="col-md-3"><label class="form-label">Respondent Barangay</label><input type="text" class="form-control" name="respondent_barangay" maxlength="150" value="<?php echo htmlspecialchars($formData['respondent_barangay']); ?>" required></div>
          <div class="col-md-3"><label class="form-label">Respondent City / Municipality</label><input type="text" class="form-control" name="respondent_city" maxlength="150" value="<?php echo htmlspecialchars($formData['respondent_city']); ?>" required></div>
        </div>

        <h5 class="mb-3">Evidence Upload</h5>
        <div class="row g-3">
          <div class="col-lg-8"><label class="form-label">Upload Evidence</label><input type="file" class="form-control" name="evidence_file" accept=".jpg,.jpeg,.png,.pdf"></div>
          <div class="col-lg-4"><div class="alert alert-light border mb-0">Accepted formats: JPG, JPEG, PNG, PDF (max 5MB).</div></div>
        </div>
      </div>
      <div class="card-footer bg-white border-0 d-flex flex-wrap justify-content-between gap-2 px-4 py-3">
        <span class="text-muted small">Submitted reports cannot be edited or deleted by residents after filing.</span>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>complaints/my_complaints.php" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary" <?php echo !$moduleReady ? 'disabled' : ''; ?>>Submit Complaint</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
