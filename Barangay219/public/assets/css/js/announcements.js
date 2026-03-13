/**
 * E-Barangay Announcements - Admin Module
 * Simplified announcement management for staff
 */

if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
    attachEventListeners();
    applyAnnouncementPermissions();
    initAnnouncementFormFormatting();
});

let announcementFilters = { q: '' };

const ANNOUNCEMENT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('announcements', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('announcements', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('announcements', 'can_delete') : true
};

function attachEventListeners() {
    const btnCreate = document.getElementById('btnCreate');
    const btnSave = document.getElementById('btnSave');
    
    if (btnCreate) btnCreate.addEventListener('click', createAnnouncement);
    if (btnSave) btnSave.addEventListener('click', saveAnnouncement);
}

function applyAnnouncementPermissions() {
    if (!ANNOUNCEMENT_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
    }
}

/**
 * Load all announcements from API
 */
function loadAnnouncements() {
    const params = new URLSearchParams({ action: 'list' });
    if (announcementFilters.q) params.append('q', announcementFilters.q);

    fetch(window.API_URL + 'announcement.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('announcementsTableBody');
            if (!tbody) return;
            
            if (d.success && d.data && Array.isArray(d.data)) {
                tbody.innerHTML = d.data.map(a => `
                    <tr>
                        <td><strong>${escapeHtml(toTitleCase(a.title || '-'))}</strong></td>
                        <td>${escapeHtml(toTitleCase(a.category || 'General'))}</td>
                        <td>
                            <span class="badge ${a.priority === 'urgent' ? 'bg-danger' : 'bg-secondary'}">
                                ${a.priority === 'urgent' ? '🚨 Urgent' : 'Normal'}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-${a.status === 'published' ? 'success' : 'warning'}">
                                ${a.status === 'published' ? 'Published' : 'Draft'}
                            </span>
                        </td>
                        <td>${formatDate(a.created_at)}</td>
                        <td>${a.expires_at ? formatDate(a.expires_at) : '-'}</td>
                        <td><span class="badge bg-info">${a.views || 0}</span></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-primary" title="Edit" onclick="editAnnouncement(${a.id})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                ${a.is_pinned ? 
                                    `<button class="btn btn-outline-warning" title="Unpin" onclick="pinAnnouncement(${a.id})">
                                        <i class="bi bi-pin-fill"></i>
                                    </button>` :
                                    `<button class="btn btn-outline-secondary" title="Pin" onclick="pinAnnouncement(${a.id})">
                                        <i class="bi bi-pin"></i>
                                    </button>`
                                }
                                ${a.status === 'published' ?
                                    `<button class="btn btn-outline-secondary" title="Unpublish" onclick="updateStatus(${a.id}, 'draft')">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>` :
                                    `<button class="btn btn-outline-success" title="Publish" onclick="updateStatus(${a.id}, 'published')">
                                        <i class="bi bi-eye"></i>
                                    </button>`
                                }
                                <button class="btn btn-outline-danger" title="Delete" onclick="deleteAnnouncement(${a.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No announcements</td></tr>';
            }
            updateStatistics(d.data || []);
        })
        .catch(err => {
            console.error('Error loading announcements:', err);
            const tbody = document.getElementById('announcementsTableBody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading announcements</td></tr>';
        });
}

/**
 * Update statistics cards
 */
function updateStatistics(data) {
    const container = document.querySelector('.module-stats[data-module="announcements"]');
    if (!container) return;
    
    const total = data.length;
    const published = data.filter(a => a.status === 'published').length;
    const draft = data.filter(a => a.status === 'draft').length;
    
    const stats = {
        'total': total,
        'published': published,
        'draft': draft
    };
    
    Object.entries(stats).forEach(([stat, value]) => {
        const elem = container.querySelector(`[data-stat="${stat}"]`);
        if (elem) elem.textContent = value;
    });
    
    // Attach click handlers to stat cards
    container.querySelectorAll('[data-status]').forEach(card => {
        card.removeEventListener('click', handleStatClick);
        card.addEventListener('click', handleStatClick);
    });
}

/**
 * Handle stat card clicks
 */
function handleStatClick(e) {
    const status = e.currentTarget.getAttribute('data-status');
    announcementFilters.q = '';
    document.getElementById('searchInput').value = '';
    
    if (status) {
        // Filter by status
        fetch(window.API_URL + 'announcement.php?action=list&status=' + status)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('announcementsTableBody');
                if (d.success && d.data) {
                    tbody.innerHTML = d.data.map(a => buildTableRow(a)).join('');
                }
            });
    } else {
        loadAnnouncements();
    }
}

/**
 * Build a table row for an announcement
 */
function buildTableRow(a) {
    return `
        <tr>
            <td><strong>${escapeHtml(toTitleCase(a.title || '-'))}</strong></td>
            <td>${escapeHtml(toTitleCase(a.category || 'General'))}</td>
            <td>
                <span class="badge ${a.priority === 'urgent' ? 'bg-danger' : 'bg-secondary'}">
                    ${a.priority === 'urgent' ? '🚨 Urgent' : 'Normal'}
                </span>
            </td>
            <td>
                <span class="badge bg-${a.status === 'published' ? 'success' : 'warning'}">
                    ${a.status === 'published' ? 'Published' : 'Draft'}
                </span>
            </td>
            <td>${formatDate(a.created_at)}</td>
            <td>${a.expires_at ? formatDate(a.expires_at) : '-'}</td>
            <td><span class="badge bg-info">${a.views || 0}</span></td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary" title="Edit" onclick="editAnnouncement(${a.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    ${a.is_pinned ? 
                        `<button class="btn btn-outline-warning" title="Unpin" onclick="pinAnnouncement(${a.id})">
                            <i class="bi bi-pin-fill"></i>
                        </button>` :
                        `<button class="btn btn-outline-secondary" title="Pin" onclick="pinAnnouncement(${a.id})">
                            <i class="bi bi-pin"></i>
                        </button>`
                    }
                    ${a.status === 'published' ?
                        `<button class="btn btn-outline-secondary" title="Unpublish" onclick="updateStatus(${a.id}, 'draft')">
                            <i class="bi bi-eye-slash"></i>
                        </button>` :
                        `<button class="btn btn-outline-success" title="Publish" onclick="updateStatus(${a.id}, 'published')">
                            <i class="bi bi-eye"></i>
                        </button>`
                    }
                    <button class="btn btn-outline-danger" title="Delete" onclick="deleteAnnouncement(${a.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Search announcements
 */
function searchAnnouncements() {
    const query = document.getElementById('searchInput')?.value.trim() || '';
    announcementFilters.q = query;
    loadAnnouncements();
}

/**
 * Reset filters and reload
 */
function resetAnnouncements() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    announcementFilters = { q: '' };
    loadAnnouncements();
}

/**
 * Create a new announcement
 */
function createAnnouncement() {
    if (!ANNOUNCEMENT_PERMS.canCreate) {
        alert('Access denied');
        return;
    }
    
    applyTitleCaseToCreateForm();
    const title = document.getElementById('createTitle')?.value.trim();
    const content = document.getElementById('createContent')?.value.trim();
    const category = document.getElementById('createCategory')?.value || 'General';
    const priority = document.getElementById('createPriority')?.value || 'normal';
    const expires_at = document.getElementById('createExpires')?.value || null;
    const is_pinned = document.getElementById('createPin')?.checked ? 1 : 0;
    const status = document.getElementById('createStatus')?.value || 'draft';
    const photo = document.getElementById('createPhoto')?.files?.[0] || null;
    
    if (!title || !content) {
        alert('Please fill in title and content');
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('title', title);
    fd.append('content', content);
    fd.append('category', category);
    fd.append('priority', priority);
    fd.append('expires_at', expires_at);
    fd.append('status', status);
    if (photo) {
        fd.append('photo', photo);
    }
    
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Handle pinning if needed
                if (is_pinned && d.data && d.data.id) {
                    const pinFd = new FormData();
                    pinFd.append('action', 'pin');
                    pinFd.append('id', d.data.id);
                    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: pinFd })
                        .then(() => {
                            bootstrap.Modal.getInstance(document.getElementById('createModal'))?.hide();
                            clearCreateForm();
                            loadAnnouncements();
                        });
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('createModal'))?.hide();
                    clearCreateForm();
                    loadAnnouncements();
                }
            } else {
                alert(d.message || 'Error creating announcement');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error creating announcement');
        });
}

/**
 * Clear create form
 */
function clearCreateForm() {
    document.getElementById('createTitle').value = '';
    document.getElementById('createContent').value = '';
    document.getElementById('createCategory').value = 'General';
    document.getElementById('createPriority').value = 'normal';
    document.getElementById('createExpires').value = '';
    document.getElementById('createPin').checked = false;
    document.getElementById('createStatus').value = 'draft';
    const createPhoto = document.getElementById('createPhoto');
    if (createPhoto) createPhoto.value = '';
}

/**
 * Edit an announcement
 */
function editAnnouncement(id) {
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        alert('Access denied');
        return;
    }
    
    fetch(window.API_URL + 'announcement.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert('Error loading announcement');
                return;
            }
            
            const a = d.data;
            document.getElementById('editId').value = a.id;
            document.getElementById('editTitle').value = toTitleCase(a.title || '');
            document.getElementById('editContent').value = toTitleCase(a.content || '');
            document.getElementById('editCategory').value = a.category || 'General';
            document.getElementById('editPriority').value = a.priority || 'normal';
            document.getElementById('editExpires').value = a.expires_at || '';
            document.getElementById('editPin').checked = a.is_pinned ? true : false;
            document.getElementById('editStatus').value = a.status || 'draft';

            const editPhoto = document.getElementById('editPhoto');
            if (editPhoto) editPhoto.value = '';

            const previewWrap = document.getElementById('editImagePreviewWrap');
            const previewImage = document.getElementById('editImagePreview');
            if (previewWrap && previewImage) {
                if (a.image_path) {
                    previewImage.src = resolveAnnouncementImageUrl(a.image_path);
                    previewWrap.style.display = 'block';
                } else {
                    previewImage.src = '';
                    previewWrap.style.display = 'none';
                }
            }
            
            bootstrap.Modal.getInstance(document.getElementById('createModal'))?.hide();
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error loading announcement');
        });
}

