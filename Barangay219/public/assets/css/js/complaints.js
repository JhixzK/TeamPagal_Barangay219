if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadComplaints();
});

function loadComplaints(status) {
    let url = 'complaints.php?action=list';
    if (status) url += '&status=' + status;
    fetch(window.API_URL + url)
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('complaintsTableBody');
            if (d.success) {
                const list = d.data.complaints || d.data || [];
                tbody.innerHTML = list.map(c => `
                    <tr>
                        <td>${c.id}</td>
                        <td>${escapeHtml(c.complaint_title)}</td>
                        <td>${escapeHtml(c.complainant_name)}</td>
                        <td>${escapeHtml(c.resident_name || '-')}</td>
                        <td>${formatDate(c.filing_date)}</td>
                        <td><span class="badge bg-${getStatusColor(c.status)}">${c.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewComplaint(${c.id})">View</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editComplaint(${c.id})">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteComplaint(${c.id})">Delete</button>
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

function viewComplaint(id) {
    fetch(window.API_URL + 'complaints.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return alert(d.message || 'Error');
            const c = d.data;
            const html = `
                <strong>Title:</strong> ${escapeHtml(c.complaint_title)}<br>
                <strong>Complainant:</strong> ${escapeHtml(c.complainant_name)}<br>
                <strong>Respondent:</strong> ${escapeHtml(c.respondent_name || '-')}<br>
                <strong>Type:</strong> ${escapeHtml(c.complaint_type || '-')}<br>
                <strong>Date:</strong> ${formatDate(c.filing_date)}<br>
                <strong>Status:</strong> <span class="badge bg-${getStatusColor(c.status)}">${c.status}</span><br>
                <strong>Narrative:</strong><br>${escapeHtml(c.narrative)}<br>
                ${c.remarks ? '<strong>Remarks:</strong><br>' + escapeHtml(c.remarks) : ''}
            `;
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            document.getElementById('viewModalBody').innerHTML = html;
            document.getElementById('viewModalFooter').innerHTML = `<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button> <button class="btn btn-primary" onclick="editComplaint(${id}); bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();">Edit</button>`;
            modal.show();
        });
}

function editComplaint(id) {
    fetch(window.API_URL + 'complaints.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return alert(d.message || 'Error');
            const c = d.data;
            document.getElementById('editId').value = c.id;
            document.getElementById('editTitle').value = c.complaint_title;
            document.getElementById('editComplainant').value = c.complainant_name;
            document.getElementById('editRespondent').value = c.respondent_name || '';
            document.getElementById('editType').value = c.complaint_type || '';
            document.getElementById('editNarrative').value = c.narrative;
            document.getElementById('editFilingDate').value = c.filing_date || '';
            document.getElementById('editStatus').value = c.status || 'pending';
            document.getElementById('editRemarks').value = c.remarks || '';
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
}

function saveComplaint() {
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
    if (!confirm('Delete this complaint?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(window.API_URL + 'complaints.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadComplaints(); else alert(d.message || 'Error'); });
}

function getStatusColor(status) {
    const c = { 'pending': 'warning', 'under_review': 'info', 'resolved': 'success', 'dismissed': 'danger' };
    return c[status] || 'secondary';
}
function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, x => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[x]); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
