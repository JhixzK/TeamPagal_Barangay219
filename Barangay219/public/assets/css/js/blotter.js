// Ensure API_URL is valid at runtime (fallback)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

// Phone number input validation - enforce +63 prefix with space and 10 digits
function validatePhoneInput(input) {
    const ensurePrefix = () => {
        if (!input.value.startsWith('+63')) {
            input.value = '+63 ';
        } else if (input.value === '+63') {
            input.value = '+63 ';
        }
    };

    ensurePrefix();

    input.addEventListener('input', function() {
        const digits = normalizePhoneDigits(this.value);
        this.value = '+63 ' + digits;
    });
    
    // Add blur event to ensure +63 is always present
    input.addEventListener('blur', function() {
        const digits = normalizePhoneDigits(this.value);
        this.value = digits ? ('+63 ' + digits) : '+63 ';
    });
}

// Name input validation - only allow letters and spaces, no numbers
function validateNameInput(input) {
    input.addEventListener('input', function() {
        // Remove any non-letter and non-space characters
        let value = this.value.replace(/[^a-zA-Z\s]/g, '');
        this.value = value;
    });
    input.addEventListener('blur', function() {
        this.value = toTitleCase(this.value);
    });
}

// Number-only validation (allow digits only)
function validateNumberInput(input) {
    input.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        this.value = value;
    });
}

const BLOTTER_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('blotters', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('blotters', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('blotters', 'can_delete') : true
};

let blotterFilters = { q: '', status: '', from: '', to: '' };
let searchDebounceTimer = null;
let residentDirectoryCache = null;

document.addEventListener('DOMContentLoaded', function() {
    loadBlotters();
    initBlotterModal();
    applyBlotterPermissions();
    initBlotterStatFilters();
    
    // Add search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.trim();
            blotterFilters.q = searchTerm;
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => loadBlotters(), 250);
        });
    }
});

function initBlotterStatFilters() {
    const tabs = document.querySelectorAll('#statusTabs .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const status = this.getAttribute('data-status') || '';
            blotterFilters.status = status;
            const statusSel = document.getElementById('filterStatus');
            if (statusSel) statusSel.value = status;
            loadBlotters();
        });
    });
}

function applyBlotterPermissions() {
    if (!BLOTTER_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
    }
}

