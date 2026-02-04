<?php
/**
 * E-Barangay Information Management System
 * Public Homepage - Visible to All Users
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$fullName = $_SESSION['full_name'] ?? ucfirst(str_replace('_', ' ', $role));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS (Local) -->
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .navbar-custom {
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .hero-section {
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        .hero-section p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        .btn-primary-custom {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #333;
            font-weight: 600;
            padding: 12px 40px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover {
            background-color: #ffb300;
            border-color: #ffb300;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        .welcome-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .user-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .footer-custom {
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 30px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
        <div class="container-lg">
            <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>home.php">
                <i class="bi bi-building"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>home.php"><i class="bi bi-house"></i> Home</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>announcement.php"><i class="bi bi-megaphone"></i> Announcements</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>blotter.php"><i class="bi bi-clipboard"></i> Blotter</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php"><i class="bi bi-person"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" id="logoutBtn"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>register.php"><i class="bi bi-person-plus"></i> Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container-lg">
            <h1>Welcome to <?php echo BARANGAY_NAME; ?></h1>
            <p>E-Barangay Information Management System</p>
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-primary-custom btn-lg">
                    <i class="bi bi-arrow-right"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary-custom btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Login Now
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Welcome Section -->
    <section class="container-lg py-5">
        <?php if ($isLoggedIn): ?>
            <div class="welcome-card">
                <div class="user-info">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-2">Welcome back, <strong><?php echo htmlspecialchars($fullName); ?></strong>!</h5>
                            <p class="mb-0"><strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $role)); ?></p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <p class="mb-0"><strong>User ID:</strong> <?php echo htmlspecialchars($userId); ?></p>
                        </div>
                    </div>
                </div>

                <h3 class="mb-4">Quick Access</h3>
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-file-text"></i></div>
                            <h5>Announcements</h5>
                            <p class="text-muted">View latest barangay announcements and updates</p>
                            <a href="<?php echo BASE_URL; ?>announcement.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-journal-text"></i></div>
                            <h5>Blotter</h5>
                            <p class="text-muted">Access incident and blotter records</p>
                            <a href="<?php echo BASE_URL; ?>blotter.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-award"></i></div>
                            <h5>Certificates</h5>
                            <p class="text-muted">Request and manage certificates</p>
                            <a href="<?php echo BASE_URL; ?>certificates.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-people"></i></div>
                            <h5>Residents</h5>
                            <p class="text-muted">Browse resident information</p>
                            <a href="<?php echo BASE_URL; ?>residents.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="welcome-card">
                <h2 class="mb-4 text-center">About <?php echo BARANGAY_NAME; ?></h2>
                <p>Welcome to the E-Barangay Information Management System. This platform provides comprehensive services for residents and barangay officials including:</p>
                
                <div class="row mt-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-megaphone"></i></div>
                            <h5>Announcements</h5>
                            <p class="text-muted">Stay updated with barangay news and announcements</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-award"></i></div>
                            <h5>Certificates</h5>
                            <p class="text-muted">Request barangay clearance, indigency, and residency certificates</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-journal-text"></i></div>
                            <h5>Blotter Reports</h5>
                            <p class="text-muted">Access community incident reports and records</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="bi bi-people"></i></div>
                            <h5>Resident Directory</h5>
                            <p class="text-muted">Browse barangay resident information</p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <h4>Get Started</h4>
                    <p class="text-muted">To access all features and services, please login or register below:</p>
                    <div class="mt-3">
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary me-2">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-outline-primary">
                            <i class="bi bi-person-plus"></i> Register as Resident
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer-custom mt-5">
        <div class="container-lg">
            <p class="mb-2"><strong><?php echo APP_NAME; ?></strong></p>
            <p class="mb-0"><?php echo BARANGAY_NAME; ?> | Version <?php echo APP_VERSION; ?></p>
            <p class="mt-3"><small>&copy; <?php echo date('Y'); ?> All Rights Reserved</small></p>
        </div>
    </footer>

    <!-- Bootstrap JS (Local) -->
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <!-- Define API URL for JavaScript -->
    <script>
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
        
        <?php if ($isLoggedIn): ?>
        // Logout handler (only for authenticated users)
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    fetch(window.API_URL + 'auth.php?action=logout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '<?php echo BASE_URL; ?>home.php';
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        window.location.href = '<?php echo BASE_URL; ?>home.php';
                    });
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
