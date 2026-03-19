/**
 * Officials module JS
 */

if (typeof window.API_URL === 'undefined' || window.API_URL === null || String(window.API_URL).indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadOfficials();
    initResidentSearch();
    // Captain is locked by backend; UI locks are handled per-slot.
    const form = document.getElementById('officialForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveOfficial();
        });
    }
});

function isSuperAdminClient() {
    return String(window.CURRENT_ROLE || '').trim().toLowerCase() === 'super_admin';
}

function setFixedPosition(positionKey) {
    const sel = document.getElementById('position');
    if (sel) sel.value = String(positionKey || '');
}

function initResidentSearch() {
    const input = document.getElementById('resident_search');
    const results = document.getElementById('residentSearchResults');
    const residentIdField = document.getElementById('resident_id');
    const fullNameField = document.getElementById('full_name');
    if (!input || !results || !residentIdField || !fullNameField) return;

    let lastQuery = '';
    let debounceTimer = null;

    const hideResults = () => {
        results.style.display = 'none';
        results.innerHTML = '';
    };

    const selectResident = (resident) => {
        residentIdField.value = resident.id;
        fullNameField.value = resident.full_name || '';
        input.value = resident.label || resident.full_name || '';
        hideResults();
    };

    input.addEventListener('input', function() {
        const q = String(input.value || '').trim();
        if (debounceTimer) clearTimeout(debounceTimer);

        // If user is typing, clear selected resident so required validation is correct.
        residentIdField.value = '';
        fullNameField.value = '';

        if (q.length < 2) {
            hideResults();
            return;
        }

        debounceTimer = setTimeout(() => {
            if (q === lastQuery) return;
            lastQuery = q;
            fetch(`${window.API_URL}officials.php?action=resident_search&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        hideResults();
                        return;
                    }
                    const rows = Array.isArray(d.data) ? d.data : [];
                    if (!rows.length) {
                        results.innerHTML = `<div class="list-group-item text-muted">No residents found</div>`;
                        results.style.display = 'block';
                        return;
                    }
                    results.innerHTML = rows.map(res => `
                        <button type="button" class="list-group-item list-group-item-action resident-result"
                            data-id="${escapeHtml(String(res.id))}"
                            data-name="${escapeHtml(res.full_name || '')}"
                            data-label="${escapeHtml(res.label || '')}">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold">${escapeHtml(res.full_name || '')}</div>
                                    <small class="text-muted">${escapeHtml(res.resident_code || '')}</small>
                                </div>
                                <span class="badge bg-light text-dark border">ID: ${escapeHtml(String(res.id))}</span>
                            </div>
                        </button>
                    `).join('');
                    results.style.display = 'block';
                })
                .catch(() => hideResults());
        }, 250);
    });

    results.addEventListener('click', function(e) {
        const btn = e.target.closest('.resident-result');
        if (!btn) return;
        selectResident({
            id: btn.getAttribute('data-id'),
            full_name: btn.getAttribute('data-name'),
            label: btn.getAttribute('data-label')
        });
    });

    document.addEventListener('click', function(e) {
        if (results.contains(e.target) || input.contains(e.target)) return;
        hideResults();
    });
}

function loadOfficials() {
    fetch(window.API_URL + 'officials.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                renderOfficials(d.data || []);
            } else {
                showOfficialsAlert('error', d.message || 'Failed to load officials');
            }
        })
        .catch(() => showOfficialsAlert('error', 'Error loading officials'));
}

function renderOfficials(rows) {
    const container = document.getElementById('officialsTiles');
    if (!container) return;

    if (!rows.length) {
        container.innerHTML = `
            <div class="officials-empty card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="officials-empty-icon mb-2"><i class="bi bi-people"></i></div>
                    <h5 class="mb-1">No officials yet</h5>
                    <p class="text-muted mb-3">Add the barangay core officials to appear here.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#officialModal" onclick="resetOfficialForm()">
                        <i class="bi bi-plus-circle"></i> Add Official
                    </button>
                </div>
            </div>`;
        return;
    }

    const normalizeStatus = s => String(s || '').toLowerCase() === 'active' ? 'active' : 'inactive';
    const sorted = [...rows].sort((a, b) => {
        const sa = normalizeStatus(a.status);
        const sb = normalizeStatus(b.status);
        if (sa !== sb) return sa === 'active' ? -1 : 1;
        return String(a.full_name || '').localeCompare(String(b.full_name || ''));
    });

    const byPos = {
        barangay_captain: [],
        kagawad: [],
        sk_chairperson: [],
        secretary: [],
        treasurer: []
    };
    sorted.forEach(o => {
        const key = String(o.position || '').toLowerCase();
        if (byPos[key]) byPos[key].push(o);
    });

    const groups = [
        { key: 'barangay_captain', title: 'Punong Barangay (Captain)', slots: 1 },
        { key: 'kagawad', title: 'Sangguniang Barangay Members (Kagawad)', slots: 7 },
        { key: 'sk_chairperson', title: 'SK Chairperson', slots: 1 },
        { key: 'secretary', title: 'Secretary', slots: 1 },
        { key: 'treasurer', title: 'Treasurer', slots: 1 }
    ];

    container.innerHTML = groups.map(g => {
        const items = byPos[g.key] || [];
        const activeItems = items.filter(o => normalizeStatus(o.status) === 'active');
        const inactiveItems = items.filter(o => normalizeStatus(o.status) !== 'active');

        const slotTiles = [];
        for (let i = 0; i < g.slots; i++) {
            const o = activeItems[i] || null;
            slotTiles.push(o ? renderOfficialTile(o) : renderVacantTile(g.key, g.title, i + 1));
        }

        // For Kagawad: show extra active tiles beyond slots (shouldn't happen, but safe)
        const extraActive = activeItems.slice(g.slots).map(renderOfficialTile).join('');

        const inactiveBlock = inactiveItems.length ? `
            <details class="officials-history mt-3">
                <summary class="text-muted">Show inactive/previous (${inactiveItems.length})</summary>
                <div class="officials-group-grid officials-history-grid mt-2">
                    ${inactiveItems.map(renderOfficialTile).join('')}
                </div>
            </details>
        ` : '';

        return `
            <div class="officials-group">
                <div class="officials-group-head">
                    <div class="officials-group-title">
                        <i class="bi ${escapeHtml(getPositionIcon(g.key))} me-2"></i>${escapeHtml(g.title)}
                    </div>
                    <div class="officials-group-meta text-muted">
                        ${g.key === 'kagawad' ? `${activeItems.length}/${g.slots} active` : (activeItems[0] ? 'Assigned' : 'Vacant')}
                    </div>
                </div>
                <div class="officials-group-grid">
                    ${slotTiles.join('')}
                    ${extraActive}
                </div>
                ${inactiveBlock}
            </div>
        `;
    }).join('');
}

function renderOfficialTile(o) {
    const term = (o.term_start || o.term_end) ? `${o.term_start || '—'} to ${o.term_end || '—'}` : '—';
    const isActive = String(o.status || '').toLowerCase() === 'active';
    const statusBadge = isActive ? 'bg-success' : 'bg-secondary';
    const icon = getPositionIcon(o.position);
    const isCaptain = String(o.position || '').toLowerCase() === 'barangay_captain';
    const isSuper = isSuperAdminClient();
    const deleteButton = isCaptain
        ? (isSuper
            ? `<button class="btn btn-sm btn-outline-danger" title="Remove (Super Admin only)" aria-label="Remove" onclick="deleteOfficial(${o.id})">
                    <i class="bi bi-trash"></i>
               </button>`
            : `<button class="btn btn-sm btn-outline-secondary" title="Protected (Super Admin only)" aria-label="Protected" disabled>
                    <i class="bi bi-shield-lock"></i>
               </button>`)
        : `<button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove" onclick="deleteOfficial(${o.id})">
                <i class="bi bi-trash"></i>
           </button>`;
    return `
        <div class="official-tile card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="official-icon ${escapeHtml(getPositionColorClass(o.position))}">
                            <i class="bi ${escapeHtml(icon)}"></i>
                        </div>
                        <div class="official-meta">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="official-name mb-0">${escapeHtml(o.full_name || '')}</h5>
                                <span class="badge ${statusBadge}">${escapeHtml(formatStatus(o.status))}</span>
                            </div>
                            <div class="official-sub text-muted">
                                <span class="badge bg-info-subtle text-info-emphasis me-1">${escapeHtml(formatPosition(o.position || ''))}</span>
                                <span class="official-term"><i class="bi bi-calendar2-week me-1"></i>${escapeHtml(term)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="official-actions text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit" onclick="editOfficial(${o.id})">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        ${deleteButton}
                    </div>
                </div>
                <div class="official-foot mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">ID: ${escapeHtml(String(o.id))}</small>
                    <small class="text-muted"><i class="bi bi-person-check me-1"></i>Resident-linked</small>
                </div>
            </div>
        </div>
    `;
}

function renderVacantTile(positionKey, positionTitle, slotNo) {
    const icon = getPositionIcon(positionKey);
    const slotLabel = positionKey === 'kagawad' ? `Slot ${slotNo}` : 'Vacant';
    const lockedCaptain = positionKey === 'barangay_captain' && !isSuperAdminClient();
    const actionBtn = lockedCaptain
        ? `<button class="btn btn-sm btn-outline-secondary" title="Restricted" aria-label="Restricted" disabled>
                <i class="bi bi-lock"></i>
           </button>`
        : `<button class="btn btn-sm btn-primary" title="Add" aria-label="Add" data-bs-toggle="modal" data-bs-target="#officialModal" onclick="resetOfficialForm(); setFixedPosition('${escapeHtml(String(positionKey))}');">
                <i class="bi bi-plus"></i>
           </button>`;
    return `
        <div class="official-tile official-tile-vacant card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="official-icon is-default">
                            <i class="bi ${escapeHtml(icon)}"></i>
                        </div>
                        <div class="official-meta">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="official-name mb-0">${escapeHtml(slotLabel)}</h5>
                                <span class="badge bg-light text-secondary border">Vacant</span>
                            </div>
                            <div class="official-sub text-muted">
                                <span class="badge bg-info-subtle text-info-emphasis me-1">${escapeHtml(positionTitle)}</span>
                                <span class="official-term"><i class="bi bi-plus-circle me-1"></i>Add an official</span>
                            </div>
                        </div>
                    </div>
                    <div class="official-actions text-nowrap">
                        ${actionBtn}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function editOfficial(id) {
    fetch(window.API_URL + 'officials.php?action=get&id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(d => {
            if (!d.success) return showOfficialsAlert('error', d.message || 'Failed to load official');
            const o = d.data;
            document.getElementById('officialId').value = o.id || '';
            document.getElementById('full_name').value = o.full_name || '';
            document.getElementById('resident_id').value = o.resident_id || '';
            const residentSearch = document.getElementById('resident_search');
            if (residentSearch) {
                residentSearch.value = o.full_name || '';
            }
            setFixedPosition(o.position || '');
            document.getElementById('term_start').value = o.term_start || '';
            document.getElementById('term_end').value = o.term_end || '';
            document.getElementById('status').value = o.status || 'active';
            document.getElementById('officialModalTitle').textContent = 'Edit Official';
            new bootstrap.Modal(document.getElementById('officialModal')).show();
        })
        .catch(() => showOfficialsAlert('error', 'Error loading official'));
}

function saveOfficial() {
    const form = document.getElementById('officialForm');
    const fd = new FormData(form);
    const id = fd.get('id');
    fd.append('action', id ? 'update' : 'create');
    const residentId = String(fd.get('resident_id') || '').trim();
    if (!residentId) {
        showOfficialsAlert('error', 'Please select a resident first.');
        return;
    }
    const position = String(fd.get('position') || '').trim();
    if (!position) {
        showOfficialsAlert('error', 'Please select a position.');
        return;
    }

    fetch(window.API_URL + 'officials.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showOfficialsAlert('success', d.message || 'Saved');
                bootstrap.Modal.getInstance(document.getElementById('officialModal')).hide();
                resetOfficialForm();
                loadOfficials();
            } else {
                showOfficialsAlert('error', d.message || 'Failed to save');
            }
        })
        .catch(() => showOfficialsAlert('error', 'Error saving official'));
}

function deleteOfficial(id) {
    if (!confirm('Remove this official?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(window.API_URL + 'officials.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showOfficialsAlert('success', d.message || 'Removed');
                loadOfficials();
            } else {
                showOfficialsAlert('error', d.message || 'Failed to remove');
            }
        })
        .catch(() => showOfficialsAlert('error', 'Error removing official'));
}

function resetOfficialForm() {
    const form = document.getElementById('officialForm');
    if (form) form.reset();
    const id = document.getElementById('officialId');
    if (id) id.value = '';
    const residentId = document.getElementById('resident_id');
    if (residentId) residentId.value = '';
    setFixedPosition('');
    const title = document.getElementById('officialModalTitle');
    if (title) title.textContent = 'Add Official';
}

function showOfficialsAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
    alertDiv.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    const container = document.querySelector('.container-fluid');
    if (container) container.insertBefore(alertDiv, container.firstChild);
    setTimeout(() => alertDiv.remove(), 5000);
}

function formatStatus(s) {
    const v = String(s || '').toLowerCase();
    return v ? v.charAt(0).toUpperCase() + v.slice(1) : '';
}

function formatPosition(p) {
    const v = String(p || '').toLowerCase();
    if (v === 'barangay_captain') return 'Punong Barangay';
    if (v === 'kagawad') return 'Kagawad';
    if (v === 'sk_chairperson') return 'SK Chairperson';
    if (v === 'secretary') return 'Secretary';
    if (v === 'treasurer') return 'Treasurer';
    return p;
}

function getPositionIcon(position) {
    const v = String(position || '').toLowerCase();
    if (v === 'barangay_captain') return 'bi-shield-fill-check';
    if (v === 'kagawad') return 'bi-person-badge-fill';
    if (v === 'sk_chairperson') return 'bi-stars';
    if (v === 'secretary') return 'bi-journal-text';
    if (v === 'treasurer') return 'bi-cash-coin';
    return 'bi-person';
}

function getPositionColorClass(position) {
    const v = String(position || '').toLowerCase();
    if (v === 'barangay_captain') return 'is-captain';
    if (v === 'kagawad') return 'is-kagawad';
    if (v === 'sk_chairperson') return 'is-sk';
    if (v === 'secretary') return 'is-secretary';
    if (v === 'treasurer') return 'is-treasurer';
    return 'is-default';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}