function loadBlotters() {
    const params = new URLSearchParams({ action: 'list' });
    if (blotterFilters.q) params.append('q', blotterFilters.q);
    if (blotterFilters.status) params.append('status', blotterFilters.status);
    if (blotterFilters.from) params.append('from', blotterFilters.from);
    if (blotterFilters.to) params.append('to', blotterFilters.to);

    fetch(window.API_URL + 'blotter.php?' + params.toString())
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('blotterTableBody');
            if (d.success) {
                tbody.innerHTML = d.data.map(b => {
                    const comp = extractPartyNamesOnly(b.complainant_name);

                        const incidentType = b.incident_type ? formatIncidentType(b.incident_type) : '-';
                        const incidentLocation = b.incident_location ? escapeHtml(toTitleCase(b.incident_location)) : '-';
                        return `
                        <tr>
                            <td class="text-center"><span class="blotter-secondary">#${b.id}</span></td>
                            <td class="text-center fw-semibold">${escapeHtml(toTitleCase(b.case_title || ''))}</td>
                            <td class="text-center"><span class="blotter-secondary">${escapeHtml(comp)}</span></td>
                            <td class="text-center">${incidentType}</td>
                            <td class="text-center"><span class="blotter-secondary">${incidentLocation}</span></td>
                            <td class="text-center"><span class="blotter-secondary">${formatDate(b.incident_date)}</span></td>
                            <td class="text-center"><span class="blotter-pill ${getStatusColor(b.status)}">${formatStatusLabel(b.status)}</span></td>
                            <td class="text-center">
                                <div class="blotter-actions" role="group">
                                    <button class="action-icon-btn" title="View" aria-label="View" onclick="viewBlotter(${b.id})"><i class="bi bi-eye"></i></button>
                                    ${BLOTTER_PERMS.canEdit ? `<button class="action-icon-btn" title="Edit" aria-label="Edit" onclick="editBlotter(${b.id})"><i class="bi bi-pencil-square"></i></button>` : ''}
                                    ${BLOTTER_PERMS.canDelete ? `<button class="action-icon-btn action-delete" title="Delete" aria-label="Delete" onclick="deleteBlotter(${b.id})"><i class="bi bi-trash"></i></button>` : ''}
                                </div>
                            </td>
                        </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No blotter records found or access denied</td></tr>';
                console.warn('Blotter API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading blotters:', err);
            const tbody = document.getElementById('blotterTableBody');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading blotters</td></tr>';
        });
}

function extractPartyNamesOnly(rawValue) {
    if (!rawValue) return '-';

    const source = String(rawValue);

    try {
        const parsed = JSON.parse(source);
        if (Array.isArray(parsed)) {
            const names = parsed
                .map(item => toTitleCase(String(item?.name || '').trim()))
                .filter(Boolean);
            return names.length ? names.join(', ') : '-';
        }
    } catch (e) {
        // Handle malformed/truncated JSON by extracting any "name":"..." fragments.
        const nameMatches = [...source.matchAll(/"name"\s*:\s*"([^"\\]+(?:\\.[^"\\]*)*)"/g)];
        if (nameMatches.length) {
            const names = nameMatches
                .map(m => toTitleCase(String(m[1] || '').replace(/\\"/g, '"').trim()))
                .filter(Boolean);
            if (names.length) return names.join(', ');
        }
    }

    const text = toTitleCase(source.trim());
    return text || '-';
}

function applyFilters() {
    blotterFilters.status = document.getElementById('filterStatus')?.value || '';
    blotterFilters.from = document.getElementById('filterFrom')?.value || '';
    blotterFilters.to = document.getElementById('filterTo')?.value || '';
    syncBlotterStatusTabs();
    loadBlotters();
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function syncBlotterStatusTabs() {
    document.querySelectorAll('#statusTabs .nav-link').forEach(tab => {
        const tabStatus = tab.getAttribute('data-status') || '';
        tab.classList.toggle('active', tabStatus === (blotterFilters.status || ''));
    });
}

function viewBlotter(id) {
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const info = d.data;
                
                // Populate Case Information
                document.getElementById('viewCaseTitle').textContent = toTitleCase(info.case_title || '-') || '-';
                document.getElementById('viewIncidentDate').textContent = formatDate(info.incident_date) || '-';
                document.getElementById('viewIncidentType').textContent = info.incident_type ? formatIncidentType(info.incident_type) : 'Not specified';
                document.getElementById('viewIncidentLocation').textContent = toTitleCase(info.incident_location || '-') || '-';
                document.getElementById('viewDescription').textContent = toTitleCase(info.description || '-') || '-';
                const proofPath = info.proof_of_incident_path || '';
                const proofEl = document.getElementById('viewIncidentProof');
                if (proofEl) {
                    if (proofPath) {
                        const href = proofPath.startsWith('http') ? proofPath : (window.location.origin + '/TeamPagal_Barangay219/Barangay219/public/' + String(proofPath).replace(/^\/+/, ''));
                        proofEl.innerHTML = `<a href="${href}" target="_blank" rel="noopener">View Proof</a>`;
                    } else {
                        proofEl.textContent = 'No proof uploaded';
                    }
                }
                
                // Populate Complainants
                let complainantsHTML = '';
                try {
                    const comps = JSON.parse(info.complainant_name);
                    if (Array.isArray(comps)) {
                        complainantsHTML = comps.map((c, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Complainant ${idx + 1}:</strong> ${toTitleCase(c.name || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${toTitleCase(c.address || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Barangay:</strong> ${toTitleCase(c.barangay || '-') || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${formatPhoneNumber(c.contact) || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                    }
                } catch(e) {
                    complainantsHTML = `<p>${toTitleCase(info.complainant_name || '-') || '-'}</p>`;
                }
                document.getElementById('viewComplainantsInfo').innerHTML = complainantsHTML || '<p>-</p>';
                
                // Populate Respondents
                let respondentsHTML = '';
                try {
                    const resps = JSON.parse(info.respondent_name);
                    if (Array.isArray(resps)) {
                        respondentsHTML = resps.map((r, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Respondent ${idx + 1}:</strong> ${toTitleCase(r.name || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${toTitleCase(r.address || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Barangay:</strong> ${toTitleCase(r.barangay || '-') || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${formatPhoneNumber(r.contact) || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                    }
                } catch(e) {
                    respondentsHTML = `<p>${toTitleCase(info.respondent_name || '-') || '-'}</p>`;
                }
                document.getElementById('viewRespondentsInfo').innerHTML = respondentsHTML || '<p>-</p>';
                
                // Populate Case Status
                document.getElementById('viewStatus').textContent = info.status || '-';
                document.getElementById('viewSettlementDate').textContent = formatDate(info.settlement_date) || '-';

                // Populate Hearings
                let hearingsHTML = '';
                if (Array.isArray(info.hearings) && info.hearings.length > 0) {
                    hearingsHTML = info.hearings.map((h, idx) => {
                        const hearingDate = formatDate(h.hearing_date);
                        const nextHearingDate = formatDate(h.next_hearing_date);
                        const status = h.status ? escapeHtml(h.status) : '-';
                        const outcome = h.outcome ? escapeHtml(h.outcome) : '-';
                        const notes = h.notes ? escapeHtml(h.notes) : '-';
                        return `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Hearing ${idx + 1} Date:</strong> ${hearingDate}</p>
                                    <p class="mb-1"><strong>Status:</strong> ${status}</p>
                                    <p class="mb-1"><strong>Outcome:</strong> ${outcome}</p>
                                    <p class="mb-1"><strong>Notes:</strong> ${notes}</p>
                                    <p class="mb-0"><strong>Next Hearing:</strong> ${nextHearingDate}</p>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    hearingsHTML = '<p>-</p>';
                }
                document.getElementById('viewHearingsInfo').innerHTML = hearingsHTML;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('viewBlotterModal'));
                modal.show();
            }
        })
        .catch(err => { console.error(err); alert('Error loading blotter details'); });
}

