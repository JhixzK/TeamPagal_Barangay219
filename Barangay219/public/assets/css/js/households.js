// Ensure API_URL is valid at runtime (fallback for cached/unprocessed files)
if (typeof window.API_URL === 'undefined' || window.API_URL === null || window.API_URL.indexOf('<?') !== -1 || window.API_URL.indexOf('%3C') !== -1) {
    window.API_URL = window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
    console.warn('API_URL invalid or missing; using fallback:', window.API_URL);
}

document.addEventListener('DOMContentLoaded', function() {
    loadHouseholds();
    document.getElementById('householdForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveHousehold();
    });
});

function loadHouseholds() {
    fetch(window.API_URL + 'households.php?action=list')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            const tbody = document.getElementById('householdsTableBody');
            if (d.success) {
                tbody.innerHTML = d.data.map(h => `
                    <tr>
                        <td>${h.id}</td>
                        <td>${escapeHtml(h.family_head_name || '-')}</td>
                        <td>${escapeHtml(h.address)}</td>
                        <td>${h.total_members}</td>
                        <td>${formatDate(h.registration_date)}</td>
                                <td>
                            <button class="btn btn-sm btn-secondary me-1" onclick="editHousehold(${h.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewHousehold(${h.id})" title="View"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteHousehold(${h.id})" title="Delete"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No households found or access denied</td></tr>';
                console.warn('Households API returned error:', d.message);
            }
        })
        .catch(err => {
            console.error('Error loading households:', err);
            const tbody = document.getElementById('householdsTableBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading households</td></tr>';
        });
}

function saveHousehold() {
    const form = document.getElementById('householdForm');
    const formData = new FormData(form);
    formData.append('action', document.getElementById('householdId').value ? 'update' : 'create');

    // Ensure numeric fields are sent as numbers
    if (formData.get('total_members')) {
        formData.set('total_members', parseInt(formData.get('total_members'), 10));
    }

    fetch(window.API_URL + 'households.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('householdModal')).hide();
                loadHouseholds();
                form.reset();
            } else {
                alert('Error: ' + (d.message || 'Failed to save household'));
            }
        })
        .catch(err => {
            console.error('Error saving household:', err);
            alert('Error saving household');
        });
}

function viewHousehold(id) {
    fetch(`${window.API_URL}households.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(`Household: ${d.data.family_head_name}\nMembers: ${d.data.total_members}`);
            }
        })
        .catch(err => console.error('Error viewing household:', err));
}

function editHousehold(id) {
    fetch(`${window.API_URL}households.php?action=get&id=${id}`)
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Invalid response (not JSON)');
            return r.json();
        })
        .then(d => {
            if (d.success) {
                const h = d.data;
                document.getElementById('householdId').value = h.id;
                document.getElementById('family_head_id').value = h.family_head_id || '';
                document.getElementById('address').value = h.address || '';
                document.getElementById('total_members').value = h.total_members || 1;
                if (h.registration_date) document.getElementById('registration_date').value = h.registration_date;
                document.getElementById('householdModalTitle').textContent = 'Edit Household';

                const modal = new bootstrap.Modal(document.getElementById('householdModal'));
                modal.show();
            } else {
                console.warn('Edit failed:', d.message);
            }
        })
        .catch(err => {
            console.error('Error editing household:', err);
            alert('Error loading household details');
        });
}

function deleteHousehold(id) {
    if (confirm('Delete this household?')) {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch(window.API_URL + 'households.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) loadHouseholds(); });
    }
}

function resetForm() {
    document.getElementById('householdForm').reset();
    document.getElementById('householdId').value = '';
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
