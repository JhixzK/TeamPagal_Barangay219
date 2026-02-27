<?php
/**
 * E-Barangay Information Management System
 * Public Homepage - Visible to All Users
 * Government-Style Landing Page
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$fullName = $_SESSION['full_name'] ?? ucfirst(str_replace('_', ' ', $role));

$barangayName = 'Barangay 219, Tondo';
$city = 'Manila';
$province = 'Metro Manila';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Barangay Portal - <?php echo htmlspecialchars($barangayName); ?></title>
    <!-- Bootstrap CSS -->
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Font: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        /* ============= NAVIGATION BAR ============= */
        .navbar-gov {
            background-color: #2563eb;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .navbar-gov .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white !important;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .navbar-gov .navbar-brand img {
            height: 40px;
            width: 40px;
            border-radius: 50%;
        }

        .navbar-gov .navbar-brand span {
            white-space: nowrap;
        }

        .navbar-gov .nav-link {
            color: white !important;
            font-weight: 500;
            margin: 0 8px;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-gov .nav-link:hover {
            opacity: 0.8;
        }

        .navbar-gov .nav-link.active {
            border-bottom: 3px solid white;
        }

        .navbar-gov .btn-login {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-gov .btn-login:hover {
            background-color: white;
            color: #2563eb;
        }

        body {
            padding-top: 70px;
        }

        /* ============= HERO SECTION ============= */
        .hero-section {
            position: relative;
            height: 600px;
            background: linear-gradient(rgba(37, 99, 235, 0.7), rgba(37, 99, 235, 0.7)), 
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23e6f2ff" width="1200" height="600"/><circle cx="100" cy="150" r="40" fill="%23dbeafe" opacity="0.3"/><circle cx="1100" cy="500" r="60" fill="%23dbeafe" opacity="0.3"/><circle cx="600" cy="300" r="80" fill="%23dbeafe" opacity="0.2"/></svg>');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            position: relative;
            z-index: 2;
        }

        .hero-text {
            color: white;
            flex: 1;
            max-width: 600px;
            animation: fadeInLeft 0.8s ease;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-text p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.95;
            margin-bottom: 30px;
        }

        .btn-about {
            background-color: rgba(255, 255, 255, 0.25);
            border: 2px solid white;
            color: white;
            padding: 12px 35px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }

        .btn-about:hover {
            background-color: white;
            color: #2563eb;
            transform: translateY(-2px);
        }

        .hero-seal {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeInRight 0.8s ease;
        }

        .hero-seal img {
            height: 350px;
            width: 350px;
            border-radius: 50%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            object-fit: contain;
            background: white;
            padding: 15px;
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ============= CONTENT SECTIONS ============= */
        .section-padding {
            padding: 80px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
        }

        .section-header::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background-color: #2563eb;
            margin: 15px auto 0;
        }

        .section-header p {
            color: #6b7280;
            font-size: 1.1rem;
            margin-top: 15px;
        }

        .service-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .service-card:hover {
            border-color: #2563eb;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.1);
            transform: translateY(-5px);
        }

        .service-icon {
            font-size: 3.5rem;
            color: #2563eb;
            margin-bottom: 20px;
        }

        .service-card h5 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
        }

        .service-card p {
            color: #6b7280;
            line-height: 1.6;
        }

        /* ============= FOOTER ============= */
        footer {
            background-color: #1f2937;
            color: white;
            padding: 40px 0 20px;
            margin-top: 80px;
        }

        footer p {
            margin: 0;
            font-size: 0.95rem;
        }

        footer .footer-brand {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        /* ============= RESPONSIVE ============= */
        @media (max-width: 768px) {
            .navbar-gov .navbar-brand span {
                display: none;
            }

            .hero-section {
                height: 400px;
            }

            .hero-text h1 {
                font-size: 2rem;
            }

            .hero-text p {
                font-size: 1rem;
            }

            .hero-seal img {
                height: 250px;
                width: 250px;
            }

            .hero-content {
                flex-direction: column;
                gap: 20px;
            }

            .section-header h2 {
                font-size: 1.8rem;
            }

            .navbar-gov .nav-link {
                margin: 0 4px;
                padding: 0.5rem 0.5rem !important;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-white">
    <!-- NAVIGATION BAR -->
    <nav class="navbar navbar-gov navbar-expand-lg">
        <div class="container-lg">
            <!-- Brand / Logo -->
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>home.php">
                <img src="<?php echo ASSETS_URL; ?>img/barangaylogo.png" alt="Barangay Logo">
                <span><?php echo htmlspecialchars($barangayName); ?></span>
            </a>

            <!-- Toggler for mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu"
                aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarMenu">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo BASE_URL; ?>home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>announcement.php">News & Announcements</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>

                <!-- Login/Register Buttons -->
                <div class="d-flex gap-2">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-login">Dashboard</a>
                        <a href="#" onclick="logout()" class="btn btn-login">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-login">Register</a>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-login">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container-lg">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>E-Barangay Portal</h1>
                    <p>A Barangay Portal System is an online platform designed to optimize the services and transactions within the local barangay.</p>
                    <a href="#about" class="btn-about">About Us</a>
                </div>
                <div class="hero-seal">
                    <img src="<?php echo ASSETS_URL; ?>img/barangaylogo.png" alt="Barangay Logo">
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section id="about" class="section-padding bg-light">
        <div class="container-lg">
            <div class="section-header">
                <h2>About <?php echo htmlspecialchars($barangayName); ?></h2>
                <p>Welcome to the E-Barangay Portal</p>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <p style="color: #374151; font-size: 1.1rem; line-height: 1.8;">
                        Welcome to the E-Barangay Portal, a modern solution built to bring essential barangay services right to your fingertips. Our mission is to empower our community by delivering fast and reliable barangay services through digital innovation.
                    </p>
                    <p style="color: #374151; font-size: 1.1rem; line-height: 1.8;">
                        This platform serves as the central hub for all official barangay information, services, and community engagement. Whether you're a resident seeking services or looking for important announcements, the E-Barangay Portal provides a seamless, accessible experience.
                    </p>
                </div>
                <div class="col-lg-6 mb-4">
                    <div style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); border-radius: 12px; padding: 40px; color: white;">
                        <h4 style="margin-bottom: 20px; font-weight: 600;">Getting Started</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li style="margin-bottom: 15px;">
                                <i class="bi bi-check-circle" style="margin-right: 10px;"></i>
                                <strong>Step 1:</strong> Click the "Register" button to create your account
                            </li>
                            <li style="margin-bottom: 15px;">
                                <i class="bi bi-check-circle" style="margin-right: 10px;"></i>
                                <strong>Step 2:</strong> Complete the resident registration form
                            </li>
                            <li style="margin-bottom: 15px;">
                                <i class="bi bi-check-circle" style="margin-right: 10px;"></i>
                                <strong>Step 3:</strong> Wait for barangay verification and approval
                            </li>
                            <li>
                                <i class="bi bi-check-circle" style="margin-right: 10px;"></i>
                                <strong>Step 4:</strong> Access all barangay services and features
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES SECTION -->
    <section id="services" class="section-padding">
        <div class="container-lg">
            <div class="section-header">
                <h2>Barangay Services</h2>
                <p>Online services available to residents</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-file-earmark-text"></i></div>
                        <h5>Barangay Clearance</h5>
                        <p>Proof that a person has no bad record in the barangay, often needed for job or permit applications.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-house-check"></i></div>
                        <h5>Barangay Residency</h5>
                        <p>Confirms that a person is a resident of the barangay and provides official documentation.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-heart"></i></div>
                        <h5>Barangay Indigency</h5>
                        <p>Issued to low-income individuals for aid, scholarships, or medical help.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-briefcase"></i></div>
                        <h5>Business Clearance</h5>
                        <p>Required for starting or operating a business in the barangay jurisdiction.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-flag"></i></div>
                        <h5>Blotters Report</h5>
                        <p>Access incident and community safety reports from barangay records.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-hospital"></i></div>
                        <h5>Vaccination Records</h5>
                        <p>Official vaccination certificates and health screening records.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-megaphone"></i></div>
                        <h5>Announcements</h5>
                        <p>Stay informed with latest barangay news, events, and community updates.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-people"></i></div>
                        <h5>Resident Directory</h5>
                        <p>Browse barangay resident information and connect with your community.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section class="section-padding" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); color: white; text-align: center;">
        <div class="container-lg">
            <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Ready to Get Started?</h2>
            <p style="font-size: 1.1rem; margin-bottom: 30px; opacity: 0.95;">
                Register now as a resident and access all barangay services online.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>register.php" class="btn" style="background: white; color: #2563eb; padding: 12px 35px; border-radius: 8px; font-weight: 600; text-decoration: none; transition: all 0.3s ease;">
                    Register Now
                </a>
                <a href="<?php echo BASE_URL; ?>login.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 35px; border-radius: 8px; font-weight: 600; text-decoration: none; transition: all 0.3s ease;">
                    Login
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container-lg">
            <div class="row mb-4">
                <div class="col-md-6">
                    <p class="footer-brand">E-Barangay Portal</p>
                    <p><?php echo htmlspecialchars($barangayName); ?> • <?php echo htmlspecialchars($city); ?>, <?php echo htmlspecialchars($province); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p><strong>Contact:</strong> (02) XXX-XXXX | email@barangay.gov.ph</p>
                </div>
            </div>
            <hr style="opacity: 0.2;">
            <p style="text-align: center; margin-top: 20px; opacity: 0.8;">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($barangayName); ?>. All Rights Reserved.
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <script>
        window.API_URL = '<?php echo addslashes(API_URL); ?>';

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = window.API_URL + 'auth.php?action=logout&redirect=<?php echo BASE_URL; ?>home.php';
            }
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#' && document.querySelector(href)) {
                    e.preventDefault();
                    document.querySelector(href).scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