function editBlotter(id) {
    if (!BLOTTER_PERMS.canEdit) { alert('Access denied'); return; }
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert('Record not found'); return; }
            const info = d.data;
            // set modal into edit mode
            document.getElementById('blotterModalTitle').textContent = 'Edit Blotter Case';
            document.querySelector('#blotterForm .btn-primary').textContent = 'Update Blotter Case';
            document.getElementById('blotterId').value = info.id;
            document.getElementById('case_title').value = toTitleCase(info.case_title || '');
            document.getElementById('incident_date').value = info.incident_date || '';
            document.getElementById('incident_location').value = toTitleCase(info.incident_location || '');
            document.getElementById('description').value = toTitleCase(info.description || '');
            const incidentTypeEl = document.getElementById('incident_type');
            if (incidentTypeEl) {
                const normalized = String(info.incident_type || '').trim().toLowerCase();
                const known = ['physical_assault','verbal_threat','theft','property_damage','public_disturbance','domestic_dispute','harassment','other'];
                if (known.includes(normalized) && normalized !== '') {
                    incidentTypeEl.value = normalized;
                    const customInput = document.getElementById('incident_type_custom');
                    if (customInput) customInput.value = '';
                } else if (info.incident_type) {
                    incidentTypeEl.value = 'other';
                    const customInput = document.getElementById('incident_type_custom');
                    if (customInput) customInput.value = info.incident_type;
                } else {
                    incidentTypeEl.value = '';
                }
                toggleIncidentTypeCustom();
            }
            document.getElementById('status').value = info.status || 'pending';
            document.getElementById('settlement_date').value = info.settlement_date || '';

            // populate complainants/respondents
            document.getElementById('complainantsContainer').innerHTML = '';
            document.getElementById('respondentsContainer').innerHTML = '';
            document.getElementById('hearingsContainer').innerHTML = '';

            try {
                const comps = JSON.parse(info.complainant_name);
                if (Array.isArray(comps)) {
                    comps.forEach(c => addComplainantRow({
                        name: toTitleCase(c.name || ''),
                        address: toTitleCase(c.address || ''),
                        contact: c.contact || '',
                        residency: c.residency || 'non_resident',
                        resident_id: c.resident_id || null
                    }));
                } else {
                    addComplainantRow({ name: toTitleCase(info.complainant_name) });
                }
            } catch (e) {
                if (info.complainant_name) addComplainantRow({ name: toTitleCase(info.complainant_name) });
            }
            try {
                const resps = JSON.parse(info.respondent_name);
                if (Array.isArray(resps)) {
                    resps.forEach(r => addRespondentRow({
                        name: toTitleCase(r.name || ''),
                        address: toTitleCase(r.address || ''),
                        contact: r.contact || '',
                        residency: r.residency || 'non_resident',
                        resident_id: r.resident_id || null
                    }));
                } else {
                    addRespondentRow({ name: toTitleCase(info.respondent_name) });
                }
            } catch (e) {
                if (info.respondent_name) addRespondentRow({ name: toTitleCase(info.respondent_name) });
            }

            if (Array.isArray(info.hearings) && info.hearings.length > 0) {
                info.hearings.forEach(h => addHearingRow({
                    hearing_date: h.hearing_date || '',
                    status: h.status || 'scheduled',
                    outcome: h.outcome || '',
                    notes: h.notes || '',
                    next_hearing_date: h.next_hearing_date || ''
                }));
            } else {
                addHearingRow();
            }

            // show modal
            const modalEl = document.getElementById('blotterModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        })
        .catch(err => { console.error(err); alert('Error fetching record'); });
}

function getStatusColor(status) {
    const normalized = String(status || '').toLowerCase().trim();
    const colors = {
        pending: 'status-pending',
        under_investigation: 'status-under-investigation',
        resolved: 'status-resolved',
        settled: 'status-settled',
        referred: 'status-referred'
    };
    return colors[normalized] || 'status-unknown';
}

