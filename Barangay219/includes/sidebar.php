<?php
/**
 * E-Barangay Information Management System
 * Sidebar Navigation Component
 */

if (!isLoggedIn()) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
// Define menu items with permission keys
$menu_items = [
    [
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'url' => 'dashboard.php',
        'module' => 'dashboard',
        'section' => 'Main'
    ],
    [
        'title' => 'Certificates',
        'icon' => 'bi-file-earmark-person',
        'url' => 'applications.php',
        'modules' => ['applications', 'certificates'],
        'section' => 'Services'
    ],
    [
        'title' => 'Resident Applications',
        'icon' => 'bi-person-lines-fill',
        'url' => 'resident-applications.php',
        'module' => 'resident_applications',
        'section' => 'Services'
    ],
    [
        'title' => 'Residents',
        'icon' => 'bi-people',
        'url' => 'residents.php',
        'module' => 'residents',
        'section' => 'Records'
    ],
    [
        'title' => 'Households',
        'icon' => 'bi-house-door',
        'url' => 'households.php',
        'module' => 'households',
        'section' => 'Records'
    ],
    [
        'title' => 'Blotters',
        'icon' => 'bi-journal-text',
        'url' => 'blotter.php',
        'module' => 'blotters',
        'section' => 'Cases'
    ],
    [
        'title' => 'Complaints',
        'icon' => 'bi-exclamation-triangle',
        'url' => 'complaints.php',
        'module' => 'complaints',
        'section' => 'Cases'
    ],
    [
        'title' => 'Announcements',
        'icon' => 'bi-megaphone',
        'url' => 'announcement.php',
        'module' => 'announcements',
        'section' => 'Communication'
    ],
    [
        'title' => 'Reports',
        'icon' => 'bi-graph-up',
        'url' => 'reports.php',
        'module' => 'reports',
        'section' => 'Communication'
    ],
    [
        'title' => 'Users',
        'icon' => 'bi-person-gear',
        'url' => 'users.php',
        'module' => 'users',
        'section' => 'Administration'
    ]
];

// Filter menu items based on permissions
$filtered_menu = array_filter($menu_items, function($item) {
    if (isset($item['modules']) && is_array($item['modules'])) {
        foreach ($item['modules'] as $mod) {
            if (canAccessModule($mod)) {
                return true;
            }
        }
        return false;
    }
    return canAccessModule($item['module']);
});
// Current user info and avatar
$current_user = getUserInfo();
$user_id = getCurrentUserId();
$avatar_path = '';
if ($user_id) {
    $possible_ext = ['png','jpg','jpeg','gif'];
    foreach ($possible_ext as $ext) {
        $file = PUBLIC_PATH . '/uploads/profile/' . $user_id . '.' . $ext;
        if (file_exists($file)) {
            $avatar_path = BASE_URL . 'uploads/profile/' . $user_id . '.' . $ext;
            break;
        }
    }
}
if (empty($avatar_path)) {
    $avatar_path = ASSETS_URL . 'img/default-avatar.svg';
}
?>
<div class="sidebar" id="appSidebar">
    <div class="sidebar-content">
        <div class="sidebar-profile text-center mb-3" style="padding:0.5rem 1rem;">
            <a href="<?php echo BASE_URL; ?>profile.php" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="<?php echo $avatar_path; ?>" alt="Avatar" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;border:2px solid #fff;box-shadow:0 0 0 2px rgba(13,110,253,0.08);">
                <div class="profile-meta text-start">
                    <div style="font-weight:600;color:#212529;">
                        <?php
                        $displayName = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
                        if (empty($displayName)) {
                            $displayName = htmlspecialchars($current_user['username'] ?? getCurrentUsername() ?? 'User');
                        } else {
                            $displayName = htmlspecialchars($displayName);
                        }
                        echo $displayName;
                        ?>
                    </div>
                    <small style="color:#6c757d;">
                        <span class="badge bg-light text-dark" style="font-size:0.75rem;">
                            <?php echo ucfirst(str_replace('_', ' ', getEffectiveUserRole() ?? '')); ?>
                        </span>
                    </small>
                </div>
            </a>
        </div>
        <ul class="nav flex-column">
            <li class="nav-section-title">Account</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>profile.php">
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="nav-divider" role="separator" aria-hidden="true"></li>
            <?php $lastSection = ''; ?>
            <?php foreach ($filtered_menu as $item): ?>
            <?php if (($item['section'] ?? '') !== $lastSection): ?>
            <li class="nav-section-title"><?php echo htmlspecialchars($item['section'] ?? 'Menu'); ?></li>
            <?php $lastSection = $item['section'] ?? ''; ?>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === $item['url']) ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $item['url']; ?>">
                    <i class="<?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['title']; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <li class="nav-divider" role="separator" aria-hidden="true"></li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="logout(); return false;">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.sidebar {
    width: 78px;
    height: calc(100vh - 56px);
    background-color: #f8f9fa;
    border-right: 1px solid #dee2e6;
    position: fixed;
    left: 0;
    top: 56px;
    overflow-y: auto;
    z-index: 1000;
    transition: width 0.25s ease, left 0.25s ease;
    scrollbar-width: none;
}

.sidebar::-webkit-scrollbar {
    width: 0;
    height: 0;
}

