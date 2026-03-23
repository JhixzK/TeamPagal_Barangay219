<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$page_title = 'My Blotters';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page resident-my-blotters-page resident-theme">
  <div class="container-fluid">
    <section class="dashboard-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
          <h2 class="mb-1"><i class="bi bi-shield-exclamation me-2"></i>My Blotter Reports</h2>
          <p class="hero-subtitle mb-0">Track your incident report status and review details securely.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="<?php echo BASE_URL; ?>report_incident.php"><i class="bi bi-plus-circle me-1"></i>New Report</a>
        </div>
      </div>
    </section>

    <div class="card p-3">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Reference #</th>
              <th>Incident Type</th>
              <th>Location</th>
              <th>Incident Date</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody id="blotterRows">
            <tr><td colspan="6" class="text-center text-muted py-4">Loading reports...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="blotterDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Blotter Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="blotterDetailBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const LIST_API = '<?php echo API_URL; ?>blotter/list.php';
  const GET_API = '<?php echo API_URL; ?>blotter/get.php?id=';
  const rowsEl = document.getElementById('blotterRows');
  const detailBody = document.getElementById('blotterDetailBody');

  const statusClass = {
    pending: 'bg-warning text-dark',
    investigation: 'bg-primary',
    mediation: 'bg-info text-dark',
    settled: 'bg-success',
    dismissed: 'bg-secondary'
  };

  function labelize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  function formatDate(value) {
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleString();
  }

  function decodeWitnesses(raw) {
    if (!raw) return '-';
    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length) {
        return arr.map(v => '<li>' + String(v).replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s])) + '</li>').join('');
      }
    } catch (e) {}
    return String(raw).replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]));
  }

  function loadList() {
    fetch(LIST_API, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        const records = d?.data?.records || [];
        if (!records.length) {
          rowsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No incident reports filed yet.</td></tr>';
          return;
        }

        rowsEl.innerHTML = records.map(r => {
          const cls = statusClass[r.status] || 'bg-light text-dark';
          return '<tr>' +
            '<td><strong>' + (r.reference_no || '-') + '</strong></td>' +
            '<td>' + labelize(r.incident_type) + '</td>' +
            '<td>' + (r.incident_location || '-') + '</td>' +
            '<td>' + formatDate(r.incident_datetime) + '</td>' +
            '<td><span class="badge ' + cls + '">' + labelize(r.status) + '</span></td>' +
            '<td class="text-end"><button class="btn btn-sm btn-outline-primary" data-id="' + r.id + '">View Details</button></td>' +
          '</tr>';
        }).join('');
      })
      .catch(() => {
        rowsEl.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load reports.</td></tr>';
      });
  }

  rowsEl.addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-id');

    fetch(GET_API + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (!d.success || !d?.data?.record) {
          alert(d.message || 'Unable to load details.');
          return;
        }
        const r = d.data.record;
        const witnesses = decodeWitnesses(r.witnesses);
        const respondentDisplay = r.respondent_name_raw || r.respondent_name || '-';
        const evidenceHtml = r.evidence_path
          ? '<a href="<?php echo BASE_URL; ?>' + String(r.evidence_path).replace(/^\/+/, '') + '" target="_blank" rel="noopener">View Uploaded Evidence</a>'
          : '-';

        detailBody.innerHTML =
          '<div class="row g-3">' +
            '<div class="col-md-6"><strong>Reference #:</strong><br>' + (r.reference_no || '-') + '</div>' +
            '<div class="col-md-6"><strong>Status:</strong><br>' + labelize(r.status) + '</div>' +
            '<div class="col-md-6"><strong>Incident Type:</strong><br>' + labelize(r.incident_type) + '</div>' +
            '<div class="col-md-6"><strong>Incident Date:</strong><br>' + formatDate(r.incident_datetime) + '</div>' +
            '<div class="col-12"><strong>Location:</strong><br>' + (r.incident_location || '-') + '</div>' +
            '<div class="col-md-6"><strong>Respondent:</strong><br>' + respondentDisplay + '</div>' +
            '<div class="col-md-6"><strong>Action Requested:</strong><br>' + (r.action_requested || '-') + '</div>' +
            '<div class="col-12"><strong>Witnesses:</strong><br>' + (witnesses.startsWith('<li>') ? '<ul class="mb-0">' + witnesses + '</ul>' : witnesses) + '</div>' +
            '<div class="col-12"><strong>Narrative:</strong><div class="p-2 bg-light border rounded mt-1" style="white-space:pre-wrap">' + (r.narrative || '-') + '</div></div>' +
            '<div class="col-12"><strong>Admin Updates:</strong><div class="p-2 bg-light border rounded mt-1" style="white-space:pre-wrap">' + (r.admin_updates || 'No updates yet.') + '</div></div>' +
            '<div class="col-12"><strong>Evidence:</strong><br>' + evidenceHtml + '</div>' +
          '</div>';

        const modal = new bootstrap.Modal(document.getElementById('blotterDetailModal'));
        modal.show();
      })
      .catch(() => alert('Unable to load details right now.'));
  });

  const params = new URLSearchParams(window.location.search);
  const submitted = params.get('submitted');
  if (submitted) {
    alert('Incident report submitted. Reference #: ' + submitted);
  }

  loadList();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