function formatStatusLabel(status) {
    const normalized = String(status || '').toLowerCase().trim();
    if (!normalized) return 'Unknown';
    return normalized
        .split('_')
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }

// --- Blotter modal / create handling ---
function initBlotterModal() {
    // initial one row each
    addComplainantRow();
    addRespondentRow();
    addHearingRow();
    updatePrimaryComplainantInfo();

    // Add name validation to case title
    const caseTitleInput = document.getElementById('case_title');
    validateNameInput(caseTitleInput);

    const incidentTypeEl = document.getElementById('incident_type');
    if (incidentTypeEl) {
        incidentTypeEl.addEventListener('change', toggleIncidentTypeCustom);
    }

    document.getElementById('addComplainantBtn').addEventListener('click', addComplainantRow);
    document.getElementById('addRespondentBtn').addEventListener('click', addRespondentRow);
    document.getElementById('addHearingBtn').addEventListener('click', addHearingRow);
    document.getElementById('blotterForm').addEventListener('submit', submitBlotterForm);
    toggleIncidentTypeCustom();
    initBlotterTextFormatting();
}

function getResidentDirectory() {
    if (Array.isArray(residentDirectoryCache)) {
        return Promise.resolve(residentDirectoryCache);
    }
    return fetch(window.API_URL + 'resident.php?action=list&limit=1000')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data && Array.isArray(d.data.residents)) {
                residentDirectoryCache = d.data.residents;
                return residentDirectoryCache;
            }
            residentDirectoryCache = [];
            return residentDirectoryCache;
        })
        .catch(() => {
            residentDirectoryCache = [];
            return residentDirectoryCache;
        });
}

function setupPartyRowResidency(row, type, data = {}) {
    const residency = String(data.residency || 'non_resident').toLowerCase() === 'resident' ? 'resident' : 'non_resident';
    const searchWrap = row.querySelector('[data-resident-search-wrap]');
    const searchInput = row.querySelector('[data-resident-search]');
    const residentList = row.querySelector('[data-resident-list]');
    const residentResults = row.querySelector('[data-resident-results]');
    const residentIdInput = row.querySelector('[data-resident-id]');
    const nameInput = row.querySelector('[data-name]');
    const addressInput = row.querySelector('[data-address]');
    const contactInput = row.querySelector('[data-contact]');
    const residentRadio = row.querySelector('[data-residency="resident"]');
    const nonResidentRadio = row.querySelector('[data-residency="non_resident"]');

    const setReadonlyState = (isResident) => {
        if (searchWrap) searchWrap.style.display = isResident ? '' : 'none';
        if (nameInput) nameInput.readOnly = isResident;
        if (addressInput) addressInput.readOnly = isResident;
        if (contactInput) {
            contactInput.readOnly = isResident;
            if (!isResident && !contactInput.value.trim()) {
                contactInput.value = '+63 ';
            }
        }
    };

    const residentLabel = (resident) => {
        const fullName = `${resident.last_name || ''}, ${resident.first_name || ''} ${resident.middle_name || ''}`.trim();
        const code = resident.resident_code ? ` (${resident.resident_code})` : '';
        return `${toTitleCase(fullName)}${code}`;
    };

    const fillFromResident = (residentId) => {
        const residentNumericId = parseInt(residentId || '0', 10);
        if (!residentNumericId || !Array.isArray(residentDirectoryCache)) return;
        const resident = residentDirectoryCache.find(r => Number(r.id) === residentNumericId);
        if (!resident) return;
        if (residentIdInput) residentIdInput.value = String(residentNumericId);
        if (searchInput) searchInput.value = residentLabel(resident);
        const fullName = `${resident.first_name || ''} ${resident.middle_name || ''} ${resident.last_name || ''} ${resident.suffix || ''}`.trim();
        if (nameInput) nameInput.value = toTitleCase(fullName);
        if (addressInput) addressInput.value = toTitleCase(resident.address || '');
        if (contactInput) contactInput.value = formatPhoneForInput(resident.contact_number || '');
    };

    const resolveResidentFromSearch = () => {
        if (!searchInput || !residentList || !residentIdInput) return;
        const value = (searchInput.value || '').trim();
        if (!value) {
            residentIdInput.value = '';
            return;
        }
        const match = Array.from(residentList.options).find(opt => opt.value === value);
        const id = match ? parseInt(match.dataset.id || '0', 10) : 0;
        if (!id) {
            residentIdInput.value = '';
            return;
        }
        fillFromResident(id);
    };

    const renderResidentResults = (keyword = '') => {
        if (!residentResults || !Array.isArray(residentDirectoryCache)) return;
        const term = String(keyword || '').trim().toLowerCase();
        const filtered = residentDirectoryCache.filter(r => {
            if (!term) return true;
            const fullName = `${r.first_name || ''} ${r.middle_name || ''} ${r.last_name || ''} ${r.suffix || ''}`.toLowerCase();
            const code = String(r.resident_code || '').toLowerCase();
            return fullName.includes(term) || code.includes(term);
        }).slice(0, 25);

        if (filtered.length === 0) {
            residentResults.innerHTML = '<div class="list-group-item text-muted small">No matching residents</div>';
            residentResults.style.display = '';
            return;
        }

        residentResults.innerHTML = filtered.map(r => {
            const label = escapeHtml(residentLabel(r));
            const address = escapeHtml(toTitleCase(r.address || ''));
            return `<button type="button" class="list-group-item list-group-item-action" data-resident-pick="${r.id}"><div class="fw-semibold">${label}</div><div class="small text-muted text-truncate">${address}</div></button>`;
        }).join('');
        residentResults.style.display = '';

        residentResults.querySelectorAll('[data-resident-pick]').forEach(btn => {
            btn.addEventListener('click', () => {
                fillFromResident(btn.getAttribute('data-resident-pick'));
                residentResults.style.display = 'none';
            });
        });
    };

    if (residentList) {
        getResidentDirectory().then(residents => {
            const currentId = parseInt(data.resident_id || '0', 10);
            residentList.innerHTML = residents.map(r => {
                const label = escapeHtml(residentLabel(r));
                return `<option value="${label}" data-id="${r.id}"></option>`;
            }).join('');
            if (currentId > 0) {
                fillFromResident(currentId);
            }
        });
        if (searchInput) {
            searchInput.addEventListener('change', resolveResidentFromSearch);
            searchInput.addEventListener('blur', resolveResidentFromSearch);
            searchInput.addEventListener('focus', () => renderResidentResults(searchInput.value));
            searchInput.addEventListener('click', () => renderResidentResults(searchInput.value));
            searchInput.addEventListener('input', () => {
                if (residentIdInput) residentIdInput.value = '';
                renderResidentResults(searchInput.value);
            });
        }
    }

    if (residentResults) {
        document.addEventListener('click', (event) => {
            if (!row.contains(event.target)) {
                residentResults.style.display = 'none';
            }
        });
    }

    if (residentRadio) {
        residentRadio.checked = residency === 'resident';
        residentRadio.addEventListener('change', () => {
            setReadonlyState(true);
            if (residentIdInput && residentIdInput.value) {
                fillFromResident(residentIdInput.value);
            } else {
                resolveResidentFromSearch();
            }
        });
    }
    if (nonResidentRadio) {
        nonResidentRadio.checked = residency === 'non_resident';
        nonResidentRadio.addEventListener('change', () => {
            setReadonlyState(false);
            if (residentIdInput) residentIdInput.value = '';
            if (searchInput) searchInput.value = '';
            if (residentResults) residentResults.style.display = 'none';
        });
    }

    setReadonlyState(residency === 'resident');
}

