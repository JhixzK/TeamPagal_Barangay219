<?php
/**
 * E-Barangay Information Management System
 * Sidebar Navigation Component
 */

if (!isLoggedIn()) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
$role = getCurrentUserRole();

// Define menu items with permissions
$menu_items = [
    [
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'url' => 'dashboard.php',
        'roles' => ['barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman']
    ],
    [
        'title' => 'Residents',
        'icon' => 'bi-people',
        'url' => 'residents.php',
        'roles' => ['barangay_captain', 'secretary']
    ],
    [
        'title' => 'Households',
        'icon' => 'bi-house-door',
        'url' => 'households.php',
        'roles' => ['barangay_captain', 'secretary']
    ],
    [
        'title' => 'Certificates',
        'icon' => 'bi-file-earmark-text',
        'url' => 'certificates.php',
        'roles' => ['barangay_captain', 'secretary', 'treasurer']
    ],
    [
        'title' => 'Blotters',
        'icon' => 'bi-journal-text',
        'url' => 'blotter.php',
        'roles' => ['barangay_captain', 'secretary', 'kagawad']
    ],
    [
        'title' => 'Complaints',
        'icon' => 'bi-exclamation-triangle',
        'url' => 'complaints.php',
        'roles' => ['barangay_captain', 'secretary', 'kagawad']
    ],
    [
        'title' => 'Announcements',
        'icon' => 'bi-megaphone',
        'url' => 'announcement.php',
        'roles' => ['barangay_captain', 'secretary', 'kagawad', 'sk_chairman']
    ],
    [
        'title' => 'Reports',
        'icon' => 'bi-graph-up',
        'url' => 'reports.php',
        'roles' => ['barangay_captain', 'secretary', 'treasurer']
    ],
    [
        'title' => 'Users',
        'icon' => 'bi-person-gear',
        'url' => 'users.php',
        'roles' => ['barangay_captain']
    ]
];

// Filter menu items based on user role
$filtered_menu = array_filter($menu_items, function($item) use ($role) {
    return in_array($role, $item['roles']);
});
?>
<div class="sidebar">
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <?php foreach ($filtered_menu as $item): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === $item['url']) ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $item['url']; ?>">
                    <i class="<?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['title']; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
.sidebar {
    width: 250px;
    min-height: calc(100vh - 56px);
    background-color: #f8f9fa;
    border-right: 1px solid #dee2e6;
    position: fixed;
    left: 0;
    top: 56px;
    overflow-y: auto;
    z-index: 1000;
}

.sidebar-content {
    padding: 1rem 0;
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
}

.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: calc(100vh - 56px);
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
}
</style>
