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
          <div class="mt-2 d-none" id="otherTypeContainer">
            <label class="form-label mb-1" for="incident_type_detail">Please specify incident type</label>
            <input type="text" class="form-control" id="incident_type_detail" name="incident_type_detail" maxlength="100" placeholder="e.g., Illegal Parking">
          </div>
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
            Respondents (Person/s Reported) <span class="text-danger">*</span>
          </label>
          <div id="respondentsContainer" class="d-flex flex-column gap-2"></div>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addRespondentBtn">
            <i class="bi bi-plus-circle me-1"></i>Add Respondent
          </button>
          <small class="text-muted d-block mt-1">For privacy, resident records are not shown on this page.</small>
        </div>

        <div class="col-12">
          <label class="form-label">Witnesses (Optional)</label>
          <textarea class="form-control" id="witnesses" name="witnesses" rows="3" maxlength="1000" placeholder="Example:&#10;Juan Dela Cruz - Nickname 'Jun' - 09171234567&#10;Aling Nena (Sari-sari store owner across the street)"></textarea>
          <small class="text-muted">Include nicknames, descriptions, or contact numbers to help the Barangay locate them faster.</small>
        </div>

        <div class="col-12">
          <label class="form-label">Incident Narrative <span class="text-danger">*</span></label>
          <textarea class="form-control" id="narrative" name="narrative" rows="6" maxlength="3000" required placeholder="Describe what happened, where, and when."></textarea>
          <small id="narrativeCounter" class="small text-muted">0 / 3000 characters</small>
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

<div class="modal fade" id="submitSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-check-circle-fill text-success me-2"></i>Report Submitted</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Your blotter report has been received successfully.</p>
        <div class="alert alert-success mb-0 d-flex align-items-center justify-content-between gap-2">
          <div>
            <small class="d-block text-muted">Reference Number</small>
            <strong id="successReferenceNo" class="fs-5">-</strong>
          </div>
          <button type="button" class="btn btn-outline-success btn-sm" id="copyReferenceBtn">
            <i class="bi bi-clipboard me-1"></i>Copy to Clipboard
          </button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="goToMyBlottersBtn">Go to My Blotters</button>
      </div>
    </div>
  </div>
</div>

<style>
.resident-incident-page textarea.form-control {
  resize: none;
}

.resident-incident-page .form-check-input[type="checkbox"] {
  border-color: #9aa4b2;
}

.resident-incident-page .form-check-input[type="checkbox"]:checked {
  background-color: #0d6efd;
  border-color: #0d6efd;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e");
  background-size: 0.8rem 0.8rem;
  background-position: center;
  background-repeat: no-repeat;
}
</style>

