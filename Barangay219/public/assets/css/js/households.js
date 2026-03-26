if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

let currentViewHouseholdId = null;

/** @type {'streets' | 'households'} */
let householdNavLevel = 'streets';
/** API filter token: actual street text or "__EMPTY__" */
let selectedStreetToken = '';
let selectedStreetLabel = '';
/** Search text for household list (per-street view only); sent as API `q` */
let householdListQuery = '';

/** Street-level household list filters. */
let householdListFilters = {
    member_range: '',
    with_senior: false,
    with_minors: false,
    single_occupant: false,
    with_registered_voters: false,
    all_members_verified: false,
    with_missing_info: false,
    house_type: '',
    indigent_status: '',
    verification_status: '',
    residency_from: '',
    residency_to: '',
    sort_by: 'newest'
};

function showHouseholdToast(message, type) {
    type = type || 'success';
    const container = document.getElementById('householdToastContainer');
    if (!container) return;
    const id = 'toast-' + Date.now();
    const toastEl = document.createElement('div');
    toastEl.id = id;
    toastEl.className = 'toast align-items-center text-bg-' + (type === 'success' ? 'success' : 'danger') + ' border-0';
    toastEl.setAttribute('role', 'alert');
    const safeMsg = typeof message === 'string' ? (message.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')) : '';
    toastEl.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi bi-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' + safeMsg + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => { toastEl.remove(); });
}

const HOUSEHOLD_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('households', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('households', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('households', 'can_delete') : true
};

/** Ensures session cookies are sent with API calls (same origin). */
const HOUSEHOLD_FETCH_INIT = { credentials: 'same-origin' };

/**
 * Read JSON from a fetch Response. Handles HTML/error pages (failed JSON parse) and HTTP errors with JSON bodies.
 */
function parseHouseholdApiJson(r) {
    return r.text().then(function(raw) {
        var d = {};
        try {
            d = raw && raw.length ? JSON.parse(raw) : {};
        } catch (e) {
            var hint = 'Server returned a non-JSON response (often a login page or PHP error). Try refreshing the page.';
            if (r.status === 401 || r.status === 403) {
                hint = 'Session expired or access denied. Refresh the page and sign in again.';
            } else if (r.status >= 500) {
                hint = 'Server error. Check the PHP error log if this keeps happening.';
            }
            throw new Error(hint);
        }
        if (!r.ok) {
            throw new Error(d.message || ('Request failed (HTTP ' + r.status + ')'));
        }
        return d;
    });
}

/**
 * Always use getOrCreateInstance for #householdModal. Each `new bootstrap.Modal(el)` overwrites the
 * stored instance with a fresh object where _isShown is false, so hide() becomes a no-op while the dialog stays visible.
 */
function showHouseholdFormModal() {
    const el = document.getElementById('householdModal');
    if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    }
}

function hideHouseholdFormModal() {
    const el = document.getElementById('householdModal');
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.hide();
    window.setTimeout(function () {
        if (!el.classList.contains('show')) return;
        el.classList.remove('show');
        el.setAttribute('aria-hidden', 'true');
        el.removeAttribute('aria-modal');
        el.removeAttribute('role');
        el.style.display = 'none';
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(function (b) {
            b.remove();
        });
        try {
            modal.dispose();
        } catch (e) { /* ignore */ }
    }, 450);
}

function householdIndigentBadge(status) {
    if (status === 'indigent') {
        return '<span class="badge rounded-pill bg-white text-dark border border-warning">Indigent</span>';
    }
    if (status === 'non_indigent') {
        return '<span class="badge rounded-pill bg-white text-primary border border-primary">Non-indigent</span>';
    }
    return '<span class="text-muted">—</span>';
}

function memberMonthlyIncomeNote(m) {
    var occ = (m.occupation != null && m.occupation !== '') ? String(m.occupation).trim() : '';
    if (occ === '') {
        return '<span class="badge bg-light text-secondary border ms-2">—</span>';
    }
    return '<span class="badge bg-light text-dark border ms-2">' + escapeHtml(toTitleCase(occ)) + '</span>';
}

function refreshEditHouseholdModalData(householdId) {
    const id = parseInt(householdId, 10) || 0;
    if (id <= 0) return Promise.resolve();
    return fetch(window.API_URL + 'households.php?action=get&id=' + encodeURIComponent(String(id)), HOUSEHOLD_FETCH_INIT)
        .then(parseHouseholdApiJson)
        .then((d) => {
            if (!d.success || !d.data) return;
            const h = d.data;
            window.__editHouseholdLastPayload = h;
            renderEditModalMembersList(h);
            const tm = document.getElementById('total_members');
            if (tm) {
                tm.value = (h.total_members === null || typeof h.total_members === 'undefined') ? 0 : h.total_members;
            }
            loadResidentsForAddMember(id, (h.members || []).map((m) => m.id));
            updateAddMemberRelationshipVisibility(h);
        });
}

/**
 * Group residents by family head (same rules as view household: family_head_resident_id, family_code, primary head bucket).
 */
function computeHouseholdHeadGroups(h) {
    const members = Array.isArray(h.members) ? h.members : [];
    const designatedHeadId = Number(h.family_head_id ?? 0);
    const householdFhCode = ((h.family_head_code ?? '').toString().trim() || '-');

    const isHead = (m) => {
        if (Number(m.id) === designatedHeadId) return true;
        const hmHead = m.hm_is_head;
        if (hmHead === 1 || hmHead === true || hmHead === '1') return true;
        const rel = (m.relationship_to_head ?? m.hm_relationship_to_head ?? '').toString().toLowerCase();
        if (rel.includes('head')) return true;
        return false;
    };

    const heads = members.filter(isHead);
    if (designatedHeadId > 0) {
        heads.sort((a, b) => (Number(b.id) === designatedHeadId ? 1 : 0) - (Number(a.id) === designatedHeadId ? 1 : 0));
    }
    const memberList = members.filter((m) => !isHead(m));

    const getFamilyCode = (m) => (m.family_code ?? '').toString().trim();
    const getMemberHeadRef = (m) => Number(m.family_head_resident_id || 0);
    const shownMemberIds = new Set();

    const headGroups = heads.map((head, idx) => {
        const headResidentId = Number(head.id);
        const headOwnFhc = (head.family_head_code ?? '').toString().trim();
        const headDisplayCode = (headOwnFhc !== '' && headOwnFhc !== '-') ? headOwnFhc : householdFhCode;
        const headFc = getFamilyCode(head);
        const headMembers = memberList.filter((m) => {
            if (shownMemberIds.has(Number(m.id))) return false;
            const ref = getMemberHeadRef(m);
            if (ref > 0) {
                if (ref === headResidentId) { shownMemberIds.add(Number(m.id)); return true; }
                return false;
            }
            if (headFc && getFamilyCode(m) === headFc) { shownMemberIds.add(Number(m.id)); return true; }
            return false;
        });
        const isPrimary = Number(head.id) === designatedHeadId;
        return { head, idx, headDisplayCode, headMembers, isPrimary, headResidentId };
    });

    const remainingMembers = memberList.filter((m) => !shownMemberIds.has(Number(m.id)));
    if (remainingMembers.length > 0 && headGroups.length > 0) {
        const primaryGroup = headGroups.find((g) => g.isPrimary) || headGroups[0];
        remainingMembers.forEach((m) => {
            primaryGroup.headMembers.push(m);
            shownMemberIds.add(Number(m.id));
        });
    }
    const ungrouped = memberList.filter((m) => !shownMemberIds.has(Number(m.id)));

    return {
        members,
        heads,
        headGroups,
        ungrouped,
        designatedHeadId,
        householdFhCode,
        isHead
    };
}

function editModalMemberTableHead() {
    return `<thead class="table-light"><tr>
        <th>Name</th>
        <th>Role</th>
        <th>Relationship</th>
        <th>Sex</th>
        <th>Age</th>
        <th class="text-end">Actions</th>
    </tr></thead>`;
}

function editModalRowHtml(m, asHead, ctx) {
    const allowEdit = !!(ctx && ctx.allowEdit);
    const transferTargetHeadId = ctx && ctx.transferTargetHeadId ? Number(ctx.transferTargetHeadId) : 0;
    const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
    const role = asHead
        ? '<span class="badge bg-primary">Head</span>'
        : '<span class="badge bg-light text-dark border">Member</span>';
    const rel = (m.relationship_to_head ?? m.hm_relationship_to_head ?? '').toString().trim();
    const relDisp = asHead ? '—' : escapeHtml(toTitleCase(rel || '—'));
    const sexRaw = (m.sex || m.gender || '').toString().trim();
    const sexDisp = sexRaw ? escapeHtml(toTitleCase(sexRaw)) : '—';
    const dob = m.birth_date || m.date_of_birth;
    const ageVal = calculateAge(dob);
    const ageDisp = typeof ageVal === 'number' && !Number.isNaN(ageVal) ? String(ageVal) : '—';

    let actionsHtml = '';
    if (allowEdit) {
        if (asHead) {
            actionsHtml = `<button type="button" class="action-icon-btn action-delete" title="Remove" aria-label="Remove" onclick="removeMember(${Number(m.id)})"><i class="bi bi-person-dash"></i></button>`;
        } else {
            const canTransfer = transferTargetHeadId > 0 && residentMeetsMinimumHeadAge(m);
            const transferBtn = canTransfer
                ? `<button type="button" class="action-icon-btn" title="Transfer Head" aria-label="Transfer Head" onclick="transferHeadTo(${Number(m.id)}, ${transferTargetHeadId})"><i class="bi bi-person-badge"></i></button>`
                : '';
            const removeBtn = `<button type="button" class="action-icon-btn action-delete" title="Remove" aria-label="Remove" onclick="removeMember(${Number(m.id)})"><i class="bi bi-person-dash"></i></button>`;
            actionsHtml = transferBtn + removeBtn;
        }
    }

    return `<tr><td>${escapeHtml(toTitleCase(name))}</td><td>${role}</td><td>${relDisp}</td><td>${sexDisp}</td><td>${ageDisp}</td><td class="text-end text-nowrap household-detail-actions">${actionsHtml}</td></tr>`;
}

function getEditHouseholdPendingAdds() {
    if (!Array.isArray(window.__editHouseholdPendingAdds)) {
        window.__editHouseholdPendingAdds = [];
    }
    return window.__editHouseholdPendingAdds;
}

function clearEditHouseholdPendingAdds() {
    window.__editHouseholdPendingAdds = [];
}

function editModalPendingRowHtml(p) {
    const rel = escapeHtml(toTitleCase((p.relationship || '').trim() || '—'));
    const name = escapeHtml((p.displayName || '').trim() || '—');
    return `<tr><td>${name}</td><td><span class="badge bg-light text-dark border">Member</span> <span class="badge bg-secondary">Pending save</span></td><td>${rel}</td><td>—</td><td>—</td><td class="text-muted small text-end">—</td></tr>`;
}

function appendPendingMembersSectionHtml(options) {
    const pending = getEditHouseholdPendingAdds();
    if (!pending.length) return '';
    const onlyPending = options && options.onlyPending;
    const wrapClass = onlyPending ? 'px-2 px-md-3 pb-3 pt-2' : 'mt-3 pt-2 border-top px-2 px-md-3 pb-3';
    const rows = pending.map(editModalPendingRowHtml).join('');
    const tableInner = `<table class="table table-sm table-hover mb-0 hh-edit-members-table">${editModalMemberTableHead()}<tbody>${rows}</tbody></table>`;
    return `<div class="${wrapClass}">
        <p class="small text-warning mb-2"><i class="bi bi-hourglass-split me-1"></i>Not saved yet — included when you click <strong>Save household details</strong>.</p>
        ${tableInner}
    </div>`;
}

function flushPendingMemberAdds(householdId) {
    const pending = getEditHouseholdPendingAdds();
    if (!pending.length) return Promise.resolve({ ok: true });
    const hid = parseInt(householdId, 10) || 0;
    if (hid <= 0) return Promise.resolve({ ok: false, message: 'Invalid household' });

    function addNext() {
        if (!pending.length) {
            return Promise.resolve({ ok: true });
        }
        const item = pending[0];
        const fd = new FormData();
        fd.append('action', 'add_member');
        fd.append('household_id', String(hid));
        fd.append('resident_id', String(item.resident_id));
        fd.append('relationship_to_head', item.relationship || '');
        return fetch(window.API_URL + 'households.php', Object.assign({ method: 'POST', body: fd }, HOUSEHOLD_FETCH_INIT))
            .then(parseHouseholdApiJson)
            .then((res) => {
                if (!res.success) {
                    return { ok: false, message: res.message || 'Failed to add a resident' };
                }
                pending.shift();
                return addNext();
            })
            .catch((err) => ({
                ok: false,
                message: (err && err.message) ? err.message : 'Network error while adding members'
            }));
    }

    return addNext();
}

function renderEditModalMembersList(h) {
    const host = document.getElementById('editHouseholdMembersHost');
    const emptyEl = document.getElementById('editHouseholdMembersEmpty');
    const wrap = document.getElementById('editHouseholdMembersTableWrap');
    if (!host) return;

    const pending = getEditHouseholdPendingAdds();
    const { members, headGroups, ungrouped, isHead, designatedHeadId } = computeHouseholdHeadGroups(h);
    const allowEdit = HOUSEHOLD_PERMS.canEdit;
    const rowCtx = (transferTargetHeadId) => ({ allowEdit, transferTargetHeadId });

    if (!members.length && !pending.length) {
        host.innerHTML = '';
        if (emptyEl) emptyEl.classList.remove('d-none');
        if (wrap) wrap.classList.add('d-none');
        return;
    }
    if (emptyEl) emptyEl.classList.add('d-none');
    if (wrap) wrap.classList.remove('d-none');

    if (!members.length && pending.length) {
        host.innerHTML = '<p class="small text-muted mb-2 pt-2">No saved members on file yet.</p>' +
            appendPendingMembersSectionHtml({ onlyPending: true });
        return;
    }

    const toName = (m) => {
        const n = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
        return toTitleCase(n);
    };

    const tableShell = (tbodyInner) =>
        `<table class="table table-sm table-hover mb-0 hh-edit-members-table">${editModalMemberTableHead()}<tbody>${tbodyInner}</tbody></table>`;

    let html = '';

    if (headGroups.length === 0) {
        const sorted = members.slice().sort((a, b) => {
            const na = `${a.last_name || ''}, ${a.first_name || ''}`.trim();
            const nb = `${b.last_name || ''}, ${b.first_name || ''}`.trim();
            return na.localeCompare(nb);
        });
        html += '<p class="small text-muted mb-2 mb-md-3">All household members</p>';
        html += tableShell(sorted.map((m) => {
            const asHead = isHead(m);
            const tt = !asHead && designatedHeadId > 0 ? designatedHeadId : 0;
            return editModalRowHtml(m, asHead, rowCtx(tt));
        }).join(''));
    } else if (headGroups.length === 1) {
        const g = headGroups[0];
        const sub = [editModalRowHtml(g.head, true, rowCtx(0))]
            .concat(
                g.headMembers
                    .slice()
                    .sort((a, b) => {
                        const na = `${a.last_name || ''}, ${a.first_name || ''}`.trim();
                        const nb = `${b.last_name || ''}, ${b.first_name || ''}`.trim();
                        return na.localeCompare(nb);
                    })
                    .map((m) => editModalRowHtml(m, false, rowCtx(Number(g.head.id))))
            )
            .join('');
        html += `<p class="small fw-semibold text-secondary mb-2">${escapeHtml(toName(g.head))} <span class="badge bg-primary">Head</span> <span class="text-muted fw-normal">(${escapeHtml(g.headDisplayCode)})</span></p>`;
        html += tableShell(sub);
        if (ungrouped.length) {
            html += '<p class="small fw-semibold text-secondary mt-3 mb-2">Unassigned to a head</p>';
            html += tableShell(
                ungrouped
                    .slice()
                    .sort((a, b) => {
                        const na = `${a.last_name || ''}, ${a.first_name || ''}`.trim();
                        const nb = `${b.last_name || ''}, ${b.first_name || ''}`.trim();
                        return na.localeCompare(nb);
                    })
                    .map((m) => editModalRowHtml(m, false, rowCtx(designatedHeadId > 0 ? designatedHeadId : 0)))
                    .join('')
            );
        }
    } else {
        html += '<p class="small text-muted mb-2">One table per head — switch tabs to see each group.</p>';
        html += '<ul class="nav household-head-tabs mb-2" role="tablist">';
        let panes = '<div class="tab-content">';
        headGroups.forEach((g, i) => {
            const tabId = 'hhEditHeadTab' + i;
            const paneId = 'hhEditHeadPane' + i;
            const active = i === 0;
            const label = toName(g.head);
            html += `<li class="nav-item" role="presentation">
                <button class="nav-link${active ? ' active' : ''}" id="${tabId}" data-bs-toggle="pill" data-bs-target="#${paneId}" type="button" role="tab" aria-controls="${paneId}" aria-selected="${active ? 'true' : 'false'}">
                    <span class="household-head-tab-label">${escapeHtml(label)}</span>
                </button>
            </li>`;
            const sub = [editModalRowHtml(g.head, true, rowCtx(0))]
                .concat(
                    g.headMembers
                        .slice()
                        .sort((a, b) => {
                            const na = `${a.last_name || ''}, ${a.first_name || ''}`.trim();
                            const nb = `${b.last_name || ''}, ${b.first_name || ''}`.trim();
                            return na.localeCompare(nb);
                        })
                        .map((m) => editModalRowHtml(m, false, rowCtx(Number(g.head.id))))
                )
                .join('');
            panes += `<div class="tab-pane fade${active ? ' show active' : ''}" id="${paneId}" role="tabpanel" aria-labelledby="${tabId}">${tableShell(sub)}</div>`;
        });
        if (ungrouped.length) {
            const ugTab = 'hhEditHeadTabUngrouped';
            const ugPane = 'hhEditHeadPaneUngrouped';
            html += `<li class="nav-item" role="presentation">
                <button class="nav-link" id="${ugTab}" data-bs-toggle="pill" data-bs-target="#${ugPane}" type="button" role="tab" aria-controls="${ugPane}" aria-selected="false">
                    <span class="household-head-tab-label">Unassigned</span>
                </button>
            </li>`;
            panes += `<div class="tab-pane fade" id="${ugPane}" role="tabpanel" aria-labelledby="${ugTab}">
                <p class="small text-muted mb-2">Members not linked to a specific head.</p>
                ${tableShell(
                    ungrouped
                        .slice()
                        .sort((a, b) => {
                            const na = `${a.last_name || ''}, ${a.first_name || ''}`.trim();
                            const nb = `${b.last_name || ''}, ${b.first_name || ''}`.trim();
                            return na.localeCompare(nb);
                        })
                        .map((m) => editModalRowHtml(m, false, rowCtx(designatedHeadId > 0 ? designatedHeadId : 0)))
                        .join('')
                )}
            </div>`;
        }
        html += '</ul>';
        panes += '</div>';
        html += panes;
    }

    host.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    initHouseholdTilesDelegation();
    renderBreadcrumb();
    loadStreets();
    applyHouseholdPermissions();
    initHouseholdFormFormatting();
    document.getElementById('householdForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveHousehold();
    });
    document.getElementById('householdModal').addEventListener('show.bs.modal', function() {
        // Edit mode builds #family_head_id in editHousehold(); loading here would replace it with
        // resident.php (often empty []) and wipe all options.
        const hid = document.getElementById('householdId');
        if (hid && String(hid.value || '').trim() !== '') {
            return;
        }
        loadResidentsForDropdown();
    });
    const addMemberEditBtn = document.getElementById('btnAddMemberEdit');
    if (addMemberEditBtn) {
        addMemberEditBtn.addEventListener('click', addMemberToHousehold);
    }
    const householdModalEl = document.getElementById('householdModal');
    if (householdModalEl) {
        householdModalEl.addEventListener('hidden.bs.modal', function () {
            clearEditHouseholdPendingAdds();
            delete window.__editHouseholdLastPayload;
            const collapseEl = document.getElementById('editHouseholdAddMemberCollapse');
            if (collapseEl && typeof bootstrap !== 'undefined') {
                const inst = bootstrap.Collapse.getInstance(collapseEl);
                if (inst) inst.hide();
            }
        });
    }
    const hhSearchInput = document.getElementById('hhHouseholdSearchInput');
    if (hhSearchInput) {
        hhSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runHouseholdListSearch();
            }
        });
    }
});

