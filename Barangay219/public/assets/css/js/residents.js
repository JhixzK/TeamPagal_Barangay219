/**
 * E-Barangay Information Management System
 * Residents Management JavaScript
 */

let currentPage = 1;
let residentFilters = { q: '', status: '', gender: '', household_head: '', age_from: '', age_to: '' };

const RESIDENT_PERMS = {
    canCreate: window.canModulePermission ? window.canModulePermission('residents', 'can_create') : true,
    canEdit: window.canModulePermission ? window.canModulePermission('residents', 'can_edit') : true,
    canDelete: window.canModulePermission ? window.canModulePermission('residents', 'can_delete') : true
};

document.addEventListener('DOMContentLoaded', function() {
    loadResidents();

    applyResidentPermissions();
    initResidentStatFilters();

    initResidentFormValidation();
    
    document.getElementById('residentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveResident();
    });
    
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchResidents();
    });
    
    document.getElementById('btnEditFromView').addEventListener('click', function() {
        const id = this.dataset.residentId;
        if (id) { bootstrap.Modal.getInstance(document.getElementById('viewResidentModal')).hide(); editResident(parseInt(id)); }
    });
});

function initResidentStatFilters() {
    const tabs = document.querySelectorAll('#statusTabs .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const status = this.getAttribute('data-status') || '';
            residentFilters.status = status;
            const statusSel = document.getElementById('filterStatus');
            if (statusSel) statusSel.value = status;
            loadResidents(1);
        });
    });
}

function initResidentFormValidation() {
    const firstName = document.getElementById('first_name');
    const middleName = document.getElementById('middle_name');
    const lastName = document.getElementById('last_name');
    const suffix = document.getElementById('suffix');
    const contact = document.getElementById('contact_number');
    const citizenship = document.getElementById('citizenship');

    if (firstName) validateNameInput(firstName);
    if (middleName) validateNameInput(middleName);
    if (lastName) validateNameInput(lastName);
    if (suffix) validateNameInput(suffix, true);
    if (contact) validatePhoneInput(contact);
    if (citizenship) attachTitleCaseOnBlur(citizenship);
}

// Name input validation - only allow letters, spaces, and dots (for suffix)
function validateNameInput(input, allowDot = false) {
    input.addEventListener('input', function() {
        const regex = allowDot ? /[^a-zA-Z\s.]/g : /[^a-zA-Z\s]/g;
        this.value = this.value.replace(regex, '');
    });
    input.addEventListener('blur', function() {
        this.value = toTitleCase(this.value);
    });
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

    input.addEventListener('blur', function() {
        const digits = normalizePhoneDigits(this.value);
        this.value = digits ? ('+63 ' + digits) : '+63 ';
    });
}

function applyResidentPermissions() {
    if (!RESIDENT_PERMS.canCreate) {
        const openBtn = document.getElementById('btnOpenCreate');
        if (openBtn) openBtn.style.display = 'none';
    }
    if (!RESIDENT_PERMS.canEdit) {
        const editBtn = document.getElementById('btnEditFromView');
        if (editBtn) editBtn.style.display = 'none';
    }
}

/**
 * Load all residents
 */