function resetBlotterForm() {
    document.getElementById('blotterForm').reset();
    document.getElementById('blotterId').value = '';
    document.getElementById('complainantsContainer').innerHTML = '';
    document.getElementById('respondentsContainer').innerHTML = '';
    document.getElementById('hearingsContainer').innerHTML = '';
    addComplainantRow();
    addRespondentRow();
    addHearingRow();
    updatePrimaryComplainantInfo();
    // reset modal title and button text
    const titleEl = document.getElementById('blotterModalTitle');
    if (titleEl) titleEl.textContent = 'Add New Blotter Case';
    const submitBtn = document.querySelector('#blotterForm .btn-primary');
    if (submitBtn) submitBtn.textContent = 'Save Blotter Case';
    
    // Reapply name validation to case title
    const caseTitleInput = document.getElementById('case_title');
    validateNameInput(caseTitleInput);
    const customInput = document.getElementById('incident_type_custom');
    if (customInput) customInput.value = '';
    const incidentType = document.getElementById('incident_type');
    if (incidentType) incidentType.value = '';
    toggleIncidentTypeCustom();
    initBlotterTextFormatting();
}

function deleteBlotter(id) {
    if (!BLOTTER_PERMS.canDelete) { alert('Access denied'); return; }
    if (!confirm('Are you sure you want to delete this blotter case? This action cannot be undone.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch(window.API_URL + 'blotter.php?action=delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadBlotters();
                alert('Blotter case deleted');
            } else {
                alert('Delete failed: ' + d.message);
            }
        })
        .catch(err => { console.error(err); alert('Error deleting blotter case'); });
}