function initHouseholdTilesDelegation() {
    const tiles = document.getElementById('householdTiles');
    if (!tiles) return;
    tiles.addEventListener('click', function(e) {
        if (e.target.closest('.action-icon-btn')) return;
        const streetEl = e.target.closest('[data-hh-street-token]');
        if (streetEl) {
            openStreet(streetEl.getAttribute('data-hh-street-token') || '', streetEl.getAttribute('data-hh-street-label') || '');
            return;
        }
        const hhEl = e.target.closest('[data-hh-household-id]');
        if (hhEl) {
            const id = parseInt(hhEl.getAttribute('data-hh-household-id') || '0', 10);
            if (id > 0) viewHousehold(id);
        }
    });
    tiles.addEventListener('keydown', function(e) {
        const streetEl = e.target.closest('[data-hh-street-token]');
        if (streetEl && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            openStreet(streetEl.getAttribute('data-hh-street-token') || '', streetEl.getAttribute('data-hh-street-label') || '');
            return;
        }
        const hhEl = e.target.closest('[data-hh-household-id]');
        if (hhEl && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            const id = parseInt(hhEl.getAttribute('data-hh-household-id') || '0', 10);
            if (id > 0) viewHousehold(id);
        }
    });
}

function setTilesLoading() {
    const tiles = document.getElementById('householdTiles');
    if (!tiles) return;
    tiles.innerHTML = '<div class="household-tiles-loading text-center py-5"><div class="spinner-border text-primary"></div></div>';
}

