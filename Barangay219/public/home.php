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
$barangayNavName = 'Barangay 219';
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
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        :root {
            --gov-blue: #1d4ed8;
            --gov-red: #dc2626;
            --gov-white: #ffffff;
            --blue-soft: #eff6ff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-soft: #e2e8f0;
            --surface-soft: #f8fafc;
            --shadow-soft: 0 8px 24px rgba(15, 23, 42, 0.07);
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--gov-white);
            color: var(--text-primary);
            padding-top: 78px;
            line-height: 1.6;
            scroll-behavior: smooth;
        }

        .section-padding {
            padding: 88px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 42px;
        }

        .section-header h2 {
            font-size: clamp(1.75rem, 3vw, 2.4rem);
            font-weight: 800;
            margin-bottom: 0.7rem;
            color: var(--text-primary);
        }

        .section-header h2::after {
            content: '';
            display: block;
            width: 74px;
            height: 4px;
            border-radius: 999px;
            margin: 0.65rem auto 0;
            background: var(--gov-blue);
        }

        .section-header p {
            color: var(--text-secondary);
            margin: 0;
        }

        .navbar-gov {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1100;
            background: var(--gov-blue);
            border-bottom: 1px solid #1e40af;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
        }

        .navbar-gov .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 1rem;
        }

        .navbar-gov .navbar-brand img {
            height: 46px;
            width: 46px;
            border-radius: 0;
            border: none;
            object-fit: cover;
            background: transparent;
        }

        .navbar-gov .nav-link {
            color: #dbeafe !important;
            font-weight: 500;
            margin: 0 0.2rem;
            border-radius: 8px;
            padding: 0.5rem 0.8rem !important;
            transition: color 0.2s ease, background-color 0.2s ease;
            position: relative;
        }

        .navbar-gov .nav-link::after {
            content: '';
            position: absolute;
            left: 0.8rem;
            right: 0.8rem;
            bottom: 2px;
            height: 2px;
            background: #ffffff;
            border-radius: 99px;
            transform: scaleX(0);
            transform-origin: center;
            transition: transform 0.22s ease;
        }

        .navbar-gov .nav-link:hover,
        .navbar-gov .nav-link:focus {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.12);
        }

        .navbar-gov .nav-link.active {
            color: #ffffff !important;
            font-weight: 600;
            background: transparent;
        }

        .navbar-gov .nav-link.active::after {
            transform: scaleX(1);
        }

        .btn-nav {
            border-radius: 9px;
            font-weight: 600;
            padding: 0.5rem 1.1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-nav:hover {
            transform: translateY(-1px);
        }

        .btn-register {
            color: #ffffff;
            border: 1px solid #ffffff;
            background: transparent;
        }

        .btn-register:hover {
            background: rgba(255, 255, 255, 0.12);
            box-shadow: none;
        }

        .btn-login {
            color: var(--gov-blue);
            border: 1px solid #ffffff;
            background: #ffffff;
        }

        .btn-login:hover {
            color: #1e40af;
            background: #eff6ff;
            box-shadow: none;
        }

        .btn-danger-outline {
            color: var(--gov-red);
            border: 1px solid #fecaca;
            background: #fff;
        }

        .btn-danger-outline:hover {
            background: #fef2f2;
            box-shadow: 0 5px 12px rgba(220, 38, 38, 0.15);
        }

        .hero-section {
            background: #ffffff;
            padding: 88px 0 72px;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2rem;
            align-items: center;
        }

        @keyframes fadeInUpSoft {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInRightSoft {
            from {
                opacity: 0;
                transform: translateX(16px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-text {
            opacity: 0;
            animation: fadeInUpSoft 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.08s forwards;
        }

        .hero-seal {
            opacity: 0;
            animation: fadeInRightSoft 1s cubic-bezier(0.22, 1, 0.36, 1) 0.16s forwards;
        }

        .hero-text h1 {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .hero-text p {
            color: var(--text-secondary);
            font-size: 1.05rem;
            max-width: 610px;
            margin-bottom: 1.8rem;
        }

        .hero-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-hero {
            border-radius: 10px;
            padding: 0.72rem 1.3rem;
            font-weight: 600;
            border: 1px solid transparent;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-hero:hover {
            transform: translateY(-1px);
        }

        .btn-hero-primary {
            background: var(--gov-blue);
            color: #fff;
        }

        .btn-hero-primary:hover {
            color: #fff;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
        }

        .btn-hero-outline {
            background: #ffffff;
            color: var(--gov-blue);
            border-color: #bfdbfe;
        }

        .btn-hero-outline:hover {
            color: var(--gov-blue);
            background: #eff6ff;
        }

        .hero-seal {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-seal img {
            width: min(520px, 96vw);
            max-height: 520px;
            object-fit: contain;
            filter: drop-shadow(0 16px 28px rgba(29, 78, 216, 0.16));
        }

        .about-panel,
        .service-card,
        .news-card,
        .official-card {
            background: #fff;
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            box-shadow: var(--shadow-soft);
        }

        .about-panel {
            padding: 2rem;
            height: 100%;
        }

        .about-panel p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .steps-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .steps-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            color: #334155;
            margin-bottom: 0.9rem;
        }

        .steps-list li i {
            color: var(--gov-blue);
            margin-top: 2px;
        }

        .service-card {
            padding: 1.7rem 1.35rem;
            height: 100%;
            border-top: 3px solid #bfdbfe;
            transition: transform 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
        }

        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .card-link:focus-visible {
            outline: none;
        }

        .card-link:focus-visible .service-card,
        .card-link:focus-visible .news-card {
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.22);
        }

        .service-card:hover {
            transform: translateY(-3px);
            border-color: #bfdbfe;
            border-top-color: var(--gov-blue);
            box-shadow: 0 14px 26px rgba(29, 78, 216, 0.12);
        }

        .service-icon {
            font-size: 2rem;
            color: var(--gov-blue);
            margin-bottom: 0.8rem;
        }

        .service-card h5 {
            font-size: 1.08rem;
            font-weight: 700;
            margin-bottom: 0.55rem;
        }

        .service-card p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.96rem;
        }

        .news-card {
            padding: 1.4rem;
            height: 100%;
            border-top: 3px solid #bfdbfe;
            transition: transform 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
        }

        .card-link:hover .news-card {
            transform: translateY(-3px);
            border-color: #bfdbfe;
            border-top-color: var(--gov-blue);
            box-shadow: 0 14px 26px rgba(29, 78, 216, 0.12);
        }

        .card-link:active .news-card {
            transform: translateY(-1px);
        }

        .news-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.83rem;
            color: #64748b;
            margin-bottom: 0.8rem;
        }

        .news-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .news-card p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        .news-card.important {
            border-left: 4px solid var(--gov-red);
        }

        .notice-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.24rem 0.58rem;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
            color: #991b1b;
            background: #fee2e2;
            margin-bottom: 0.55rem;
        }

        .official-card {
            text-align: center;
            padding: 1.4rem;
            height: 100%;
        }

        .official-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin: 0 auto 0.85rem;
            display: grid;
            place-items: center;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: var(--gov-blue);
            font-weight: 700;
            font-size: 1.15rem;
        }

        .official-name {
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .official-role {
            margin: 0;
            color: #475569;
            font-size: 0.92rem;
        }

        .cta-panel {
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            background: #fff;
            box-shadow: var(--shadow-soft);
            padding: 2.2rem;
            text-align: center;
        }

        .cta-panel h2 {
            font-weight: 800;
            font-size: clamp(1.5rem, 3vw, 2.1rem);
            margin-bottom: 0.7rem;
        }

        .cta-panel p {
            color: var(--text-secondary);
            margin-bottom: 1.3rem;
        }

        footer {
            border-top: 1px solid var(--border-soft);
            background: #fff;
            color: #334155;
            padding: 2rem 0 1.2rem;
        }

        .footer-brand {
            font-weight: 700;
            color: var(--text-primary);
        }

        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 0.75s cubic-bezier(0.22, 1, 0.36, 1), transform 0.75s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform;
        }

        .reveal-on-scroll.revealed {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-text,
            .hero-seal {
                opacity: 1;
                animation: none;
            }

            .reveal-on-scroll {
                opacity: 1;
                transform: none;
                transition: none;
            }
        }

        @media (max-width: 991px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 2.2rem;
            }

            .hero-text {
                text-align: center;
                max-width: 100%;
            }

            .hero-text p {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-actions {
                justify-content: center;
            }

            .navbar-gov .navbar-brand span {
                font-size: 0.95rem;
            }

            .navbar-gov .d-flex {
                margin-top: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 74px;
            }

            .section-padding {
                padding: 72px 0;
            }

            .about-panel,
            .cta-panel {
                padding: 1.35rem;
            }

            .btn-nav,
            .btn-hero {
                width: 100%;
                text-align: center;
            }

            .hero-actions {
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-white">
    <!-- NAVIGATION BAR -->
    <nav class="navbar navbar-gov navbar-expand-lg navbar-light">
        <div class="container-lg">
            <!-- Brand / Logo -->
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>home.php">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo">
                <span><?php echo htmlspecialchars($barangayNavName); ?></span>
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
                        <a class="nav-link" href="#news">News</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>

                <!-- Login/Register Buttons -->
                <div class="d-flex gap-2">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-nav btn-login">Dashboard</a>
                        <a href="#" onclick="logout()" class="btn btn-nav btn-danger-outline">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-nav btn-register">Register</a>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-nav btn-login">Login</a>
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
                    <p>
                        Access barangay services, announcements, and community updates in one official and easy-to-use platform designed for every resident.
                    </p>
                    <div class="hero-actions">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn-hero btn-hero-primary">Go to Dashboard</a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>register.php" class="btn-hero btn-hero-primary">Register as Resident</a>
                        <?php endif; ?>
                        <a href="#services" class="btn-hero btn-hero-outline">Explore Services</a>
                    </div>
                </div>
                    <div class="hero-seal">
                        <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo">
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section id="about" class="section-padding">
        <div class="container-lg">
            <div class="section-header">
                <h2>About <?php echo htmlspecialchars($barangayName . ', ' . $city); ?></h2>
                <p>Welcome to the E-Barangay Portal</p>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="about-panel">
                        <p>
                            Welcome to the E-Barangay Portal, a modern solution built to bring essential barangay services right to your fingertips. Our mission is to empower our community by delivering fast and reliable barangay services through digital innovation.
                        </p>
                        <p class="mb-0">
                            This platform serves as the central hub for all official barangay information, services, and community engagement. Whether you're a resident seeking services or looking for important announcements, the E-Barangay Portal provides a seamless, accessible experience.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="about-panel">
                            <h4 class="mb-3" style="font-weight: 700; color: var(--gov-blue);">Getting Started</h4>
                        <ul class="steps-list">
                            <li><i class="bi bi-check-circle-fill"></i><span><strong>Step 1:</strong> Click the Register button to create your account.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span><strong>Step 2:</strong> Complete the resident registration form.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span><strong>Step 3:</strong> Wait for barangay verification and approval.</span></li>
                            <li class="mb-0"><i class="bi bi-check-circle-fill"></i><span><strong>Step 4:</strong> Access all barangay services and features.</span></li>
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
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Barangay Clearance">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-file-earmark-text"></i></div>
                            <h5>Barangay Clearance</h5>
                            <p>Proof that a person has no bad record in the barangay, often needed for job or permit applications.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Barangay Residency">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-house-check"></i></div>
                            <h5>Barangay Residency</h5>
                            <p>Confirms that a person is a resident of the barangay and provides official documentation.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Barangay Indigency">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-heart"></i></div>
                            <h5>Barangay Indigency</h5>
                            <p>Issued to low-income individuals for aid, scholarships, or medical help.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Business Clearance">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-briefcase"></i></div>
                            <h5>Business Clearance</h5>
                            <p>Required for starting or operating a business in the barangay jurisdiction.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Blotters Report">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-flag"></i></div>
                            <h5>Blotters Report</h5>
                            <p>Access incident and community safety reports from barangay records.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Vaccination Records">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-hospital"></i></div>
                            <h5>Vaccination Records</h5>
                            <p>Official vaccination certificates and health screening records.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Announcements service">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-megaphone"></i></div>
                            <h5>Announcements</h5>
                            <p>Stay informed with latest barangay news, events, and community updates.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Resident Directory">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-people"></i></div>
                            <h5>Resident Directory</h5>
                            <p>Browse barangay resident information and connect with your community.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ANNOUNCEMENTS SECTION -->
    <section id="news" class="section-padding" style="background: #ffffff;">
        <div class="container-lg">
            <div class="section-header">
                <h2>Latest Announcements</h2>
                <p>Important updates and community notices from the barangay</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to view Barangay Assembly announcement">
                        <article class="news-card important">
                            <div class="notice-badge"><i class="bi bi-exclamation-circle-fill"></i>Important Notice</div>
                            <div class="news-meta"><i class="bi bi-calendar3"></i><span>March 2026</span></div>
                            <h3 class="news-title">Barangay Assembly and Community Consultation</h3>
                            <p>Residents are encouraged to attend the monthly assembly to discuss programs, safety priorities, and local initiatives.</p>
                        </article>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to view Free Health Checkup announcement">
                        <article class="news-card">
                            <div class="news-meta"><i class="bi bi-calendar3"></i><span>March 2026</span></div>
                            <h3 class="news-title">Free Health Checkup Schedule</h3>
                            <p>Barangay health workers will provide free basic consultation and blood pressure monitoring at the health center.</p>
                        </article>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to view Youth Skills announcement">
                        <article class="news-card">
                            <div class="news-meta"><i class="bi bi-calendar3"></i><span>March 2026</span></div>
                            <h3 class="news-title">Youth Skills and Employment Orientation</h3>
                            <p>A capacity-building session for youth residents on employment readiness and community volunteer opportunities.</p>
                        </article>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- OFFICIALS SECTION -->
    <section id="officials" class="section-padding">
        <div class="container-lg">
            <div class="section-header">
                <h2>Barangay Officials</h2>
                <p>Serving the community with transparency, responsibility, and care</p>
            </div>

            <div class="row g-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="official-card">
                        <div class="official-avatar">BC</div>
                        <p class="official-name">Barangay Captain</p>
                        <p class="official-role">Chief Executive Officer</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="official-card">
                        <div class="official-avatar">SK</div>
                        <p class="official-name">Barangay Secretary</p>
                        <p class="official-role">Records and Documentation</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="official-card">
                        <div class="official-avatar">TR</div>
                        <p class="official-name">Barangay Treasurer</p>
                        <p class="official-role">Finance and Budget</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="official-card">
                        <div class="official-avatar">KG</div>
                        <p class="official-name">Barangay Kagawad</p>
                        <p class="official-role">Committee Services</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section class="section-padding">
        <div class="container-lg">
            <div class="cta-panel">
                <h2>Ready to Get Started?</h2>
                <p>Register now as a resident and access barangay services through one secure digital portal.</p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn-hero btn-hero-primary">Register Now</a>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn-hero btn-hero-outline">Login</a>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="contact">
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
            <hr style="opacity: 0.1; margin: 0;">
            <p style="text-align: center; margin-top: 16px; color: #64748b;">
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

        // Navbar active indicator follows section while scrolling
        (function() {
            const navLinks = document.querySelectorAll('.navbar-gov .nav-link');
            const homeLink = document.querySelector('.navbar-gov .nav-link[href$="home.php"]');
            const sectionLinks = Array.from(navLinks).filter(link => {
                const href = link.getAttribute('href') || '';
                return href.startsWith('#') && document.querySelector(href);
            });

            const sections = sectionLinks.map(link => ({
                link,
                section: document.querySelector(link.getAttribute('href'))
            }));

            function setActive(linkToActivate) {
                navLinks.forEach(link => link.classList.remove('active'));
                if (linkToActivate) {
                    linkToActivate.classList.add('active');
                }
            }

            function updateActiveNav() {
                const scrollY = window.scrollY;
                const offset = 140;
                let active = null;

                for (const item of sections) {
                    if (!item.section) continue;
                    if (scrollY + offset >= item.section.offsetTop) {
                        active = item.link;
                    }
                }

                if (active) {
                    setActive(active);
                } else if (homeLink) {
                    setActive(homeLink);
                }

                const contactLink = document.querySelector('.navbar-gov .nav-link[href="#contact"]');
                const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 8;
                if (nearBottom && contactLink) {
                    setActive(contactLink);
                }
            }

            window.addEventListener('scroll', updateActiveNav, { passive: true });
            window.addEventListener('load', updateActiveNav);
            updateActiveNav();
        })();

        // Smooth reveal animation for sections on first view
        (function() {
            const revealTargets = document.querySelectorAll('#about, #services, #news, #officials, .cta-panel, footer#contact');
            revealTargets.forEach(el => el.classList.add('reveal-on-scroll'));

            revealTargets.forEach((el, index) => {
                el.style.transitionDelay = `${Math.min(index * 70, 280)}ms`;
            });

            if (!('IntersectionObserver' in window)) {
                revealTargets.forEach(el => el.classList.add('revealed'));
                return;
            }

            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                });
            }, {
                threshold: 0.08,
                rootMargin: '0px 0px -28px 0px'
            });

            revealTargets.forEach(el => revealObserver.observe(el));
        })();
    </script>
</body>
</html>