function loadResidents(page = 1) {
    currentPage = page;
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    const itemsPerPage = window.ITEMS_PER_PAGE || 20;
    const params = new URLSearchParams({
        action: 'list',
        page: page.toString(),
        limit: itemsPerPage.toString()
    });
    if (residentFilters.q) params.append('q', residentFilters.q);
    if (residentFilters.status) params.append('status', residentFilters.status);
    if (residentFilters.gender) params.append('gender', residentFilters.gender);
    if (residentFilters.household_head) params.append('household_head', residentFilters.household_head);
    if (residentFilters.age_from) params.append('age_from', residentFilters.age_from);
    if (residentFilters.age_to) params.append('age_to', residentFilters.age_to);

    fetch(`${apiUrl}resident.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayResidents(data.data.residents);
                displayPagination(data.data);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error loading residents');
        });
}

/**
 * Display residents in table
 */
function displayResidents(residents) {
    const tbody = document.getElementById('residentsTableBody');
    
    if (residents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">No residents found</td></tr>';
        return;
    }

    const statusRank = status => {
        const s = String(status || '').toLowerCase();
        if (s === 'active') return 0;
        if (s === 'inactive') return 2;
        return 1;
    };

    const sortedResidents = residents
        .map((resident, idx) => ({ resident, idx }))
        .sort((a, b) => {
            const ra = statusRank(a.resident.status);
            const rb = statusRank(b.resident.status);
            if (ra !== rb) return ra - rb;
            return a.idx - b.idx;
        })
        .map(item => item.resident);

    tbody.innerHTML = sortedResidents.map(resident => {
        const rawFullName = `${resident.first_name || ''} ${resident.middle_name || ''} ${resident.last_name || ''} ${resident.suffix || ''}`.trim();
        const fullName = escapeHtml(toTitleCase(rawFullName));
        const age = calculateAge(resident.birth_date);
        const residentCode = resident.resident_code
            ? `<span class="resident-code-badge">${escapeHtml(resident.resident_code)}</span>`
            : '<span class="resident-secondary">N/A</span>';
        const verificationStatus = normalizeVerificationStatus(resident);
        const hasIdUpload = !!(resident.id_document_path && String(resident.id_document_path).trim() !== '');
        const canVerifyNow = RESIDENT_PERMS.canEdit && hasIdUpload && verificationStatus !== 'verified';
        const canRejectNow = RESIDENT_PERMS.canEdit && hasIdUpload && verificationStatus === 'pending';
        
        const isHead = String(resident.is_household_head) === '1';
        const householdCode = resident.household_code
            ? `<span class="resident-code-badge">${escapeHtml(String(resident.household_code))}</span>`
            : '<span class="resident-secondary">-</span>';
        const familyHeadCode = resident.family_head_code
            ? `<span class="resident-code-badge">${escapeHtml(String(resident.family_head_code))}</span>`
            : '<span class="resident-secondary">-</span>';
        return `
            <tr>
                <td class="text-center">${residentCode}</td>
                <td class="text-center fw-semibold">${fullName}</td>
                <td class="text-center"><span class="resident-secondary">${formatDate(resident.birth_date)} (${age} yrs)</span></td>
                <td class="text-center">${formatGender(resident.gender)}</td>
                <td class="text-center"><span class="resident-secondary">${escapeHtml(formatTitleCaseTruncate(resident.address || '', 40))}${(resident.address||'').length>40?'...':''}</span></td>
                <td class="text-center"><span class="resident-secondary">${escapeHtml(formatPhoneNumber(resident.contact_number) || '-')}</span></td>
                <td class="text-center">${householdCode}</td>
                <td class="text-center">${familyHeadCode}</td>
                <td class="text-center">${getVerificationBadge(verificationStatus)}</td>
                <td class="text-center"><span class="resident-pill ${getStatusClass(resident.status)}">${formatStatus(resident.status)}</span></td>
                <td class="text-center">
                    <div class="resident-actions">
                        ${RESIDENT_PERMS.canEdit ? `<button class="action-icon-btn" title="Edit" aria-label="Edit" onclick="editResident(${resident.id})"><i class="bi bi-pencil-square"></i></button>` : ''}
                        <button class="action-icon-btn" title="View" aria-label="View" onclick="viewResident(${resident.id})"><i class="bi bi-eye"></i></button>
                        ${canVerifyNow ? `<button class="action-icon-btn" title="Verify ID" aria-label="Verify ID" onclick="verifyResidentId(${resident.id}, 'verified')"><i class="bi bi-patch-check"></i></button>` : ''}
                        ${canRejectNow ? `<button class="action-icon-btn" title="Reject ID" aria-label="Reject ID" onclick="verifyResidentId(${resident.id}, 'rejected')"><i class="bi bi-patch-exclamation"></i></button>` : ''}
                        ${RESIDENT_PERMS.canDelete ? `<button class="action-icon-btn action-delete" title="Delete" aria-label="Delete" onclick="deleteResident(${resident.id})"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Display pagination
 */
function displayPagination(data) {
    const pagination = document.getElementById('pagination');
    const totalPages = data.total_pages;
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadResidents(${currentPage - 1}); return false;">Previous</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadResidents(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next button
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadResidents(${currentPage + 1}); return false;">Next</a>
    </li>`;
    
    pagination.innerHTML = html;
}

/**
 * Search residents
 */
function searchResidents() {
    const query = document.getElementById('searchInput').value.trim();

    residentFilters.q = query;
    loadResidents(1);
}

function applyFilters() {
    residentFilters.status = document.getElementById('filterStatus')?.value || '';
    residentFilters.gender = document.getElementById('filterGender')?.value || '';
    residentFilters.household_head = document.getElementById('filterHouseholdHead')?.value || '';
    residentFilters.age_from = document.getElementById('filterAgeFrom')?.value || '';
    residentFilters.age_to = document.getElementById('filterAgeTo')?.value || '';
    syncResidentStatusTabs();
    loadResidents(1);
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) modal.hide();
}

function resetResidents() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    residentFilters = { q: '', status: '', gender: '', household_head: '', age_from: '', age_to: '' };
    const statusSel = document.getElementById('filterStatus');
    const genderSel = document.getElementById('filterGender');
    const householdHeadSel = document.getElementById('filterHouseholdHead');
    const ageFrom = document.getElementById('filterAgeFrom');
    const ageTo = document.getElementById('filterAgeTo');
    if (statusSel) statusSel.value = '';
    if (genderSel) genderSel.value = '';
    if (householdHeadSel) householdHeadSel.value = '';
    if (ageFrom) ageFrom.value = '';
    if (ageTo) ageTo.value = '';
    syncResidentStatusTabs();
    loadResidents(1);
}

