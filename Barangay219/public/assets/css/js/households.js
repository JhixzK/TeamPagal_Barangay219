if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

let currentViewHouseholdId = null;
let householdFilters = { q: '', from: '', to: '' };

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

document.addEventListener('DOMContentLoaded', function() {
    loadHouseholds();
    applyHouseholdPermissions();
    initHouseholdStatFilters();
    initHouseholdFormFormatting();
    document.getElementById('householdForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveHousehold();
    });
    document.getElementById('householdModal').addEventListener('show.bs.modal', function() {
        loadResidentsForDropdown();
    });
    const addMemberEditBtn = document.getElementById('btnAddMemberEdit');
    if (addMemberEditBtn) {
        addMemberEditBtn.addEventListener('click', addMemberToHousehold);
    }
});

function initHouseholdStatFilters() {
    const tabs = document.querySelectorAll('#rangeTabs .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const range = this.getAttribute('data-range') || 'all';
            const fromInput = document.getElementById('filterFrom');
            const toInput = document.getElementById('filterTo');
            const today = new Date();
            let fromVal = '';
            let toVal = '';

            if (range === 'month') {
                fromVal = formatDateInput(new Date(today.getFullYear(), today.getMonth(), 1));
                toVal = formatDateInput(today);
            } else if (range === 'year') {
                fromVal = formatDateInput(new Date(today.getFullYear(), 0, 1));
                toVal = formatDateInput(today);
            }

            householdFilters.from = fromVal;
            householdFilters.to = toVal;
            if (fromInput) fromInput.value = fromVal;
            if (toInput) toInput.value = toVal;
            loadHouseholds();
        });
    });
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
    if (!HOUSEHOLD_PERMS.canCreate) { alert('Access denied'); return; }
    resetForm();
    document.getElementById('householdModalTitle').textContent = 'New Household';
    const reg = document.getElementById('registration_date');
    if (reg && !reg.value) reg.value = formatDateInput(new Date());
    const totalMembersGroup = document.getElementById('totalMembersGroup');
    if (totalMembersGroup) totalMembersGroup.style.display = 'none';
    const joinSection = document.getElementById('joinHouseholdSection');
    if (joinSection) joinSection.style.display = 'none';
    new bootstrap.Modal(document.getElementById('householdModal')).show();
}