function addComplainantRow(data = {}) {
    const id = 'comp_' + Date.now() + Math.floor(Math.random()*1000);
    const container = document.getElementById('complainantsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-3 g-2 party-row';
    div.innerHTML = `
        <div class="col-12">
            <label class="form-label d-block mb-1">Residency</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="${id}_residency" data-residency="resident" value="resident">
                <label class="form-check-label">Resident</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="${id}_residency" data-residency="non_resident" value="non_resident" checked>
                <label class="form-check-label">Non-Resident</label>
            </div>
        </div>
        <div class="col-12" data-resident-search-wrap style="display:none; position:relative;">
            <label class="form-label">Search Resident</label>
            <input type="text" class="form-control" data-resident-search list="${id}_resident_list" placeholder="Search resident by name">
            <datalist id="${id}_resident_list" data-resident-list></datalist>
            <input type="hidden" data-resident-id>
            <div class="list-group mt-1 shadow-sm" data-resident-results style="display:none; max-height:220px; overflow:auto;"></div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Complainant Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Full name" data-name name="complainant_name" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Address" data-address name="complainant_address" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Contact Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="+63 9XXXXXXXXX" data-contact name="complainant_contact" maxlength="14" pattern="\+63\s\d{10}" value="+63 " required>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-party" style="width:100%;">&times;</button>
        </div>
    `;
    // populate
    if (data.name) div.querySelector('[data-name]').value = toTitleCase(data.name);
    if (data.address) div.querySelector('[data-address]').value = toTitleCase(data.address);
    if (data.contact) div.querySelector('[data-contact]').value = formatPhoneForInput(data.contact);

    setupPartyRowResidency(div, 'complainant', data);

    // Add name input validation to all name fields
    const nameInput = div.querySelector('[data-name]');
    validateNameInput(nameInput);
    
    const addressInput = div.querySelector('[data-address]');
    attachTitleCaseOnBlur(addressInput);

    // Add phone input validation
    const contactInput = div.querySelector('[data-contact]');
    validatePhoneInput(contactInput);

    const refresh = () => updatePrimaryComplainantInfo();
    nameInput.addEventListener('input', refresh);
    contactInput.addEventListener('input', refresh);

    div.querySelector('.remove-party').addEventListener('click', () => { div.remove(); updatePrimaryComplainantInfo(); });
    container.appendChild(div);
    updatePrimaryComplainantInfo();
}

function addRespondentRow(data = {}) {
    const id = 'resp_' + Date.now() + Math.floor(Math.random()*1000);
    const container = document.getElementById('respondentsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-3 g-2 party-row';
    div.innerHTML = `
        <div class="col-12">
            <label class="form-label d-block mb-1">Residency</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="${id}_residency" data-residency="resident" value="resident">
                <label class="form-check-label">Resident</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="${id}_residency" data-residency="non_resident" value="non_resident" checked>
                <label class="form-check-label">Non-Resident</label>
            </div>
        </div>
        <div class="col-12" data-resident-search-wrap style="display:none; position:relative;">
            <label class="form-label">Search Resident</label>
            <input type="text" class="form-control" data-resident-search list="${id}_resident_list" placeholder="Search resident by name">
            <datalist id="${id}_resident_list" data-resident-list></datalist>
            <input type="hidden" data-resident-id>
            <div class="list-group mt-1 shadow-sm" data-resident-results style="display:none; max-height:220px; overflow:auto;"></div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Respondent Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Full name" data-name name="respondent_name" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="Address" data-address name="respondent_address" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Contact Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" placeholder="+63 9XXXXXXXXX" data-contact name="respondent_contact" maxlength="14" pattern="\+63\s\d{10}" value="+63 " required>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-party" style="width:100%;">&times;</button>
        </div>
    `;
    if (data.name) div.querySelector('[data-name]').value = toTitleCase(data.name);
    if (data.address) div.querySelector('[data-address]').value = toTitleCase(data.address);
    if (data.contact) div.querySelector('[data-contact]').value = formatPhoneForInput(data.contact);

    setupPartyRowResidency(div, 'respondent', data);

    // Add name input validation to all name fields
    const nameInput = div.querySelector('[data-name]');
    validateNameInput(nameInput);
    
    const addressInput = div.querySelector('[data-address]');
    attachTitleCaseOnBlur(addressInput);

    // Add phone input validation
    const contactInput = div.querySelector('[data-contact]');
    validatePhoneInput(contactInput);

    div.querySelector('.remove-party').addEventListener('click', () => div.remove());
    container.appendChild(div);
}

function addHearingRow(data = {}) {
    const container = document.getElementById('hearingsContainer');
    const div = document.createElement('div');
    div.className = 'row mb-3 g-2 hearing-row';
    div.innerHTML = `
        <div class="col-12 col-md-3">
            <label class="form-label">Hearing Date</label>
            <input type="date" class="form-control" data-hearing-date>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Status</label>
            <select class="form-select" data-hearing-status>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="postponed">Postponed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Outcome</label>
            <input type="text" class="form-control" placeholder="Outcome" data-hearing-outcome>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Next Hearing Date</label>
            <input type="date" class="form-control" data-next-hearing-date>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-hearing" style="width:100%;">&times;</button>
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" rows="2" placeholder="Notes" data-hearing-notes></textarea>
        </div>
    `;

    if (data.hearing_date) div.querySelector('[data-hearing-date]').value = data.hearing_date;
    if (data.status) div.querySelector('[data-hearing-status]').value = data.status;
    if (data.outcome) div.querySelector('[data-hearing-outcome]').value = toTitleCase(data.outcome);
    if (data.notes) div.querySelector('[data-hearing-notes]').value = toTitleCase(data.notes);
    if (data.next_hearing_date) div.querySelector('[data-next-hearing-date]').value = data.next_hearing_date;

    const outcomeInput = div.querySelector('[data-hearing-outcome]');
    const notesInput = div.querySelector('[data-hearing-notes]');
    attachTitleCaseOnBlur(outcomeInput);
    attachTitleCaseOnBlur(notesInput);

    div.querySelector('.remove-hearing').addEventListener('click', () => div.remove());
    container.appendChild(div);
}

function submitBlotterForm(e) {
    e.preventDefault();
    applyTitleCaseToBlotterForm();
    const form = document.getElementById('blotterForm');
    const payload = new FormData();
    payload.append('case_title', document.getElementById('case_title').value || '');
    payload.append('incident_date', document.getElementById('incident_date').value || '');
    const incidentType = document.getElementById('incident_type')?.value || '';
    const incidentTypeCustom = document.getElementById('incident_type_custom')?.value?.trim() || '';
    if (incidentType === 'other' && !incidentTypeCustom) {
        alert('Please specify custom incident type');
        return;
    }
    payload.append('incident_type', incidentType);
    payload.append('incident_type_custom', incidentTypeCustom);
    payload.append('incident_location', document.getElementById('incident_location').value || '');
    payload.append('description', document.getElementById('description').value || '');
    payload.append('status', document.getElementById('status').value || 'pending');
    payload.append('settlement_date', document.getElementById('settlement_date').value || '');

    // collect complainants
    const comps = [];
    let partyValidationError = '';
    document.querySelectorAll('#complainantsContainer .party-row').forEach(row => {
        const name = row.querySelector('[data-name]').value.trim();
        const address = row.querySelector('[data-address]').value.trim();
        const contact = row.querySelector('[data-contact]').value.trim();
        const residency = row.querySelector('[data-residency="resident"]')?.checked ? 'resident' : 'non_resident';
        const residentId = row.querySelector('[data-resident-id]')?.value || '';
        if (residency === 'resident' && !residentId) {
            partyValidationError = 'Please select a resident for all complainants marked as resident.';
            return;
        }
        if (name) comps.push({ name, address, contact, residency, resident_id: residentId ? parseInt(residentId, 10) : null });
    });
    const resps = [];
    document.querySelectorAll('#respondentsContainer .party-row').forEach(row => {
        const name = row.querySelector('[data-name]').value.trim();
        const address = row.querySelector('[data-address]').value.trim();
        const contact = row.querySelector('[data-contact]').value.trim();
        const residency = row.querySelector('[data-residency="resident"]')?.checked ? 'resident' : 'non_resident';
        const residentId = row.querySelector('[data-resident-id]')?.value || '';
        if (residency === 'resident' && !residentId) {
            partyValidationError = 'Please select a resident for all respondents marked as resident.';
            return;
        }
        if (name) resps.push({ name, address, contact, residency, resident_id: residentId ? parseInt(residentId, 10) : null });
    });

    if (partyValidationError) {
        alert(partyValidationError);
        return;
    }

    payload.append('complainants', JSON.stringify(comps));
    payload.append('respondents', JSON.stringify(resps));

    const hearings = [];
    document.querySelectorAll('#hearingsContainer .hearing-row').forEach(row => {
        const hearing_date = row.querySelector('[data-hearing-date]').value.trim();
        const status = row.querySelector('[data-hearing-status]').value;
        const outcome = row.querySelector('[data-hearing-outcome]').value.trim();
        const notes = row.querySelector('[data-hearing-notes]').value.trim();
        const next_hearing_date = row.querySelector('[data-next-hearing-date]').value.trim();
        if (hearing_date || outcome || notes || next_hearing_date) {
            hearings.push({ hearing_date, status, outcome, notes, next_hearing_date });
        }
    });
    payload.append('hearings', JSON.stringify(hearings));

    const proofInput = document.getElementById('proof_of_incident');
    if (proofInput && proofInput.files && proofInput.files[0]) {
        payload.append('proof_of_incident', proofInput.files[0]);
    }

    // Determine if this is create or update
    const id = document.getElementById('blotterId').value;
    let action = 'create';
    if (id) { action = 'update'; payload.append('id', id); }

    if (action === 'create' && !BLOTTER_PERMS.canCreate) {
        alert('Access denied');
        return;
    }
    if (action === 'update' && !BLOTTER_PERMS.canEdit) {
        alert('Access denied');
        return;
    }

    fetch(window.API_URL + `blotter.php?action=${action}`, { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('blotterModal'));
                if (modal) modal.hide();
                resetBlotterForm();
                loadBlotters();
                alert(id ? 'Blotter case updated successfully' : 'Blotter case created successfully');
            } else {
                alert('Error: ' + d.message);
            }
        })
        .catch(err => { console.error(err); alert('Error creating blotter'); });
}