function syncResidentStatusTabs() {
    document.querySelectorAll('#statusTabs .nav-link').forEach(tab => {
        const tabStatus = tab.getAttribute('data-status') || '';
        tab.classList.toggle('active', tabStatus === (residentFilters.status || ''));
    });
}

/**
 * Edit resident
 */
function editResident(id) {
    if (!RESIDENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }
    const apiUrl = window.API_URL;
    if (!apiUrl) { showAlert('error', 'Configuration error.'); return; }
    fetch(apiUrl + 'resident.php?action=get&id=' + id)
        .then(r => r.json())
        .then(residentData => {
        if (!residentData.success) { showAlert('error', residentData.message); return; }
        const resident = residentData.data;
        document.getElementById('residentId').value = resident.id;
        document.getElementById('first_name').value = toTitleCase(resident.first_name || '');
        document.getElementById('middle_name').value = toTitleCase(resident.middle_name || '');
        document.getElementById('last_name').value = toTitleCase(resident.last_name || '');
        document.getElementById('suffix').value = toTitleCase(resident.suffix || '');
        const bd = resident.birth_date != null ? String(resident.birth_date) : '';
        document.getElementById('birth_date').value = bd ? bd.slice(0, 10) : '';
        document.getElementById('gender').value = resident.gender;
        document.getElementById('civil_status').value = resident.civil_status || '';
        const occupationSelect = document.getElementById('occupation');
        if (occupationSelect) {
            const occupationValue = resident.occupation || '';
            if (occupationValue && !occupationSelect.querySelector(`option[value="${occupationValue.replace(/"/g, '&quot;')}"]`)) {
                const opt = document.createElement('option');
                opt.value = occupationValue;
                opt.textContent = occupationValue;
                occupationSelect.appendChild(opt);
            }
            occupationSelect.value = occupationValue;
        }
        document.getElementById('citizenship').value = toTitleCase(resident.citizenship || 'Filipino');
        const addrDefaults = window.RESIDENT_EDIT_ADDRESS_DEFAULTS || {};
        document.getElementById('house_number').value = pickAddrPart(
            resident.house_number,
            resident.registration_house_number
        );
        const streetEl = document.getElementById('street');
        const streetVal = pickAddrPart(resident.street, resident.registration_street);
        if (streetEl) ensureSelectOptionValue(streetEl, streetVal);
        const brgy = pickAddrPart(resident.barangay, resident.registration_barangay, addrDefaults.barangay);
        const cty = pickAddrPart(resident.city, resident.registration_city, addrDefaults.city);
        const prov = pickAddrPart(resident.province, resident.registration_province, addrDefaults.province);
        const brEl = document.getElementById('barangay');
        const ctyEl = document.getElementById('city');
        const provEl = document.getElementById('province');
        if (brEl) brEl.value = brgy;
        if (ctyEl) ctyEl.value = cty;
        if (provEl) provEl.value = prov;
        const brd = document.getElementById('barangay_display');
        const ctyd = document.getElementById('city_display');
        const provd = document.getElementById('province_display');
        if (brd) brd.value = brgy;
        if (ctyd) ctyd.value = cty;
        if (provd) provd.value = prov;
        document.getElementById('contact_number').value = formatPhoneForInput(resident.contact_number) || '+63 ';
        const miEl = document.getElementById('monthly_income');
        if (miEl) {
            const monthlyIncomeVal = (resident.registration_monthly_income != null && resident.registration_monthly_income !== '')
                ? resident.registration_monthly_income
                : ((resident.monthly_income != null && resident.monthly_income !== '')
                    ? resident.monthly_income
                    : resident.registration_household_income);
            miEl.value = (monthlyIncomeVal != null && monthlyIncomeVal !== '') ? String(monthlyIncomeVal) : '';
        }
        const residencyRaw = (resident.registration_residency_start_date != null && String(resident.registration_residency_start_date).trim() !== '')
            ? resident.registration_residency_start_date
            : resident.residency_start_date;
        const rsd = residencyRaw != null ? String(residencyRaw) : '';
        document.getElementById('residency_start_date').value = rsd.trim() ? rsd.slice(0, 10) : '';
        document.getElementById('status').value = resident.status;
        document.getElementById('residentModalTitle').textContent = 'Edit Resident';
        initResidentFormValidation();
        new bootstrap.Modal(document.getElementById('residentModal')).show();
    }).catch(() => showAlert('error', 'Error loading resident'));
}