function loadHouseholds() {
    const params = new URLSearchParams({ action: 'list' });
    if (householdFilters.q) params.append('q', householdFilters.q);
    if (householdFilters.from) params.append('from', householdFilters.from);
    if (householdFilters.to) params.append('to', householdFilters.to);

    fetch(window.API_URL + 'households.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tiles = document.getElementById('householdTiles');
            if (!tiles) return;

            const data = Array.isArray(d.data) ? d.data : (d.data && typeof d.data === 'object' ? Object.values(d.data) : []);
            if (d.success && data.length) {
                tiles.innerHTML = data.map(h => {
                    const id = Number(h.id);
                    const headName = (h.family_head_name || '').toString().trim();
                    const allHeadNames = (h.family_head_names || headName).toString().trim();
                    const head = toTitleCase(headName || allHeadNames || '');
                    const address = toTitleCase(h.address || '');
                    const members = Number((h.total_members ?? 0));
                    const reg = formatDate(h.registration_date);
                    const hhCode = (h.household_id_code || '').trim();

                    const editBtn = HOUSEHOLD_PERMS.canEdit
                        ? `<button class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit" onclick="editHousehold(${id})"><i class="bi bi-pencil-square"></i></button>`
                        : '';
                    const viewBtn = `<button class="btn btn-sm btn-primary" title="View" aria-label="View" onclick="viewHousehold(${id})"><i class="bi bi-eye"></i></button>`;
                    const delBtn = HOUSEHOLD_PERMS.canDelete
                        ? `<button class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete" onclick="deleteHousehold(${id})"><i class="bi bi-trash"></i></button>`
                        : '';

                    const subtitle = head ? `Head: ${head}` : 'No head assigned yet';
                    const addrDisplay = address ? formatTitleCaseTruncate(address, 70) : '(no address)';
                    const householdIdLabel = '<small class="text-muted d-block">Household ID</small>';
                    const householdIdBadge = `<span class="badge bg-white text-dark border">${escapeHtml(hhCode || '-')}</span>`;
                    return `
                        <div class="household-tile card shadow-sm">
                            <div class="tile-top">
                                <div class="tile-title">
                                    <div class="tile-icon"><i class="bi bi-house-heart"></i></div>
                                    <div class="min-w-0">
                                        <p class="tile-name mb-0">Household ${id}</p>
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
                                        <dd>${escapeHtml(addrDisplay)}${address.length > 70 ? '...' : ''}</dd>
                                    </div>
                                    <div>
                                        <dt>Registered</dt>
                                        <dd>${escapeHtml(reg)}</dd>
                                    </div>
                                </dl>
                                <div class="tile-actions">
                                    ${editBtn}
                                    ${viewBtn}
                                    ${delBtn}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-muted py-5" style="min-height:280px;">No households found</div>';
            }
        })
        .catch(() => {
            const tiles = document.getElementById('householdTiles');
            if (tiles) tiles.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 text-center text-danger py-5" style="min-height:280px;">Error loading</div>';
        });
}

function searchHouseholds() {
    const q = document.getElementById('searchHousehold').value.trim();
    householdFilters.q = q;
    loadHouseholds();
}

function applyFilters() {
    householdFilters.from = document.getElementById('filterFrom')?.value || '';
    householdFilters.to = document.getElementById('filterTo')?.value || '';
    syncHouseholdRangeTabs();
    loadHouseholds();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetHouseholds() {
    const searchInput = document.getElementById('searchHousehold');
    if (searchInput) searchInput.value = '';
    householdFilters = { q: '', from: '', to: '' };
    const fromInput = document.getElementById('filterFrom');
    const toInput = document.getElementById('filterTo');
    if (fromInput) fromInput.value = '';
    if (toInput) toInput.value = '';
    syncHouseholdRangeTabs();
    loadHouseholds();
}

function syncHouseholdRangeTabs() {
    const today = new Date();
    const monthFrom = formatDateInput(new Date(today.getFullYear(), today.getMonth(), 1));
    const yearFrom = formatDateInput(new Date(today.getFullYear(), 0, 1));
    const todayVal = formatDateInput(today);

    let activeRange = 'all';
    if (householdFilters.from === monthFrom && householdFilters.to === todayVal) {
        activeRange = 'month';
    } else if (householdFilters.from === yearFrom && householdFilters.to === todayVal) {
        activeRange = 'year';
    }

    document.querySelectorAll('#rangeTabs .nav-link').forEach(tab => {
        tab.classList.toggle('active', (tab.getAttribute('data-range') || 'all') === activeRange);
    });
}

function loadResidentsForDropdown() {
    const sel = document.getElementById('family_head_id');
    if (!sel) return;
    const currentVal = sel.value;
    fetch(window.API_URL + 'resident.php?action=list&limit=500')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data && d.data.residents) {
                sel.innerHTML = '<option value="">-- Select Resident --</option>' +
                    d.data.residents.map(r => {
                        const name = `${r.last_name || ''}, ${r.first_name || ''} ${r.middle_name || ''}`.trim();
                        const fullAddress = (r.address || r.household_address || '').trim();
                        const houseNumber = r.house_number || '';
                        const street = r.street || '';
                        const purok = r.purok_sitio || '';
                        return `<option value="${r.id}"
                                    data-address="${escapeHtml(fullAddress)}"
                                    data-house-number="${escapeHtml(houseNumber)}"
                                    data-street="${escapeHtml(street)}"
                                    data-purok="${escapeHtml(purok)}"
                                >${escapeHtml(toTitleCase(name))}</option>`;
                    }).join('');
                if (currentVal) sel.value = currentVal;
            }
        })
        .catch(() => {});
}

document.addEventListener('change', function (e) {
    const target = e.target;
    if (!target || target.id !== 'family_head_id') return;
    const selected = target.selectedOptions && target.selectedOptions[0];
    if (!selected) return;

    const fullAddress = selected.getAttribute('data-address') || '';
    const houseNumber = selected.getAttribute('data-house-number') || '';
    const street = selected.getAttribute('data-street') || '';

    const houseNumberInput = document.getElementById('house_number');
    const streetInput = document.getElementById('street');
    const addressTextarea = document.getElementById('address');

    if (houseNumberInput) {
        houseNumberInput.value = houseNumber;
    }
    if (streetInput) {
        streetInput.value = street;
    }
    if (addressTextarea) {
        addressTextarea.value = fullAddress;
    }
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
                loadHouseholds();
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
    fetch(window.API_URL + 'households.php?action=get&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert(d.message || 'Error'); return; }
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

            document.getElementById('viewHouseholdInfo').innerHTML = `
                <p><strong>Household ID Code:</strong> ${escapeHtml((h.household_id_code || '-'))}</p>
                <p><strong>Family Head:</strong> <span id="viewInfoHeadName">${escapeHtml(firstHeadName)}</span></p>
                <p><strong>Household Type:</strong> <span id="viewInfoHouseholdType">--</span></p>
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
                document.getElementById('viewHouseholdMembers').innerHTML = '<p class="text-muted">No members yet.</p>';
            } else if (heads.length <= 1) {
                // Single head — simple flat layout, no tabs needed
                let html = '<h6 class="mb-3">Members</h6>';
                if (headGroups.length === 1) {
                    const g = headGroups[0];
                    const removeBtn = allowEditMembers ? ` <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove" onclick="removeMember(${g.head.id})"><i class="bi bi-person-dash"></i></button>` : '';
                    html += `
                        <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-2 bg-light">
                            <div>
                                <span class="fw-semibold">${escapeHtml(toName(g.head))}</span>
                                <span class="badge bg-primary ms-2">Head</span>
                                <small class="text-muted ms-2">(${escapeHtml(g.headDisplayCode)})</small>
                            </div>
                            <div>${removeBtn}</div>
                        </div>`;
                    html += buildMemberRows(g.headMembers, g.head.id, allowEditMembers);
                }
                html += buildUngroupedRows(ungrouped, designatedHeadId, allowEditMembers);
                document.getElementById('viewHouseholdMembers').innerHTML = html;
            } else {
                // Multiple heads — tabbed layout
                let tabNav = '<ul class="nav nav-pills nav-fill mb-3" role="tablist">';
                let tabContent = '<div class="tab-content">';

                headGroups.forEach((g, i) => {
                    const tabId = 'headTab' + i;
                    const paneId = 'headPane' + i;
                    const active = i === 0;
                    const shortName = (g.head.last_name || g.head.first_name || 'Head ' + (i + 1)).toString().trim();
                    const memberCount = g.headMembers.length;
                    const headFullName = toName(g.head);
                    const headType = deriveHouseholdType(g.head, g.headMembers);

                    tabNav += `
                        <li class="nav-item" role="presentation">
                            <button class="nav-link${active ? ' active' : ''} px-2 py-2" id="${tabId}" data-bs-toggle="pill" data-bs-target="#${paneId}" type="button" role="tab" aria-controls="${paneId}" aria-selected="${active}" data-head-name="${escapeHtml(headFullName)}" data-head-type="${escapeHtml(headType)}" style="font-size: 0.8rem; line-height:1.3;">
                                ${escapeHtml(toTitleCase(shortName))}
                                <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">${memberCount}</span>
                            </button>
                        </li>`;

                    const removeBtn = allowEditMembers ? ` <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove" onclick="removeMember(${g.head.id})"><i class="bi bi-person-dash"></i></button>` : '';

                    tabContent += `
                        <div class="tab-pane fade${active ? ' show active' : ''}" id="${paneId}" role="tabpanel" aria-labelledby="${tabId}">
                            <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-2 bg-light">
                                <div>
                                    <span class="fw-semibold">${escapeHtml(toName(g.head))}</span>
                                    <span class="badge bg-primary ms-2">Head</span>
                                    <small class="text-muted ms-2">(${escapeHtml(g.headDisplayCode)})</small>
                                </div>
                                <div>${removeBtn}</div>
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
                            <button class="nav-link px-2 py-2" id="${ugTabId}" data-bs-toggle="pill" data-bs-target="#${ugPaneId}" type="button" role="tab" aria-controls="${ugPaneId}" aria-selected="false" style="font-size: 0.85rem;">
                                Unassigned
                                <span class="badge bg-secondary ms-1" style="font-size: 0.7rem;">${ungrouped.length}</span>
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

                document.getElementById('viewHouseholdMembers').innerHTML =
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

            const viewModalEl = document.getElementById('viewHouseholdModal');
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
        .catch(() => alert('Error loading household'));
}

function buildMemberRows(memberArr, headId, allowEdit) {
    if (!memberArr.length) return '';
    return memberArr.map(m => {
        const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
        const transferBtn = allowEdit ? ` <button class="btn btn-sm btn-outline-primary" title="Transfer Head" aria-label="Transfer Head" onclick="transferHeadTo(${m.id}, ${headId})"><i class="bi bi-person-badge"></i></button>` : '';
        const removeBtn = allowEdit ? ` <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove" onclick="removeMember(${m.id})"><i class="bi bi-person-dash"></i></button>` : '';
        return `
            <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-1 ms-3" style="border-left: 3px solid var(--bs-primary) !important;">
                <div>
                    <span>${escapeHtml(toTitleCase(name))}</span>
                    <span class="badge bg-light text-dark border ms-2">Member</span>
                </div>
                <div>${transferBtn}${removeBtn}</div>
            </div>`;
    }).join('');
}

function buildUngroupedRows(ungroupedArr, designatedHeadId, allowEdit) {
    if (!ungroupedArr.length) return '';
    let html = '<div class="mt-2"><div class="small text-muted mb-1 px-2">Unassigned members</div>';
    ungroupedArr.forEach(m => {
        const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
        const transferBtn = (allowEdit && designatedHeadId > 0) ? ` <button class="btn btn-sm btn-outline-primary" title="Transfer Head" aria-label="Transfer Head" onclick="transferHeadTo(${m.id}, ${designatedHeadId})"><i class="bi bi-person-badge"></i></button>` : '';
        const removeBtn = allowEdit ? ` <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove" onclick="removeMember(${m.id})"><i class="bi bi-person-dash"></i></button>` : '';
        html += `
            <div class="d-flex align-items-center justify-content-between py-2 px-3 border rounded mb-1">
                <div>
                    <span>${escapeHtml(toTitleCase(name))}</span>
                    <span class="badge bg-light text-dark border ms-2">Member</span>
                </div>
                <div>${transferBtn}${removeBtn}</div>
            </div>`;
    });
    html += '</div>';
    return html;
}

function loadResidentsForAddMember(householdId, excludeIds) {
    const sel = document.getElementById('addMemberResidentEdit');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Select resident to add --</option>';
    sel.dataset.householdId = householdId;
    fetch(window.API_URL + 'resident.php?action=list&limit=500')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data && d.data.residents) {
                const ids = new Set(excludeIds || []);
                d.data.residents.forEach(r => {
                    if (!ids.has(r.id)) {
                        const name = `${r.last_name || ''}, ${r.first_name || ''} ${r.middle_name || ''}`.trim();
                        sel.innerHTML += `<option value="${r.id}">${escapeHtml(toTitleCase(name))}</option>`;
                    }
                });
            }
        });
}

function addMemberToHousehold() {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    const sel = document.getElementById('addMemberResidentEdit');
    if (!sel) { alert('Member selector not available'); return; }
    const residentId = sel.value;
    const formHouseholdId = document.getElementById('householdId')?.value || '';
    const householdId = formHouseholdId || currentViewHouseholdId || sel.dataset.householdId;
    if (!residentId || !householdId) { alert('Select a resident'); return; }
    const fd = new FormData();
    fd.append('action', 'add_member');
    fd.append('household_id', householdId);
    fd.append('resident_id', residentId);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadHouseholds();
                Promise.all([
                    fetch(window.API_URL + 'households.php?action=get&id=' + householdId).then(r => r.json()),
                    fetch(window.API_URL + 'resident.php?action=list&limit=500').then(r => r.json())
                ]).then(([householdData]) => {
                    if (!householdData.success) return;
                    const h = householdData.data;
                    loadResidentsForAddMember(parseInt(householdId, 10), (h.members || []).map(m => m.id));
                    document.getElementById('total_members').value = (h.total_members === null || typeof h.total_members === 'undefined') ? 0 : h.total_members;
                });
            } else alert('Error: ' + (d.message || 'Failed'));
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
                loadHouseholds();
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
                loadHouseholds();
            } else alert('Error: ' + (d.message || 'Failed'));
        })
        .catch(() => alert('Error removing member'))
        .finally(() => { if (btn) btn.disabled = false; });
}

