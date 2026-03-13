if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadComplaints();
    applyComplaintPermissions();
    initComplaintStatFilters();
    initComplaintFormFormatting();
});

let complaintFilters = { q: '', status: '', from: '', to: '' };

const COMPLAINT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('complaints', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('complaints', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('complaints', 'can_delete') : true
};

function applyComplaintPermissions() {
    if (!COMPLAINT_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
        const createBtn = document.getElementById('btnCreate');
        if (createBtn) createBtn.style.display = 'none';
    }
    if (!COMPLAINT_PERMS.canEdit) {
        const saveBtn = document.getElementById('btnSaveComplaint');
        if (saveBtn) saveBtn.style.display = 'none';
    }
}

function loadComplaints() {
    const params = new URLSearchParams({ action: 'list' });
    if (complaintFilters.q) params.append('q', complaintFilters.q);
    if (complaintFilters.status) params.append('status', complaintFilters.status);
    if (complaintFilters.from) params.append('from', complaintFilters.from);
    if (complaintFilters.to) params.append('to', complaintFilters.to);

    fetch(window.API_URL + 'complaints.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('complaintsTableBody');
            if (d.success) {
                const list = d.data.complaints || d.data || [];
                tbody.innerHTML = list.map(c => `
                    <tr>
                        <td class="text-center">${c.id}</td>
                        <td class="text-center">${escapeHtml(toTitleCase(c.title || c.complaint_title || '-'))}</td>
                        <td class="text-center">${escapeHtml(toTitleCase(c.complainant_name || '-'))}</td>
                        <td class="text-center">${escapeHtml(toTitleCase(c.resident_name || '-'))}</td>
                        <td class="text-center">${formatDate(c.date_submitted || c.filing_date)}</td>
                        <td class="text-center"><span class="badge bg-${getStatusColor(c.status)}">${escapeHtml(formatComplaintStatus(c.status))}</span></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary" title="View" aria-label="View" onclick="viewComplaint(${c.id})"><i class="bi bi-eye"></i></button>
                            ${COMPLAINT_PERMS.canEdit ? `<button class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit" onclick="editComplaint(${c.id})"><i class="bi bi-pencil-square"></i></button>` : ''}
                            ${COMPLAINT_PERMS.canDelete ? `<button class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete" onclick="deleteComplaint(${c.id})"><i class="bi bi-trash"></i></button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No complaints found</td></tr>';
            }
        })
        .catch(() => {
            document.getElementById('complaintsTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading</td></tr>';
        });
}

function searchComplaints() {
    const query = document.getElementById('searchInput')?.value.trim() || '';
    complaintFilters.q = query;
    loadComplaints();
}

function applyComplaintFilters() {
    complaintFilters.status = document.getElementById('filterStatus')?.value || '';
    complaintFilters.from = document.getElementById('filterFrom')?.value || '';
    complaintFilters.to = document.getElementById('filterTo')?.value || '';
    syncComplaintStatusTabs();
    loadComplaints();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetComplaints() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    complaintFilters = { q: '', status: '', from: '', to: '' };
    const statusSel = document.getElementById('filterStatus');
    const fromInput = document.getElementById('filterFrom');
    const toInput = document.getElementById('filterTo');
    if (statusSel) statusSel.value = '';
    if (fromInput) fromInput.value = '';
    if (toInput) toInput.value = '';
    syncComplaintStatusTabs();
    loadComplaints();
}

function initComplaintStatFilters() {
    const tabs = document.querySelectorAll('#statusTabs .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const status = this.getAttribute('data-status') || '';
            complaintFilters.status = status;
            const statusSel = document.getElementById('filterStatus');
            if (statusSel) statusSel.value = status;
            loadComplaints();
        });
    });
}

function syncComplaintStatusTabs() {
    document.querySelectorAll('#statusTabs .nav-link').forEach(tab => {
        const tabStatus = tab.getAttribute('data-status') || '';
        tab.classList.toggle('active', tabStatus === (complaintFilters.status || ''));
    });
}

function viewComplaint(id) {
    fetch(window.API_URL + 'complaints.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return alert(d.message || 'Error');
            const c = d.data;
            const html = `
                <strong>Title:</strong> ${escapeHtml(toTitleCase(c.title || c.complaint_title || '-'))}<br>
                <strong>Complainant:</strong> ${escapeHtml(toTitleCase(c.complainant_name || '-'))}<br>
                <strong>Respondent:</strong> ${escapeHtml(toTitleCase(c.respondent_name || '-'))}<br>
                <strong>Type:</strong> ${escapeHtml(toTitleCase(c.category || c.complaint_type || '-'))}<br>
                <strong>Date:</strong> ${formatDate(c.date_submitted || c.filing_date)}<br>
                <strong>Status:</strong> <span class="badge bg-${getStatusColor(c.status)}">${escapeHtml(formatComplaintStatus(c.status))}</span><br>
                <strong>Narrative:</strong><br>${escapeHtml(toTitleCase(c.description || c.narrative || '-'))}<br>
                ${c.remarks ? '<strong>Remarks:</strong><br>' + escapeHtml(toTitleCase(c.remarks)) : ''}
            `;
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            document.getElementById('viewModalBody').innerHTML = html;
            const editBtn = COMPLAINT_PERMS.canEdit ? ` <button class="btn btn-primary" onclick="editComplaint(${id}); bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();">Edit</button>` : '';
            document.getElementById('viewModalFooter').innerHTML = `<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>${editBtn}`;
            modal.show();
        });
}

function editComplaint(id) {
    if (!COMPLAINT_PERMS.canEdit) { alert('Access denied'); return; }
    fetch(window.API_URL + 'complaints.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return alert(d.message || 'Error');
            const c = d.data;
            document.getElementById('editId').value = c.id;
            document.getElementById('editTitle').value = toTitleCase(c.title || c.complaint_title || '');
            document.getElementById('editComplainant').value = toTitleCase(c.complainant_name || '');
            document.getElementById('editRespondent').value = toTitleCase(c.respondent_name || '');
            document.getElementById('editType').value = toTitleCase(c.category || c.complaint_type || '');
            document.getElementById('editNarrative').value = toTitleCase(c.description || c.narrative || '');
            document.getElementById('editFilingDate').value = c.filing_date || c.incident_date || '';
            document.getElementById('editStatus').value = c.status || 'Pending Review';
            document.getElementById('editRemarks').value = toTitleCase(c.remarks || '');
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
}

function saveComplaint() {
    if (!COMPLAINT_PERMS.canEdit) { alert('Access denied'); return; }
    applyTitleCaseToEditForm();
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', document.getElementById('editId').value);
    fd.append('complaint_title', document.getElementById('editTitle').value);
    fd.append('complainant_name', document.getElementById('editComplainant').value);
    fd.append('respondent_name', document.getElementById('editRespondent').value);
    fd.append('complaint_type', document.getElementById('editType').value);
    fd.append('narrative', document.getElementById('editNarrative').value);
    fd.append('filing_date', document.getElementById('editFilingDate').value);
    fd.append('status', document.getElementById('editStatus').value);
    fd.append('remarks', document.getElementById('editRemarks').value);
    fetch(window.API_URL + 'complaints.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                loadComplaints();
            } else alert(d.message || 'Error');
        });
}

function deleteComplaint(id) {
    if (!COMPLAINT_PERMS.canDelete) { alert('Access denied'); return; }
    if (!confirm('Delete this complaint?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(window.API_URL + 'complaints.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadComplaints(); else alert(d.message || 'Error'); });
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'Pending Review': 'warning',
        'under_review': 'info',
        'Under Investigation': 'info',
        'Scheduled for Mediation': 'primary',
        'Referred to Other Barangay': 'secondary',
        'resolved': 'success',
        'Resolved': 'success',
        'dismissed': 'danger',
        'Dismissed': 'danger'
    };
    return colors[status] || 'secondary';
}
function formatComplaintStatus(status) {
    const labels = {
        pending: 'Pending Review',
        under_review: 'Under Investigation',
        resolved: 'Resolved',
        dismissed: 'Dismissed'
    };
    return labels[status] || status || 'Pending Review';
}
function toTitleCase(text) {
    if (!text) return '';
    return String(text)
        .trim()
        .split(/\s+/)
        .map(word => {
            if (!word) return '';
            const clean = word.replace(/[^a-zA-Z]/g, '');
            if (clean.length > 0 && clean === clean.toUpperCase() && clean.length <= 3) {
                return word;
            }
            const first = word.charAt(0).toUpperCase();
            const rest = word.slice(1).toLowerCase();
            return first + rest;
        })
        .join(' ');
}

function attachTitleCaseOnBlur(input) {
    if (!input) return;
    input.addEventListener('blur', function() {
        this.value = toTitleCase(this.value);
    });
}

function initComplaintFormFormatting() {
    const createForm = document.getElementById('createForm');
    if (createForm) {
        attachTitleCaseOnBlur(createForm.querySelector('[name="complaint_title"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="complainant_name"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="respondent_name"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="complaint_type"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="narrative"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="remarks"]'));
    }
    const editTitle = document.getElementById('editTitle');
    const editComplainant = document.getElementById('editComplainant');
    const editRespondent = document.getElementById('editRespondent');
    const editType = document.getElementById('editType');
    const editNarrative = document.getElementById('editNarrative');
    const editRemarks = document.getElementById('editRemarks');
    attachTitleCaseOnBlur(editTitle);
    attachTitleCaseOnBlur(editComplainant);
    attachTitleCaseOnBlur(editRespondent);
    attachTitleCaseOnBlur(editType);
    attachTitleCaseOnBlur(editNarrative);
    attachTitleCaseOnBlur(editRemarks);
}

function applyTitleCaseToEditForm() {
    const ids = ['editTitle','editComplainant','editRespondent','editType','editNarrative','editRemarks'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = toTitleCase(el.value);
    });
}

function applyTitleCaseToCreateForm(form) {
    if (!form) return;
    const fields = ['complaint_title','complainant_name','respondent_name','complaint_type','narrative','remarks'];
    fields.forEach(name => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) el.value = toTitleCase(el.value);
    });
}

function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, x => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[x]); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