function withTilesTransition(runFetch) {
    const wrap = document.getElementById('householdTilesWrap');
    if (wrap) wrap.classList.add('hh-tiles-dim');
    const done = () => { if (wrap) wrap.classList.remove('hh-tiles-dim'); };
    Promise.resolve(runFetch()).then(done).catch(done);
}

function updateHouseholdToolbarVisibility() {
    const searchWrap = document.getElementById('hhHouseholdSearchWrap');
    const backWrap = document.getElementById('hhBackBtnWrap');
    const show = householdNavLevel === 'households';
    if (searchWrap) searchWrap.classList.toggle('d-none', !show);
    if (backWrap) backWrap.classList.toggle('d-none', !show);
}

function renderBreadcrumb() {
    const ol = document.getElementById('hhBreadcrumb');
    if (!ol) return;
    if (householdNavLevel === 'streets') {
        ol.innerHTML = '<li class="breadcrumb-item active" aria-current="page">All Streets</li>';
        updateHouseholdToolbarVisibility();
        return;
    }
    const streetEsc = escapeHtml(selectedStreetLabel || 'Street');
    ol.innerHTML = `
        <li class="breadcrumb-item"><a href="#" id="hhCrumbStreets">All Streets</a></li>
        <li class="breadcrumb-item active" aria-current="page">${streetEsc}</li>
    `;
    const a = document.getElementById('hhCrumbStreets');
    if (a) {
        a.addEventListener('click', function(ev) {
            ev.preventDefault();
            householdNavBack();
        });
    }
    updateHouseholdToolbarVisibility();
}

function resetHouseholdListSearchFields() {
    householdListQuery = '';
    householdListFilters = {
        member_range: '',
        with_senior: false,
        with_minors: false,
        single_occupant: false,
        with_registered_voters: false,
        all_members_verified: false,
        with_missing_info: false,
        house_type: '',
        indigent_status: '',
        verification_status: '',
        residency_from: '',
        residency_to: '',
        sort_by: 'newest'
    };

    const input = document.getElementById('hhHouseholdSearchInput');
    if (input) input.value = '';

    resetHouseholdFilterModalFields();
}

function resetHouseholdFilterModalFields() {
    const fields = {
        'hhModalFilterMemberRange': '',
        'hhModalFilterHouseType': '',
        'hhModalFilterIndigentStatus': '',
        'hhModalFilterVerificationStatus': '',
        'hhModalFilterSortBy': 'newest',
        'hhModalFilterResidencyFrom': '',
        'hhModalFilterResidencyTo': ''
    };

    Object.entries(fields).forEach(function([id, value]) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    });

    const checkboxes = [
        'hhModalFilterWithSenior',
        'hhModalFilterWithMinors',
        'hhModalFilterSingleOccupant',
        'hhModalFilterWithRegisteredVoters',
        'hhModalFilterAllMembersVerified',
        'hhModalFilterWithMissingInfo'
    ];

    checkboxes.forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
}

function collectHouseholdListFiltersFromUI() {
    const memberRange = document.getElementById('hhModalFilterMemberRange');
    const houseType = document.getElementById('hhModalFilterHouseType');
    const indigentStatus = document.getElementById('hhModalFilterIndigentStatus');
    const verificationStatus = document.getElementById('hhModalFilterVerificationStatus');
    const sortBy = document.getElementById('hhModalFilterSortBy');
    const residencyFrom = document.getElementById('hhModalFilterResidencyFrom');
    const residencyTo = document.getElementById('hhModalFilterResidencyTo');
    const withSenior = document.getElementById('hhModalFilterWithSenior');
    const withMinors = document.getElementById('hhModalFilterWithMinors');
    const singleOccupant = document.getElementById('hhModalFilterSingleOccupant');
    const withRegisteredVoters = document.getElementById('hhModalFilterWithRegisteredVoters');
    const allMembersVerified = document.getElementById('hhModalFilterAllMembersVerified');
    const withMissingInfo = document.getElementById('hhModalFilterWithMissingInfo');

    householdListFilters.member_range = memberRange ? (memberRange.value || '') : '';
    householdListFilters.house_type = houseType ? (houseType.value || '') : '';
    householdListFilters.indigent_status = indigentStatus ? (indigentStatus.value || '') : '';
    householdListFilters.verification_status = verificationStatus ? (verificationStatus.value || '') : '';
    householdListFilters.residency_from = residencyFrom ? (residencyFrom.value || '') : '';
    householdListFilters.residency_to = residencyTo ? (residencyTo.value || '') : '';
    householdListFilters.sort_by = sortBy ? (sortBy.value || 'newest') : 'newest';
    householdListFilters.with_senior = !!(withSenior && withSenior.checked);
    householdListFilters.with_minors = !!(withMinors && withMinors.checked);
    householdListFilters.single_occupant = !!(singleOccupant && singleOccupant.checked);
    householdListFilters.with_registered_voters = !!(withRegisteredVoters && withRegisteredVoters.checked);
    householdListFilters.all_members_verified = !!(allMembersVerified && allMembersVerified.checked);
    householdListFilters.with_missing_info = !!(withMissingInfo && withMissingInfo.checked);
}