function normalizePhoneDigits(raw) {
    if (!raw) return '';
    let digits = String(raw).replace(/\D/g, '');
    if (digits.startsWith('63')) digits = digits.slice(2);
    if (digits.startsWith('0')) digits = digits.slice(1);
    return digits.slice(0, 10);
}

function formatPhoneNumber(raw) {
    if (!raw) return '';
    const digits = normalizePhoneDigits(raw);
    if (!digits) return String(raw).trim();
    if (digits.length < 10) return String(raw).trim();
    return '+63 ' + digits;
}

function formatPhoneForInput(raw) {
    const digits = normalizePhoneDigits(raw);
    return '+63 ' + digits;
}

function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/\"/g, "&quot;")
         .replace(/'/g, "&#039;");
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

function initBlotterTextFormatting() {
    const caseTitleInput = document.getElementById('case_title');
    const incidentLocationInput = document.getElementById('incident_location');
    const descriptionInput = document.getElementById('description');
    const incidentTypeCustomInput = document.getElementById('incident_type_custom');
    attachTitleCaseOnBlur(caseTitleInput);
    attachTitleCaseOnBlur(incidentLocationInput);
    attachTitleCaseOnBlur(descriptionInput);
    attachTitleCaseOnBlur(incidentTypeCustomInput);
}

function applyTitleCaseToBlotterForm() {
    const ids = ['case_title', 'incident_location', 'description', 'incident_type_custom'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = toTitleCase(el.value);
    });
    document.querySelectorAll('#complainantsContainer .party-row, #respondentsContainer .party-row').forEach(row => {
        const name = row.querySelector('[data-name]');
        const address = row.querySelector('[data-address]');
        if (name) name.value = toTitleCase(name.value);
        if (address) address.value = toTitleCase(address.value);
    });
    document.querySelectorAll('#hearingsContainer .hearing-row').forEach(row => {
        const outcome = row.querySelector('[data-hearing-outcome]');
        const notes = row.querySelector('[data-hearing-notes]');
        if (outcome) outcome.value = toTitleCase(outcome.value);
        if (notes) notes.value = toTitleCase(notes.value);
    });
}

