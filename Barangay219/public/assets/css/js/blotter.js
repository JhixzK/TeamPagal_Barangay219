// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

document.addEventListener('DOMContentLoaded', function() {
    loadBlotters();
    initBlotterModal();
});

function loadBlotters() {
    fetch(window.API_URL + 'blotter.php?action=list')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('blotterTableBody');
            if (d.success) {
                tbody.innerHTML = d.data.map(b => {
                    // try to parse complainants/respondents JSON stored in DB
                    let comp = '-';
                    try {
                        const c = JSON.parse(b.complainant_name);
                        if (Array.isArray(c)) comp = c.map(x => x.name).join(', ');
                        else comp = b.complainant_name;
                    } catch (e) { comp = b.complainant_name || '-'; }

                    let resp = '-';
                    try {
                        const r = JSON.parse(b.respondent_name);
                        if (Array.isArray(r)) resp = r.map(x => x.name).join(', ');
                        else resp = b.respondent_name;
                    } catch (e) { resp = b.respondent_name || '-'; }

                        return `
                        <tr>
                            <td>${b.id}</td>
                            <td>${b.case_title}</td>
                            <td>${escapeHtml(comp)}</td>
                            <td>${formatDate(b.incident_date)}</td>
                            <td><span class="badge bg-${getStatusColor(b.status)}">${b.status}</span></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="viewBlotter(${b.id})">View</button>
                                    <button class="btn btn-sm btn-primary" onclick="editBlotter(${b.id})">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteBlotter(${b.id})">Delete</button>
                                </div>
                            </td>
                        </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No blotter records found or access denied</td></tr>';
                console.warn('Blotter API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading blotters:', err);
            const tbody = document.getElementById('blotterTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading blotters</td></tr>';
        });
}

function viewBlotter(id) {
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // show a simple modal-like alert for now
                const info = d.data;
                let comp = info.complainant_name;
                try { const c = JSON.parse(info.complainant_name); comp = Array.isArray(c) ? c.map(x=>x.name).join(', ') : comp; } catch(e){}
                let resp = info.respondent_name;
                try { const r = JSON.parse(info.respondent_name); resp = Array.isArray(r) ? r.map(x=>x.name).join(', ') : resp; } catch(e){}
                alert(`Case: ${info.case_title}\nComplainant(s): ${comp}\nRespondent(s): ${resp}\n\nDescription:\n${info.description}`);
            }
        });
}

function editBlotter(id) {
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert('Record not found'); return; }
            const info = d.data;
            // set modal into edit mode
            document.getElementById('blotterModalTitle').textContent = 'Edit Blotter Case';
            document.querySelector('#blotterForm .btn-primary').textContent = 'Update Blotter Case';
            document.getElementById('blotterId').value = info.id;
            document.getElementById('case_title').value = info.case_title || '';
            document.getElementById('incident_date').value = info.incident_date || '';
            document.getElementById('incident_location').value = info.incident_location || '';
            document.getElementById('description').value = info.description || '';
            document.getElementById('status').value = info.status || 'pending';
            document.getElementById('settlement_date').value = info.settlement_date || '';

            // populate complainants/respondents
            document.getElementById('complainantsContainer').innerHTML = '';
            document.getElementById('respondentsContainer').innerHTML = '';

            try {
                const comps = JSON.parse(info.complainant_name);
                if (Array.isArray(comps)) {
                    comps.forEach(c => addComplainantRow({ name: c.name || '', barangay: c.barangay || '', contact: c.contact || '' }));
                } else {
                    addComplainantRow({ name: info.complainant_name });
                }
            } catch (e) {
                if (info.complainant_name) addComplainantRow({ name: info.complainant_name });
            }
            try {
                const resps = JSON.parse(info.respondent_name);
                if (Array.isArray(resps)) {
                    resps.forEach(r => addRespondentRow({ name: r.name || '', barangay: r.barangay || '', contact: r.contact || '' }));
                } else {
                    addRespondentRow({ name: info.respondent_name });
                }
            } catch (e) {
                if (info.respondent_name) addRespondentRow({ name: info.respondent_name });
            }

            // show modal
            const modalEl = document.getElementById('blotterModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        })
        .catch(err => { console.error(err); alert('Error fetching record'); });
}

function getStatusColor(status) {
    const colors = { 'pending': 'warning', 'resolved': 'success', 'settled': 'info' };
    return colors[status] || 'secondary';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }

// --- Blotter modal / create handling ---
function initBlotterModal() {
    // initial one row each
    addComplainantRow();
    addRespondentRow();

    document.getElementById('addComplainantBtn').addEventListener('click', addComplainantRow);
    document.getElementById('addRespondentBtn').addEventListener('click', addRespondentRow);
    document.getElementById('blotterForm').addEventListener('submit', submitBlotterForm);
}

function resetBlotterForm() {
    document.getElementById('blotterForm').reset();
    document.getElementById('blotterId').value = '';
    document.getElementById('complainantsContainer').innerHTML = '';
    document.getElementById('respondentsContainer').innerHTML = '';
    addComplainantRow();
    addRespondentRow();
    // reset modal title and button text
    const titleEl = document.getElementById('blotterModalTitle');
    if (titleEl) titleEl.textContent = 'Add New Blotter Case';
    const submitBtn = document.querySelector('#blotterForm .btn-primary');
    if (submitBtn) submitBtn.textContent = 'Save Blotter Case';
}

function deleteBlotter(id) {
    if (!confirm('Are you sure you want to delete this blotter case? This action cannot be undone.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch(window.API_URL + 'blotter.php?action=delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadBlotters();
                alert('Blotter case deleted');
            } else {
                alert('Delete failed: ' + d.message);
            }
        })
        .catch(err => { console.error(err); alert('Error deleting blotter case'); });
}

function addComplainantRow(data = {}) {
    const id = 'comp_' + Date.now() + Math.floor(Math.random()*1000);
    const container = document.getElementById('complainantsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-2 party-row';
    div.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" placeholder="Complainant Name" data-name name="complainant_name">
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control" placeholder="Barangay" data-barangay name="complainant_barangay">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" placeholder="Contact Number" data-contact name="complainant_contact">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm remove-party">&times;</button>
        </div>
    `;
    // populate
    if (data.name) div.querySelector('[data-name]').value = data.name;
    if (data.barangay) div.querySelector('[data-barangay]').value = data.barangay;
    if (data.contact) div.querySelector('[data-contact]').value = data.contact;

    div.querySelector('.remove-party').addEventListener('click', () => div.remove());
    container.appendChild(div);
}

function addRespondentRow(data = {}) {
    const container = document.getElementById('respondentsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-2 party-row';
    div.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" placeholder="Respondent Name" data-name name="respondent_name">
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control" placeholder="Barangay" data-barangay name="respondent_barangay">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" placeholder="Contact Number" data-contact name="respondent_contact">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm remove-party">&times;</button>
        </div>
    `;
    if (data.name) div.querySelector('[data-name]').value = data.name;
    if (data.barangay) div.querySelector('[data-barangay]').value = data.barangay;
    if (data.contact) div.querySelector('[data-contact]').value = data.contact;
    div.querySelector('.remove-party').addEventListener('click', () => div.remove());
    container.appendChild(div);
}

function submitBlotterForm(e) {
    e.preventDefault();
    const form = document.getElementById('blotterForm');
    const payload = new FormData();
    payload.append('case_title', document.getElementById('case_title').value || '');
    payload.append('incident_date', document.getElementById('incident_date').value || '');
    payload.append('incident_location', document.getElementById('incident_location').value || '');
    payload.append('description', document.getElementById('description').value || '');
    payload.append('status', document.getElementById('status').value || 'pending');
    payload.append('settlement_date', document.getElementById('settlement_date').value || '');

    // collect complainants
    const comps = [];
    document.querySelectorAll('#complainantsContainer .party-row').forEach(row => {
        const name = row.querySelector('[data-name]').value.trim();
        const barangay = row.querySelector('[data-barangay]').value.trim();
        const contact = row.querySelector('[data-contact]').value.trim();
        if (name) comps.push({ name, barangay, contact });
    });
    const resps = [];
    document.querySelectorAll('#respondentsContainer .party-row').forEach(row => {
        const name = row.querySelector('[data-name]').value.trim();
        const barangay = row.querySelector('[data-barangay]').value.trim();
        const contact = row.querySelector('[data-contact]').value.trim();
        if (name) resps.push({ name, barangay, contact });
    });

    payload.append('complainants', JSON.stringify(comps));
    payload.append('respondents', JSON.stringify(resps));

    // Determine if this is create or update
    const id = document.getElementById('blotterId').value;
    let action = 'create';
    if (id) { action = 'update'; payload.append('id', id); }

    fetch(window.API_URL + `blotter.php?action=${action}`, { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('blotterModal'));
                if (modal) modal.hide();
                resetBlotterForm();
                loadBlotters();
                alert(id ? 'Blotter case updated successfully' : 'Blotter case created successfully');
            } else {
                alert('Error: ' + d.message);
            }
        })
        .catch(err => { console.error(err); alert('Error creating blotter'); });
}

function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/\"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
