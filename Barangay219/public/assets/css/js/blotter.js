// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

// Phone number input validation - only allow + and digits, max 13 characters, always starts with +63
function validatePhoneInput(input) {
    input.addEventListener('input', function() {
        // Always ensure it starts with +63
        if (!this.value.startsWith('+63')) {
            this.value = '+63';
            return;
        }
        // Remove any non-digit and non-plus characters
        let value = this.value.replace(/[^\d+]/g, '');
        // Limit to 13 characters (+63 + 10 digits)
        value = value.substring(0, 13);
        this.value = value;
    });
    
    // Add blur event to ensure +63 is always present
    input.addEventListener('blur', function() {
        if (this.value.trim() === '' || this.value === '+63') {
            this.value = '+63';
        } else if (!this.value.startsWith('+63')) {
            this.value = '+63';
        }
    });
}

// Name input validation - only allow letters and spaces, no numbers
function validateNameInput(input) {
    input.addEventListener('input', function() {
        // Remove any non-letter and non-space characters
        let value = this.value.replace(/[^a-zA-Z\s]/g, '');
        this.value = value;
    });
}

// Number-only validation (allow digits only)
function validateNumberInput(input) {
    input.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        this.value = value;
    });
}

const BLOTTER_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('blotters', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('blotters', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('blotters', 'can_delete') : true
};

let blotterFilters = { q: '', status: '', from: '', to: '' };
let searchDebounceTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    loadBlotters();
    initBlotterModal();
    applyBlotterPermissions();
    
    // Add search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.trim();
            blotterFilters.q = searchTerm;
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => loadBlotters(), 250);
        });
    }
});

function applyBlotterPermissions() {
    if (!BLOTTER_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
    }
}

