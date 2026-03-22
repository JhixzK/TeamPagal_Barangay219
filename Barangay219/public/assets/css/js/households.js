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
    const input = document.getElementById('hhHouseholdSearchInput');
    if (input) input.value = '';
}

function runHouseholdListSearch() {
    if (householdNavLevel !== 'households') return;
    const input = document.getElementById('hhHouseholdSearchInput');
    householdListQuery = (input && input.value) ? input.value.trim() : '';
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
    const joinSection = document.getElementById('joinHouseholdSection');
    if (joinSection) joinSection.style.display = 'none';
    if (householdNavLevel === 'households' && selectedStreetToken && selectedStreetToken !== '__EMPTY__') {
        setStreetSelectValue(selectedStreetToken);
    }
    new bootstrap.Modal(document.getElementById('householdModal')).show();
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

function buildFamilyHeadOptionHtml(r) {
    const name = `${r.last_name || ''}, ${r.first_name || ''} ${r.middle_name || ''}`.trim();
    const fullAddress = (r.address || r.household_address || '').trim();
    const houseNumber = r.house_number || '';
    const street = r.street || '';
    const purok = r.purok_sitio || '';
    const regHt = (r.registration_house_type ?? '').toString();
    const regOwn = (r.registration_house_ownership ?? '').toString();
    const hhHt = (r.current_household_house_type ?? '').toString();
    const hhHs = (r.current_household_housing_status ?? '').toString();
    return `<option value="${r.id}"
                data-address="${escapeHtml(fullAddress)}"
                data-house-number="${escapeHtml(houseNumber)}"
                data-street="${escapeHtml(street)}"
                data-purok="${escapeHtml(purok)}"
                data-reg-house-type="${escapeHtml(regHt)}"
                data-reg-house-ownership="${escapeHtml(regOwn)}"
                data-hh-house-type="${escapeHtml(hhHt)}"
                data-hh-housing-status="${escapeHtml(hhHs)}"
            >${escapeHtml(toTitleCase(name))}</option>`;
}

/** Same rules as view household: designated head, hm_is_head, or relationship containing "head". */
function isHouseholdMemberHead(m, designatedHeadId) {
    if (Number(m.id) === designatedHeadId) return true;
    const hmHead = m.hm_is_head;
    if (hmHead === 1 || hmHead === true || hmHead === '1') return true;
    const rel = (m.relationship_to_head ?? m.hm_relationship_to_head ?? '').toString().toLowerCase();
    if (rel.includes('head')) return true;
    return false;
}

/**
 * All residents in the household for the family-head dropdown (designated head and co-heads first).
 * Includes everyone linked to the household, not only rows flagged as "head" in enrichment.
 */
function collectFamilyHeadSelectMemberRows(h) {
    const members = h.members || [];
    const designatedHeadId = Number(h.family_head_id ?? 0);
    return [...members].sort((a, b) => {
        const rank = (m) => {
            const id = Number(m.id);
            if (id === designatedHeadId) return 3;
            if (isHouseholdMemberHead(m, designatedHeadId)) return 2;
            return 1;
        };
        const diff = rank(b) - rank(a);
        if (diff !== 0) return diff;
        const na = `${a.last_name || ''}, ${a.first_name || ''}`.trim();
        const nb = `${b.last_name || ''}, ${b.first_name || ''}`.trim();
        return na.localeCompare(nb);
    });
}

/** Map household member row to the shape expected by buildFamilyHeadOptionHtml. */
function memberRowToFamilyHeadOptionShape(m) {
    return {
        id: m.id,
        last_name: m.last_name,
        first_name: m.first_name,
        middle_name: m.middle_name,
        address: m.address,
        household_address: m.address,
        house_number: m.house_number,
        street: m.street,
        purok_sitio: m.purok_sitio,
        registration_house_type: m.registration_house_type ?? m.registration_household_type ?? '',
        registration_house_ownership: m.registration_house_ownership ?? '',
        current_household_house_type: m.current_household_house_type ?? '',
        current_household_housing_status: m.current_household_housing_status ?? ''
    };
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
    const sel = document.getElementById('family_head_id');
    if (!sel) return;
    const hid = document.getElementById('householdId');
    if (hid && String(hid.value || '').trim() !== '') {
        return;
    }
    const currentVal = sel.value;
    fetch(window.API_URL + 'resident.php?action=list&limit=500&head_account_only=1', HOUSEHOLD_FETCH_INIT)
        .then(r => r.json())
        .then(d => {
            const list = d.success && d.data && Array.isArray(d.data.residents) ? d.data.residents : [];
            sel.innerHTML = '<option value="">-- Select Resident --</option>' +
                list.map(r => buildFamilyHeadOptionHtml(r)).join('');
            if (currentVal) sel.value = currentVal;
        })
        .catch(() => {});
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
    if (!target || target.id !== 'family_head_id') return;
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
    const householdId = document.getElementById('householdId').value;
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
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('householdModal')).hide();
                refreshCurrentView();
                form.reset();
                document.getElementById('householdId').value = '';
            } else {
                alert('Error: ' + (d.message || 'Failed to save'));
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
            const members = h.members || [];
            const allowEditMembers = HOUSEHOLD_PERMS.canEdit;
            const designatedHeadId = Number(h.family_head_id ?? 0);
            const householdFhCode = ((h.family_head_code ?? '').toString().trim() || '-');

            const toName = (m) => {
                const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
                return toTitleCase(name);
            };

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
            const memberList = members.filter(m => !isHead(m));

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

            infoEl.innerHTML = `
                <p><strong>Household ID Code:</strong> ${escapeHtml((h.household_id_code || '-'))}</p>
                <p><strong>Family Head:</strong> <span id="viewInfoHeadName">${escapeHtml(firstHeadName)}</span></p>
                <p><strong>Household Type:</strong> <span id="viewInfoHouseholdType">--</span></p>
                <p><strong>House Type:</strong> ${escapeHtml(formatStructureHouseTypeLabel(h.house_type))}</p>
                <p><strong>Address:</strong> ${escapeHtml(toTitleCase(h.address || '-'))}</p>
                <p><strong>Total Members:</strong> ${members.length}</p>
                <p><strong>Registration:</strong> ${formatDate(h.registration_date)}</p>
            `;

            const getFamilyCode = (m) => (m.family_code ?? '').toString().trim();
            const getMemberHeadRef = (m) => Number(m.family_head_resident_id || 0);
            const shownMemberIds = new Set();

            const headGroups = heads.map((head, idx) => {
                const headResidentId = Number(head.id);
                const headOwnFhc = (head.family_head_code ?? '').toString().trim();
                const headDisplayCode = (headOwnFhc !== '' && headOwnFhc !== '-') ? headOwnFhc : householdFhCode;
                const headFc = getFamilyCode(head);
                const headMembers = memberList.filter(m => {
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

            // Members not matched to any head go under the designated head (first group)
            const remainingMembers = memberList.filter(m => !shownMemberIds.has(Number(m.id)));
            if (remainingMembers.length > 0 && headGroups.length > 0) {
                const primaryGroup = headGroups.find(g => g.isPrimary) || headGroups[0];
                remainingMembers.forEach(m => {
                    primaryGroup.headMembers.push(m);
                    shownMemberIds.add(Number(m.id));
                });
            }
            const ungrouped = memberList.filter(m => !shownMemberIds.has(Number(m.id)));

            // Set initial household type for the first head now that groups are built
            if (headGroups.length > 0) {
                const initType = deriveHouseholdType(headGroups[0].head, headGroups[0].headMembers);
                const typeEl = document.getElementById('viewInfoHouseholdType');
                if (typeEl) typeEl.textContent = initType;
            } else {
                const typeEl = document.getElementById('viewInfoHouseholdType');
                if (typeEl) typeEl.textContent = householdTypeVal || '--';
            }

            if (!members.length) {
                membersHost.innerHTML = '<p class="text-muted">No members yet.</p>';
            } else if (heads.length <= 1) {
                // Single head — simple flat layout, no tabs needed
                let html = '<h6 class="mb-3">Members</h6>';
                if (headGroups.length === 1) {
                    const g = headGroups[0];
                    const removeBtn = allowEditMembers ? `<button type="button" class="action-icon-btn action-delete" title="Remove" aria-label="Remove" onclick="removeMember(${g.head.id})"><i class="bi bi-person-dash"></i></button>` : '';
                    html += `
                        <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-2 bg-light">
                            <div>
                                <span class="fw-semibold">${escapeHtml(toName(g.head))}</span>
                                <span class="badge bg-primary ms-2">Head</span>
                                <small class="text-muted ms-2">(${escapeHtml(g.headDisplayCode)})</small>
                            </div>
                            <div class="household-detail-actions">${removeBtn}</div>
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

                    const removeBtn = allowEditMembers ? `<button type="button" class="action-icon-btn action-delete" title="Remove" aria-label="Remove" onclick="removeMember(${g.head.id})"><i class="bi bi-person-dash"></i></button>` : '';

                    tabContent += `
                        <div class="tab-pane fade${active ? ' show active' : ''}" id="${paneId}" role="tabpanel" aria-labelledby="${tabId}">
                            <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-2 bg-light">
                                <div>
                                    <span class="fw-semibold">${escapeHtml(toName(g.head))}</span>
                                    <span class="badge bg-primary ms-2">Head</span>
                                    <small class="text-muted ms-2">(${escapeHtml(g.headDisplayCode)})</small>
                                </div>
                                <div class="household-detail-actions">${removeBtn}</div>
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

                // When a head tab is clicked, update the info section dynamically
                document.querySelectorAll('#viewHouseholdMembers .nav-link[data-head-name]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const nameEl = document.getElementById('viewInfoHeadName');
                        const typeEl = document.getElementById('viewInfoHouseholdType');
                        if (nameEl) nameEl.textContent = this.getAttribute('data-head-name') || '-';
                        if (typeEl) typeEl.textContent = this.getAttribute('data-head-type') || '--';
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
        const canTransfer = allowEdit && residentMeetsMinimumHeadAge(m);
        const transferBtn = canTransfer ? `<button type="button" class="action-icon-btn" title="Transfer Head" aria-label="Transfer Head" onclick="transferHeadTo(${m.id}, ${headId})"><i class="bi bi-person-badge"></i></button>` : '';
        const removeBtn = allowEdit ? `<button type="button" class="action-icon-btn action-delete" title="Remove" aria-label="Remove" onclick="removeMember(${m.id})"><i class="bi bi-person-dash"></i></button>` : '';
        return `
            <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-1 ms-3" style="border-left: 3px solid var(--bs-primary) !important;">
                <div>
                    <span>${escapeHtml(toTitleCase(name))}</span>
                    <span class="badge bg-light text-dark border ms-2">Member</span>
                </div>
                <div class="household-detail-actions">${transferBtn}${removeBtn}</div>
            </div>`;
    }).join('');
}

function buildUngroupedRows(ungroupedArr, designatedHeadId, allowEdit) {
    if (!ungroupedArr.length) return '';
    let html = '<div class="mt-2"><div class="small text-muted mb-1 px-2">Unassigned members</div>';
    ungroupedArr.forEach(m => {
        const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
        const canTransfer = allowEdit && designatedHeadId > 0 && residentMeetsMinimumHeadAge(m);
        const transferBtn = canTransfer ? `<button type="button" class="action-icon-btn" title="Transfer Head" aria-label="Transfer Head" onclick="transferHeadTo(${m.id}, ${designatedHeadId})"><i class="bi bi-person-badge"></i></button>` : '';
        const removeBtn = allowEdit ? `<button type="button" class="action-icon-btn action-delete" title="Remove" aria-label="Remove" onclick="removeMember(${m.id})"><i class="bi bi-person-dash"></i></button>` : '';
        html += `
            <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-1">
                <div>
                    <span>${escapeHtml(toTitleCase(name))}</span>
                    <span class="badge bg-light text-dark border ms-2">Member</span>
                </div>
                <div class="household-detail-actions">${transferBtn}${removeBtn}</div>
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
    const exclude = new Set((excludeIds || []).map((id) => Number(id)));
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
    const relGroup = document.getElementById('addMemberRelationshipGroup');
    let relationship = '';
    if (relGroup && !relGroup.classList.contains('d-none')) {
        relationship = (document.getElementById('addMemberRelationshipToHead')?.value || '').trim();
    }
    const fd = new FormData();
    fd.append('action', 'add_member');
    fd.append('household_id', householdId);
    fd.append('resident_id', residentId);
    fd.append('relationship_to_head', relationship);
    fetch(window.API_URL + 'households.php', Object.assign({ method: 'POST', body: fd }, HOUSEHOLD_FETCH_INIT))
        .then(parseHouseholdApiJson)
        .then(d => {
            if (d.success) {
                showHouseholdToast('Resident added to household.', 'success');
                refreshCurrentView();
                fetch(window.API_URL + 'households.php?action=get&id=' + encodeURIComponent(householdId), HOUSEHOLD_FETCH_INIT)
                    .then(parseHouseholdApiJson)
                    .then((householdData) => {
                        if (!householdData.success) return;
                        const h = householdData.data;
                        loadResidentsForAddMember(parseInt(householdId, 10), (h.members || []).map(m => m.id));
                        updateAddMemberRelationshipVisibility(h);
                        const relSel = document.getElementById('addMemberRelationshipToHead');
                        if (relSel) relSel.value = '';
                        const tm = document.getElementById('total_members');
                        if (tm) tm.value = (h.total_members === null || typeof h.total_members === 'undefined') ? 0 : h.total_members;
                    })
                    .catch(() => {});
            } else {
                alert('Error: ' + (d.message || 'Failed'));
            }
        })
        .catch(function(err) {
            alert((err && err.message) ? err.message : 'Could not add member. Check your connection and try again.');
        });
}

let pendingTransferHead = null;

function transferHeadTo(newHeadResidentId, oldHeadResidentId) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    pendingTransferHead = { newHeadResidentId, oldHeadResidentId };
    window._householdSwitchingToConfirm = true;
    const viewEl = document.getElementById('viewHouseholdModal');
    const viewModal = bootstrap.Modal.getInstance(viewEl);
    const showTransferModal = () => {
        window._householdSwitchingToConfirm = false;
        const el = document.getElementById('transferHeadModal');
        let m = bootstrap.Modal.getInstance(el);
        if (!m) m = new bootstrap.Modal(el);
        el.addEventListener('hidden.bs.modal', function onHidden() {
            if (pendingTransferHead) pendingTransferHead = null;
            if (viewModal && currentViewHouseholdId) viewModal.show();
            el.removeEventListener('hidden.bs.modal', onHidden);
        }, { once: true });
        m.show();
    };
    if (viewModal && viewEl.classList.contains('show')) {
        viewEl.addEventListener('hidden.bs.modal', showTransferModal, { once: true });
        viewModal.hide();
    } else {
        showTransferModal();
    }
}

function confirmTransferHead() {
    if (!pendingTransferHead) return;
    const { newHeadResidentId, oldHeadResidentId } = pendingTransferHead;
    pendingTransferHead = null;
    const btn = document.getElementById('transferHeadConfirmBtn');
    if (btn) btn.disabled = true;
    bootstrap.Modal.getInstance(document.getElementById('transferHeadModal'))?.hide();
    const fd = new FormData();
    fd.append('action', 'assign_head_official');
    fd.append('household_id', currentViewHouseholdId);
    fd.append('new_head_resident_id', newHeadResidentId);
    if (oldHeadResidentId) fd.append('old_head_resident_id', oldHeadResidentId);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showHouseholdToast('Head role transferred successfully.');
                viewHousehold(currentViewHouseholdId);
                refreshCurrentView();
            } else alert('Error: ' + (d.message || 'Failed'));
        })
        .catch(() => alert('Error transferring head'))
        .finally(() => { if (btn) btn.disabled = false; });
}

let pendingRemoveMemberId = null;

function removeMember(residentId) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    pendingRemoveMemberId = residentId;
    window._householdSwitchingToConfirm = true;
    const viewEl = document.getElementById('viewHouseholdModal');
    const viewModal = bootstrap.Modal.getInstance(viewEl);
    const showRemoveModal = () => {
        window._householdSwitchingToConfirm = false;
        const el = document.getElementById('removeMemberModal');
        let m = bootstrap.Modal.getInstance(el);
        if (!m) m = new bootstrap.Modal(el);
        el.addEventListener('hidden.bs.modal', function onHidden() {
            if (pendingRemoveMemberId !== null) pendingRemoveMemberId = null;
            if (viewModal && currentViewHouseholdId) viewModal.show();
            el.removeEventListener('hidden.bs.modal', onHidden);
        }, { once: true });
        m.show();
    };
    if (viewModal && viewEl.classList.contains('show')) {
        viewEl.addEventListener('hidden.bs.modal', showRemoveModal, { once: true });
        viewModal.hide();
    } else {
        showRemoveModal();
    }
}

function confirmRemoveMember() {
    if (pendingRemoveMemberId === null) return;
    const residentId = pendingRemoveMemberId;
    pendingRemoveMemberId = null;
    const btn = document.getElementById('removeMemberConfirmBtn');
    if (btn) btn.disabled = true;
    bootstrap.Modal.getInstance(document.getElementById('removeMemberModal'))?.hide();
    const fd = new FormData();
    fd.append('action', 'remove_member');
    fd.append('resident_id', residentId);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showHouseholdToast('Member removed from household.');
                viewHousehold(currentViewHouseholdId);
                refreshCurrentView();
            } else alert('Error: ' + (d.message || 'Failed'));
        })
        .catch(() => alert('Error removing member'))
        .finally(() => { if (btn) btn.disabled = false; });
}

function editHousehold(id) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    fetch(window.API_URL + 'households.php?action=get&id=' + id, HOUSEHOLD_FETCH_INIT)
        .then(parseHouseholdApiJson)
        .then(async (householdData) => {
            if (!householdData.success || !householdData.data) {
                alert(householdData.message || 'Could not load household.');
                return;
            }
            const h = householdData.data;
            const sel = document.getElementById('family_head_id');
            const hidEl = document.getElementById('householdId');
            const addrEl = document.getElementById('address');
            const tmEl = document.getElementById('total_members');
            const regEl = document.getElementById('registration_date');
            const titleEl = document.getElementById('householdModalTitle');
            const modalEl = document.getElementById('householdModal');
            if (!sel || !hidEl || !modalEl) {
                alert('Household form is not available.');
                return;
            }

            const headOptionRows = collectFamilyHeadSelectMemberRows(h).map(memberRowToFamilyHeadOptionShape);

            let residents = [];
            try {
                const rr = await fetch(window.API_URL + 'resident.php?action=list&limit=500&head_account_only=1', HOUSEHOLD_FETCH_INIT);
                if (rr.ok) {
                    const residentsData = await parseHouseholdApiJson(rr);
                    if (residentsData.success && residentsData.data && residentsData.data.residents) {
                        residents = residentsData.data.residents;
                    }
                }
            } catch (e) { /* optional list */ }

            const seenHeadSelectIds = new Set(headOptionRows.map(r => Number(r.id)));
            for (const r of residents) {
                const rid = Number(r.id);
                if (!seenHeadSelectIds.has(rid)) {
                    seenHeadSelectIds.add(rid);
                    headOptionRows.push(r);
                }
            }

            if (headOptionRows.length) {
                sel.innerHTML = '<option value="">-- Select Resident --</option>' +
                    headOptionRows.map(r => buildFamilyHeadOptionHtml(r)).join('');
            } else {
                sel.innerHTML = '<option value="">-- Select Resident --</option>';
            }

            const headId = Number(h.family_head_id || 0);
            if (headId > 0 && !seenHeadSelectIds.has(headId)) {
                try {
                    const rr = await fetch(window.API_URL + 'resident.php?action=get&id=' + headId, HOUSEHOLD_FETCH_INIT);
                    if (!rr.ok) throw new Error('bad');
                    const rj = await parseHouseholdApiJson(rr);
                    if (rj.success && rj.data) {
                        sel.insertAdjacentHTML('beforeend', buildFamilyHeadOptionHtml(rj.data));
                    }
                } catch (e) { /* ignore */ }
            }
            hidEl.value = h.id;
            sel.value = h.family_head_id || '';
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
            const joinSection = document.getElementById('joinHouseholdSection');
            if (joinSection) joinSection.style.display = '';
            loadResidentsForAddMember(h.id, (h.members || []).map(m => m.id));
            updateAddMemberRelationshipVisibility(h);
            new bootstrap.Modal(modalEl).show();
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
    const totalMembersGroup = document.getElementById('totalMembersGroup');
    if (totalMembersGroup) totalMembersGroup.style.display = '';
    const joinSection = document.getElementById('joinHouseholdSection');
    if (joinSection) joinSection.style.display = '';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }

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