function updatePrimaryComplainantInfo() {
    const infoEl = document.getElementById('primaryComplainantInfo');
    if (!infoEl) return;
    const firstRow = document.querySelector('#complainantsContainer .party-row');
    if (!firstRow) {
        infoEl.textContent = 'Complainant Name & Contact: -';
        return;
    }
    const name = firstRow.querySelector('[data-name]')?.value?.trim() || '-';
    const contact = firstRow.querySelector('[data-contact]')?.value?.trim() || '-';
    const contactLabel = contact === '-' ? '-' : (formatPhoneNumber(contact) || contact);
    infoEl.textContent = `Complainant Name & Contact: ${toTitleCase(name) || name} (${contactLabel})`;
}

function formatIncidentType(type) {
    const key = String(type || '').trim().toLowerCase();
    const labels = {
        physical_assault: 'Physical Assault',
        verbal_threat: 'Verbal Threat',
        theft: 'Theft',
        property_damage: 'Property Damage',
        public_disturbance: 'Public Disturbance',
        domestic_dispute: 'Domestic Dispute',
        harassment: 'Harassment',
        other: 'Other'
    };
    return labels[key] || (type ? String(type).replace(/_/g, ' ') : '-');
}

function toggleIncidentTypeCustom() {
    const incidentType = document.getElementById('incident_type');
    const wrap = document.getElementById('incidentTypeCustomWrap');
    const customInput = document.getElementById('incident_type_custom');
    if (!incidentType || !wrap || !customInput) return;

    const show = incidentType.value === 'other';
    wrap.style.display = show ? '' : 'none';
    customInput.required = show;
    if (!show) customInput.value = '';
}