function loadBlotters() {
    const params = new URLSearchParams({ action: 'list' });
    if (blotterFilters.q) params.append('q', blotterFilters.q);
    if (blotterFilters.status) params.append('status', blotterFilters.status);
    if (blotterFilters.from) params.append('from', blotterFilters.from);
    if (blotterFilters.to) params.append('to', blotterFilters.to);

    fetch(window.API_URL + 'blotter.php?' + params.toString())
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
                                    ${BLOTTER_PERMS.canEdit ? `<button class="btn btn-sm btn-primary" onclick="editBlotter(${b.id})">Edit</button>` : ''}
                                    ${BLOTTER_PERMS.canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteBlotter(${b.id})">Delete</button>` : ''}
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

function applyFilters() {
    blotterFilters.status = document.getElementById('filterStatus')?.value || '';
    blotterFilters.from = document.getElementById('filterFrom')?.value || '';
    blotterFilters.to = document.getElementById('filterTo')?.value || '';
    loadBlotters();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function viewBlotter(id) {
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const info = d.data;
                
                // Populate Case Information
                document.getElementById('viewCaseTitle').textContent = info.case_title || '-';
                document.getElementById('viewIncidentDate').textContent = formatDate(info.incident_date) || '-';
                document.getElementById('viewIncidentLocation').textContent = info.incident_location || '-';
                document.getElementById('viewDescription').textContent = info.description || '-';
                
                // Populate Complainants
                let complainantsHTML = '';
                try {
                    const comps = JSON.parse(info.complainant_name);
                    if (Array.isArray(comps)) {
                        complainantsHTML = comps.map((c, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Complainant ${idx + 1}:</strong> ${c.name || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${c.address || '-'}</p>
                                    <p class="mb-1"><strong>Barangay:</strong> ${c.barangay || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${c.contact || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                    }
                } catch(e) {
                    complainantsHTML = `<p>${info.complainant_name || '-'}</p>`;
                }
                document.getElementById('viewComplainantsInfo').innerHTML = complainantsHTML || '<p>-</p>';
                
                // Populate Respondents
                let respondentsHTML = '';
                try {
                    const resps = JSON.parse(info.respondent_name);
                    if (Array.isArray(resps)) {
                        respondentsHTML = resps.map((r, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Respondent ${idx + 1}:</strong> ${r.name || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${r.address || '-'}</p>
                                    <p class="mb-1"><strong>Barangay:</strong> ${r.barangay || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${r.contact || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                    }
                } catch(e) {
                    respondentsHTML = `<p>${info.respondent_name || '-'}</p>`;
                }
                document.getElementById('viewRespondentsInfo').innerHTML = respondentsHTML || '<p>-</p>';
                
                // Populate Case Status
                document.getElementById('viewStatus').textContent = info.status || '-';
                document.getElementById('viewSettlementDate').textContent = formatDate(info.settlement_date) || '-';

                // Populate Hearings
                let hearingsHTML = '';
                if (Array.isArray(info.hearings) && info.hearings.length > 0) {
                    hearingsHTML = info.hearings.map((h, idx) => {
                        const hearingDate = formatDate(h.hearing_date);
                        const nextHearingDate = formatDate(h.next_hearing_date);
                        const status = h.status ? escapeHtml(h.status) : '-';
                        const outcome = h.outcome ? escapeHtml(h.outcome) : '-';
                        const notes = h.notes ? escapeHtml(h.notes) : '-';
                        return `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Hearing ${idx + 1} Date:</strong> ${hearingDate}</p>
                                    <p class="mb-1"><strong>Status:</strong> ${status}</p>
                                    <p class="mb-1"><strong>Outcome:</strong> ${outcome}</p>
                                    <p class="mb-1"><strong>Notes:</strong> ${notes}</p>
                                    <p class="mb-0"><strong>Next Hearing:</strong> ${nextHearingDate}</p>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    hearingsHTML = '<p>-</p>';
                }
                document.getElementById('viewHearingsInfo').innerHTML = hearingsHTML;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('viewBlotterModal'));
                modal.show();
            }
        })
        .catch(err => { console.error(err); alert('Error loading blotter details'); });
}

function editBlotter(id) {
    if (!BLOTTER_PERMS.canEdit) { alert('Access denied'); return; }
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
            document.getElementById('hearingsContainer').innerHTML = '';

            try {
                const comps = JSON.parse(info.complainant_name);
                if (Array.isArray(comps)) {
                    comps.forEach(c => addComplainantRow({ name: c.name || '', address: c.address || '', barangay: c.barangay || '', contact: c.contact || '' }));
                } else {
                    addComplainantRow({ name: info.complainant_name });
                }
            } catch (e) {
                if (info.complainant_name) addComplainantRow({ name: info.complainant_name });
            }
            try {
                const resps = JSON.parse(info.respondent_name);
                if (Array.isArray(resps)) {
                    resps.forEach(r => addRespondentRow({ name: r.name || '', address: r.address || '', barangay: r.barangay || '', contact: r.contact || '' }));
                } else {
                    addRespondentRow({ name: info.respondent_name });
                }
            } catch (e) {
                if (info.respondent_name) addRespondentRow({ name: info.respondent_name });
            }

            if (Array.isArray(info.hearings) && info.hearings.length > 0) {
                info.hearings.forEach(h => addHearingRow({
                    hearing_date: h.hearing_date || '',
                    status: h.status || 'scheduled',
                    outcome: h.outcome || '',
                    notes: h.notes || '',
                    next_hearing_date: h.next_hearing_date || ''
                }));
            } else {
                addHearingRow();
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
    addHearingRow();

    // Add name validation to case title
    const caseTitleInput = document.getElementById('case_title');
    validateNameInput(caseTitleInput);

    document.getElementById('addComplainantBtn').addEventListener('click', addComplainantRow);
    document.getElementById('addRespondentBtn').addEventListener('click', addRespondentRow);
    document.getElementById('addHearingBtn').addEventListener('click', addHearingRow);
    document.getElementById('blotterForm').addEventListener('submit', submitBlotterForm);
}

function resetBlotterForm() {
    document.getElementById('blotterForm').reset();
    document.getElementById('blotterId').value = '';
    document.getElementById('complainantsContainer').innerHTML = '';
    document.getElementById('respondentsContainer').innerHTML = '';
    document.getElementById('hearingsContainer').innerHTML = '';
    addComplainantRow();
    addRespondentRow();
    addHearingRow();
    // reset modal title and button text
    const titleEl = document.getElementById('blotterModalTitle');
    if (titleEl) titleEl.textContent = 'Add New Blotter Case';
    const submitBtn = document.querySelector('#blotterForm .btn-primary');
    if (submitBtn) submitBtn.textContent = 'Save Blotter Case';
    
    // Reapply name validation to case title
    const caseTitleInput = document.getElementById('case_title');
    validateNameInput(caseTitleInput);
}

function deleteBlotter(id) {
    if (!BLOTTER_PERMS.canDelete) { alert('Access denied'); return; }
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
    div.className = 'row mb-3 g-2 party-row';
    div.innerHTML = `
        <div class="col-12 col-md-4">
            <label class="form-label">Complainant Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Full name" data-name name="complainant_name" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Address" data-address name="complainant_address" required>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Barangay <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Barangay" data-barangay name="complainant_barangay" required>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Contact Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="+63xxxxxxxxxx" data-contact name="complainant_contact" maxlength="13" pattern="\+63\d{10}" value="+63" required>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-party" style="width:100%;">&times;</button>
        </div>
    `;
    // populate
    if (data.name) div.querySelector('[data-name]').value = data.name;
    if (data.address) div.querySelector('[data-address]').value = data.address;
    if (data.barangay) div.querySelector('[data-barangay]').value = data.barangay;
    if (data.contact) div.querySelector('[data-contact]').value = data.contact;

    // Add name input validation to all name fields
    const nameInput = div.querySelector('[data-name]');
    validateNameInput(nameInput);
    
    const barangayInput = div.querySelector('[data-barangay]');
    validateNumberInput(barangayInput);

    // Add phone input validation
    const contactInput = div.querySelector('[data-contact]');
    validatePhoneInput(contactInput);

    div.querySelector('.remove-party').addEventListener('click', () => div.remove());
    container.appendChild(div);
}

function addRespondentRow(data = {}) {
    const container = document.getElementById('respondentsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-3 g-2 party-row';
    div.innerHTML = `
        <div class="col-12 col-md-4">
            <label class="form-label">Respondent Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Full name" data-name name="respondent_name" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Address" data-address name="respondent_address" required>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Barangay <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Barangay" data-barangay name="respondent_barangay" required>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Contact Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="+63xxxxxxxxxx" data-contact name="respondent_contact" maxlength="13" pattern="\+63\d{10}" value="+63" required>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-party" style="width:100%;">&times;</button>
        </div>
    `;
    if (data.name) div.querySelector('[data-name]').value = data.name;
    if (data.address) div.querySelector('[data-address]').value = data.address;
    if (data.barangay) div.querySelector('[data-barangay]').value = data.barangay;
    if (data.contact) div.querySelector('[data-contact]').value = data.contact;

    // Add name input validation to all name fields
    const nameInput = div.querySelector('[data-name]');
    validateNameInput(nameInput);
    
    const barangayInput = div.querySelector('[data-barangay]');
    validateNumberInput(barangayInput);

    // Add phone input validation
    const contactInput = div.querySelector('[data-contact]');
    validatePhoneInput(contactInput);

    div.querySelector('.remove-party').addEventListener('click', () => div.remove());
    container.appendChild(div);
}

function addHearingRow(data = {}) {
    const container = document.getElementById('hearingsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-3 g-2 hearing-row';
    div.innerHTML = `
        <div class="col-12 col-md-3">
            <label class="form-label">Hearing Date</label>
            <input type="date" class="form-control" data-hearing-date>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Status</label>
            <select class="form-select" data-hearing-status>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="postponed">Postponed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Outcome</label>
            <input type="text" class="form-control" placeholder="Outcome" data-hearing-outcome>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Next Hearing Date</label>
            <input type="date" class="form-control" data-next-hearing-date>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-hearing" style="width:100%;">&times;</button>
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" rows="2" placeholder="Notes" data-hearing-notes></textarea>
        </div>
    `;

    if (data.hearing_date) div.querySelector('[data-hearing-date]').value = data.hearing_date;
    if (data.status) div.querySelector('[data-hearing-status]').value = data.status;
    if (data.outcome) div.querySelector('[data-hearing-outcome]').value = data.outcome;
    if (data.notes) div.querySelector('[data-hearing-notes]').value = data.notes;
    if (data.next_hearing_date) div.querySelector('[data-next-hearing-date]').value = data.next_hearing_date;

    div.querySelector('.remove-hearing').addEventListener('click', () => div.remove());
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
        const address = row.querySelector('[data-address]').value.trim();
        const barangay = row.querySelector('[data-barangay]').value.trim();
        const contact = row.querySelector('[data-contact]').value.trim();
        if (name) comps.push({ name, address, barangay, contact });
    });
    const resps = [];
    document.querySelectorAll('#respondentsContainer .party-row').forEach(row => {
        const name = row.querySelector('[data-name]').value.trim();
        const address = row.querySelector('[data-address]').value.trim();
        const barangay = row.querySelector('[data-barangay]').value.trim();
        const contact = row.querySelector('[data-contact]').value.trim();
        if (name) resps.push({ name, address, barangay, contact });
    });

    payload.append('complainants', JSON.stringify(comps));
    payload.append('respondents', JSON.stringify(resps));

    const hearings = [];
    document.querySelectorAll('#hearingsContainer .hearing-row').forEach(row => {
        const hearing_date = row.querySelector('[data-hearing-date]').value.trim();
        const status = row.querySelector('[data-hearing-status]').value;
        const outcome = row.querySelector('[data-hearing-outcome]').value.trim();
        const notes = row.querySelector('[data-hearing-notes]').value.trim();
        const next_hearing_date = row.querySelector('[data-next-hearing-date]').value.trim();
        if (hearing_date || outcome || notes || next_hearing_date) {
            hearings.push({ hearing_date, status, outcome, notes, next_hearing_date });
        }
    });
    payload.append('hearings', JSON.stringify(hearings));

    // Determine if this is create or update
    const id = document.getElementById('blotterId').value;
    let action = 'create';
    if (id) { action = 'update'; payload.append('id', id); }

    if (action === 'create' && !BLOTTER_PERMS.canCreate) {
        alert('Access denied');
        return;
    }
    if (action === 'update' && !BLOTTER_PERMS.canEdit) {
        alert('Access denied');
        return;
    }

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
