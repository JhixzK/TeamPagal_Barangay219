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

    <section class="card table-card">
      <div class="table-wrap">
        <table class="requests-table blotter-table">
          <thead>
            <tr>
              <th>Reference #</th>
              <th>Incident Type</th>
              <th>Date Reported</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="blotterRows">
            <tr><td colspan="4" class="text-center text-muted py-4">Loading reports...</td></tr>
          </tbody>
        </table>
      </div>
    </section>
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

<style>
.resident-my-blotters-page .dashboard-hero {
  border-radius: 16px;
  background: radial-gradient(circle at 0% 0%, rgba(147, 197, 253, 0.24), transparent 36%), linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
  border: 1px solid rgba(59, 130, 246, 0.2) !important;
  box-shadow: 0 16px 34px -24px rgba(37, 99, 235, 0.45);
}

.resident-my-blotters-page .table-card {
  padding: 14px;
  border-radius: 14px;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.18);
}

.resident-my-blotters-page .table-wrap {
  overflow-x: auto;
}

.resident-my-blotters-page .requests-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  min-width: 720px;
}

.resident-my-blotters-page .requests-table th,
.resident-my-blotters-page .requests-table td {
  text-align: left;
  padding: 10px 8px;
  border-bottom: 1px solid #e2e9f4;
  vertical-align: middle;
}

.resident-my-blotters-page .requests-table th {
  font-size: 12px;
  color: #637790;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.resident-my-blotters-page .blotter-table {
  min-width: 720px;
}

.resident-my-blotters-page .blotter-row {
  cursor: pointer;
}

.resident-my-blotters-page .blotter-row:hover {
  background: #f8fafc;
}

.resident-my-blotters-page .status-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.resident-my-blotters-page .status-pill.pending {
  background: #fff4da;
  color: #a86500;
}

.resident-my-blotters-page .status-pill.processing {
  background: #dbeafe;
  color: #1d4ed8;
}

.resident-my-blotters-page .status-pill.settled {
  background: #dcf7ec;
  color: #127852;
}

.resident-my-blotters-page .status-pill.dismissed {
  background: #e5e7eb;
  color: #374151;
}

@media (max-width: 768px) {
  .resident-my-blotters-page .dashboard-hero .card-body {
    padding: 1rem;
  }
}
</style>