/**
 * Save announcement changes
 */
function saveAnnouncement() {
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        alert('Access denied');
        return;
    }
    
    applyTitleCaseToEditForm();
    const id = document.getElementById('editId')?.value;
    const title = document.getElementById('editTitle')?.value.trim();
    const content = document.getElementById('editContent')?.value.trim();
    const category = document.getElementById('editCategory')?.value || 'General';
    const priority = document.getElementById('editPriority')?.value || 'normal';
    const expires_at = document.getElementById('editExpires')?.value || null;
    const is_pinned = document.getElementById('editPin')?.checked ? 1 : 0;
    const status = document.getElementById('editStatus')?.value || 'draft';
    const photo = document.getElementById('editPhoto')?.files?.[0] || null;
    
    if (!title || !content) {
        alert('Please fill in title and content');
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('title', title);
    fd.append('content', content);
    fd.append('category', category);
    fd.append('priority', priority);
    fd.append('expires_at', expires_at);
    fd.append('status', status);
    if (photo) {
        fd.append('photo', photo);
    }
    
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Handle pinning
                const pinFd = new FormData();
                pinFd.append('action', 'pin');
                pinFd.append('id', id);
                
                if (is_pinned) {
                    // Fetch current state first
                    fetch(window.API_URL + 'announcement.php?action=get&id=' + id)
                        .then(r => r.json())
                        .then(checkD => {
                            if (checkD.data && !checkD.data.is_pinned) {
                                fetch(window.API_URL + 'announcement.php', { method: 'POST', body: pinFd })
                                    .then(() => {
                                        bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
                                        loadAnnouncements();
                                    });
                            } else {
                                bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
                                loadAnnouncements();
                            }
                        });
                } else if (!is_pinned) {
                    // Unpin if currently pinned
                    fetch(window.API_URL + 'announcement.php?action=get&id=' + id)
                        .then(r => r.json())
                        .then(checkD => {
                            if (checkD.data && checkD.data.is_pinned) {
                                fetch(window.API_URL + 'announcement.php', { method: 'POST', body: pinFd })
                                    .then(() => {
                                        bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
                                        loadAnnouncements();
                                    });
                            } else {
                                bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
                                loadAnnouncements();
                            }
                        });
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
                    loadAnnouncements();
                }
            } else {
                alert(d.message || 'Error saving announcement');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error saving announcement');
        });
}

