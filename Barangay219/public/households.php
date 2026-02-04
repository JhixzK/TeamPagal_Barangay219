<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY]);

$page_title = 'Households Management';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-house-door"></i> Households Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#householdModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> Add New Household
            </button>
        </div>

        <div class="data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Family Head</th>
                            <th>Address</th>
                            <th>Total Members</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="householdsTableBody">
                        <tr><td colspan="6" class="text-center"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="householdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="householdModalTitle">Add New Household</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="householdForm">
                <div class="modal-body">
                    <input type="hidden" id="householdId" name="id">
                    <div class="mb-3">
                        <label for="family_head_id" class="form-label">Family Head ID <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="family_head_id" name="family_head_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="registration_date" class="form-label">Registration Date</label>
                        <input type="date" class="form-control" id="registration_date" name="registration_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/households.js"></script>
