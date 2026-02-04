// Handles dynamic Family Members section for registration

document.addEventListener('DOMContentLoaded', function() {
    const familyStatus = document.getElementById('familyStatus');
    const occupationDiv = document.querySelector('input[name="occupation"]').closest('.mb-3');
    let familyMembersSection = null;

    function createFamilyMemberRow(index = 0, data = {}) {
        const relationships = ['Spouse', 'Child', 'Parent', 'Sibling', 'Other'];
        const row = document.createElement('div');
        row.className = 'row align-items-end mb-2 family-member-row';
        row.innerHTML = `
            <div class="col-md-3 mb-1">
                <input type="text" name="family_members[${index}][first_name]" class="form-control" placeholder="First Name" value="${data.first_name || ''}" required>
            </div>
            <div class="col-md-3 mb-1">
                <input type="text" name="family_members[${index}][last_name]" class="form-control" placeholder="Last Name" value="${data.last_name || ''}" required>
            </div>
            <div class="col-md-5 mb-1">
                <select name="family_members[${index}][relationship]" class="form-select" required>
                    <option value="">Relationship</option>
                    ${relationships.map(r => `<option value="${r}" ${data.relationship === r ? 'selected' : ''}>${r}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-1 mb-1 text-end">
                <button type="button" class="btn btn-danger btn-xs remove-member" style="padding:2px 6px;font-size:0.85em;"><i class="bi bi-x"></i></button>
            </div>
        `;
        return row;
    }

    function renderFamilyMembersSection() {
        if (!familyMembersSection) {
            familyMembersSection = document.createElement('div');
            familyMembersSection.id = 'familyMembersSection';
            familyMembersSection.className = 'mb-3';
            familyMembersSection.innerHTML = `
                <label class="fw-bold">Family Members <span class="text-muted">(Optional)</span></label>
                <div id="familyMembersList"></div>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addFamilyMemberBtn"><i class="bi bi-plus"></i> Add Family Member</button>
            `;
        }
        return familyMembersSection;
    }

    function addFamilyMemberRow(data = {}) {
        const list = document.getElementById('familyMembersList');
        const index = list.children.length;
        const row = createFamilyMemberRow(index, data);
        list.appendChild(row);
    }

    // Event delegation for remove
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-member')) {
            const row = e.target.closest('.family-member-row');
            if (row) row.remove();
        }
    });

    // Add member button
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'addFamilyMemberBtn') {
            addFamilyMemberRow();
        }
    });

    // Show/hide Family Members section
    familyStatus.addEventListener('change', function() {
        if (familyStatus.value === 'head') {
            if (!document.getElementById('familyMembersSection')) {
                occupationDiv.parentNode.insertBefore(renderFamilyMembersSection(), occupationDiv);
            }
            document.getElementById('familyMembersSection').style.display = '';
        } else {
            if (document.getElementById('familyMembersSection')) {
                document.getElementById('familyMembersSection').style.display = 'none';
                document.getElementById('familyMembersList').innerHTML = '';
            }
        }
    });

    // On page load, if "Head of Family" is selected, show section
    if (familyStatus.value === 'head') {
        if (!document.getElementById('familyMembersSection')) {
            occupationDiv.parentNode.insertBefore(renderFamilyMembersSection(), occupationDiv);
        }
    }
});