<script>
(function () {
  const LIST_API = '<?php echo API_URL; ?>blotter/list.php';
  const GET_API = '<?php echo API_URL; ?>blotter/get.php?id=';
  const rowsEl = document.getElementById('blotterRows');
  const detailBody = document.getElementById('blotterDetailBody');
  const detailModalEl = document.getElementById('blotterDetailModal');
  const detailModal = (window.bootstrap && detailModalEl) ? bootstrap.Modal.getOrCreateInstance(detailModalEl) : null;

  function labelize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  function formatDate(value) {
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleString();
  }

  function formatReportedDate(value) {
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
  }

  function statusPillClass(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'pending') return 'pending';
    if (s === 'investigation' || s === 'mediation') return 'processing';
    if (s === 'settled') return 'settled';
    if (s === 'dismissed') return 'dismissed';
    return 'dismissed';
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

  function decodeRespondents(rawJson, rawName) {
    const fallback = String(rawName || '').trim();
    if (!rawJson) {
      return fallback || '-';
    }

    try {
      const parsed = JSON.parse(rawJson);
      if (Array.isArray(parsed) && parsed.length) {
        const names = parsed
          .map(function (item) { return String(item?.name || '').trim(); })
          .filter(Boolean)
          .map(function (name) { return name.replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s])); });

        if (names.length) {
          return '<ul class="mb-0">' + names.map(function (name) { return '<li>' + name + '</li>'; }).join('') + '</ul>';
        }
      }
    } catch (e) {}

    return (fallback || '-').replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]));
  }

  function incidentTypeDisplay(type, detail) {
    const base = String(type || '').toLowerCase();
    const detailText = String(detail || '').trim();
    if (base === 'other' && detailText !== '') {
      return 'Other (' + detailText + ')';
    }
    return labelize(type);
  }

  function loadList() {
    fetch(LIST_API, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        const records = d?.data?.records || [];
        if (!records.length) {
          rowsEl.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No incident reports filed yet.</td></tr>';
          return;
        }

        rowsEl.innerHTML = records.map(r => {
          const cls = statusPillClass(r.status);
          return '<tr class="blotter-row" data-id="' + r.id + '">' +
            '<td><strong>' + (r.reference_no || '-') + '</strong></td>' +
            '<td>' + incidentTypeDisplay(r.incident_type, r.incident_type_detail) + '</td>' +
            '<td>' + formatReportedDate(r.created_at) + '</td>' +
            '<td><span class="status-pill ' + cls + '">' + labelize(r.status) + '</span></td>' +
          '</tr>';
        }).join('');
      })
      .catch(() => {
        rowsEl.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Failed to load reports.</td></tr>';
      });
  }

  rowsEl.addEventListener('click', function (e) {
    const row = e.target.closest('tr[data-id]');
    if (!row) return;
    const id = row.getAttribute('data-id');

    fetch(GET_API + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (!d.success || !d?.data?.record) {
          alert(d.message || 'Unable to load details.');
          return;
        }
        const r = d.data.record;
        const witnesses = decodeWitnesses(r.witnesses);
        const respondentDisplay = decodeRespondents(r.respondent_name, r.respondent_name_raw);
        const evidenceHtml = r.evidence_path
          ? '<a href="<?php echo BASE_URL; ?>' + String(r.evidence_path).replace(/^\/+/, '') + '" target="_blank" rel="noopener">View Uploaded Evidence</a>'
          : '-';
        const resolutionHtml = r.resolution_file
          ? '<a href="<?php echo BASE_URL; ?>' + String(r.resolution_file).replace(/^\/+/, '') + '" target="_blank" rel="noopener">View Signed Resolution</a>'
          : '-';

        detailBody.innerHTML =
          '<div class="row g-3">' +
            '<div class="col-md-6"><strong>Reference #:</strong><br>' + (r.reference_no || '-') + '</div>' +
            '<div class="col-md-6"><strong>Status:</strong><br>' + labelize(r.status) + '</div>' +
            '<div class="col-md-6"><strong>Incident Type:</strong><br>' + incidentTypeDisplay(r.incident_type, r.incident_type_detail) + '</div>' +
            '<div class="col-md-6"><strong>Incident Date:</strong><br>' + formatDate(r.incident_datetime) + '</div>' +
            '<div class="col-12"><strong>Location:</strong><br>' + (r.incident_location || '-') + '</div>' +
            '<div class="col-md-6"><strong>Respondents:</strong><br>' + respondentDisplay + '</div>' +
            '<div class="col-md-6"><strong>Action Requested:</strong><br>' + (r.action_requested || '-') + '</div>' +
            '<div class="col-md-6"><strong>Hearing Date:</strong><br>' + (function(){
              try {
                const hs = JSON.parse(r.hearings || '[]');
                if (Array.isArray(hs) && hs.length && hs[0].date) return formatDate(hs[0].date);
              } catch (e) {}
              return formatDate(r.hearing_date);
            })() + '</div>' +
            '<div class="col-md-6"><strong>Settlement Date:</strong><br>' + formatDate(r.settlement_date) + '</div>' +
            '<div class="col-12"><strong>Witnesses:</strong><br>' + (witnesses.startsWith('<li>') ? '<ul class="mb-0">' + witnesses + '</ul>' : witnesses) + '</div>' +
            '<div class="col-12"><strong>Narrative:</strong><div class="p-2 bg-light border rounded mt-1" style="white-space:pre-wrap">' + (r.narrative || '-') + '</div></div>' +
            '<div class="col-12"><strong>Dismissal Reason:</strong><div class="p-2 bg-light border rounded mt-1" style="white-space:pre-wrap">' + (r.dismissal_reason || '-') + '</div></div>' +
            '<div class="col-12"><strong>Admin Remarks:</strong><div class="p-2 bg-light border rounded mt-1" style="white-space:pre-wrap">' + (r.admin_updates || 'No updates yet.') + '</div></div>' +
            '<div class="col-12"><strong>Evidence:</strong><br>' + evidenceHtml + '</div>' +
            '<div class="col-12"><strong>Resolution File:</strong><br>' + resolutionHtml + '</div>' +
          '</div>';

        if (detailModal) {
          detailModal.show();
        }
      })
      .catch(() => alert('Unable to load details right now.'));
  });

  if (detailModalEl) {
    detailModalEl.addEventListener('hidden.bs.modal', function () {
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.querySelectorAll('.modal-backdrop').forEach(function (el) {
        el.remove();
      });
    });
  }

  const params = new URLSearchParams(window.location.search);
  const submitted = params.get('submitted');
  if (submitted) {
    alert('Incident report submitted. Reference #: ' + submitted);
  }

  loadList();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
