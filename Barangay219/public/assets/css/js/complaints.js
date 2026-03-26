if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadComplaints();
    applyComplaintPermissions();
    initComplaintFilterControls();
    initComplaintFormFormatting();
});

let complaintFilters = { q: '', status: '', category: '', from: '', to: '', list_scope: 'active', page: 1 };
let complaintListMeta = { total: 0, total_pages: 1, limit: 10 };

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

function initComplaintFilterControls() {
    const applyBtn = document.getElementById('btnApplyComplaintFilters');
    const resetBtn = document.getElementById('btnResetComplaintFilters');
    const searchInput = document.getElementById('searchInput');
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            complaintFilters.q = searchInput ? searchInput.value.trim() : '';
            complaintFilters.status = document.getElementById('filterStatus')?.value || '';
            complaintFilters.category = document.getElementById('filterCategory')?.value || '';
            complaintFilters.from = document.getElementById('filterFrom')?.value || '';
            complaintFilters.to = document.getElementById('filterTo')?.value || '';
            complaintFilters.list_scope = document.getElementById('filterListScope')?.value || 'all';
            complaintFilters.page = 1;
            loadComplaints();
        });
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            const st = document.getElementById('filterStatus');
            const cat = document.getElementById('filterCategory');
            const ls = document.getElementById('filterListScope');
            const f = document.getElementById('filterFrom');
            const t = document.getElementById('filterTo');
            if (st) st.value = '';
            if (cat) cat.value = '';
            if (ls) ls.value = 'active';
            if (f) f.value = '';
            if (t) t.value = '';
            complaintFilters = { q: '', status: '', category: '', from: '', to: '', list_scope: 'active', page: 1 };
            loadComplaints();
        });
    }
}

function splitFullNameForForm(full) {
    const s = String(full || '').trim();
    if (!s) return { first: '', last: '' };
    const i = s.indexOf(' ');
    if (i === -1) return { first: s, last: '' };
    return { first: s.slice(0, i).trim(), last: s.slice(i + 1).trim() };
}

function complaintSubmittedBy(c) {
    const rn = String(c.resident_name || '').replace(/\s+/g, ' ').trim();
    if (rn) return rn;
    return String(c.complainant_name || '').trim() || '—';
}

function complaintContact(c) {
    const p = String(c.resident_contact || '').trim();
    return p || '—';
}

function complaintLocationLine(c) {
    const parts = [c.incident_house_street, c.incident_landmark].map(x => String(x || '').trim()).filter(Boolean);
    return parts.length ? parts.join(' · ') : '—';
}