/**
 * View resident details
 */
function viewResident(id) {
    const apiUrl = window.API_URL;
    if (!apiUrl) { showAlert('error', 'Configuration error.'); return; }
    fetch(`${apiUrl}resident.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showAlert('error', data.message); return; }
            const r = data.data;
            const pickFirstValue = (...values) => {
                for (const value of values) {
                    if (value === null || value === undefined) continue;
                    const str = String(value).trim();
                    if (str !== '') return value;
                }
                return '';
            };

            const shouldShowOtherSpecify = (value) => {
                const normalized = String(value || '').trim().toLowerCase();
                if (!normalized) return false;
                if (normalized === 'other') return true;
                return normalized.includes('other') && normalized.includes('specify');
            };

            const firstName = pickFirstValue(r.registration_first_name, r.first_name);
            const middleName = pickFirstValue(r.registration_middle_name, r.middle_name);
            const lastName = pickFirstValue(r.registration_last_name, r.last_name);
            const suffixName = pickFirstValue(r.registration_suffix, r.suffix);
            const fullName = `${firstName || ''} ${middleName || ''} ${lastName || ''} ${suffixName || ''}`.trim();
            const birthDateValue = pickFirstValue(r.birth_date, r.registration_birth_date);
            const age = calculateAge(birthDateValue);
            const residentCode = r.resident_code ? escapeHtml(r.resident_code) : '-';
            const verificationStatus = normalizeVerificationStatus(r);
            const idDocLink = buildResidentFileLink(r.id_document_path, 'View Uploaded ID');
            const voterStatus = pickFirstValue(r.registration_voter_status, r.voter_status, '-');
            const precinctNumber = pickFirstValue(r.registration_precinct_number, r.precinct_number, '-');
            const houseType = pickFirstValue(r.registration_house_type, r.house_type, '-');
            const houseOwnership = pickFirstValue(r.registration_house_ownership, r.house_ownership, '-');
            const householdRole = pickFirstValue(r.registration_household_role, r.relationship_to_head, r.household_role, '-');
            const residencyStart = pickFirstValue(r.registration_residency_start_date, r.residency_start_date, '');
            const residencyLength = pickFirstValue(r.registration_length_of_residency, r.computed_length_of_residency, r.length_of_residency, '-');
            const monthlyIncome = formatCurrency(
                (r.registration_monthly_income != null && r.registration_monthly_income !== '')
                    ? r.registration_monthly_income
                    : ((r.monthly_income != null && r.monthly_income !== '')
                        ? r.monthly_income
                        : r.registration_household_income)
            );
            const specialCategories = buildResidentSpecialCategories(r);
            const sexValue = pickFirstValue(r.registration_sex, r.registration_gender, r.gender);
            const civilStatusValue = pickFirstValue(r.registration_civil_status, r.civil_status, '-');
            const contactValue = pickFirstValue(r.registration_mobile_number, r.contact_number, '-');
            const emailValue = pickFirstValue(r.registration_email, r.email, '-');
            const placeOfBirthValue = pickFirstValue(r.registration_place_of_birth, r.place_of_birth, '-');
            const citizenshipValue = pickFirstValue(r.registration_citizenship, r.citizenship, '-');
            const occupationValue = pickFirstValue(r.registration_occupation, r.occupation, '-');
            const educationValue = pickFirstValue(r.registration_educational_attainment, r.educational_attainment, '-');
            const occupationOtherValue = pickFirstValue(r.registration_occupation_other, '');
            const educationOtherValue = pickFirstValue(r.registration_educational_attainment_other, '');
            const employmentValue = pickFirstValue(r.registration_employment_status, r.employment_status, '-');
            const showOccupationOther = shouldShowOtherSpecify(occupationValue) && String(occupationOtherValue || '').trim() !== '';
            const showEducationOther = shouldShowOtherSpecify(educationValue) && String(educationOtherValue || '').trim() !== '';
            const registrationAddressParts = [
                r.registration_house_number,
                r.registration_street,
                r.registration_purok_sitio,
                r.registration_barangay,
                r.registration_city,
                r.registration_province
            ].map(v => (v == null ? '' : String(v).trim())).filter(Boolean);
            const registrationAddress = registrationAddressParts.join(', ');
            const addressValue = pickFirstValue(registrationAddress, r.address, '-');

            document.getElementById('viewResidentBody').innerHTML = `
                <table class="table table-sm">
                    <tr><td><strong>Resident ID</strong></td><td>${residentCode}</td></tr>
                    <tr><td><strong>Full Name</strong></td><td>${escapeHtml(toTitleCase(fullName))}</td></tr>
                    <tr><td><strong>Birth Date</strong></td><td>${formatDate(birthDateValue)} (${age} yrs)</td></tr>
                    <tr><td><strong>Place of Birth</strong></td><td>${escapeHtml(toTitleCase(placeOfBirthValue))}</td></tr>
                    <tr><td><strong>Gender</strong></td><td>${formatGender(sexValue)}</td></tr>
                    <tr><td><strong>Civil Status</strong></td><td>${escapeHtml(toTitleCase(civilStatusValue))}</td></tr>
                    <tr><td><strong>Contact</strong></td><td>${escapeHtml(formatPhoneNumber(contactValue) || '-')}</td></tr>
                    <tr><td><strong>Email</strong></td><td>${escapeHtml(emailValue || '-')}</td></tr>
                    <tr><td><strong>Address</strong></td><td>${escapeHtml(toTitleCase(addressValue || '-'))}</td></tr>
                    <tr><td><strong>Citizenship</strong></td><td>${escapeHtml(toTitleCase(citizenshipValue || '-'))}</td></tr>
                    <tr><td><strong>Occupation</strong></td><td>${escapeHtml(toTitleCase(occupationValue || '-'))}</td></tr>
                    ${showOccupationOther ? `<tr><td><strong>Occupation (Please Specify)</strong></td><td>${escapeHtml(toTitleCase(occupationOtherValue))}</td></tr>` : ''}
                    <tr><td><strong>Education</strong></td><td>${escapeHtml(toTitleCase(educationValue || '-'))}</td></tr>
                    ${showEducationOther ? `<tr><td><strong>Education (Please Specify)</strong></td><td>${escapeHtml(toTitleCase(educationOtherValue))}</td></tr>` : ''}
                    <tr><td><strong>Employment Status</strong></td><td>${escapeHtml(toTitleCase(employmentValue || '-'))}</td></tr>
                    <tr><td><strong>Household Role</strong></td><td>${escapeHtml(toTitleCase(householdRole || '-'))}</td></tr>
                    <tr><td><strong>Voter Status</strong></td><td>${escapeHtml(toTitleCase(voterStatus))}</td></tr>
                    <tr><td><strong>Precinct Number</strong></td><td>${escapeHtml(precinctNumber)}</td></tr>
                    <tr><td><strong>Monthly income (this resident)</strong></td><td>${monthlyIncome}</td></tr>
                    <tr><td><strong>House Type</strong></td><td>${escapeHtml(toTitleCase(houseType))}</td></tr>
                    <tr><td><strong>House Ownership</strong></td><td>${escapeHtml(toTitleCase(houseOwnership))}</td></tr>
                    <tr><td><strong>Residency Start</strong></td><td>${formatDate(residencyStart)}</td></tr>
                    <tr><td><strong>Length of Residency</strong></td><td>${escapeHtml(residencyLength)}</td></tr>
                    <tr><td><strong>Special Categories</strong></td><td>${escapeHtml(specialCategories)}</td></tr>
                    <tr><td><strong>Household</strong></td><td>${r.household_address ? 'Household #'+r.household_id+' ('+r.total_members+' members)' : 'None'}</td></tr>
                    <tr><td><strong>Household Code</strong></td><td>${r.household_code ? (escapeHtml(String(r.household_code)) + (String(r.is_household_head) === '1' ? ' <i class="bi bi-patch-check-fill text-success ms-1" title="Family Head" aria-label="Family Head"></i>' : '')) : '-'}</td></tr>
                    <tr><td><strong>Family Head Code</strong></td><td>${String(r.is_household_head) === '1' ? (r.family_head_code ? escapeHtml(String(r.family_head_code)) : '-') : ''}</td></tr>
                    <tr><td><strong>Verification</strong></td><td>${getVerificationBadge(verificationStatus)}</td></tr>
                    <tr><td><strong>Uploaded ID</strong></td><td>${idDocLink}</td></tr>
                    <tr><td><strong>Certificates</strong></td><td>${r.certificates_count || 0} issued</td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="resident-pill ${getStatusClass(r.status)}">${formatStatus(r.status)}</span></td></tr>
                </table>
                ${RESIDENT_PERMS.canEdit && r.id_document_path ? `
                    <div class="d-flex gap-2 justify-content-end">
                        ${verificationStatus !== 'verified' ? `<button class="btn btn-success btn-sm" onclick="verifyResidentId(${r.id}, 'verified')"><i class="bi bi-patch-check"></i> Verify Uploaded ID</button>` : ''}
                        ${verificationStatus === 'pending' ? `<button class="btn btn-warning btn-sm" onclick="verifyResidentId(${r.id}, 'rejected')"><i class="bi bi-patch-exclamation"></i> Reject ID</button>` : ''}
                    </div>
                ` : ''}
            `;
            document.getElementById('btnEditFromView').dataset.residentId = id;
            document.getElementById('linkCertificates').href = (window.BASE_URL || '') + 'certificates.php';
            new bootstrap.Modal(document.getElementById('viewResidentModal')).show();
        })
        .catch(() => showAlert('error', 'Error loading resident'));
}

/**
 * Save resident (create or update)
 */
function saveResident() {
    applyTitleCaseToForm();
    const form = document.getElementById('residentForm');
    const formData = new FormData(form);
    const residentId = document.getElementById('residentId').value;

    if (!residentId) {
        showAlert('error', 'Creating new residents from Residents Management is disabled. Use approved resident applications instead.');
        return;
    }

    if (residentId && !RESIDENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }
    if (!residentId && !RESIDENT_PERMS.canCreate) {
        showAlert('error', 'Access denied');
        return;
    }
    
    formData.append('action', residentId ? 'update' : 'create');
    if (residentId) {
        formData.append('id', residentId);
    }
    
    const apiUrl = window.API_URL;
    if (!apiUrl) {
        console.error('API_URL is not defined. Please check your configuration.');
        showAlert('error', 'Configuration error. Please refresh the page.');
        return;
    }
    fetch(`${apiUrl}resident.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('residentModal')).hide();
            resetForm();
            loadResidents(currentPage);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Error saving resident');
    });
}

