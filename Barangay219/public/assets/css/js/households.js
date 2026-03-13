if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
}

let currentViewHouseholdId = null;
let householdFilters = { q: '', from: '', to: '' };

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
    document.getElementById('btnAddMember').addEventListener('click', addMemberToHousehold);
});

function initHouseholdStatFilters() {
    const container = document.querySelector('.module-stats[data-module="households"]');
    if (!container) return;
    container.querySelectorAll('[data-range]').forEach(card => {
        const handleClick = () => {
            const range = card.getAttribute('data-range') || 'all';
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
        };
        card.addEventListener('click', handleClick);
        card.addEventListener('keypress', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleClick();
            }
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
    if (!HOUSEHOLD_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
    }
    if (!HOUSEHOLD_PERMS.canEdit) {
        const addMemberBtn = document.getElementById('btnAddMember');
        if (addMemberBtn) addMemberBtn.style.display = 'none';
    }
}

function loadHouseholds() {
    const params = new URLSearchParams({ action: 'list' });
    if (householdFilters.q) params.append('q', householdFilters.q);
    if (householdFilters.from) params.append('from', householdFilters.from);
    if (householdFilters.to) params.append('to', householdFilters.to);

    fetch(window.API_URL + 'households.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('householdsTableBody');
            if (d.success && d.data) {
                tbody.innerHTML = d.data.map(h => `
                    <tr>
                        <td>${h.id}</td>
                        <td>${escapeHtml(toTitleCase(h.family_head_name || '-'))}</td>
                        <td>${escapeHtml(formatTitleCaseTruncate(h.address || '', 50))}${(h.address||'').length>50?'...':''}</td>
                        <td>${h.total_members}</td>
                        <td>${formatDate(h.registration_date)}</td>
                        <td>
                            ${HOUSEHOLD_PERMS.canEdit ? `<button class="btn btn-sm btn-outline-secondary me-1" title="Edit" aria-label="Edit" onclick="editHousehold(${h.id})"><i class="bi bi-pencil-square"></i></button>` : ''}
                            <button class="btn btn-sm btn-primary me-1" title="View" aria-label="View" onclick="viewHousehold(${h.id})"><i class="bi bi-eye"></i></button>
                            ${HOUSEHOLD_PERMS.canDelete ? `<button class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete" onclick="deleteHousehold(${h.id})"><i class="bi bi-trash"></i></button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No households found</td></tr>';
            }
        })
        .catch(() => {
            document.getElementById('householdsTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading</td></tr>';
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
    loadHouseholds();
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
                        return `<option value="${r.id}">${escapeHtml(toTitleCase(name))}</option>`;
                    }).join('');
                if (currentVal) sel.value = currentVal;
            }
        })
        .catch(() => {});
}

function saveHousehold() {
    applyTitleCaseToForm();
    const householdId = document.getElementById('householdId').value;
    if (householdId && !HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    if (!householdId && !HOUSEHOLD_PERMS.canCreate) { alert('Access denied'); return; }
    const form = document.getElementById('householdForm');
    const formData = new FormData(form);
    formData.append('action', document.getElementById('householdId').value ? 'update' : 'create');
    const total = form.total_members.value;
    if (total) formData.set('total_members', parseInt(total, 10) || 1);

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
            document.getElementById('viewHouseholdInfo').innerHTML = `
                <p><strong>Family Head:</strong> ${escapeHtml(toTitleCase(h.family_head_name || '-'))}</p>
                <p><strong>Address:</strong> ${escapeHtml(toTitleCase(h.address || '-'))}</p>
                <p><strong>Total Members:</strong> ${h.total_members}</p>
                <p><strong>Registration:</strong> ${formatDate(h.registration_date)}</p>
            `;
            const members = h.members || [];
                        const allowEditMembers = HOUSEHOLD_PERMS.canEdit;
                        document.getElementById('viewHouseholdMembers').innerHTML = members.length
                ? '<table class="table table-sm"><thead><tr><th>Name</th><th>Birth Date</th><th></th></tr></thead><tbody>' +
                  members.map(m => {
                      const name = `${m.first_name || ''} ${m.middle_name || ''} ${m.last_name || ''}`.trim();
                      const titledName = toTitleCase(name);
                                            const removeBtn = allowEditMembers && m.id !== h.family_head_id ? `<button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove" onclick="removeMember(${m.id})"><i class="bi bi-person-dash"></i></button>` : (m.id !== h.family_head_id ? '' : '<span class="badge bg-primary">Head</span>');
                                            return `<tr><td>${escapeHtml(titledName)}</td><td>${formatDate(m.birth_date)}</td><td>${removeBtn}</td></tr>`;
                  }).join('') + '</tbody></table>'
                : '<p class="text-muted">No members yet.</p>';
            loadResidentsForAddMember(id, members.map(m => m.id));
            new bootstrap.Modal(document.getElementById('viewHouseholdModal')).show();
        })
        .catch(() => alert('Error loading household'));
}

function loadResidentsForAddMember(householdId, excludeIds) {
    const sel = document.getElementById('addMemberResident');
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
    const sel = document.getElementById('addMemberResident');
    const residentId = sel.value;
    const householdId = currentViewHouseholdId || sel.dataset.householdId;
    if (!residentId || !householdId) { alert('Select a resident'); return; }
    const fd = new FormData();
    fd.append('action', 'add_member');
    fd.append('household_id', householdId);
    fd.append('resident_id', residentId);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                viewHousehold(parseInt(householdId));
            } else alert('Error: ' + (d.message || 'Failed'));
        });
}

function removeMember(residentId) {
    if (!HOUSEHOLD_PERMS.canEdit) { alert('Access denied'); return; }
    if (!confirm('Remove this member from household?')) return;
    const fd = new FormData();
    fd.append('action', 'remove_member');
    fd.append('resident_id', residentId);
    fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                viewHousehold(currentViewHouseholdId);
                loadHouseholds();
            } else alert('Error: ' + (d.message || 'Failed'));
        });
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
        document.getElementById('total_members').value = h.total_members || 1;
        document.getElementById('registration_date').value = h.registration_date || '';
        document.getElementById('householdModalTitle').textContent = 'Edit Household';
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
    document.getElementById('householdModalTitle').textContent = 'Add New Household';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
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