body.sidebar-expanded .sidebar {
    width: 250px;
}

.sidebar-content {
    padding: 1rem 0;
}

.sidebar .sidebar-profile {
    padding-left: 0.8rem !important;
    padding-right: 0.8rem !important;
}

.sidebar .sidebar-profile a {
    justify-content: center;
}

.sidebar .sidebar-profile .profile-meta {
    opacity: 0;
    max-width: 0;
    overflow: hidden;
    white-space: nowrap;
    transform: translateX(-6px);
    transition: opacity 0.2s ease, max-width 0.2s ease, transform 0.2s ease;
}

body.sidebar-expanded .sidebar .sidebar-profile a {
    justify-content: flex-start;
}

body.sidebar-expanded .sidebar .sidebar-profile .profile-meta {
    opacity: 1;
    max-width: 180px;
    transform: translateX(0);
}

.sidebar .nav-link {
    color: #495057;
    padding: 0.75rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.sidebar .nav-link span {
    opacity: 0;
    max-width: 0;
    overflow: hidden;
    white-space: nowrap;
    transition: opacity 0.2s ease, max-width 0.2s ease;
}

body.sidebar-expanded .sidebar .nav-link span {
    opacity: 1;
    max-width: 160px;
}

.sidebar .nav-link:hover {
    background-color: #e9ecef;
    color: #0d6efd;
    border-left-color: #0d6efd;
}

.sidebar .nav-link.active {
    background-color: #e7f1ff;
    color: #0d6efd;
    border-left-color: #0d6efd;
    font-weight: 600;
}

.sidebar .nav-link i {
    font-size: 1.1rem;
    width: 20px;
    flex: 0 0 20px;
}

.sidebar .nav-section-title {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6c757d;
    padding: 0.65rem 1.5rem 0.35rem;
    font-weight: 700;
}

.sidebar .nav-section-title,
.sidebar .nav-divider {
    opacity: 0;
    max-height: 0;
    overflow: hidden;
    margin-top: 0;
    margin-bottom: 0;
    transition: opacity 0.2s ease, max-height 0.2s ease, margin 0.2s ease;
}

body.sidebar-expanded .sidebar .nav-section-title,
body.sidebar-expanded .sidebar .nav-divider {
    opacity: 1;
    max-height: 40px;
}

body.sidebar-expanded .sidebar .nav-divider {
    margin: 0.45rem 1rem;
}

.sidebar .nav-divider {
    margin: 0.45rem 1rem;
    border-top: 1px solid #dee2e6;
    list-style: none;
}

.main-content {
    margin-left: 78px;
    padding: 2rem;
    min-height: calc(100vh - 56px);
    overflow-x: auto;
    transition: margin-left 0.25s ease;
}

body.sidebar-expanded .main-content {
    margin-left: 250px;
}

@media (max-width: 768px) {
    .sidebar {
        left: -250px;
        width: 250px;
        height: calc(100vh - 56px);
        top: 56px;
        transition: left 0.25s ease;
        border-right: 1px solid #dee2e6;
        border-bottom: 0;
        overflow-y: auto;
    }

    body.sidebar-expanded .sidebar {
        left: 0;
    }

    .sidebar .sidebar-profile a {
        justify-content: flex-start;
    }

    .sidebar .sidebar-profile .profile-meta,
    .sidebar .nav-link span,
    .sidebar .nav-section-title,
    .sidebar .nav-divider {
        opacity: 1;
        max-width: none;
        max-height: none;
        overflow: visible;
        transform: none;
    }
    
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }

    body.sidebar-expanded .main-content {
        margin-left: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('appSidebar') || document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (!sidebar || !toggleBtn) return;

    const SIDEBAR_STATE_KEY = 'bp_sidebar_expanded';
    const mobile = () => window.matchMedia('(max-width: 768px)').matches;
    const icon = toggleBtn.querySelector('i');

    function setExpanded(expanded, persist = true) {
        document.body.classList.toggle('sidebar-expanded', expanded);
        toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (icon) {
            icon.classList.toggle('bi-list', !expanded);
            icon.classList.toggle('bi-x-lg', expanded);
        }
        if (persist) {
            try {
                localStorage.setItem(SIDEBAR_STATE_KEY, expanded ? '1' : '0');
            } catch (e) {
                // Ignore storage errors (private mode/storage disabled)
            }
        }
    }

    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const isExpanded = document.body.classList.contains('sidebar-expanded');
        setExpanded(!isExpanded);
    });

    document.addEventListener('click', function(e) {
        if (!mobile()) {
            return;
        }
        if (!document.body.classList.contains('sidebar-expanded')) {
            return;
        }
        if (sidebar.contains(e.target) || toggleBtn.contains(e.target)) {
            return;
        }
        setExpanded(false, false);
    });

    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (mobile()) {
                setExpanded(false, false);
            }
        });
    });

    window.addEventListener('resize', function() {
        if (mobile()) {
            setExpanded(false, false);
        }
    });

    let preferredExpanded = false;
    try {
        preferredExpanded = localStorage.getItem(SIDEBAR_STATE_KEY) === '1';
    } catch (e) {
        preferredExpanded = false;
    }

    setExpanded(!mobile() && preferredExpanded, false);
});
</script>