/**
 * Delete resident
 */
function deleteResident(id) {
    if (!RESIDENT_PERMS.canDelete) {
        showAlert('error', 'Access denied');
        return;
    }
    if (confirm('Are you sure you want to delete this resident?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const apiUrl = window.API_URL;
        if (!apiUrl) {
            console.error('API_URL is not defined. Please check your configuration.');
            showAlert('error', 'Configuration error. Please refresh the page.');
            return;
        }
        fetch(`${apiUrl}resident.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadResidents(currentPage);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error deleting resident');
        });
    }
}

/**
 * Reset form
 */
function resetForm() {
    document.getElementById('residentForm').reset();
    document.getElementById('residentId').value = '';
    document.getElementById('citizenship').value = 'Filipino';
    document.getElementById('status').value = 'active';
    const miReset = document.getElementById('monthly_income');
    if (miReset) miReset.value = '';
    document.getElementById('contact_number').value = '+63 ';
    const d = window.RESIDENT_EDIT_ADDRESS_DEFAULTS || {};
    const brEl = document.getElementById('barangay');
    const ctyEl = document.getElementById('city');
    const provEl = document.getElementById('province');
    if (brEl && d.barangay) brEl.value = d.barangay;
    if (ctyEl && d.city) ctyEl.value = d.city;
    if (provEl && d.province) provEl.value = d.province;
    const brd = document.getElementById('barangay_display');
    const ctyd = document.getElementById('city_display');
    const provd = document.getElementById('province_display');
    if (brd && brEl) brd.value = brEl.value;
    if (ctyd && ctyEl) ctyd.value = ctyEl.value;
    if (provd && provEl) provd.value = provEl.value;
    document.getElementById('residentModalTitle').textContent = 'Edit Resident';
    initResidentFormValidation();
}

/**
 * Helper functions
 */
function calculateAge(birthDate) {
    if (!birthDate) return '-';
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

function formatGender(gender) {
    return gender ? gender.charAt(0).toUpperCase() + gender.slice(1) : '-';
}

function formatStatus(status) {
    return status ? status.charAt(0).toUpperCase() + status.slice(1) : '-';
}

function getStatusClass(status) {
    const classes = {
        'active': 'status-active',
        'inactive': 'status-inactive',
        'deceased': 'status-deceased',
        'transferred': 'status-transferred'
    };
    return classes[status] || 'status-inactive';
}

function normalizeVerificationStatus(row) {
    const raw = String(row?.verification_status || row?.record_status || '').toLowerCase().trim();
    if (raw === 'verified' || raw === 'approved' || raw === 'active') return 'verified';
    if (raw === 'rejected') return 'rejected';
    return 'pending';
}

function getVerificationBadge(status) {
    if (status === 'verified') {
        return '<span class="resident-pill verify-verified">Verified</span>';
    }
    if (status === 'rejected') {
        return '<span class="resident-pill verify-rejected">Rejected</span>';
    }
    return '<span class="resident-pill verify-pending">Pending</span>';
}

function buildResidentFileLink(path, label) {
    if (!path) return '<span class="text-muted">No ID uploaded</span>';
    const trimmed = String(path).trim();
    if (!trimmed) return '<span class="text-muted">No ID uploaded</span>';
    const base = window.BASE_URL || '';
    const normalized = trimmed.replace(/^\/+/, '');
    const url = trimmed.startsWith('http') ? trimmed : (base + normalized);
    return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(label || 'View file')}</a>`;
}

function verifyResidentId(id, nextStatus) {
    if (!RESIDENT_PERMS.canEdit) {
        showAlert('error', 'Access denied');
        return;
    }

    const isVerify = nextStatus === 'verified';
    let remarks = '';
    if (isVerify) {
        if (!confirm('Mark this resident ID as verified?')) return;
    } else {
        remarks = prompt('Enter rejection reason (required):', 'ID photo is unclear or invalid. Please upload a clearer, valid government ID.') || '';
        remarks = remarks.trim();
        if (!remarks) {
            showAlert('error', 'Rejection reason is required.');
            return;
        }
        if (!confirm('Reject this resident ID upload?')) return;
    }

    const formData = new FormData();
    formData.append('action', isVerify ? 'verify_id' : 'reject_id');
    formData.append('id', String(id));
    if (remarks) formData.append('remarks', remarks);

    fetch((window.API_URL || '') + 'resident.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showAlert('error', data.message || 'Failed to update verification status.');
                return;
            }
            showAlert('success', data.message || 'Verification status updated.');

            const viewModalEl = document.getElementById('viewResidentModal');
            const viewModal = viewModalEl ? bootstrap.Modal.getInstance(viewModalEl) : null;
            if (viewModal) {
                viewModal.hide();
            }

            loadResidents(currentPage);
        })
        .catch(error => {
            console.error('Verification update error:', error);
            showAlert('error', 'Failed to update verification status.');
        });
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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

function applyTitleCaseToForm() {
    const fields = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'citizenship'
    ];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = toTitleCase(el.value);
    });
}