function loadComplaints() {
    const params = new URLSearchParams({ action: 'list' });
    if (complaintFilters.q) params.append('q', complaintFilters.q);
    if (complaintFilters.status) params.append('status', complaintFilters.status);
    if (complaintFilters.category) params.append('category', complaintFilters.category);
    if (complaintFilters.from) params.append('from', complaintFilters.from);
    if (complaintFilters.to) params.append('to', complaintFilters.to);
    if (complaintFilters.list_scope && complaintFilters.list_scope !== 'all') {
        params.append('list_scope', complaintFilters.list_scope);
    }
    params.append('page', String(complaintFilters.page));
    params.append('limit', String(complaintListMeta.limit));

    fetch(window.API_URL + 'complaints.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('complaintsTableBody');
            const pagerWrap = document.getElementById('complaintsPagerWrap');
            const pagerInfo = document.getElementById('complaintsPagerInfo');
            if (d.success) {
                const payload = d.data || {};
                const list = payload.complaints || [];
                complaintListMeta.total = payload.total || 0;
                complaintListMeta.total_pages = payload.total_pages || 1;
                complaintListMeta.limit = payload.limit || complaintListMeta.limit;
                if (pagerWrap && pagerInfo) {
                    const from = list.length ? (complaintFilters.page - 1) * complaintListMeta.limit + 1 : 0;
                    const to = from + list.length - 1;
                    pagerInfo.textContent = list.length
                        ? `Showing ${from}–${to} of ${complaintListMeta.total}`
                        : 'No results';
                }
                if (typeof window.renderModuleBtnPagination === 'function') {
                    window.renderModuleBtnPagination({
                        containerId: 'complaintsPagination',
                        outerWrapId: 'complaintsPagerWrap',
                        currentPage: complaintFilters.page,
                        total: complaintListMeta.total,
                        totalPages: complaintListMeta.total_pages,
                        onPage: pg => {
                            complaintFilters.page = pg;
                            loadComplaints();
                        }
                    });
                }
                tbody.innerHTML = list.length
                    ? list.map(c => `
                    <tr>
                        <td class="text-center"><span class="complaints-code-badge">#${c.id}</span></td>
                        <td class="text-center">${escapeHtml(c.category || c.complaint_type || '—')}</td>
                        <td class="text-center">${escapeHtml(complaintSubmittedBy(c))}</td>
                        <td class="text-center"><span class="complaints-secondary">${escapeHtml(complaintContact(c))}</span></td>
                        <td class="text-center"><span class="complaints-secondary">${formatDateUs(c.incident_date)}</span></td>
                        <td class="text-center"><span class="complaints-secondary">${escapeHtml(complaintLocationLine(c))}</span></td>
                        <td class="text-center"><span class="complaints-pill ${getStatusColor(c.status)}">${escapeHtml(formatComplaintStatus(c.status))}</span></td>
                        <td class="text-center"><span class="complaints-secondary">${escapeHtml(String(c.assigned_officer || '').trim() || '—')}</span></td>
                        <td class="text-center">
                            <div class="complaints-actions">
                            <button type="button" class="action-icon-btn" title="View" aria-label="View" onclick="viewComplaint(${c.id})"><i class="bi bi-eye"></i></button>
                            ${COMPLAINT_PERMS.canEdit ? `<button type="button" class="action-icon-btn" title="Edit" aria-label="Edit" onclick="editComplaint(${c.id})"><i class="bi bi-pencil-square"></i></button>` : ''}
                            ${COMPLAINT_PERMS.canDelete ? `<button type="button" class="action-icon-btn action-delete" title="Delete" aria-label="Delete" onclick="deleteComplaint(${c.id})"><i class="bi bi-trash"></i></button>` : ''}
                            </div>
                        </td>
                    </tr>
                `).join('')
                    : '<tr><td colspan="9" class="text-center text-muted">No complaints found</td></tr>';
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No complaints found</td></tr>';
                if (pagerWrap) pagerWrap.style.display = 'none';
                const cp = document.getElementById('complaintsPagination');
                if (cp) {
                    cp.innerHTML = '';
                    cp.className = '';
                }
            }
        })
        .catch(() => {
            const tbody = document.getElementById('complaintsTableBody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Error loading</td></tr>';
            const pw = document.getElementById('complaintsPagerWrap');
            if (pw) pw.style.display = 'none';
            const cp = document.getElementById('complaintsPagination');
            if (cp) {
                cp.innerHTML = '';
                cp.className = '';
            }
        });
}

function complaintStatusMessage(code) {
    const c = String(code || '').toLowerCase().trim();
    const msg = {
        pending: 'Awaiting initial review by barangay staff.',
        approved: 'This complaint has been approved for handling.',
        assigned: 'An officer has been assigned to this case.',
        in_progress: 'Work is currently in progress.',
        resolved: 'This complaint has been resolved. No further actions.',
        rejected: 'This complaint was rejected or closed without resolution.'
    };
    return msg[c] || 'Use Edit to update status, officer, or remarks.';
}

function viewComplaintReadonlyValue(s) {
    const t = String(s || '').trim();
    return t ? escapeHtml(toTitleCase(t)) : '';
}

