<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2><i class="bi bi-person"></i> My Profile</h2>
        <?php
        // Determine avatar URL
        $user_id = getCurrentUserId();
        $avatar_url = '';
        if ($user_id) {
            $exts = ['png','jpg','jpeg','gif'];
            foreach ($exts as $e) {
                $f = PUBLIC_PATH . '/uploads/profile/' . $user_id . '.' . $e;
                if (file_exists($f)) {
                    $avatar_url = BASE_URL . 'uploads/profile/' . $user_id . '.' . $e;
                    break;
                }
            }
        }
        if (empty($avatar_url)) {
            $avatar_url = ASSETS_URL . 'img/default-avatar.svg';
        }
        ?>

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card p-3 text-center">
                    <img id="profileAvatar" src="<?php echo $avatar_url; ?>" alt="Avatar" class="rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover;">
                    <form id="avatarForm" enctype="multipart/form-data">
                        <input type="file" name="avatar" accept="image/*" class="form-control mb-2" required>
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Upload Photo</button>
                    </form>
                    <div id="avatarMsg" class="mt-2 small text-muted"></div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-card">
                    <h5>User Information</h5>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars(getCurrentUsername()); ?></p>
                    <p><strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', getCurrentUserRole())); ?></p>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('avatarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = e.target;
            var fd = new FormData(form);
            var msg = document.getElementById('avatarMsg');
            msg.textContent = 'Uploading...';

            fetch('<?php echo API_URL; ?>profile.php?action=upload', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function(res){ return res.json(); })
            .then(function(data){
                if (data.success) {
                    // Update avatar image (add cache buster)
                    var img = document.getElementById('profileAvatar');
                    img.src = data.data.url + '?t=' + new Date().getTime();
                    msg.textContent = 'Uploaded successfully.';
                } else {
                    msg.textContent = data.message || 'Upload failed.';
                }
            }).catch(function(){ msg.textContent = 'Upload error.'; });
        });
        </script>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