function applyHouseholdFilters() {
    collectHouseholdListFiltersFromUI();
    const modalEl = document.getElementById('hhFilterModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
    withTilesTransition(() => Promise.resolve(loadHouseholds()));
}

function runHouseholdListSearch() {
    if (householdNavLevel !== 'households') {
        return;
    }
    const input = document.getElementById('hhHouseholdSearchInput');
    householdListQuery = (input && input.value) ? input.value.trim() : '';
    collectHouseholdListFiltersFromUI();
    withTilesTransition(() => Promise.resolve(loadHouseholds()));
}

function clearHouseholdListSearch() {
    if (householdNavLevel !== 'households') return;
    resetHouseholdListSearchFields();
    withTilesTransition(() => Promise.resolve(loadHouseholds()));
}

function householdNavBack() {
    householdNavLevel = 'streets';
    selectedStreetToken = '';
    selectedStreetLabel = '';
    resetHouseholdListSearchFields();
    renderBreadcrumb();
    withTilesTransition(() => Promise.resolve(loadStreets()));
}

function openStreet(token, label) {
    selectedStreetToken = token || '';
    selectedStreetLabel = label || '';
    householdNavLevel = 'households';
    resetHouseholdListSearchFields();
    renderBreadcrumb();
    withTilesTransition(() => Promise.resolve(loadHouseholds()));
}

function refreshCurrentView() {
    if (householdNavLevel === 'streets') {
        return loadStreets();
    }
    return loadHouseholds();
}

function loadStreets() {
    const tiles = document.getElementById('householdTiles');
    if (!tiles) return Promise.resolve();

    const params = new URLSearchParams({ action: 'list_streets' });

    return fetch(window.API_URL + 'households.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            if (!tiles.parentElement) return;
            const rows = d.success && Array.isArray(d.data) ? d.data : [];
            if (d.success && rows.length) {
                let totalHH = 0;
                const cards = rows.map(s => {
                    const count = Number(s.household_count ?? 0);
                    totalHH += count;
                    const token = (s.filter_token != null ? String(s.filter_token) : '');
                    const label = (s.street_label != null ? String(s.street_label) : '');
                    const barangay = (s.barangay || '').toString().trim();
                    const subLine = barangay
                        ? escapeHtml(toTitleCase(barangay))
                        : 'Households grouped by registered street';
                    const keyDd = token === '__EMPTY__'
                        ? '<span class="text-muted">Not specified</span>'
                        : escapeHtml(toTitleCase(token));
                    return `
                        <div class="household-tile street-tile card shadow-sm"
                             role="button"
                             tabindex="0"
                             data-hh-street-token="${escapeHtml(token)}"
                             data-hh-street-label="${escapeHtml(label)}">
                            <div class="tile-top">
                                <div class="tile-title">
                                    <div class="tile-icon"><i class="bi bi-signpost-2"></i></div>
                                    <div class="min-w-0">
                                        <p class="tile-name mb-0">${escapeHtml(label === '(No street on file)' ? label : toTitleCase(label))}</p>
                                        <p class="tile-sub mb-0">${subLine}</p>
                                    </div>
                                </div>
                                <span class="badge bg-primary">${count} household${count === 1 ? '' : 's'}</span>
                            </div>
                            <div class="tile-body">
                                <dl class="tile-meta">
                                    <div>
                                        <dt>Barangay</dt>
                                        <dd>${barangay ? escapeHtml(toTitleCase(barangay)) : '—'}</dd>
                                    </div>
                                    <div>
                                        <dt>Street</dt>
                                        <dd>${keyDd}</dd>
                                    </div>
                                </dl>
                                <p class="small text-muted mb-0"><i class="bi bi-chevron-right me-1"></i>Open to load households</p>
                            </div>
                        </div>`;
                }).join('');
                const summary = `<div class="hh-street-summary text-muted small mb-2" style="grid-column: 1 / -1;">Showing <strong>${rows.length}</strong> street${rows.length === 1 ? '' : 's'} · <strong>${totalHH}</strong> household${totalHH === 1 ? '' : 's'} total</div>`;
                tiles.innerHTML = summary + cards;
            } else if (d.success) {
                tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-muted py-5" style="min-height:280px;">No streets found. Add a household with a street name.</div>';
            } else {
                tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-danger py-5" style="min-height:280px;">' + escapeHtml(d.message || 'Unable to load streets') + '</div>';
            }
        })
        .catch(() => {
            if (tiles) tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-danger py-5" style="min-height:280px;">Error loading streets</div>';
        });
}

function newHouseholdForContext() {
    if (!HOUSEHOLD_PERMS.canCreate) { alert('Access denied'); return; }
    resetForm();
    document.getElementById('householdModalTitle').textContent = 'New Household';
    const reg = document.getElementById('registration_date');
    if (reg && !reg.value) reg.value = formatDateInput(new Date());
    const totalMembersGroup = document.getElementById('totalMembersGroup');
    if (totalMembersGroup) totalMembersGroup.style.display = 'none';
    const memCardNew = document.getElementById('editHouseholdMembersCard');
    if (memCardNew) memCardNew.classList.add('d-none');
    if (householdNavLevel === 'households' && selectedStreetToken && selectedStreetToken !== '__EMPTY__') {
        setStreetSelectValue(selectedStreetToken);
    }
    showHouseholdFormModal();
    loadResidentsForDropdown();
}

function formatDateInput(date) {
    const d = new Date(date);
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + mm + '-' + dd;
}

function applyHouseholdPermissions() {
    const createBtn = document.getElementById('btnCreateHousehold');
    if (createBtn) createBtn.style.display = HOUSEHOLD_PERMS.canCreate ? '' : 'none';
    if (!HOUSEHOLD_PERMS.canEdit) {
        const addMemberBtn = document.getElementById('btnAddMemberEdit');
        if (addMemberBtn) addMemberBtn.style.display = 'none';
        const toggleAdd = document.getElementById('btnToggleAddMemberEdit');
        if (toggleAdd) toggleAdd.style.display = 'none';
    }
}

function newHousehold() {
    newHouseholdForContext();
}