function pickAddrPart(...vals) {
    for (const v of vals) {
        if (v === null || v === undefined) continue;
        const s = String(v).trim();
        if (s !== '') return s;
    }
    return '';
}

function ensureSelectOptionValue(selectEl, value) {
    if (!selectEl) return;
    if (value == null || String(value).trim() === '') {
        selectEl.value = '';
        return;
    }
    const v = String(value).trim();
    let found = false;
    for (let i = 0; i < selectEl.options.length; i++) {
        if (selectEl.options[i].value === v) {
            found = true;
            break;
        }
    }
    if (!found) {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        selectEl.appendChild(opt);
    }
    selectEl.value = v;
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

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '-';
    const numeric = Number(value);
    if (Number.isNaN(numeric)) return escapeHtml(String(value));
    return 'PHP ' + numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function buildResidentSpecialCategories(row) {
    const categories = [];
    const isSenior = Number(row.is_senior_citizen || row.registration_is_senior_citizen || 0) === 1;
    const isPwd = Number(row.is_pwd || row.registration_is_pwd || 0) === 1;
    const isSoloParent = Number(row.is_solo_parent || row.registration_is_solo_parent || 0) === 1;
    const isIp = Number(row.is_ip_member || row.registration_is_ip_member || 0) === 1;
    const is4ps = Number(row.is_4ps_beneficiary || row.registration_is_4ps_beneficiary || 0) === 1;

    if (isSenior) categories.push('Senior Citizen');
    if (isPwd) {
        const pwdId = (row.pwd_id_number || row.registration_pwd_id_number || '').toString().trim();
        categories.push(pwdId ? `PWD (${pwdId})` : 'PWD');
    }
    if (isSoloParent) {
        const soloId = (row.solo_parent_id_number || row.registration_solo_parent_id_number || '').toString().trim();
        categories.push(soloId ? `Solo Parent (${soloId})` : 'Solo Parent');
    }
    if (isIp) {
        const ipGroup = (row.ip_group || row.registration_ip_group || '').toString().trim();
        categories.push(ipGroup ? `IP Member (${ipGroup})` : 'IP Member');
    }
    if (is4ps) categories.push('4Ps Beneficiary');

    return categories.length ? categories.join(', ') : '-';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