/**
 * Delete an announcement
 */
function deleteAnnouncement(id) {
    if (!ANNOUNCEMENT_PERMS.canDelete) {
        alert('Access denied');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this announcement?')) {
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadAnnouncements();
            } else {
                alert(d.message || 'Error deleting announcement');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error deleting announcement');
        });
}

/**
 * Pin an announcement
 */
function pinAnnouncement(id) {
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        alert('Access denied');
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'pin');
    fd.append('id', id);
    
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadAnnouncements();
            } else {
                alert(d.message || 'Error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error');
        });
}

/**
 * Update announcement status
 */
function updateStatus(id, newStatus) {
    if (!ANNOUNCEMENT_PERMS.canEdit) {
        alert('Access denied');
        return;
    }
    
    const fd = new FormData();
    fd.append('action', newStatus === 'published' ? 'publish' : 'unpublish');
    fd.append('id', id);
    
    fetch(window.API_URL + 'announcement.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadAnnouncements();
            } else {
                alert(d.message || 'Error updating status');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error updating status');
        });
}

/**
 * Utility functions
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
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

function initAnnouncementFormFormatting() {
    attachTitleCaseOnBlur(document.getElementById('createTitle'));
    attachTitleCaseOnBlur(document.getElementById('createContent'));
    attachTitleCaseOnBlur(document.getElementById('editTitle'));
    attachTitleCaseOnBlur(document.getElementById('editContent'));
}

function applyTitleCaseToCreateForm() {
    const title = document.getElementById('createTitle');
    const content = document.getElementById('createContent');
    if (title) title.value = toTitleCase(title.value);
    if (content) content.value = toTitleCase(content.value);
}

function applyTitleCaseToEditForm() {
    const title = document.getElementById('editTitle');
    const content = document.getElementById('editContent');
    if (title) title.value = toTitleCase(title.value);
    if (content) content.value = toTitleCase(content.value);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString();
}

function resolveAnnouncementImageUrl(rawPath) {
    if (!rawPath) return '';
    const normalized = String(rawPath).replace(/\\/g, '/').replace(/^\/+/, '');
    if (normalized.startsWith('uploads/')) {
        return '../' + normalized;
    }
    if (normalized.startsWith('public/')) {
        return normalized.replace(/^public\//, '');
    }
    return normalized;
}