<script>
(function () {
  const API_CREATE = '<?php echo API_URL; ?>blotter/create.php';
  const BASE = '<?php echo BASE_URL; ?>';

  const form = document.getElementById('incidentForm');
  const btn = document.getElementById('submitIncidentBtn');
  const incidentType = document.getElementById('incidentType');
  const otherTypeContainer = document.getElementById('otherTypeContainer');
  const incidentTypeDetail = document.getElementById('incident_type_detail');
  const respondentsContainer = document.getElementById('respondentsContainer');
  const addRespondentBtn = document.getElementById('addRespondentBtn');
  const narrative = document.getElementById('narrative');
  const narrativeCounter = document.getElementById('narrativeCounter');
  const successRefEl = document.getElementById('successReferenceNo');
  const copyReferenceBtn = document.getElementById('copyReferenceBtn');
  const goToMyBlottersBtn = document.getElementById('goToMyBlottersBtn');
  const successModalEl = document.getElementById('submitSuccessModal');
  const successModal = (window.bootstrap && successModalEl) ? new bootstrap.Modal(successModalEl) : null;

  function addRespondentRow(value = '') {
    if (!respondentsContainer) return;
    const row = document.createElement('div');
    row.className = 'input-group respondent-row';
    row.innerHTML =
      '<input type="text" class="form-control respondent-name-input" maxlength="150" placeholder="Enter respondent full name" value="">' +
      '<button type="button" class="btn btn-outline-danger remove-respondent-btn" aria-label="Remove respondent">Remove</button>';

    const input = row.querySelector('.respondent-name-input');
    const removeBtn = row.querySelector('.remove-respondent-btn');
    if (input) {
      input.value = String(value || '');
    }
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        if (respondentsContainer.querySelectorAll('.respondent-row').length <= 1) {
          alert('At least one respondent is required.');
          return;
        }
        row.remove();
      });
    }

    respondentsContainer.appendChild(row);
  }

  function collectRespondents() {
    if (!respondentsContainer) return [];
    const names = Array.from(respondentsContainer.querySelectorAll('.respondent-name-input'))
      .map(function (input) { return String(input.value || '').trim(); })
      .filter(Boolean);

    return names.map(function (name) {
      return {
        name: name,
        address: '',
        contact: '',
        residency: 'non_resident',
        resident_id: null
      };
    });
  }

  function todayBadge() {
    const b = document.getElementById('mainDateBadge');
    if (!b) return;
    const now = new Date();
    b.innerHTML = '<i class="bi bi-calendar3 me-1"></i>' + now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
  }

  function toggleOtherIncidentType() {
    const value = String(incidentType?.value || '').toLowerCase();
    const isOther = value === 'other';
    otherTypeContainer.classList.toggle('d-none', !isOther);
    incidentTypeDetail.required = isOther;
    if (!isOther) {
      incidentTypeDetail.value = '';
    }
  }

  function updateNarrativeCounter() {
    const max = 3000;
    const len = String(narrative?.value || '').length;
    narrativeCounter.textContent = len + ' / ' + max + ' characters';
    if (len >= max) {
      narrativeCounter.classList.remove('text-muted');
      narrativeCounter.classList.add('text-danger');
    } else {
      narrativeCounter.classList.remove('text-danger');
      narrativeCounter.classList.add('text-muted');
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const respondents = collectRespondents();
    if (!respondents.length) {
      alert('At least one respondent is required.');
      return;
    }

    const fd = new FormData(form);
    fd.append('respondents', JSON.stringify(respondents));
    fd.append('respondent_name_raw', respondents[0].name);

    btn.disabled = true;
    fetch(API_CREATE, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
      .then(r => r.json())
      .then(d => {
        btn.disabled = false;
        if (d?.status === 'error' || d?.success === false || !d?.success) {
          alert(d.message || 'Submission failed.');
          return;
        }
        const ref = d?.data?.reference_no || '';
        successRefEl.textContent = ref || '-';
        if (successModal) {
          successModal.show();
        } else {
          alert('Report submitted. Reference #: ' + ref);
        }
        form.reset();
        if (respondentsContainer) {
          respondentsContainer.innerHTML = '';
        }
        addRespondentRow();
        toggleOtherIncidentType();
        updateNarrativeCounter();
      })
      .catch(() => {
        btn.disabled = false;
        alert('Unable to submit report right now.');
      });
  });

  copyReferenceBtn.addEventListener('click', function () {
    const ref = String(successRefEl.textContent || '').trim();
    if (!ref || ref === '-') {
      return;
    }

    const restoreLabel = copyReferenceBtn.innerHTML;
    const copiedLabel = '<i class="bi bi-clipboard-check me-1"></i>Copied';

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(ref).then(function () {
        copyReferenceBtn.innerHTML = copiedLabel;
        setTimeout(function () {
          copyReferenceBtn.innerHTML = restoreLabel;
        }, 1400);
      }).catch(function () {
        alert('Unable to copy automatically. Please copy the reference number manually.');
      });
      return;
    }

    alert('Clipboard access is unavailable on this browser. Please copy the reference number manually.');
  });

  goToMyBlottersBtn.addEventListener('click', function () {
    const ref = String(successRefEl.textContent || '').trim();
    window.location.href = BASE + 'my_blotters.php' + (ref && ref !== '-' ? ('?submitted=' + encodeURIComponent(ref)) : '');
  });

  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }

  if (addRespondentBtn) {
    addRespondentBtn.addEventListener('click', function () {
      addRespondentRow();
    });
  }

  incidentType.addEventListener('change', toggleOtherIncidentType);
  narrative.addEventListener('input', updateNarrativeCounter);

  todayBadge();
  addRespondentRow();
  toggleOtherIncidentType();
  updateNarrativeCounter();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
