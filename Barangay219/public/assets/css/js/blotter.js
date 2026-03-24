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
let currentViewingCaseId = null;
let currentViewingCaseData = null;
let respondentSearchTimer = null;
let editRespondentSelections = [];

document.addEventListener('DOMContentLoaded', function() {
    loadBlotters();
    initBlotterModal();
    applyBlotterPermissions();
    initBlotterStatFilters();
    initDetailModalEditMode();
    
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
    currentViewingCaseId = id;
    // Ensure modal is in view mode when opened (reset any previous edit state)
    try { disableEditMode(); } catch (e) {}
    fetch(`${window.API_URL}blotter.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const info = d.data;
                currentViewingCaseData = info;
                
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
                // Populate Witnesses (multiline string or JSON)
                try {
                    const rawWitnesses = info.witnesses || '';
                    let witnessesHTML = '';
                    if (rawWitnesses) {
                        // If it looks like JSON array, parse it
                        try {
                            const arr = JSON.parse(rawWitnesses);
                            if (Array.isArray(arr) && arr.length) {
                                witnessesHTML = '<ul class="mb-0">' + arr.map(w => '<li>' + escapeHtml(String(w)) + '</li>').join('') + '</ul>';
                            }
                        } catch (e) {
                            // Otherwise treat as multiline text
                            const lines = String(rawWitnesses).split(/\r?\n/).map(l => l.trim()).filter(Boolean);
                            if (lines.length) {
                                witnessesHTML = lines.length > 1 ? ('<ul class="mb-0">' + lines.map(l => '<li>' + escapeHtml(l) + '</li>').join('') + '</ul>') : ('<p>' + escapeHtml(lines[0]) + '</p>');
                            }
                        }
                    }
                    document.getElementById('viewWitnesses').innerHTML = witnessesHTML || '<p>-</p>';
                } catch (e) {
                    console.warn('Unable to render witnesses:', e);
                    document.getElementById('viewWitnesses').innerHTML = '<p>-</p>';
                }

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
                document.getElementById('viewHearingDate').textContent = formatDate(info.hearing_date) || '-';
                document.getElementById('viewDismissalReason').textContent = info.dismissal_reason || '-';
                document.getElementById('viewResolutionFile').innerHTML = renderResolutionFileLink(info.resolution_file);

                // Populate Admin Notes
                const adminNotesEl = document.getElementById('viewAdminNotes');
                if (adminNotesEl) {
                    adminNotesEl.textContent = info.admin_updates || 'No admin notes yet.';
                }

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
                
                // Load audit log
                loadAuditLog(id);
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('viewBlotterModal'));
                modal.show();
            }
        })
        .catch(err => { console.error(err); alert('Error loading blotter details'); });
}

// Initialize detail modal edit/view mode functionality
function initDetailModalEditMode() {
    const modal = document.getElementById('viewBlotterModal');
    if (!modal) return;

    const btnEdit = document.getElementById('btnEnableEditMode');
    const btnCancel = document.getElementById('btnCancelEdit');
    const btnClearRespondent = document.getElementById('clearRespondentBtn');
    const btnAddRespondent = document.getElementById('addRespondentLinkBtn');
    const searchInput = document.getElementById('editRespondentSearch');
    const statusSelect = document.getElementById('editStatus');
    const caseForm = document.getElementById('caseDetailForm');
    const viewContent = document.getElementById('viewModeContent');

    if (btnEdit) {
        btnEdit.addEventListener('click', () => enableEditMode());
    }

    if (btnCancel) {
        btnCancel.addEventListener('click', () => disableEditMode());
    }

    if (btnClearRespondent) {
        btnClearRespondent.addEventListener('click', () => {
            editRespondentSelections = [];
            syncEditRespondentIdsField();
            renderEditRespondentSelections();
            document.getElementById('editRespondentSearch').value = '';
            document.getElementById('respondentSearchResults').style.display = 'none';
        });
    }

    if (btnAddRespondent) {
        btnAddRespondent.addEventListener('click', () => {
            const keyword = (searchInput?.value || '').trim();
            if (keyword.length >= 3) {
                searchResidentsForRespondent(keyword);
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const keyword = e.target.value;
            if (respondentSearchTimer) clearTimeout(respondentSearchTimer);
            respondentSearchTimer = setTimeout(() => searchResidentsForRespondent(keyword), 250);
        });

        searchInput.addEventListener('blur', (e) => {
            // Don't hide if clicking on a result button
            setTimeout(() => {
                const resultsContainer = document.getElementById('respondentSearchResults');
                if (resultsContainer && !resultsContainer.contains(document.activeElement)) {
                    resultsContainer.style.display = 'none';
                }
            }, 100);
        });

        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 3) {
                searchResidentsForRespondent(searchInput.value);
            }
        });

        // Also add event listener to results container to prevent closing when hovering/clicking
        const resultsContainer = document.getElementById('respondentSearchResults');
        if (resultsContainer) {
            resultsContainer.addEventListener('click', (e) => {
                // Prevent default to ensure our button handlers work
                if (e.target.closest('button')) {
                    e.preventDefault();
                }
            });
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', () => {
            toggleProcessFieldsByStatus(statusSelect.value);
        });
    }

    if (caseForm) {
        caseForm.addEventListener('submit', (e) => submitCaseDetailUpdate(e));
    }

    // Monitor the edit-mode alert for unexpected hiding and restore it while logging the cause
    initEditModeAlertObserver();
}

function initEditModeAlertObserver() {
    const alertEl = document.getElementById('editModeAlert');
    if (!alertEl || typeof MutationObserver === 'undefined') return;

    let restoring = false;
    const obs = new MutationObserver((mutations) => {
        try {
            // Check computed style to detect cases where inline style isn't changed but CSS hides it
            const comp = window.getComputedStyle(alertEl);
            if (comp && comp.display === 'none') {
                if (restoring) return;
                restoring = true;
                console.warn('editModeAlert was hidden unexpectedly — restoring. Mutation details:', mutations, '\nStack:', new Error().stack);
                // Force it visible and mark restoration timestamp
                alertEl.style.display = '';
                alertEl.setAttribute('data-edit-alert-restored-at', new Date().toISOString());
                // release guard shortly after
                setTimeout(() => { restoring = false; }, 250);
            }
        } catch (e) {
            console.error('Error in editModeAlert observer:', e);
        }
    });

    obs.observe(alertEl, { attributes: true, attributeFilter: ['style', 'class'], childList: false, subtree: false });

    // Also watch the modal container in case the element is being replaced
    const modal = document.getElementById('viewBlotterModal');
    if (modal) {
        const modalObs = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList' && m.removedNodes && Array.from(m.removedNodes).includes(alertEl)) {
                    console.warn('editModeAlert node was removed from DOM — re-inserting placeholder and logging stack. Stack:', new Error().stack);
                    // re-add a lightweight placeholder so user still sees message
                    try {
                        const placeholder = document.createElement('div');
                        placeholder.id = 'editModeAlert';
                        placeholder.className = 'alert alert-info mb-3';
                        placeholder.innerHTML = '<i class="bi bi-info-circle"></i> You are now editing this case. Make changes and click Save.';
                        modal.querySelector('.modal-body')?.insertBefore(placeholder, modal.querySelector('.modal-body').firstChild || null);
                    } catch (e) { console.error('Failed to re-insert editModeAlert placeholder:', e); }
                }
            }
        });
        modalObs.observe(modal, { childList: true, subtree: true });
    }
}

function enableEditMode() {
    if (!currentViewingCaseId) {
        alert('No case loaded');
        return;
    }

    const caseForm = document.getElementById('caseDetailForm');
    const viewContent = document.getElementById('viewModeContent');
    const statusEl = document.getElementById('editStatus');
    const adminNotesEl = document.getElementById('editAdminNotes');
    const hearingDateEl = document.getElementById('editHearingDate');
    const settlementDateEl = document.getElementById('editSettlementDate');
    const dismissalReasonEl = document.getElementById('editDismissalReason');
    const respondentSearchEl = document.getElementById('editRespondentSearch');

    if (viewContent && caseForm) {
        viewContent.style.display = 'none';
        caseForm.style.display = 'block';

        // Populate form with current values
        const currentStatus = document.getElementById('viewStatus').textContent || 'pending';
        const currentAdminNotes = document.getElementById('viewAdminNotes').textContent || '';

        document.getElementById('editCaseId').value = currentViewingCaseId;
        if (statusEl) statusEl.value = mapAdminStatusToDB(currentStatus);
        if (adminNotesEl) adminNotesEl.value = currentAdminNotes;

        if (currentViewingCaseData) {
            const data = currentViewingCaseData;
            if (hearingDateEl) hearingDateEl.value = formatDateTimeLocal(data.hearing_date || '');
            if (settlementDateEl) settlementDateEl.value = (data.settlement_date || '').substring(0, 10);
            if (dismissalReasonEl) dismissalReasonEl.value = data.dismissal_reason || '';

            editRespondentSelections = [];
            try {
                const respondents = JSON.parse(data.respondent_name || '[]');
                if (Array.isArray(respondents) && respondents.length > 0) {
                    respondents.forEach((item) => {
                        if (!item) return;
                        const residentId = Number(item.resident_id || 0);
                        const name = String(item.name || '').trim();
                        if (!residentId && !name) return;
                        editRespondentSelections.push({
                            resident_id: residentId > 0 ? residentId : null,
                            name: name,
                            address: String(item.address || ''),
                            contact: String(item.contact || ''),
                            residency: String(item.residency || (residentId > 0 ? 'resident' : 'non_resident'))
                        });
                    });
                } else if (data.respondent_id) {
                    editRespondentSelections.push({
                        resident_id: Number(data.respondent_id),
                        name: String(data.respondent_name_raw || ''),
                        address: '',
                        contact: '',
                        residency: 'resident'
                    });
                }
            } catch (e) {
                if (data.respondent_id || data.respondent_name_raw) {
                    editRespondentSelections.push({
                        resident_id: data.respondent_id ? Number(data.respondent_id) : null,
                        name: String(data.respondent_name_raw || ''),
                        address: '',
                        contact: '',
                        residency: data.respondent_id ? 'resident' : 'non_resident'
                    });
                }
            }
        }

        if (respondentSearchEl) {
            respondentSearchEl.value = '';
        }
        syncEditRespondentIdsField();
        renderEditRespondentSelections();

        const editAlert = document.getElementById('editModeAlert');
        if (editAlert) editAlert.style.display = '';

        toggleProcessFieldsByStatus(statusEl ? statusEl.value : 'pending');

    }
}

function disableEditMode() {
    const caseForm = document.getElementById('caseDetailForm');
    const viewContent = document.getElementById('viewModeContent');

    if (caseForm && viewContent) {
        caseForm.style.display = 'none';
        viewContent.style.display = 'block';
        caseForm.reset();
        editRespondentSelections = [];
        syncEditRespondentIdsField();
        renderEditRespondentSelections();
        toggleProcessFieldsByStatus('pending');

        const editAlert = document.getElementById('editModeAlert');
        if (editAlert) editAlert.style.display = 'none';
    }
}

function toggleProcessFieldsByStatus(status) {
    const mediationFields = document.getElementById('mediationFields');
    const settledFields = document.getElementById('settledFields');
    const dismissedFields = document.getElementById('dismissedFields');

    if (mediationFields) mediationFields.style.display = status === 'mediation' ? '' : 'none';
    if (settledFields) settledFields.style.display = status === 'settled' ? '' : 'none';
    if (dismissedFields) dismissedFields.style.display = status === 'dismissed' ? '' : 'none';
}

function searchResidentsForRespondent(keyword) {
    const resultsContainer = document.getElementById('respondentSearchResults');
    if (!resultsContainer) return;

    if (!keyword.trim() || keyword.trim().length < 3) {
        resultsContainer.innerHTML = '';
        resultsContainer.style.display = 'none';
        return;
    }

    fetch(`${window.API_URL}residents/search.php?q=${encodeURIComponent(keyword.trim())}`)
        .then(r => r.json())
        .then(d => {
            const residents = (d && d.success && Array.isArray(d.data)) ? d.data : [];

            if (residents.length === 0) {
                resultsContainer.innerHTML = '<div class="list-group-item text-muted small">No matching residents</div>';
                resultsContainer.style.display = 'block';
                return;
            }

            resultsContainer.innerHTML = residents.map(r => {
                const fullName = String(r.full_name || '').trim();
                const purok = String(r.purok_sitio || 'Not specified').trim();
                return `<button type="button" class="list-group-item list-group-item-action select-resident-btn" data-resident-id="${r.id}" data-resident-name="${escapeHtml(fullName)}" onmousedown='selectRespondentFromSearch(${r.id}, ${JSON.stringify(fullName)}); return false;'>
                    <div class="fw-semibold">${escapeHtml(fullName)}</div>
                    <div class="small text-muted">Purok: ${escapeHtml(purok)}</div>
                </button>`;
            }).join('');

            resultsContainer.style.display = 'block';
        })
        .catch(() => {
            resultsContainer.innerHTML = '<div class="list-group-item text-danger small">Unable to search residents</div>';
            resultsContainer.style.display = 'block';
        });
}

function syncEditRespondentIdsField() {
    const idsField = document.getElementById('editRespondentIds');
    if (!idsField) return;
    const ids = editRespondentSelections
        .map(item => Number(item.resident_id || 0))
        .filter(id => id > 0);
    idsField.value = JSON.stringify(ids);
}

function renderEditRespondentSelections() {
    const container = document.getElementById('selectedRespondentsList');
    if (!container) return;
    if (!editRespondentSelections.length) {
        container.innerHTML = '<span class="text-muted small">No respondents linked.</span>';
        return;
    }

    container.innerHTML = editRespondentSelections.map((item, index) => {
        const label = escapeHtml(String(item.name || ('Respondent #' + (index + 1))));
        const linked = Number(item.resident_id || 0) > 0;
        const badgeClass = linked ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
        const title = linked ? 'Resident linked' : 'Name-only respondent';
        return `<span class="badge ${badgeClass}" title="${title}">${label} <button type="button" class="btn btn-sm p-0 border-0 bg-transparent align-baseline ms-1" data-remove-respondent-index="${index}" aria-label="Remove">&times;</button></span>`;
    }).join('');

    container.querySelectorAll('[data-remove-respondent-index]').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = Number(btn.getAttribute('data-remove-respondent-index'));
            if (Number.isNaN(idx)) return;
            editRespondentSelections.splice(idx, 1);
            syncEditRespondentIdsField();
            renderEditRespondentSelections();
        });
    });
}

function selectRespondentFromSearch(residentId, residentName) {
    const id = Number(residentId || 0);
    if (!id) return;
    // Allow adding the same resident multiple times
    editRespondentSelections.push({
        resident_id: id,
        name: String(residentName || '').trim(),
        address: '',
        contact: '',
        residency: 'resident'
    });
    syncEditRespondentIdsField();
    renderEditRespondentSelections();
    document.getElementById('editRespondentSearch').value = residentName;
    document.getElementById('respondentSearchResults').style.display = 'none';
}

function submitCaseDetailUpdate(e) {
    e.preventDefault();

    const caseId = document.getElementById('editCaseId').value;
    const status = document.getElementById('editStatus').value;
    const respondentsJson = JSON.stringify(editRespondentSelections);
    const adminNotes = document.getElementById('editAdminNotes').value;
    const hearingDate = document.getElementById('editHearingDate')?.value || '';
    const settlementDate = document.getElementById('editSettlementDate')?.value || '';
    const dismissalReason = document.getElementById('editDismissalReason')?.value || '';
    const resolutionFile = document.getElementById('editResolutionFile')?.files?.[0] || null;


    // Require at least one respondent for Under Investigation and all statuses except Pending
    if ((status === 'investigation' || status === 'mediation' || status === 'settled' || status === 'dismissed') && !editRespondentSelections.length) {
        alert('Please link at least one respondent before setting this status.');
        return;
    }

    // Require hearing date for Mediation
    if (status === 'mediation' && !hearingDate) {
        alert('Hearing date is required when status is Mediation.');
        return;
    }

    // Require settlement date and resolution file for Settled
    if (status === 'settled') {
        if (!settlementDate) {
            alert('Settlement date is required when status is Settled.');
            return;
        }
        if (!resolutionFile) {
            alert('Resolution file is required when status is Settled.');
            return;
        }
    }

    // Require admin notes for Dismissed
    if (status === 'dismissed' && !adminNotes.trim()) {
        alert('Admin notes are required when status is Dismissed.');
        return;
    }

    // Lock editing for Settled (disable form fields and edit button after save)
    if (status === 'settled') {
        setTimeout(() => {
            const btnEdit = document.getElementById('btnEnableEditMode');
            if (btnEdit) btnEdit.disabled = true;
            const caseForm = document.getElementById('caseDetailForm');
            if (caseForm) {
                Array.from(caseForm.elements).forEach(el => { el.disabled = true; });
            }
        }, 1000);
    }

    const formData = new FormData();
    formData.append('case_id', caseId);
    formData.append('status', status);
    formData.append('respondents_json', respondentsJson);
    formData.append('admin_notes', adminNotes);
    formData.append('hearing_date', hearingDate);
    formData.append('settlement_date', settlementDate);
    formData.append('dismissal_reason', dismissalReason);
    if (resolutionFile) formData.append('resolution_file', resolutionFile);

    fetch(window.API_URL + 'blotter/process_case.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // If backend returned respondents, render them into the view immediately
                try {
                    const resps = Array.isArray(d.data && d.data.respondents) ? d.data.respondents : null;
                    if (resps) {
                        const respondentsHTML = resps.map((r, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Respondent ${idx + 1}:</strong> ${toTitleCase(r.name || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${toTitleCase(r.address || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Barangay:</strong> ${toTitleCase(r.barangay || '-') || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${formatPhoneNumber(r.contact) || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                        const viewEl = document.getElementById('viewRespondentsInfo');
                        if (viewEl) viewEl.innerHTML = respondentsHTML || '<p>-</p>';
                        // Update cached currentViewingCaseData so subsequent actions reflect new data
                        if (!currentViewingCaseData) currentViewingCaseData = {};
                        currentViewingCaseData.respondent_name = JSON.stringify(resps);
                        currentViewingCaseData.respondent_id = d.data.respondent_id ?? currentViewingCaseData.respondent_id;
                    }
                } catch (e) {
                    console.warn('Unable to render respondents from response:', e);
                }

                const modalEl = document.getElementById('viewBlotterModal');
                const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                if (modal) modal.hide();
                loadBlotters();
                showBlotterSuccessToast('Case processed successfully.');
                // Always reset modal to view mode after save
                disableEditMode();
            } else {
                alert('Error: ' + d.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error updating case: ' + err.message);
        });
}

function showBlotterSuccessToast(message) {
    const existing = document.getElementById('blotterSuccessToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'blotterSuccessToast';
    toast.className = 'alert alert-success shadow';
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '2000';
    toast.style.minWidth = '260px';
    toast.textContent = message || 'Saved successfully.';

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

function formatDateTimeLocal(value) {
    if (!value) return '';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return '';
    const yyyy = dt.getFullYear();
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const dd = String(dt.getDate()).padStart(2, '0');
    const hh = String(dt.getHours()).padStart(2, '0');
    const mi = String(dt.getMinutes()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
}

function renderResolutionFileLink(path) {
    const filePath = String(path || '').trim();
    if (!filePath) {
        return 'No file uploaded';
    }
    const href = filePath.startsWith('http')
        ? filePath
        : (window.location.origin + '/TeamPagal_Barangay219/Barangay219/public/' + filePath.replace(/^\/+/, ''));
    return `<a href="${href}" target="_blank" rel="noopener">View Signed Resolution</a>`;
}

function refreshBlotterDetailView(caseId) {
    // Fetch updated case data and refresh only the view section without remounting modal
    fetch(`${window.API_URL}blotter.php?action=get&id=${caseId}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const info = d.data;
                currentViewingCaseData = info;
                
                // Update all view mode fields
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
                
                // Update Complainants
                let complainantsHTML = '';
                try {
                    const comps = JSON.parse(info.complainant_name);
                    if (Array.isArray(comps)) {
                        complainantsHTML = comps.map((c, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Complainant ${idx + 1}:</strong> ${toTitleCase(c.name || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${toTitleCase(c.address || '-') || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${formatPhoneNumber(c.contact) || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                    }
                } catch(e) {
                    complainantsHTML = `<p>${toTitleCase(info.complainant_name || '-') || '-'}</p>`;
                }
                document.getElementById('viewComplainantsInfo').innerHTML = complainantsHTML || '<p>-</p>';
                
                // Update Witnesses
                try {
                    const rawWitnesses = info.witnesses || '';
                    let witnessesHTML = '';
                    if (rawWitnesses) {
                        try {
                            const arr = JSON.parse(rawWitnesses);
                            if (Array.isArray(arr) && arr.length) {
                                witnessesHTML = '<ul class="mb-0">' + arr.map(w => '<li>' + escapeHtml(String(w)) + '</li>').join('') + '</ul>';
                            }
                        } catch (e) {
                            const lines = String(rawWitnesses).split(/\r?\n/).map(l => l.trim()).filter(Boolean);
                            if (lines.length) {
                                witnessesHTML = lines.length > 1 ? ('<ul class="mb-0">' + lines.map(l => '<li>' + escapeHtml(l) + '</li>').join('') + '</ul>') : ('<p>' + escapeHtml(lines[0]) + '</p>');
                            }
                        }
                    }
                    document.getElementById('viewWitnesses').innerHTML = witnessesHTML || '<p>-</p>';
                } catch (e) {
                    console.warn('Unable to render witnesses:', e);
                    document.getElementById('viewWitnesses').innerHTML = '<p>-</p>';
                }

                // Update Respondents
                let respondentsHTML = '';
                try {
                    const resps = JSON.parse(info.respondent_name);
                    if (Array.isArray(resps)) {
                        respondentsHTML = resps.map((r, idx) => `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Respondent ${idx + 1}:</strong> ${toTitleCase(r.name || '-') || '-'}</p>
                                    <p class="mb-1"><strong>Address:</strong> ${toTitleCase(r.address || '-') || '-'}</p>
                                    <p class="mb-0"><strong>Contact:</strong> ${formatPhoneNumber(r.contact) || '-'}</p>
                                </div>
                            </div>
                        `).join('');
                    }
                } catch(e) {
                    respondentsHTML = `<p>${toTitleCase(info.respondent_name || '-') || '-'}</p>`;
                }
                document.getElementById('viewRespondentsInfo').innerHTML = respondentsHTML || '<p>-</p>';
                
                // Update Status and Settlement Date
                document.getElementById('viewStatus').textContent = info.status || '-';
                document.getElementById('viewSettlementDate').textContent = formatDate(info.settlement_date) || '-';
                document.getElementById('viewHearingDate').textContent = formatDate(info.hearing_date) || '-';
                document.getElementById('viewDismissalReason').textContent = info.dismissal_reason || '-';
                document.getElementById('viewResolutionFile').innerHTML = renderResolutionFileLink(info.resolution_file);
                
                // Update Admin Notes
                const adminNotesEl = document.getElementById('viewAdminNotes');
                if (adminNotesEl) {
                    adminNotesEl.textContent = info.admin_updates || 'No admin notes yet.';
                }
                
                // Update Hearings
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
                
                // Reload audit log
                loadAuditLog(caseId);
                
                // Switch back to view mode
                disableEditMode();
            }
        })
        .catch(err => {
            console.error('Error refreshing blotter detail:', err);
            alert('Error refreshing case details');
        });
}

function loadAuditLog(caseId) {
    fetch(window.API_URL + 'blotter/audit-log.php?case_id=' + caseId)
        .then(r => r.json())
        .then(d => {
            const auditContainer = document.getElementById('viewAuditLog');
            if (!auditContainer) return;

            if (!d.success || !Array.isArray(d.data) || d.data.length === 0) {
                auditContainer.innerHTML = '<p class="text-muted">No changes recorded yet.</p>';
                return;
            }

            auditContainer.innerHTML = d.data
                .slice()
                .reverse()
                .map(log => {
                    const timestamp = new Date(log.timestamp).toLocaleString();
                    const action = log.action === 'status_change' ? '🔄 Status Changed' :
                                  log.action === 'respondent_link' ? '🔗 Respondent Linked' :
                                  log.action === 'admin_notes' ? '📝 Notes Updated' : log.action;
                    return `<div class="mb-2 pb-2 border-bottom">
                        <strong>${action}</strong> by ${log.admin_name}<br>
                        <small class="text-muted">${timestamp}</small><br>
                        ${log.notes ? `<small>${escapeHtml(log.notes)}</small>` : ''}
                    </div>`;
                })
                .join('');
        })
        .catch(err => {
            console.error('Error loading audit log:', err);
            const auditContainer = document.getElementById('viewAuditLog');
            if (auditContainer) auditContainer.innerHTML = '<p class="text-muted">Unable to load audit log.</p>';
        });
}

function mapAdminStatusToDB(adminStatus) {
    if (!adminStatus) return 'pending';
    const key = String(adminStatus).trim().toLowerCase().replace(/\s+/g, '_');
    const mapping = {
        'pending': 'pending',
        'under_investigation': 'investigation',
        'under_investigation': 'investigation',
        'investigation': 'investigation',
        'mediation': 'mediation',
        'settled': 'settled',
        'resolved': 'settled',
        'dismissed': 'dismissed',
        'referred': 'referred'
    };
    return mapping[key] || 'pending';
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
                const known = ['physical_assault','verbal_threat','vawc','theft','property_damage','public_disturbance','domestic_dispute','harassment','other'];
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
        vawc: 'VAWC',
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