function editHousehold(id) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    Promise.all([
        fetch(window.API_URL + 'households.php?action=get&id=' + id).then(r => r.json()),
        fetch(window.API_URL + 'resident.php?action=list&limit=500').then(r => r.json())
    ]).then(([householdData, residentsData]) => {
        if (!householdData.success) { alert(householdData.message); return; }
        const h = householdData.data;
        const sel = document.getElementById('family_head_id');
        if (residentsData.success && residentsData.data && residentsData.data.residents) {
            sel.innerHTML = '<option value="">-- Select Resident --</option>' +
                residentsData.data.residents.map(r => {
                    const name = `${r.last_name || ''}, ${r.first_name || ''}`.trim();
                    return `<option value="${r.id}">${escapeHtml(toTitleCase(name))}</option>`;
                }).join('');
        }
        document.getElementById('householdId').value = h.id;
        document.getElementById('family_head_id').value = h.family_head_id || '';
        document.getElementById('address').value = toTitleCase(h.address || '');
        document.getElementById('total_members').value = (h.total_members === null || typeof h.total_members === 'undefined') ? 0 : h.total_members;
        document.getElementById('registration_date').value = h.registration_date || '';
        const householdTypeSel = document.getElementById('household_type');
        if (householdTypeSel) {
            const storedType = (h.household_type || '').toString().trim();
            if (storedType) {
                const hasOption = Array.from(householdTypeSel.options).some(o => o.value === storedType);
                if (hasOption) {
                    householdTypeSel.value = storedType;
                } else {
                    const opt = document.createElement('option');
                    opt.value = storedType;
                    opt.textContent = storedType;
                    householdTypeSel.appendChild(opt);
                    householdTypeSel.value = storedType;
                }
            }
            const hasHeadFromRegistration = !!(h.family_head_id && Number(h.family_head_id) > 0);
            householdTypeSel.disabled = hasHeadFromRegistration;
            householdTypeSel.title = hasHeadFromRegistration ? 'Household type is from the head\'s registration' : '';
        }
        document.getElementById('householdModalTitle').textContent = 'Edit Household';
        const totalMembersGroup = document.getElementById('totalMembersGroup');
        if (totalMembersGroup) totalMembersGroup.style.display = '';
        const joinSection = document.getElementById('joinHouseholdSection');
        if (joinSection) joinSection.style.display = '';
        loadResidentsForAddMember(h.id, (h.members || []).map(m => m.id));
        new bootstrap.Modal(document.getElementById('householdModal')).show();
    }).catch(() => alert('Error loading household'));
}

function deleteHousehold(id) {
    if (!HOUSEHOLD_PERMS.canDelete) { alert('Access denied'); return; }
    if (!confirm('Delete this household? Members will be unlinked.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadHouseholds(); else alert(d.message || 'Error'); });
}

function resetForm() {
    document.getElementById('householdForm').reset();
    document.getElementById('householdId').value = '';
    document.getElementById('householdModalTitle').textContent = 'Edit Household';
    const sel = document.getElementById('addMemberResidentEdit');
    if (sel) {
        sel.innerHTML = '<option value="">-- Select resident to add --</option>';
        delete sel.dataset.householdId;
    }
    const householdTypeSel = document.getElementById('household_type');
    if (householdTypeSel) {
        householdTypeSel.disabled = false;
        householdTypeSel.title = '';
    }
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