function viewComplaint(id) {
    fetch(window.API_URL + 'complaints.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return alert(d.message || 'Error');
            const c = d.data;
            const narrativeRaw = c.description || c.narrative || '';
            const narrativeVal = narrativeRaw.trim() ? escapeHtml(narrativeRaw) : '—';
            const titleVal = viewComplaintReadonlyValue(c.title || c.complaint_title || '');
            const typeVal = viewComplaintReadonlyValue(c.category || c.complaint_type || '');
            const submittedVal = escapeHtml(formatDateUs(c.date_submitted || c.filing_date) || '—');
            const incidentVal = escapeHtml(formatDateUs(c.incident_date) || '—');
            const locVal = escapeHtml(complaintLocationLine(c));
            const byVal = escapeHtml(complaintSubmittedBy(c));
            const statusRaw = String(c.status || 'pending').toLowerCase();
            const statusLabel = formatComplaintStatus(c.status);
            const html = `
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="complaint-review-card p-4">
                            <div class="card-section-title"><i class="bi bi-file-text me-2"></i>Issue Details</div>
                            <div class="mb-3">
                                <div class="complaint-kv-label">Title</div>
                                <div class="complaint-kv-value">${titleVal || '—'}</div>
                            </div>
                            <div class="mb-3">
                                <div class="complaint-kv-label">Category</div>
                                <div class="complaint-kv-value">${typeVal || '—'}</div>
                            </div>
                            <div class="mb-3">
                                <div class="complaint-kv-label">Submitted By</div>
                                <div class="complaint-kv-value">${byVal}</div>
                            </div>
                            <div class="mb-3">
                                <div class="complaint-kv-label">Date Submitted</div>
                                <div class="complaint-kv-value">${submittedVal}</div>
                            </div>
                            <div class="mb-3">
                                <div class="complaint-kv-label">Incident Date</div>
                                <div class="complaint-kv-value">${incidentVal}</div>
                            </div>
                            <div class="mb-3">
                                <div class="complaint-kv-label">Location</div>
                                <div class="complaint-kv-value">${locVal}</div>
                            </div>
                            <div>
                                <div class="complaint-kv-label">Description</div>
                                <div class="complaint-desc-box mt-1">${narrativeVal}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="complaint-review-card p-4">
                            <div class="card-section-title"><i class="bi bi-flag me-2"></i>Action Panel</div>
                            <div class="action-status-box">
                                <div class="complaint-kv-label mb-2">Status</div>
                                <div class="mb-2"><span class="complaints-pill ${getStatusColor(c.status)}">${escapeHtml(statusLabel)}</span></div>
                                <p class="mb-0 small text-muted">${escapeHtml(complaintStatusMessage(statusRaw))}</p>
                            </div>
                            ${String(c.assigned_officer || '').trim() ? `
                            <div class="mt-3">
                                <div class="complaint-kv-label">Assigned Officer</div>
                                <div class="complaint-kv-value">${escapeHtml(String(c.assigned_officer).trim())}</div>
                            </div>` : ''}
                        </div>
                    </div>
                </div>
            `;
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            document.getElementById('viewModalBody').innerHTML = html;
            document.getElementById('viewModalFooter').innerHTML = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Close</button>';
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
            const comp = splitFullNameForForm(c.complainant_name || '');
            document.getElementById('editComplainantFirst').value = toTitleCase(comp.first);
            document.getElementById('editComplainantLast').value = toTitleCase(comp.last);
            const resp = splitFullNameForForm(c.respondent_name || '');
            document.getElementById('editRespondentFirst').value = toTitleCase(resp.first);
            document.getElementById('editRespondentLast').value = toTitleCase(resp.last);
            const cat = (c.category || c.complaint_type || '').trim();
            const catSel = document.getElementById('editCategory');
            if (catSel) {
                catSel.value = cat;
                if (cat && catSel.value !== cat) {
                    catSel.value = '';
                }
            }
            document.getElementById('editNarrative').value = c.description || c.narrative || '';
            document.getElementById('editFilingDate').value = c.filing_date || c.incident_date || '';
            const st = String(c.status || 'pending').toLowerCase();
            const stEl = document.getElementById('editStatus');
            if (stEl) {
                stEl.value = st;
                if (stEl.value !== st) stEl.value = 'pending';
            }
            document.getElementById('editRemarks').value = toTitleCase(c.remarks || '');
            const ao = document.getElementById('editAssignedOfficer');
            if (ao) ao.value = c.assigned_officer || '';
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
}

function saveComplaint() {
    if (!COMPLAINT_PERMS.canEdit) { alert('Access denied'); return; }
    applyTitleCaseToEditForm();
    const cf = document.getElementById('editComplainantFirst').value.trim();
    const cl = document.getElementById('editComplainantLast').value.trim();
    if (!cf || !cl) {
        alert('Complainant first and last name are required');
        return;
    }
    const rf = document.getElementById('editRespondentFirst').value.trim();
    const rl = document.getElementById('editRespondentLast').value.trim();
    const narrative = document.getElementById('editNarrative').value;
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', document.getElementById('editId').value);
    fd.append('complaint_title', document.getElementById('editTitle').value);
    fd.append('title', document.getElementById('editTitle').value);
    fd.append('complainant_first_name', cf);
    fd.append('complainant_last_name', cl);
    fd.append('respondent_first_name', rf);
    fd.append('respondent_last_name', rl);
    const cat = document.getElementById('editCategory')?.value.trim() || '';
    fd.append('complaint_type', cat);
    fd.append('category', cat);
    fd.append('narrative', narrative);
    fd.append('description', narrative);
    fd.append('filing_date', document.getElementById('editFilingDate').value);
    fd.append('status', document.getElementById('editStatus').value);
    fd.append('remarks', document.getElementById('editRemarks').value);
    const ao = document.getElementById('editAssignedOfficer');
    if (ao) fd.append('assigned_officer', ao.value.trim());
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
    const s = String(status || '').toLowerCase().trim();
    const colors = {
        pending: 'status-pending',
        approved: 'status-approved',
        assigned: 'status-assigned',
        in_progress: 'status-in-progress',
        resolved: 'status-resolved',
        rejected: 'status-rejected'
    };
    return colors[s] || 'status-unknown';
}

function formatComplaintStatus(status) {
    const s = String(status || '').toLowerCase().trim();
    const labels = {
        pending: 'Pending',
        approved: 'Approved',
        assigned: 'Assigned',
        in_progress: 'In Progress',
        resolved: 'Resolved',
        rejected: 'Rejected'
    };
    return labels[s] || (status ? String(status) : 'Pending');
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
        attachTitleCaseOnBlur(createForm.querySelector('[name="complainant_first_name"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="complainant_last_name"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="respondent_first_name"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="respondent_last_name"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="narrative"]'));
        attachTitleCaseOnBlur(createForm.querySelector('[name="remarks"]'));
    }
    attachTitleCaseOnBlur(document.getElementById('editTitle'));
    attachTitleCaseOnBlur(document.getElementById('editComplainantFirst'));
    attachTitleCaseOnBlur(document.getElementById('editComplainantLast'));
    attachTitleCaseOnBlur(document.getElementById('editRespondentFirst'));
    attachTitleCaseOnBlur(document.getElementById('editRespondentLast'));
    attachTitleCaseOnBlur(document.getElementById('editRemarks'));
    attachTitleCaseOnBlur(document.getElementById('editAssignedOfficer'));
}

function applyTitleCaseToEditForm() {
    const ids = ['editTitle', 'editComplainantFirst', 'editComplainantLast', 'editRespondentFirst', 'editRespondentLast', 'editRemarks', 'editAssignedOfficer'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = toTitleCase(el.value);
    });
}

function applyTitleCaseToCreateForm(form) {
    if (!form) return;
    const fields = ['complaint_title', 'complainant_first_name', 'complainant_last_name', 'respondent_first_name', 'respondent_last_name', 'narrative', 'remarks'];
    fields.forEach(name => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) el.value = toTitleCase(el.value);
    });
}

window.applyTitleCaseToCreateForm = applyTitleCaseToCreateForm;

function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, x => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[x]); }

function formatDateUs(d) {
    if (!d) return '—';
    const x = new Date(d);
    if (Number.isNaN(x.getTime())) return '—';
    return x.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
}
