if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
    document.getElementById('btnCreate').addEventListener('click', createAnnouncement);
    document.getElementById('btnSave').addEventListener('click', saveAnnouncement);
    applyAnnouncementPermissions();
});

let announcementFilters = { q: '', status: '', from: '', to: '' };

const ANNOUNCEMENT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('announcements', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('announcements', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('announcements', 'can_delete') : true
};

function applyAnnouncementPermissions() {
    if (!ANNOUNCEMENT_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
        const createBtn = document.getElementById('btnCreate');
        if (createBtn) createBtn.style.display = 'none';
    }
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        const saveBtn = document.getElementById('btnSave');
        if (saveBtn) saveBtn.style.display = 'none';
    }
}

function loadAnnouncements() {
    const params = new URLSearchParams({ action: 'list' });
    if (announcementFilters.q) params.append('q', announcementFilters.q);
    if (announcementFilters.status) params.append('status', announcementFilters.status);
    if (announcementFilters.from) params.append('from', announcementFilters.from);
    if (announcementFilters.to) params.append('to', announcementFilters.to);

    fetch(window.API_URL + 'announcement.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('announcementsTableBody');
            if (d.success && d.data) {
                tbody.innerHTML = d.data.map(a => `
                    <tr>
                        <td>${a.id}</td>
                        <td>${escapeHtml(a.title)}</td>
                        <td>${escapeHtml(a.posted_by_name || '-')}</td>
                        <td>${formatDate(a.date_posted)}</td>
                        <td>${a.expiration_date ? formatDate(a.expiration_date) : '-'}</td>
                        <td><span class="badge bg-${getStatusColor(a.status)}">${a.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewAnnouncement(${a.id})">View</button>
                            ${ANNOUNCEMENT_PERMS.canEdit ? `<button class="btn btn-sm btn-outline-secondary" onclick="editAnnouncement(${a.id})">Edit</button>` : ''}
                            ${ANNOUNCEMENT_PERMS.canDelete ? `<button class="btn btn-sm btn-outline-warning" onclick="archiveAnnouncement(${a.id})">Archive</button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No announcements</td></tr>';
            }
        })
        .catch(() => {
            document.getElementById('announcementsTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading</td></tr>';
        });
}

function searchAnnouncements() {
    const query = document.getElementById('searchInput')?.value.trim() || '';
    announcementFilters.q = query;
    loadAnnouncements();
}

function applyAnnouncementFilters() {
    announcementFilters.status = document.getElementById('filterStatus')?.value || '';
    announcementFilters.from = document.getElementById('filterFrom')?.value || '';
    announcementFilters.to = document.getElementById('filterTo')?.value || '';
    loadAnnouncements();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetAnnouncements() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    announcementFilters = { q: '', status: '', from: '', to: '' };
    const statusSel = document.getElementById('filterStatus');
    const fromInput = document.getElementById('filterFrom');
    const toInput = document.getElementById('filterTo');
    if (statusSel) statusSel.value = '';
    if (fromInput) fromInput.value = '';
    if (toInput) toInput.value = '';
    loadAnnouncements();
}

function getStatusColor(s) {
    const c = { 'active': 'success', 'inactive': 'secondary', 'expired': 'warning', 'archived': 'dark' };
    return c[s] || 'secondary';
}

function viewAnnouncement(id) {
    fetch(window.API_URL + 'announcement.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const a = d.data;
            document.getElementById('viewTitle').textContent = a.title;
            document.getElementById('viewContent').innerHTML = a.content.replace(/\n/g, '<br>');
            document.getElementById('viewDate').textContent = formatDate(a.date_posted);
            document.getElementById('viewBy').textContent = a.posted_by_name || '-';
            const editBtn = ANNOUNCEMENT_PERMS.canEdit ? ` <button class="btn btn-primary" onclick="editAnnouncement(${a.id}); bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();">Edit</button>` : '';
            document.getElementById('viewFooter').innerHTML = `<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>${editBtn}`;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        });
}

function editAnnouncement(id) {
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        alert('Access denied');
        return;
    }
    fetch(window.API_URL + 'announcement.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const a = d.data;
            document.getElementById('editModalTitle').textContent = 'Edit Announcement';
            document.getElementById('editId').value = a.id;
            document.getElementById('editTitle').value = a.title;
            document.getElementById('editContent').value = a.content;
            document.getElementById('editDatePosted').value = a.date_posted || '';
            document.getElementById('editExpiration').value = a.expiration_date || '';
            document.getElementById('editStatus').value = a.status || 'active';
            document.getElementById('editStatusRow').style.display = 'block';
            bootstrap.Modal.getInstance(document.getElementById('createModal'))?.hide();
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
}

function saveAnnouncement() {
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        alert('Access denied');
        return;
    }
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', document.getElementById('editId').value);
    fd.append('title', document.getElementById('editTitle').value);
    fd.append('content', document.getElementById('editContent').value);
    fd.append('date_posted', document.getElementById('editDatePosted').value);
    fd.append('expiration_date', document.getElementById('editExpiration').value);
    fd.append('status', document.getElementById('editStatus').value);
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                loadAnnouncements();
            } else alert(d.message || 'Error');
        });
}

function createAnnouncement() {
    if (!ANNOUNCEMENT_PERMS.canCreate) {
        alert('Access denied');
        return;
    }
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('title', document.getElementById('createTitle').value);
    fd.append('content', document.getElementById('createContent').value);
    fd.append('date_posted', document.getElementById('createDatePosted').value);
    fd.append('expiration_date', document.getElementById('createExpiration').value);
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
                document.getElementById('createTitle').value = '';
                document.getElementById('createContent').value = '';
                document.getElementById('createDatePosted').value = new Date().toISOString().slice(0, 10);
                document.getElementById('createExpiration').value = '';
                loadAnnouncements();
            } else alert(d.message || 'Error');
        });
}

function archiveAnnouncement(id) {
    if (!ANNOUNCEMENT_PERMS.canDelete) {
        alert('Access denied');
        return;
    }
    if (!confirm('Archive this announcement?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadAnnouncements(); else alert(d.message || 'Error'); });
}

function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, x => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[x]); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
