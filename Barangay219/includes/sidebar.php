<?php
/**
 * E-Barangay Information Management System
 * Sidebar Navigation Component
 */

if (!isLoggedIn()) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
// Define menu items.
// - Residents (resident view) get a portal navigation (same sidebar design, different links).
// - Officials/staff get permission-gated modules.
$isResidentSidebar = function_exists('isResidentView') && isResidentView();

if ($isResidentSidebar) {
    $menu_items = [
        [
            'title' => 'My Profile',
            'icon' => 'bi-person-circle',
            'url' => 'resident_profile.php',
            'section' => 'Account'
        ],
        [
            'title' => 'Dashboard',
            'icon' => 'bi-speedometer2',
            'url' => 'resident_dashboard.php',
            'section' => 'Main'
        ],
        [
            'title' => 'Request Certificate',
            'icon' => 'bi-file-earmark-text',
            'url' => 'request_certificate.php',
            'section' => 'Services'
        ],
        [
            'title' => 'My Requests',
            'icon' => 'bi-list-check',
            'url' => 'my_requests.php',
            'section' => 'Services'
        ],
        [
            'title' => 'Household Information',
            'icon' => 'bi-house-door',
            'url' => 'resident_household.php',
            'section' => 'Records'
        ],
        [
            'title' => 'My Complaints',
            'icon' => 'bi-exclamation-triangle',
            'url' => 'complaints/my_complaints.php',
            'section' => 'Cases'
        ],
        [
            'title' => 'Submit Complaint',
            'icon' => 'bi-pencil-square',
            'url' => 'complaints/submit_complaint.php',
            'section' => 'Cases'
        ],
        [
            'title' => 'Report Incident',
            'icon' => 'bi-journal-plus',
            'url' => 'report_incident.php',
            'section' => 'Cases'
        ],
        [
            'title' => 'My Blotters',
            'icon' => 'bi-journal-check',
            'url' => 'my_blotters.php',
            'section' => 'Cases'
        ],
        [
            'title' => 'Announcements',
            'icon' => 'bi-megaphone',
            'url' => 'resident_announcements.php',
            'section' => 'Communication'
        ]
    ];
    $filtered_menu = $menu_items;
} else {
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
            'title' => 'Officials',
            'icon' => 'bi-people-fill',
            'url' => 'officials.php',
            'module' => 'officials',
            'section' => 'Administration',
            'roles' => [ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]
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
        if (isset($item['roles']) && is_array($item['roles'])) {
            if (!hasAnyRole($item['roles'])) {
                return false;
            }
        }
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
}
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
        <div class="sidebar-toggle-wrap">
            <button class="btn btn-sm btn-outline-primary sidebar-toggle-btn" type="button" id="sidebarToggleBtn" aria-label="Toggle sidebar" aria-controls="appSidebar" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div class="sidebar-profile text-center mb-3" style="padding:0.5rem 1rem;">
            <a href="<?php echo BASE_URL; ?><?php echo $isResidentSidebar ? 'resident_profile.php' : 'profile.php'; ?>" class="d-flex align-items-center gap-2 text-decoration-none">
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
            <?php if (!$isResidentSidebar): ?>
            <li class="nav-section-title">Account</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>profile.php">
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="nav-divider" role="separator" aria-hidden="true"></li>
            <?php endif; ?>
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
    width: 82px;
    height: calc(100vh - 56px);
    background: linear-gradient(180deg, #f8fbff 0%, #f2f6fb 100%);
    border-right: 1px solid #dce5f1;
    position: fixed;
    left: 0;
    top: 56px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 10px 0 24px -24px rgba(15, 23, 42, 0.5);
    transition: width 0.25s ease, left 0.25s ease, box-shadow 0.25s ease;
    scrollbar-width: none;
}

.sidebar::-webkit-scrollbar {
    width: 0;
    height: 0;
}

body.sidebar-expanded .sidebar {
    width: 250px;
    box-shadow: 14px 0 30px -24px rgba(15, 23, 42, 0.45);
}

.sidebar-content {
    padding: 0.9rem 0.6rem 1rem;
}

.sidebar .sidebar-toggle-wrap {
    display: flex;
    justify-content: center;
    padding: 0 0.2rem 0.7rem;
}

.sidebar .sidebar-toggle-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border-color: #cdd9ea;
    color: #42648f;
    background: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.sidebar .sidebar-toggle-btn:hover,
.sidebar .sidebar-toggle-btn:focus-visible {
    border-color: #bad0ee;
    background: #eff5ff;
    color: #254b84;
}

body.sidebar-expanded .sidebar .sidebar-toggle-wrap {
    justify-content: flex-end;
    padding: 0 1rem 0.65rem;
}

.sidebar .sidebar-profile {
    padding: 0.6rem !important;
    margin-bottom: 0.7rem !important;
}

.sidebar .sidebar-profile a {
    justify-content: center;
    border-radius: 12px;
    border: 1px solid #dde6f3;
    background: #ffffff;
    padding: 0.5rem;
    box-shadow: 0 8px 16px -18px rgba(15, 23, 42, 0.55);
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
    color: #4b5563;
    padding: 0.62rem 0.72rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s ease;
    border-radius: 10px;
    margin: 0.13rem 0;
    border: 1px solid transparent;
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
    background-color: #edf4ff;
    color: #22508f;
    border-color: #d1e2f9;
    transform: translateX(1px);
}

.sidebar .nav-link.active {
    background-color: #e8f1ff;
    color: #1d4f91;
    border-color: #c5daf9;
    font-weight: 600;
    box-shadow: 0 8px 16px -18px rgba(37, 99, 235, 0.75);
}

.sidebar .nav-link i {
    font-size: 1rem;
    width: 20px;
    flex: 0 0 20px;
    text-align: center;
}

.sidebar .nav-section-title {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7a8798;
    padding: 0.72rem 0.72rem 0.28rem;
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
    margin: 0.55rem 0.6rem;
}

.sidebar .nav-divider {
    margin: 0.55rem 0.6rem;
    border-top: 1px solid #dbe4ef;
    list-style: none;
}

.main-content {
    margin-left: 82px;
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
        border-right: 1px solid #dce5f1;
        border-bottom: 0;
        overflow-y: auto;
        box-shadow: 16px 0 30px -22px rgba(15, 23, 42, 0.6);
    }

    body.sidebar-expanded .sidebar {
        left: 0;
    }

    .sidebar .sidebar-profile a {
        justify-content: flex-start;
    }

    .sidebar .sidebar-toggle-wrap {
        justify-content: flex-end;
        padding: 0 1rem 0.65rem;
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
