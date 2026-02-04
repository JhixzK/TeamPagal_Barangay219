document.addEventListener('DOMContentLoaded', function() {
    loadHouseholds();
    document.getElementById('householdForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveHousehold();
    });
});

function loadHouseholds() {
    fetch('<?php echo API_URL; ?>households.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const tbody = document.getElementById('householdsTableBody');
                tbody.innerHTML = d.data.map(h => `
                    <tr>
                        <td>${h.id}</td>
                        <td>${escapeHtml(h.family_head_name || '-')}</td>
                        <td>${escapeHtml(h.address)}</td>
                        <td>${h.total_members}</td>
                        <td>${formatDate(h.registration_date)}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewHousehold(${h.id})"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteHousehold(${h.id})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            }
        });
}

function saveHousehold() {
    const formData = new FormData(document.getElementById('householdForm'));
    formData.append('action', document.getElementById('householdId').value ? 'update' : 'create');
    fetch('<?php echo API_URL; ?>households.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('householdModal')).hide();
                loadHouseholds();
            }
        });
}

function viewHousehold(id) {
    fetch(`<?php echo API_URL; ?>households.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(`Household: ${d.data.family_head_name}\nMembers: ${d.data.total_members}`);
            }
        });
}

function deleteHousehold(id) {
    if (confirm('Delete this household?')) {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('<?php echo API_URL; ?>households.php', { method: 'POST', body: fd })
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
