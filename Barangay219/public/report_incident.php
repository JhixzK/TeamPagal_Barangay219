<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$page_title = 'Report Incident';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page resident-incident-page resident-theme">
  <div class="container-fluid">
    <section class="dashboard-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="hero-copy">
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
          <h2 class="mb-1"><i class="bi bi-exclamation-octagon me-2"></i>Report Incident (Blotter)</h2>
          <p class="hero-subtitle mb-0">File an incident report, attach evidence, and track your case status securely.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
        </div>
      </div>
    </section>

    <form id="incidentForm" class="card p-3 p-md-4" enctype="multipart/form-data" novalidate>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Incident Type <span class="text-danger">*</span></label>
          <select class="form-select" id="incidentType" name="incident_type" required>
            <option value="">Select incident type</option>
            <option value="physical_assault">Physical Assault</option>
            <option value="theft">Theft</option>
            <option value="threat">Threat</option>
            <option value="harassment">Harassment</option>
            <option value="property_damage">Property Damage</option>
            <option value="domestic_dispute">Domestic Dispute</option>
            <option value="public_disturbance">Public Disturbance</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Action Requested</label>
          <select class="form-select" id="actionRequested" name="action_requested">
            <option value="Mediation">Mediation</option>
            <option value="Record Only">Record Only</option>
            <option value="Immediate Intervention">Immediate Intervention</option>
          </select>
        </div>

        <div class="col-md-7">
          <label class="form-label">Incident Location <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="incidentLocation" name="incident_location" maxlength="255" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">Incident Date & Time <span class="text-danger">*</span></label>
          <input type="datetime-local" class="form-control" id="incidentDatetime" name="incident_datetime" required>
        </div>

        <div class="col-12">
          <label class="form-label d-flex align-items-center gap-2">
            Respondent (Person Reported) <span class="text-danger">*</span>
          </label>
          <input type="text" class="form-control" id="respondentNameRaw" name="respondent_name_raw" maxlength="255" required placeholder="Enter respondent full name">
          <small class="text-muted">For privacy, resident records are not shown on this page.</small>
        </div>

        <div class="col-12">
          <label class="form-label">Witnesses (Optional)</label>
          <textarea class="form-control" id="witnesses" name="witnesses" rows="3" placeholder="Example:&#10;Juan Dela Cruz - Nickname 'Jun' - 09171234567&#10;Aling Nena (Sari-sari store owner across the street)"></textarea>
          <small class="text-muted">Include nicknames, descriptions, or contact numbers to help the Barangay locate them faster.</small>
        </div>

        <div class="col-12">
          <label class="form-label">Incident Narrative <span class="text-danger">*</span></label>
          <textarea class="form-control" id="narrative" name="narrative" rows="6" maxlength="5000" required placeholder="Describe what happened, where, and when."></textarea>
        </div>

        <div class="col-md-8">
          <label class="form-label">Evidence Upload (Images only)</label>
          <input type="file" class="form-control" id="evidence" name="evidence" accept="image/jpeg,image/png,image/webp">
          <small class="text-muted">Accepted: JPG, PNG, WEBP (max 10MB)</small>
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="isConfidential" name="is_confidential" value="1">
            <label class="form-check-label" for="isConfidential" data-bs-toggle="tooltip" data-bs-placement="top" title="Only the Barangay Captain and specialized officers will see this.">
              Confidential Report
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>my_blotters.php">View My Blotters</a>
        <button type="submit" class="btn btn-primary" id="submitIncidentBtn">
          <i class="bi bi-send-check me-1"></i>Submit Report
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const API_CREATE = '<?php echo API_URL; ?>blotter/create.php';

  const form = document.getElementById('incidentForm');
  const btn = document.getElementById('submitIncidentBtn');
  const respondentNameRaw = document.getElementById('respondentNameRaw');

  function todayBadge() {
    const b = document.getElementById('mainDateBadge');
    if (!b) return;
    const now = new Date();
    b.innerHTML = '<i class="bi bi-calendar3 me-1"></i>' + now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!String(respondentNameRaw.value || '').trim()) {
      alert('Respondent name is required.');
      return;
    }

    const fd = new FormData(form);

    btn.disabled = true;
    fetch(API_CREATE, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
      .then(r => r.json())
      .then(d => {
        btn.disabled = false;
        if (!d.success) {
          alert(d.message || 'Submission failed.');
          return;
        }
        const ref = d?.data?.reference_no || '';
        window.location.href = '<?php echo BASE_URL; ?>my_blotters.php?submitted=' + encodeURIComponent(ref);
      })
      .catch(() => {
        btn.disabled = false;
        alert('Unable to submit report right now.');
      });
  });

  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }

  todayBadge();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