function loadHouseholds() {
    const params = new URLSearchParams({ action: 'list' });
    if (householdNavLevel === 'households' && selectedStreetToken !== '') {
        params.append('street', selectedStreetToken);
    }
    if (householdListQuery) {
        params.append('q', householdListQuery);
    }

    if (householdListFilters.member_range) {
        params.append('member_range', householdListFilters.member_range);
    }
    if (householdListFilters.with_senior) {
        params.append('with_senior', '1');
    }
    if (householdListFilters.with_minors) {
        params.append('with_minors', '1');
    }
    if (householdListFilters.single_occupant) {
        params.append('single_occupant', '1');
    }
    if (householdListFilters.house_type) {
        params.append('house_type', householdListFilters.house_type);
    }
    if (householdListFilters.indigent_status) {
        params.append('indigent_status', householdListFilters.indigent_status);
    }
    if (householdListFilters.sort_by) {
        params.append('sort_by', householdListFilters.sort_by);
    }
    if (householdListFilters.with_registered_voters) {
        params.append('with_registered_voters', '1');
    }
    if (householdListFilters.all_members_verified) {
        params.append('all_members_verified', '1');
    }
    if (householdListFilters.with_missing_info) {
        params.append('with_missing_info', '1');
    }
    if (householdListFilters.verification_status) {
        params.append('verification_status', householdListFilters.verification_status);
    }
    if (householdListFilters.residency_from) {
        params.append('residency_from', householdListFilters.residency_from);
    }
    if (householdListFilters.residency_to) {
        params.append('residency_to', householdListFilters.residency_to);
    }

    return fetch(window.API_URL + 'households.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tiles = document.getElementById('householdTiles');
            if (!tiles) return;

            const data = Array.isArray(d.data) ? d.data : (d.data && typeof d.data === 'object' ? Object.values(d.data) : []);
            if (d.success && data.length) {
                const onStreet = householdNavLevel === 'households' && selectedStreetToken !== '';
                const qNote = householdListQuery
                    ? ` · matching “${escapeHtml(householdListQuery)}”`
                    : '';
                const summary = onStreet
                    ? `<div class="hh-household-summary text-muted small mb-2" style="grid-column: 1 / -1;">${escapeHtml(selectedStreetLabel)} · <strong>${data.length}</strong> household${data.length === 1 ? '' : 's'}${qNote}</div>`
                    : '';
                tiles.innerHTML = summary + data.map(h => {
                    const id = Number(h.id);
                    const headName = (h.family_head_name || '').toString().trim();
                    const allHeadNames = (h.family_head_names || headName).toString().trim();
                    const head = toTitleCase(headName || allHeadNames || '');
                    const address = toTitleCase(h.address || '');
                    const hn = (h.house_number || '').toString().trim();
                    const st = (h.street || '').toString().trim();
                    const members = Number((h.total_members ?? 0));
                    const reg = formatDate(h.registration_date);
                    const hhCode = (h.household_id_code || '').trim();

                    const viewBtn = `<button type="button" class="action-icon-btn" title="View" aria-label="View" onclick="event.stopPropagation(); viewHousehold(${id})"><i class="bi bi-eye"></i></button>`;
                    const editBtn = HOUSEHOLD_PERMS.canEdit
                        ? `<button type="button" class="action-icon-btn" title="Edit" aria-label="Edit" onclick="event.stopPropagation(); editHousehold(${id})"><i class="bi bi-pencil-square"></i></button>`
                        : '';
                    const delBtn = HOUSEHOLD_PERMS.canDelete
                        ? `<button type="button" class="action-icon-btn action-delete" title="Delete" aria-label="Delete" onclick="event.stopPropagation(); deleteHousehold(${id})"><i class="bi bi-trash"></i></button>`
                        : '';

                    const subtitle = head ? `Head: ${head}` : 'No head assigned yet';
                    const addrTrunc = address ? formatTitleCaseTruncate(address, 70) : '(no address)';
                    let addrDisplay = addrTrunc;
                    if (onStreet && selectedStreetToken !== '__EMPTY__' && st && selectedStreetToken === st) {
                        if (hn) {
                            addrDisplay = `House ${toTitleCase(hn)}`;
                        } else {
                            addrDisplay = address ? addrTrunc : 'Same street';
                        }
                    }
                    const addrEllipsis = address.length > 70 && addrDisplay === addrTrunc;
                    const householdIdLabel = '<small class="text-muted d-block">Household ID</small>';
                    const householdIdBadge = `<span class="badge bg-white text-dark border">${escapeHtml(hhCode || '-')}</span>`;
                    const titleLine = hhCode ? escapeHtml(hhCode) : `Household ${id}`;
                    return `
                        <div class="household-tile card shadow-sm hh-tile-clickable" data-hh-household-id="${id}" role="button" tabindex="0">
                            <div class="tile-top">
                                <div class="tile-title">
                                    <div class="tile-icon"><i class="bi bi-house-heart"></i></div>
                                    <div class="min-w-0">
                                        <p class="tile-name mb-0">${titleLine}</p>
                                        <p class="tile-sub">${escapeHtml(subtitle)}</p>
                                        <div class="mt-1">${householdIdLabel}${householdIdBadge}</div>
                                    </div>
                                </div>
                                <span class="badge bg-success">Members: ${members}</span>
                            </div>
                            <div class="tile-body">
                                <dl class="tile-meta">
                                    <div>
                                        <dt>Address</dt>
                                        <dd>${escapeHtml(addrDisplay)}${addrEllipsis ? '...' : ''}</dd>
                                    </div>
                                    <div>
                                        <dt>Registered</dt>
                                        <dd>${escapeHtml(reg)}</dd>
                                    </div>
                                </dl>
                                <p class="small text-muted mb-0 d-md-none"><i class="bi bi-hand-index-thumb me-1"></i>Tap card for full details</p>
                                <div class="tile-actions">
                                    ${viewBtn}
                                    ${editBtn}
                                    ${delBtn}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                const emptyMsg = householdListQuery
                    ? 'No households match your search on this street.'
                    : 'No households found';
                tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-muted py-5" style="min-height:280px;">' + escapeHtml(emptyMsg) + '</div>';
            }
        })
        .catch(() => {
            const tiles = document.getElementById('householdTiles');
            if (tiles) tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-danger py-5" style="min-height:280px;">Error loading</div>';
        });
}

function formatStructureHouseTypeLabel(raw) {
    if (raw === null || raw === undefined || String(raw).trim() === '') {
        return '--';
    }
    const s = String(raw).trim();
    const slug = s
        .toLowerCase()
        .replace(/\//g, ' ')
        .replace(/-/g, '_')
        .replace(/\s+/g, '_')
        .replace(/_+/g, '_');
    const map = {
        concrete: 'Concrete',
        semi_concrete: 'Semi-Concrete',
        light_materials: 'Light Materials',
        apartment_boarding: 'Apartment / Boarding House',
        apartment_boarding_house: 'Apartment / Boarding House',
        townhouse_row: 'Townhouse / Row House',
        townhouse_row_house: 'Townhouse / Row House',
        informal_improvised: 'Informal / Improvised'
    };
    if (map[slug]) {
        return map[slug];
    }
    return toTitleCase(s.replace(/_/g, ' '));
}

function mapRegistrationHouseTypeToFormValue(raw) {
    if (!raw) return '';
    const s = String(raw).trim();
    const labelMap = {
        'Concrete': 'concrete',
        'Semi-Concrete': 'semi_concrete',
        'Light Materials': 'light_materials',
        'Apartment / Boarding House': 'apartment_boarding',
        'Townhouse / Row House': 'townhouse_row',
        'Informal / Improvised': 'informal_improvised'
    };
    if (labelMap[s]) return labelMap[s];
    const sLower = s.toLowerCase();
    for (const [label, slug] of Object.entries(labelMap)) {
        if (label.toLowerCase() === sLower) return slug;
    }
    const normSlug = sLower.replace(/-/g, '_').replace(/\s+/g, '_').replace(/_+/g, '_');
    const sel = document.getElementById('house_type');
    if (sel && normSlug && Array.from(sel.options).some(o => o.value === normSlug)) return normSlug;
    if (sel && s && Array.from(sel.options).some(o => o.value === s)) return s;
    return '';
}

function mapHousingStatusToOwnershipValue(raw) {
    if (!raw) return '';
    const x = String(raw).toLowerCase().trim();
    if (x === 'owned') return 'owned';
    if (x === 'renting' || x === 'rented') return 'rented';
    return '';
}

function applyHeadHousingFromSelection(selected) {
    const typeSel = document.getElementById('house_type');
    const ownSel = document.getElementById('house_ownership');
    if (!typeSel && !ownSel) return;

    const hhHt = (selected.getAttribute('data-hh-house-type') || '').trim();
    const hhHs = (selected.getAttribute('data-hh-housing-status') || '').trim();
    const regHt = (selected.getAttribute('data-reg-house-type') || '').trim();
    const regOwn = (selected.getAttribute('data-reg-house-ownership') || '').trim();

    if (typeSel) {
        let v = mapRegistrationHouseTypeToFormValue(hhHt);
        if (!v) v = mapRegistrationHouseTypeToFormValue(regHt);
        typeSel.value = v || '';
    }
    if (ownSel) {
        let o = mapHousingStatusToOwnershipValue(hhHs);
        if (!o) o = mapHousingStatusToOwnershipValue(regOwn);
        ownSel.value = o || '';
    }
}

function loadResidentsForDropdown() {
    // Family head is stored in hidden #family_head_id (set on edit load); no resident picker in the modal.
}

function setStreetSelectValue(rawStreet) {
    const streetSel = document.getElementById('street');
    if (!streetSel || streetSel.tagName !== 'SELECT') return;
    const v = (rawStreet || '').toString().trim();
    if (!v) {
        streetSel.value = '';
        return;
    }
    const match = Array.from(streetSel.options).find(function(o) {
        return (o.value || '').trim() === v || (o.textContent || '').trim() === v;
    });
    if (match) {
        streetSel.value = match.value;
        return;
    }
    const o = document.createElement('option');
    o.value = v;
    o.textContent = toTitleCase(v);
    streetSel.appendChild(o);
    streetSel.value = v;
}

document.addEventListener('change', function (e) {
    const target = e.target;
    if (!target || target.id !== 'family_head_id' || target.tagName !== 'SELECT') return;
    const selected = target.selectedOptions && target.selectedOptions[0];
    if (!selected) return;

    const houseNumberInput = document.getElementById('house_number');
    const addressTextarea = document.getElementById('address');

    if (!selected.value || String(selected.value).trim() === '') {
        if (houseNumberInput) houseNumberInput.value = '';
        setStreetSelectValue('');
        if (addressTextarea) addressTextarea.value = '';
        const typeSel = document.getElementById('house_type');
        const ownSel = document.getElementById('house_ownership');
        if (typeSel) typeSel.value = '';
        if (ownSel) ownSel.value = '';
        return;
    }

    const fullAddress = selected.getAttribute('data-address') || '';
    const houseNumber = selected.getAttribute('data-house-number') || '';
    const street = selected.getAttribute('data-street') || '';

    if (houseNumberInput) {
        houseNumberInput.value = houseNumber;
    }
    setStreetSelectValue(street);
    if (addressTextarea) {
        addressTextarea.value = fullAddress;
    }
    applyHeadHousingFromSelection(selected);
});

function saveHousehold() {
    applyTitleCaseToForm();
    const householdIdEl = document.getElementById('householdId');
    const householdId = (householdIdEl.value || '').trim();
    const isUpdate = !!householdId;
    if (!householdId && !HOUSEHOLD_PERMS.canCreate) { alert('Access denied'); return; }
    if (householdId && !HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    const form = document.getElementById('householdForm');
    const formData = new FormData(form);
    formData.append('action', householdId ? 'update' : 'create');
    const total = form.total_members.value;
    if (total !== '') formData.set('total_members', Math.max(0, parseInt(total, 10) || 0));

    fetch(window.API_URL + 'households.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert('Error: ' + (d.message || 'Failed to save'));
                return;
            }
            if (isUpdate) {
                const hid = parseInt(householdId, 10);
                const pending = getEditHouseholdPendingAdds();
                const finishAndClose = () => {
                    refreshCurrentView();
                    hideHouseholdFormModal();
                    resetForm();
                };
                if (!pending.length) {
                    showHouseholdToast(d.message || 'Household saved.', 'success');
                    finishAndClose();
                    return;
                }
                flushPendingMemberAdds(hid).then((flushRes) => {
                    if (flushRes.ok) {
                        showHouseholdToast('Household details and pending members saved.', 'success');
                    } else {
                        showHouseholdToast('Household saved, but some members failed to add: ' + (flushRes.message || 'Unknown error'), 'danger');
                    }
                    finishAndClose();
                });
            } else {
                hideHouseholdFormModal();
                refreshCurrentView();
                form.reset();
                householdIdEl.value = '';
                clearEditHouseholdPendingAdds();
            }
        })
        .catch(() => alert('Error saving household'));
}

function viewHousehold(id) {
    currentViewHouseholdId = id;
    fetch(window.API_URL + 'households.php?action=get&id=' + id, HOUSEHOLD_FETCH_INIT)
        .then(parseHouseholdApiJson)
        .then(d => {
            if (!d.success || !d.data) {
                alert(d.message || 'Could not load household.');
                return;
            }
            const infoEl = document.getElementById('viewHouseholdInfo');
            const membersHost = document.getElementById('viewHouseholdMembers');
            const viewModalEl = document.getElementById('viewHouseholdModal');
            if (!infoEl || !membersHost || !viewModalEl) {
                alert('Household view is not available on this page.');
                return;
            }
            const h = d.data;
            const allowEditMembers = HOUSEHOLD_PERMS.canEdit;
            const {
                members,
                heads,
                headGroups,
                ungrouped,
                designatedHeadId,
                householdFhCode
            } = computeHouseholdHeadGroups(h);

            const toName = (m) => {
                const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
                return toTitleCase(name);
            };

            const householdTypeVal = (h.household_type || '').toString().trim();

            // Derive household type from a head's family group composition
            const deriveHouseholdType = (head, groupMembers) => {
                // Check registration type first
                const regType = (head.registration_household_type ?? '').toString().trim();
                if (regType) return regType;

                if (!groupMembers || groupMembers.length === 0) return 'Single Inhabitant';

                const rels = groupMembers.map(m =>
                    (m.relationship_to_head ?? m.hm_relationship_to_head ?? '').toString().toLowerCase()
                );
                const hasSpouse = rels.some(r => r.includes('spouse') || r.includes('wife') || r.includes('husband'));
                const hasFamily = rels.some(r =>
                    r.includes('son') || r.includes('daughter') || r.includes('child') ||
                    r.includes('parent') || r.includes('mother') || r.includes('father') ||
                    r.includes('sibling') || r.includes('brother') || r.includes('sister') ||
                    r.includes('grandchild') || r.includes('grandparent')
                );
                const hasNonRelative = rels.some(r =>
                    r.includes('boarder') || r.includes('helper') || r.includes('non-relative') ||
                    r.includes('tenant') || r.includes('shared')
                );

                if (hasFamily || (hasSpouse && groupMembers.length > 1)) return 'Family Household';
                if (hasSpouse && groupMembers.length === 1) return 'Couple Only';
                if (hasNonRelative && !hasFamily && !hasSpouse) return 'Non-Relative Household (Shared / Boarders)';
                if (groupMembers.length > 0) return 'Family Household';
                return householdTypeVal || '--';
            };

            const firstHead = heads.length > 0 ? heads[0] : null;
            const firstHeadName = firstHead ? toName(firstHead) : (h.family_head_name ? toTitleCase(h.family_head_name) : '-');

            const indigentEnabled = !!h.indigent_classification_enabled;
            let indigentSection = '';
            if (indigentEnabled) {
                const st = (h.computed_indigent_status || h.effective_indigent_status || '').toString();
                indigentSection = `<p class="mb-0"><strong>Status:</strong> ${householdIndigentBadge(st)}</p>`;
            }

            const residencyLength = formatResidencyLength(h.registration_date);

            infoEl.innerHTML = `
                <p><strong>Household ID Code:</strong> ${escapeHtml((h.household_id_code || '-'))}</p>
                <p><strong>Head:</strong> <span id="viewInfoHeadName">${escapeHtml(firstHeadName)}</span></p>
                <p><strong>House Type:</strong> ${escapeHtml(formatStructureHouseTypeLabel(h.house_type))}</p>
                <p><strong>Address:</strong> ${escapeHtml(toTitleCase(h.address || '-'))}</p>
                <p><strong>Total Members:</strong> ${members.length}</p>
                ${indigentSection}
            `;

            // Household type display removed by requirement (logic retained for internal derivations if needed)

            if (!members.length) {
                membersHost.innerHTML = '<p class="text-muted">No members yet.</p>';
            } else if (heads.length <= 1) {
                // Single head — simple flat layout, no tabs needed
                let html = '<h6 class="mb-3">Members</h6>';
                if (headGroups.length === 1) {
                    const g = headGroups[0];
                    html += `
                        <div class="d-flex align-items-center py-2 px-3 border rounded mb-2 bg-light">
                            <div>
                                <span class="fw-semibold">${escapeHtml(toName(g.head))}</span>
                                <span class="badge bg-primary ms-2">Head</span>
                                <small class="text-muted ms-2">(${escapeHtml(g.headDisplayCode)})</small>
                                ${memberMonthlyIncomeNote(g.head)}
                            </div>
                        </div>`;
                    html += buildMemberRows(g.headMembers, g.head.id, allowEditMembers);
                }
                html += buildUngroupedRows(ungrouped, designatedHeadId, allowEditMembers);
                membersHost.innerHTML = html;
            } else {
                // Multiple heads — tabbed layout
                let tabNav = '<ul class="nav household-head-tabs mb-3" role="tablist">';
                let tabContent = '<div class="tab-content">';

                headGroups.forEach((g, i) => {
                    const tabId = 'headTab' + i;
                    const paneId = 'headPane' + i;
                    const active = i === 0;
                    const headFullName = toName(g.head);
                    const headType = deriveHouseholdType(g.head, g.headMembers);

                    tabNav += `
                        <li class="nav-item" role="presentation">
                            <button class="nav-link${active ? ' active' : ''}" id="${tabId}" data-bs-toggle="pill" data-bs-target="#${paneId}" type="button" role="tab" aria-controls="${paneId}" aria-selected="${active}" data-head-name="${escapeHtml(headFullName)}" data-head-type="${escapeHtml(headType)}">
                                <span class="household-head-tab-label">${escapeHtml(headFullName)}</span>
                            </button>
                        </li>`;


                    tabContent += `
                        <div class="tab-pane fade${active ? ' show active' : ''}" id="${paneId}" role="tabpanel" aria-labelledby="${tabId}">
                            <div class="d-flex align-items-center py-2 px-3 border rounded mb-2 bg-light">
                                <div>
                                    <span class="fw-semibold">${escapeHtml(toName(g.head))}</span>
                                    <span class="badge bg-primary ms-2">Head</span>
                                    <small class="text-muted ms-2">(${escapeHtml(g.headDisplayCode)})</small>
                                    ${memberMonthlyIncomeNote(g.head)}
                                </div>
                            </div>
                            ${g.headMembers.length > 0
                                ? buildMemberRows(g.headMembers, g.head.id, allowEditMembers)
                                : '<p class="text-muted small ms-3 mb-2">No members under this head.</p>'
                            }
                        </div>`;
                });

                if (ungrouped.length > 0) {
                    const ugTabId = 'headTabUngrouped';
                    const ugPaneId = 'headPaneUngrouped';
                    tabNav += `
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="${ugTabId}" data-bs-toggle="pill" data-bs-target="#${ugPaneId}" type="button" role="tab" aria-controls="${ugPaneId}" aria-selected="false">
                                <span class="household-head-tab-label">Unassigned</span>
                            </button>
                        </li>`;
                    tabContent += `
                        <div class="tab-pane fade" id="${ugPaneId}" role="tabpanel" aria-labelledby="${ugTabId}">
                            <p class="text-muted small mb-2">Members not yet assigned to a specific head.</p>
                            ${buildUngroupedRows(ungrouped, designatedHeadId, allowEditMembers)}
                        </div>`;
                }

                tabNav += '</ul>';
                tabContent += '</div>';

                membersHost.innerHTML =
                    `<h6 class="mb-2">Family Groups <span class="badge bg-secondary">${heads.length} heads</span></h6>`
                    + tabNav + tabContent;

                // When a head tab is clicked, update the displayed head name dynamically
                document.querySelectorAll('#viewHouseholdMembers .nav-link[data-head-name]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const nameEl = document.getElementById('viewInfoHeadName');
                        if (nameEl) nameEl.textContent = this.getAttribute('data-head-name') || '-';
                    });
                });
            }

            let viewModalInstance = bootstrap.Modal.getInstance(viewModalEl);
            if (!viewModalInstance) {
                viewModalInstance = new bootstrap.Modal(viewModalEl);
                viewModalEl.addEventListener('hidden.bs.modal', function() {
                    if (window._householdSwitchingToConfirm) return;
                    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                });
            }
            viewModalInstance.show();
        })
        .catch(function(err) {
            alert((err && err.message) ? err.message : 'Error loading household');
        });
}

/** Designated household head must be at least 18 (aligned with API). */
function residentMeetsMinimumHeadAge(m) {
    const dob = m.birth_date || m.date_of_birth;
    if (!dob) return false;
    const age = calculateAge(dob);
    return typeof age === 'number' && !Number.isNaN(age) && age >= 18;
}

function buildMemberRows(memberArr, headId, allowEdit) {
    if (!memberArr.length) return '';
    return memberArr.map(m => {
        const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
        return `
            <div class="d-flex align-items-center py-2 px-3 border rounded mb-1 ms-3" style="border-left: 3px solid var(--bs-primary) !important;">
                <div>
                    <span>${escapeHtml(toTitleCase(name))}</span>
                    <span class="badge bg-light text-dark border ms-2">Member</span>
                    ${memberMonthlyIncomeNote(m)}
                </div>
            </div>`;
    }).join('');
}

function buildUngroupedRows(ungroupedArr, designatedHeadId, allowEdit) {
    if (!ungroupedArr.length) return '';
    let html = '<div class="mt-2"><div class="small text-muted mb-1 px-2">Unassigned members</div>';
    ungroupedArr.forEach(m => {
        const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
        html += `
            <div class="d-flex align-items-center py-2 px-3 border rounded mb-1">
                <div>
                    <span>${escapeHtml(toTitleCase(name))}</span>
                    <span class="badge bg-light text-dark border ms-2">Member</span>
                    ${memberMonthlyIncomeNote(m)}
                </div>
            </div>`;
    });
    html += '</div>';
    return html;
}

const ADD_MEMBER_PLACEHOLDER = '— Choose a resident —';

function rebuildAddMemberSelect(query) {
    const sel = document.getElementById('addMemberResidentEdit');
    if (!sel || !window.__addMemberEligibleRows) {
        return;
    }
    const q = (query || '').toLowerCase().trim().replace(/\s+/g, " ");
    const allRows = window.__addMemberEligibleRows || [];
    const rows = allRows.filter((row) => {
        if (!q) {
            return true;
        }
        const lab = row.label.toLowerCase();
        const code = (row.resident_code || "").toLowerCase();
        return lab.includes(q) || code.includes(q) || String(row.id).includes(q);
    });
    const prev = sel.value;
    sel.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = ADD_MEMBER_PLACEHOLDER;
    sel.appendChild(opt0);
    if (allRows.length === 0) {
        const optEmpty = document.createElement('option');
        optEmpty.value = '';
        optEmpty.disabled = true;
        optEmpty.textContent = 'No unassigned residents available';
        sel.appendChild(optEmpty);
        return;
    }
    if (rows.length === 0) {
        const optNone = document.createElement('option');
        optNone.value = '';
        optNone.disabled = true;
        optNone.textContent = 'No residents match your search';
        sel.appendChild(optNone);
        return;
    }
    rows.forEach((row) => {
        const opt = document.createElement("option");
        opt.value = String(row.id);
        opt.textContent = row.label;
        sel.appendChild(opt);
    });
    if (prev && Array.from(sel.options).some((o) => o.value === prev)) {
        sel.value = prev;
    }
}

function loadResidentsForAddMember(householdId, excludeIds) {
    const sel = document.getElementById("addMemberResidentEdit");
    const searchEl = document.getElementById("addMemberResidentSearch");
    if (!sel) {
        return;
    }
    sel.dataset.householdId = householdId;
    sel.innerHTML = '<option value="">' + ADD_MEMBER_PLACEHOLDER + '</option>';
    window.__addMemberEligibleRows = [];
    if (searchEl) {
        searchEl.value = "";
    }
    const pendingIds = getEditHouseholdPendingAdds().map((p) => Number(p.resident_id));
    const exclude = new Set((excludeIds || []).map((id) => Number(id)).concat(pendingIds));
    const hid = parseInt(householdId, 10) || 0;
    let url = window.API_URL + "resident.php?action=list&limit=5000";
    if (hid > 0) {
        url += "&not_in_household_id=" + encodeURIComponent(String(hid));
    }
    fetch(url, HOUSEHOLD_FETCH_INIT)
        .then((r) => r.json())
        .then((d) => {
            if (!d.success || !d.data || !d.data.residents) {
                window.__addMemberEligibleRows = [];
                rebuildAddMemberSelect('');
                return;
            }
            const rows = [];
            d.data.residents.forEach((r) => {
                const rid = Number(r.id);
                if (exclude.has(rid)) {
                    return;
                }
                const name = `${r.last_name || ""}, ${r.first_name || ""} ${r.middle_name || ""}`.trim();
                rows.push({
                    id: r.id,
                    label: toTitleCase(name),
                    resident_code: (r.resident_code || "").toString().trim()
                });
            });
            rows.sort((a, b) => a.label.localeCompare(b.label));
            window.__addMemberEligibleRows = rows;
            rebuildAddMemberSelect("");
            if (searchEl) {
                searchEl.oninput = function () {
                    rebuildAddMemberSelect((searchEl.value || "").trim());
                };
            }
        })
        .catch(() => {
            window.__addMemberEligibleRows = [];
            rebuildAddMemberSelect('');
        });
}

function updateAddMemberRelationshipVisibility(h) {
    const group = document.getElementById('addMemberRelationshipGroup');
    const relSel = document.getElementById('addMemberRelationshipToHead');
    const note = document.getElementById('addMemberRelationshipHeadNote');
    if (!group || !relSel) return;
    const head = Number(h?.family_head_id ?? 0);
    if (head <= 0) {
        group.classList.add('d-none');
        relSel.removeAttribute('required');
        relSel.value = '';
        if (note) note.classList.remove('d-none');
    } else {
        group.classList.remove('d-none');
        relSel.removeAttribute('required');
        if (note) note.classList.add('d-none');
    }
}

function addMemberToHousehold() {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    const sel = document.getElementById('addMemberResidentEdit');
    if (!sel) { alert('Member selector not available'); return; }
    const residentId = sel.value;
    const formHouseholdId = document.getElementById('householdId')?.value || '';
    const householdId = formHouseholdId || currentViewHouseholdId || sel.dataset.householdId;
    if (!householdId || String(householdId).trim() === '') {
        alert('Save the household first so it has an ID, or open Edit on an existing household before adding members.');
        return;
    }
    if (!residentId) {
        alert('Select a resident to add.');
        return;
    }
    const rid = Number(residentId);
    const relGroup = document.getElementById('addMemberRelationshipGroup');
    let relationship = '';
    if (relGroup && !relGroup.classList.contains('d-none')) {
        relationship = (document.getElementById('addMemberRelationshipToHead')?.value || '').trim();
        if (!relationship) {
            alert('Select relationship to head.');
            return;
        }
    }
    const payload = window.__editHouseholdLastPayload;
    const membersForUi = (payload && payload.members) ? payload.members : [];
    const existingIds = new Set(membersForUi.map((m) => Number(m.id)));
    getEditHouseholdPendingAdds().forEach((p) => existingIds.add(Number(p.resident_id)));
    if (existingIds.has(rid)) {
        alert('This resident is already in the household or in your pending list.');
        return;
    }
    const opt = sel.selectedOptions && sel.selectedOptions[0];
    const displayName = opt ? (opt.textContent || '').trim() : '';
    getEditHouseholdPendingAdds().push({
        resident_id: rid,
        relationship: relationship,
        displayName: displayName || ('Resident #' + rid)
    });
    showHouseholdToast('Queued — click Save household details to apply.', 'success');
    const hForList = payload || {
        members: [],
        family_head_id: document.getElementById('family_head_id')?.value || null
    };
    renderEditModalMembersList(hForList);
    loadResidentsForAddMember(parseInt(householdId, 10), membersForUi.map((m) => m.id));
    const relSel = document.getElementById('addMemberRelationshipToHead');
    if (relSel) relSel.value = '';
    sel.value = '';
    rebuildAddMemberSelect((document.getElementById('addMemberResidentSearch')?.value || '').trim());
}

/** If user cancels transfer/remove confirm, restore the view or edit household modal. */
let householdMemberActionParent = null;

let pendingTransferHead = null;

function getHouseholdIdForMemberActions() {
    const formHid = parseInt(document.getElementById('householdId')?.value || '', 10);
    if (formHid > 0) return formHid;
    const vid = parseInt(currentViewHouseholdId, 10) || 0;
    return vid;
}

function applyHouseholdDataToEditForm(h) {
    if (!h) return;
    window.__editHouseholdLastPayload = h;
    const headEl = document.getElementById('family_head_id');
    if (headEl) headEl.value = h.family_head_id != null && h.family_head_id !== '' ? String(h.family_head_id) : '';
    const tmEl = document.getElementById('total_members');
    if (tmEl) tmEl.value = (h.total_members === null || typeof h.total_members === 'undefined') ? 0 : h.total_members;
    renderEditModalMembersList(h);
    const hid = parseInt(h.id, 10);
    if (hid > 0) {
        loadResidentsForAddMember(hid, (h.members || []).map((m) => m.id));
    }
    updateAddMemberRelationshipVisibility(h);
}

function transferHeadTo(newHeadResidentId, oldHeadResidentId) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    pendingTransferHead = { newHeadResidentId, oldHeadResidentId };
    window._householdSwitchingToConfirm = true;
    const viewEl = document.getElementById('viewHouseholdModal');
    const editEl = document.getElementById('householdModal');
    const viewModal = viewEl ? bootstrap.Modal.getInstance(viewEl) : null;
    const showTransferModal = () => {
        window._householdSwitchingToConfirm = false;
        const el = document.getElementById('transferHeadModal');
        let m = bootstrap.Modal.getInstance(el);
        if (!m) m = new bootstrap.Modal(el);
        el.addEventListener('hidden.bs.modal', function onHidden() {
            if (pendingTransferHead) {
                pendingTransferHead = null;
                if (householdMemberActionParent === 'view' && viewModal && currentViewHouseholdId) {
                    viewModal.show();
                } else if (householdMemberActionParent === 'edit') {
                    showHouseholdFormModal();
                }
            }
            householdMemberActionParent = null;
            el.removeEventListener('hidden.bs.modal', onHidden);
        }, { once: true });
        m.show();
    };
    const editOpen = editEl && editEl.classList.contains('show');
    const viewOpen = viewEl && viewEl.classList.contains('show');
    if (editOpen) {
        householdMemberActionParent = 'edit';
        editEl.addEventListener('hidden.bs.modal', showTransferModal, { once: true });
        hideHouseholdFormModal();
    } else if (viewOpen && viewModal) {
        householdMemberActionParent = 'view';
        viewEl.addEventListener('hidden.bs.modal', showTransferModal, { once: true });
        viewModal.hide();
    } else {
        householdMemberActionParent = null;
        showTransferModal();
    }
}

function confirmTransferHead() {
    if (!pendingTransferHead) return;
    const returnContext = householdMemberActionParent;
    householdMemberActionParent = null;
    const { newHeadResidentId, oldHeadResidentId } = pendingTransferHead;
    pendingTransferHead = null;
    const btn = document.getElementById('transferHeadConfirmBtn');
    if (btn) btn.disabled = true;
    const hid = getHouseholdIdForMemberActions();
    bootstrap.Modal.getInstance(document.getElementById('transferHeadModal'))?.hide();
    const fd = new FormData();
    fd.append('action', 'assign_head_official');
    fd.append('household_id', String(hid));
    fd.append('new_head_resident_id', newHeadResidentId);
    if (oldHeadResidentId) fd.append('old_head_resident_id', oldHeadResidentId);
    function restoreAfterFailedConfirm() {
        if (returnContext === 'edit') {
            showHouseholdFormModal();
        } else if (returnContext === 'view' && currentViewHouseholdId) {
            const ve = document.getElementById('viewHouseholdModal');
            const vm = ve ? bootstrap.Modal.getInstance(ve) : null;
            if (vm) vm.show();
        }
    }
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert('Error: ' + (d.message || 'Failed'));
                restoreAfterFailedConfirm();
                return;
            }
            showHouseholdToast('Head role transferred successfully.');
            refreshCurrentView();
            if (returnContext === 'edit' && hid > 0) {
                fetch(window.API_URL + 'households.php?action=get&id=' + encodeURIComponent(String(hid)), HOUSEHOLD_FETCH_INIT)
                    .then(parseHouseholdApiJson)
                    .then((hd) => {
                        if (hd.success && hd.data) applyHouseholdDataToEditForm(hd.data);
                        showHouseholdFormModal();
                    })
                    .catch(() => {
                        showHouseholdFormModal();
                    });
            } else {
                viewHousehold(hid || currentViewHouseholdId);
            }
        })
        .catch(() => {
            alert('Error transferring head');
            restoreAfterFailedConfirm();
        })
        .finally(() => { if (btn) btn.disabled = false; });
}

let pendingRemoveMemberId = null;

function removeMember(residentId) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    pendingRemoveMemberId = residentId;
    window._householdSwitchingToConfirm = true;
    const viewEl = document.getElementById('viewHouseholdModal');
    const editEl = document.getElementById('householdModal');
    const viewModal = viewEl ? bootstrap.Modal.getInstance(viewEl) : null;
    const showRemoveModal = () => {
        window._householdSwitchingToConfirm = false;
        const el = document.getElementById('removeMemberModal');
        let m = bootstrap.Modal.getInstance(el);
        if (!m) m = new bootstrap.Modal(el);
        el.addEventListener('hidden.bs.modal', function onHidden() {
            if (pendingRemoveMemberId !== null) {
                pendingRemoveMemberId = null;
                if (householdMemberActionParent === 'view' && viewModal && currentViewHouseholdId) {
                    viewModal.show();
                } else if (householdMemberActionParent === 'edit') {
                    showHouseholdFormModal();
                }
            }
            householdMemberActionParent = null;
            el.removeEventListener('hidden.bs.modal', onHidden);
        }, { once: true });
        m.show();
    };
    const editOpen = editEl && editEl.classList.contains('show');
    const viewOpen = viewEl && viewEl.classList.contains('show');
    if (editOpen) {
        householdMemberActionParent = 'edit';
        editEl.addEventListener('hidden.bs.modal', showRemoveModal, { once: true });
        hideHouseholdFormModal();
    } else if (viewOpen && viewModal) {
        householdMemberActionParent = 'view';
        viewEl.addEventListener('hidden.bs.modal', showRemoveModal, { once: true });
        viewModal.hide();
    } else {
        householdMemberActionParent = null;
        showRemoveModal();
    }
}

function confirmRemoveMember() {
    if (pendingRemoveMemberId === null) return;
    const returnContext = householdMemberActionParent;
    householdMemberActionParent = null;
    const residentId = pendingRemoveMemberId;
    pendingRemoveMemberId = null;
    const btn = document.getElementById('removeMemberConfirmBtn');
    if (btn) btn.disabled = true;
    const hid = getHouseholdIdForMemberActions();
    bootstrap.Modal.getInstance(document.getElementById('removeMemberModal'))?.hide();
    const fd = new FormData();
    fd.append('action', 'remove_member');
    fd.append('resident_id', residentId);
    function restoreAfterFailedConfirm() {
        if (returnContext === 'edit') {
            showHouseholdFormModal();
        } else if (returnContext === 'view' && currentViewHouseholdId) {
            const ve = document.getElementById('viewHouseholdModal');
            const vm = ve ? bootstrap.Modal.getInstance(ve) : null;
            if (vm) vm.show();
        }
    }
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert('Error: ' + (d.message || 'Failed'));
                restoreAfterFailedConfirm();
                return;
            }
            showHouseholdToast('Member removed from household.');
            refreshCurrentView();
            if (returnContext === 'edit' && hid > 0) {
                fetch(window.API_URL + 'households.php?action=get&id=' + encodeURIComponent(String(hid)), HOUSEHOLD_FETCH_INIT)
                    .then(parseHouseholdApiJson)
                    .then((hd) => {
                        if (hd.success && hd.data) applyHouseholdDataToEditForm(hd.data);
                        showHouseholdFormModal();
                    })
                    .catch(() => {
                        showHouseholdFormModal();
                    });
            } else {
                viewHousehold(hid || currentViewHouseholdId);
            }
        })
        .catch(() => {
            alert('Error removing member');
            restoreAfterFailedConfirm();
        })
        .finally(() => { if (btn) btn.disabled = false; });
}

function editHousehold(id) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    clearEditHouseholdPendingAdds();
    fetch(window.API_URL + 'households.php?action=get&id=' + id, HOUSEHOLD_FETCH_INIT)
        .then(parseHouseholdApiJson)
        .then((householdData) => {
            if (!householdData.success || !householdData.data) {
                alert(householdData.message || 'Could not load household.');
                return;
            }
            const h = householdData.data;
            window.__editHouseholdLastPayload = h;
            const headEl = document.getElementById('family_head_id');
            const hidEl = document.getElementById('householdId');
            const addrEl = document.getElementById('address');
            const tmEl = document.getElementById('total_members');
            const regEl = document.getElementById('registration_date');
            const titleEl = document.getElementById('householdModalTitle');
            const modalEl = document.getElementById('householdModal');
            if (!headEl || !hidEl || !modalEl) {
                alert('Household form is not available.');
                return;
            }

            hidEl.value = h.id;
            headEl.value = h.family_head_id != null && h.family_head_id !== '' ? String(h.family_head_id) : '';
            if (addrEl) addrEl.value = toTitleCase(h.address || '');
            if (tmEl) tmEl.value = (h.total_members === null || typeof h.total_members === 'undefined') ? 0 : h.total_members;
            if (regEl) regEl.value = h.registration_date || '';
            const hn = document.getElementById('house_number');
            if (hn) hn.value = (h.house_number || '').toString();
            setStreetSelectValue(h.street || '');
            const ht = document.getElementById('house_type');
            if (ht) ht.value = (h.house_type || '').toString();
            const ho = document.getElementById('house_ownership');
            if (ho) {
                const hs = (h.housing_status || '').toString().toLowerCase().trim();
                if (hs === 'owned') ho.value = 'owned';
                else if (hs === 'renting' || hs === 'rented') ho.value = 'rented';
                else ho.value = '';
            }
            if (titleEl) titleEl.textContent = 'Edit Household';
            const totalMembersGroup = document.getElementById('totalMembersGroup');
            if (totalMembersGroup) totalMembersGroup.style.display = '';
            const memCard = document.getElementById('editHouseholdMembersCard');
            if (memCard) memCard.classList.remove('d-none');
            renderEditModalMembersList(h);
            loadResidentsForAddMember(h.id, (h.members || []).map(m => m.id));
            updateAddMemberRelationshipVisibility(h);
            showHouseholdFormModal();
        })
        .catch(() => alert('Error loading household'));
}

function deleteHousehold(id) {
    if (!HOUSEHOLD_PERMS.canDelete) { alert('Access denied'); return; }
    if (!confirm('Delete this household? Members will be unlinked.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) refreshCurrentView(); else alert(d.message || 'Error'); });
}

function resetForm() {
    document.getElementById('householdForm').reset();
    document.getElementById('householdId').value = '';
    document.getElementById('householdModalTitle').textContent = 'Edit Household';
    const memCard = document.getElementById('editHouseholdMembersCard');
    if (memCard) memCard.classList.add('d-none');
    const editHost = document.getElementById('editHouseholdMembersHost');
    if (editHost) editHost.innerHTML = '';
    const editEmpty = document.getElementById('editHouseholdMembersEmpty');
    const editWrap = document.getElementById('editHouseholdMembersTableWrap');
    if (editEmpty) editEmpty.classList.add('d-none');
    if (editWrap) editWrap.classList.remove('d-none');
    const sel = document.getElementById('addMemberResidentEdit');
    if (sel) {
        sel.innerHTML = '<option value="">' + ADD_MEMBER_PLACEHOLDER + '</option>';
        delete sel.dataset.householdId;
    }
    const addSearch = document.getElementById('addMemberResidentSearch');
    if (addSearch) {
        addSearch.value = '';
        addSearch.oninput = null;
    }
    const relSel = document.getElementById('addMemberRelationshipToHead');
    const relG = document.getElementById('addMemberRelationshipGroup');
    const relNote = document.getElementById('addMemberRelationshipHeadNote');
    if (relSel) {
        relSel.value = '';
        relSel.setAttribute('required', 'required');
    }
    if (relG) relG.classList.remove('d-none');
    if (relNote) relNote.classList.add('d-none');
    delete window.__addMemberEligibleRows;
    clearEditHouseholdPendingAdds();
    delete window.__editHouseholdLastPayload;
    const totalMembersGroup = document.getElementById('totalMembersGroup');
    if (totalMembersGroup) totalMembersGroup.style.display = '';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }

function formatResidencyLength(startDate) {
    if (!startDate) return '-';

    const raw = String(startDate).trim();
    if (!raw) return '-';

    const parsed = /^\d{4}-\d{2}-\d{2}$/.test(raw)
        ? new Date(raw + 'T00:00:00')
        : new Date(raw);

    if (Number.isNaN(parsed.getTime())) return '-';

    const today = new Date();
    if (parsed > today) {
        return 'Less than 1 month';
    }

    let years = today.getFullYear() - parsed.getFullYear();
    let months = today.getMonth() - parsed.getMonth();
    const dayDelta = today.getDate() - parsed.getDate();

    if (dayDelta < 0) {
        months -= 1;
    }
    if (months < 0) {
        years -= 1;
        months += 12;
    }

    if (years <= 0 && months <= 0) {
        return 'Less than 1 month';
    }

    const parts = [];
    if (years > 0) {
        parts.push(years + ' year' + (years === 1 ? '' : 's'));
    }
    if (months > 0) {
        parts.push(months + ' month' + (months === 1 ? '' : 's'));
    }

    return parts.join(', ');
}

function calculateAge(birthDate) {
    if (!birthDate) return '-';
    const today = new Date();
    const birth = new Date(birthDate);
    if (Number.isNaN(birth.getTime())) return '-';
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age >= 0 ? age : '-';
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

function formatTitleCaseTruncate(text, maxLen) {
    const titled = toTitleCase(text || '');
    return titled.substring(0, maxLen);
}

function attachTitleCaseOnBlur(input) {
    input.addEventListener('blur', function() {
        this.value = toTitleCase(this.value);
    });
}

function initHouseholdFormFormatting() {
    const address = document.getElementById('address');
    if (address) attachTitleCaseOnBlur(address);
}

function applyTitleCaseToForm() {
    const address = document.getElementById('address');
    if (address) address.value = toTitleCase(address.value);
}

function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
