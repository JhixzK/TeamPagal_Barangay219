<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$canAdminProcess = canPerformModulePermission('certificates', 'can_edit') || canPerformModulePermission('applications', 'can_edit');
$isResident = hasRole(ROLE_RESIDENT);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Process Module</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
        .section { border: 1px solid #ddd; border-radius: 8px; padding: 14px; margin-bottom: 18px; }
        .row { display: grid; grid-template-columns: 170px 1fr; gap: 8px; margin-bottom: 8px; align-items: center; }
        input, select, textarea, button { padding: 8px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f6f6f6; text-align: left; }
        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .small { font-size: 12px; color: #555; }
        .msg { margin-top: 8px; font-size: 13px; }
        .ok { color: #0b7a32; }
        .err { color: #b3261e; }
    </style>
</head>
<body>
    <h2>Barangay Certificate Process</h2>
    <p class="small">Process only module: submit, review, approve + edit, reject with reason, ready for pickup, and release.</p>

    <?php if ($isResident): ?>
    <div class="section">
        <h3>Resident Request Form</h3>
        <div class="row"><label for="r_type">Certificate Type</label><select id="r_type">
            <option value="barangay_clearance">Barangay Clearance</option>
            <option value="certificate_residency">Certificate of Residency</option>
            <option value="certificate_indigency">Certificate of Indigency</option>
            <option value="certificate_good_moral">Certificate of Good Moral</option>
            <option value="transfer_request">Transfer Request</option>
        </select></div>
        <div class="row"><label for="r_name">Name</label><input id="r_name" type="text"></div>
        <div class="row"><label for="r_age">Age</label><input id="r_age" type="number" min="1"></div>
        <div class="row"><label for="r_address">Address</label><textarea id="r_address" rows="2"></textarea></div>
        <div class="row"><label for="r_purpose">Purpose</label><select id="r_purpose">
            <option value="Application for Employment">Application for Employment</option>
            <option value="Hospital Purpose">Hospital Purpose</option>
            <option value="Medical Purpose">Medical Purpose</option>
            <option value="Bank Transaction">Bank Transaction</option>
            <option value="School Admission/Requirement">School Admission/Requirement</option>
            <option value="Others">Others</option>
        </select></div>
        <div class="row"><label for="r_purpose_other">Purpose (Others)</label><input id="r_purpose_other" type="text" placeholder="Required if Others"></div>
        <button id="btnResidentSubmit" type="button">Submit Request</button>
        <div id="residentMsg" class="msg"></div>
    </div>
    <?php endif; ?>

    <?php if ($canAdminProcess): ?>
    <div class="section">
        <h3>Admin Pending Requests</h3>
        <button id="btnReloadPending" type="button">Reload Pending</button>
        <table>
            <thead>
                <tr>
                    <th>Application Ref</th>
                    <th>Resident</th>
                    <th>Type</th>
                    <th>Editable Details</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="pendingBody"><tr><td colspan="5">Loading...</td></tr></tbody>
        </table>
    </div>

    <div class="section">
        <h3>Ready For Pickup</h3>
        <button id="btnReloadReady" type="button">Reload Ready</button>
        <table>
            <thead>
                <tr>
                    <th>Application Ref</th>
                    <th>Resident</th>
                    <th>Type</th>
                    <th>Purpose</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="readyBody"><tr><td colspan="5">Loading...</td></tr></tbody>
        </table>
    </div>
    <?php endif; ?>

<script>
const API = '<?php echo API_URL; ?>certificates.php';

function esc(v) {
    return String(v || '').replace(/[&<>"']/g, s => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[s]));
}

async function postForm(data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    const res = await fetch(API, { method: 'POST', body: fd });
    return res.json();
}

<?php if ($isResident): ?>
document.getElementById('btnResidentSubmit').addEventListener('click', async () => {
    const payload = {
        action: 'resident_submit',
        certificate_type: document.getElementById('r_type').value,
        name: document.getElementById('r_name').value.trim(),
        age: document.getElementById('r_age').value,
        address: document.getElementById('r_address').value.trim(),
        purpose: document.getElementById('r_purpose').value,
        purpose_other: document.getElementById('r_purpose_other').value.trim()
    };
    const out = document.getElementById('residentMsg');
    try {
        const d = await postForm(payload);
        out.className = 'msg ' + (d.success ? 'ok' : 'err');
        out.textContent = d.message || (d.success ? 'Submitted' : 'Failed');
    } catch (e) {
        out.className = 'msg err';
        out.textContent = 'Request failed.';
    }
});
<?php endif; ?>

<?php if ($canAdminProcess): ?>
async function loadPending() {
    const tbody = document.getElementById('pendingBody');
    tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
    try {
        const r = await fetch(API + '?action=list_pending');
        const d = await r.json();
        const rows = d?.data?.certificates || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5">No pending requests.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const id = Number(row.id);
            const appRef = esc(row.application_ref || ('APP-' + id));
            const resident = esc(row.resident_name || '-');
            const type = esc((row.certificate_type || '').replaceAll('_', ' '));
            const name = esc(row.cert_name || row.resident_name || '');
            const age = esc(row.cert_age || '');
            const addr = esc(row.cert_address || row.address || '');
            const purpose = esc(row.purpose_option || row.cert_purpose || row.purpose || '');
            const purposeOther = esc(row.purpose_other || '');

            return `
<tr>
    <td>${appRef}</td>
    <td>${resident}</td>
    <td>${type}</td>
    <td>
        <div class="row"><label>Name</label><input id="n_${id}" value="${name}"></div>
        <div class="row"><label>Age</label><input id="a_${id}" type="number" min="1" value="${age}"></div>
        <div class="row"><label>Address</label><textarea id="ad_${id}" rows="2">${addr}</textarea></div>
        <div class="row"><label>Purpose</label><select id="p_${id}">
            <option ${purpose==='Application for Employment'?'selected':''}>Application for Employment</option>
            <option ${purpose==='Hospital Purpose'?'selected':''}>Hospital Purpose</option>
            <option ${purpose==='Medical Purpose'?'selected':''}>Medical Purpose</option>
            <option ${purpose==='Bank Transaction'?'selected':''}>Bank Transaction</option>
            <option ${purpose==='School Admission/Requirement'?'selected':''}>School Admission/Requirement</option>
            <option ${purpose==='Others'?'selected':''}>Others</option>
        </select></div>
        <div class="row"><label>Purpose (Others)</label><input id="po_${id}" value="${purposeOther}"></div>
    </td>
    <td>
        <div class="actions">
            <button type="button" onclick="moveUnderReview(${id})">Review</button>
            <button type="button" onclick="approveReady(${id})">Approve + Ready</button>
            <button type="button" onclick="rejectReq(${id})">Reject</button>
        </div>
        <div id="m_${id}" class="msg"></div>
    </td>
</tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5">Failed to load pending requests.</td></tr>';
    }
}

async function loadReady() {
    const tbody = document.getElementById('readyBody');
    tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
    try {
        const r = await fetch(API + '?action=list&status=ready_for_pickup');
        const d = await r.json();
        const rows = d?.data?.certificates || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5">No ready requests.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => `
<tr>
    <td>${esc(row.application_ref || ('APP-' + row.id))}</td>
    <td>${esc(row.resident_name || '-')}</td>
    <td>${esc((row.certificate_type || '').replaceAll('_', ' '))}</td>
    <td>${esc(row.cert_purpose || row.purpose || '-')}</td>
    <td><button type="button" onclick="markReleased(${Number(row.id)})">Mark Released</button><div id="mr_${Number(row.id)}" class="msg"></div></td>
</tr>`).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5">Failed to load ready requests.</td></tr>';
    }
}

function setMsg(id, ok, text, prefix='m_') {
    const el = document.getElementById(prefix + id);
    if (!el) return;
    el.className = 'msg ' + (ok ? 'ok' : 'err');
    el.textContent = text;
}

window.moveUnderReview = async function(id) {
    const d = await postForm({ action:'update', id, status:'approved' });
    setMsg(id, !!d.success, d.message || (d.success ? 'Updated' : 'Failed'));
    if (d.success) { loadPending(); }
};

window.approveReady = async function(id) {
    const payload = {
        action: 'approve_ready',
        id,
        cert_name: (document.getElementById('n_' + id)?.value || '').trim(),
        cert_age: (document.getElementById('a_' + id)?.value || '').trim(),
        cert_address: (document.getElementById('ad_' + id)?.value || '').trim(),
        purpose: (document.getElementById('p_' + id)?.value || '').trim(),
        purpose_other: (document.getElementById('po_' + id)?.value || '').trim()
    };
    const d = await postForm(payload);
    setMsg(id, !!d.success, d.message || (d.success ? 'Approved' : 'Failed'));
    if (d.success) { loadPending(); loadReady(); }
};

window.rejectReq = async function(id) {
    const reason = window.prompt('Enter rejection reason:');
    if (!reason) return;
    const d = await postForm({ action:'reject', id, reason });
    setMsg(id, !!d.success, d.message || (d.success ? 'Rejected' : 'Failed'));
    if (d.success) { loadPending(); }
};

window.markReleased = async function(id) {
    const d = await postForm({ action:'release', id });
    setMsg(id, !!d.success, d.message || (d.success ? 'Released' : 'Failed'), 'mr_');
    if (d.success) { loadReady(); }
};

document.getElementById('btnReloadPending').addEventListener('click', loadPending);
document.getElementById('btnReloadReady').addEventListener('click', loadReady);
loadPending();
loadReady();
<?php endif; ?>
</script>
</body>
</html>
