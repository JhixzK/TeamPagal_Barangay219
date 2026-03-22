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
$role = $_SESSION['role'] ?? '';

if ($isLoggedIn) {
    if ($role === 'resident') {
        header('Location: ' . BASE_URL . 'resident_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'dashboard.php');
    }
    exit();
}

$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? '';
$fullName = $_SESSION['full_name'] ?? ucfirst(str_replace('_', ' ', $role));

$barangayName = 'Barangay 219, Tondo';
$barangayNavName = 'Tondo, Manila';
$city = 'Manila';
$province = 'Metro Manila';

$heroSlides = [
    ASSETS_URL . 'img/219pic4.jpg',
    ASSETS_URL . 'img/219pic3.jpg',
    ASSETS_URL . 'img/219pic5.jpg',
    ASSETS_URL . 'img/219pic6.jpg'
];
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
            --nav-height-desktop: 78px;
            --nav-height-mobile: 74px;
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
            padding-top: var(--nav-height-desktop);
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
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 14px 26px -22px rgba(15, 23, 42, 0.7);
        }

        .navbar-gov .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 1rem;
        }

        .navbar-gov .navbar-brand img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.78);
        }

        .navbar-gov .brand-text {
            display: inline-flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .navbar-gov .brand-title {
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #f8fafc;
        }

        .navbar-gov .brand-subtitle {
            font-size: 0.74rem;
            color: rgba(248, 250, 252, 0.82);
            font-weight: 500;
        }

        .navbar-gov .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.32);
        }

        .navbar-gov .navbar-toggler:focus {
            box-shadow: 0 0 0 0.18rem rgba(147, 197, 253, 0.35);
        }

        .navbar-gov .nav-link {
            color: rgba(248, 250, 252, 0.86) !important;
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
            background: #e2e8f0;
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

        /* Navigation Buttons (navbar) */
        .btn-nav {
            border-radius: 10px;
            font-weight: 600;
            padding: 0.5rem 1.1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, color 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .btn-nav:hover {
            transform: scale(1.03);
        }

        .btn-register {
            color: #ffffff;
            border: 1px solid #ffffff;
            background: transparent;
        }

        .btn-register:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: scale(1.03);
        }

        .btn-login {
            color: var(--gov-blue);
            border: 1px solid #ffffff;
            background: #ffffff;
        }

        .btn-login:hover {
            color: #1e40af;
            background: #eff6ff;
            transform: scale(1.03);
        }

        .btn-danger-outline {
            color: var(--gov-red);
            border: 1px solid #fecaca;
            background: #fff;
        }

        .btn-danger-outline:hover {
            background: #fef2f2;
            transform: scale(1.03);
            box-shadow: 0 5px 12px rgba(220, 38, 38, 0.15);
        }

        .hero-section {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            background: #ffffff;
            margin-top: calc(-1 * var(--nav-height-desktop));
            min-height: 100vh;
            padding: calc(88px + var(--nav-height-desktop)) 0 84px;
        }

        .hero-slideshow {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 1.2s ease-in-out;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            will-change: opacity;
        }

        .hero-slide.is-active {
            opacity: 1;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.52);
            pointer-events: none;
        }

        .hero-section .container-lg {
            position: relative;
            z-index: 1;
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
            color: #ffffff;
        }

        .hero-text p {
            color: rgba(248, 250, 252, 0.92);
            font-size: 1.05rem;
            max-width: 610px;
            margin-bottom: 1.8rem;
        }

        .hero-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Hero Button Base Styles (extends global .btn system) */
        .btn-hero {
            border-radius: 10px;
            padding: 0.72rem 1.3rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border: 1px solid transparent;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, color 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-hero:hover {
            transform: scale(1.03);
        }

        /* Hero Primary Button (Blue with transparency for brightness on dark hero) */
        .btn-hero-primary {
            background: rgba(37, 99, 235, 0.72);
            color: #ffffff;
            border-color: rgba(191, 219, 254, 0.95);
        }

        .btn-hero-primary:hover,
        .btn-hero-primary:focus {
            color: #ffffff;
            background: rgba(59, 130, 246, 0.9);
            border-color: rgba(191, 219, 254, 0.95);
            box-shadow: 0 15px 30px rgba(29, 78, 216, 0.3);
        }

        /* Hero Outline Button (White semi-transparent) */
        .btn-hero-outline {
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.98);
        }

        .btn-hero-outline:hover,
        .btn-hero-outline:focus {
            color: #fff;
            background: rgba(255, 255, 255, 0.34);
            border-color: rgba(255, 255, 255, 0.98);
            box-shadow: 0 15px 30px rgba(255, 255, 255, 0.15);
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
            filter: drop-shadow(0 16px 28px rgba(15, 23, 42, 0.38));
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

        .about-panel.about-tips-panel {
            height: auto;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem 1.4rem;
        }

        .about-panel.about-tips-panel .steps-list li {
            margin-bottom: 0.7rem;
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

        .official-list {
            text-align: left;
        }

        .official-list-title {
            font-weight: 800;
            letter-spacing: 0.02em;
            margin-bottom: 0.55rem;
            color: var(--text-primary);
            text-transform: uppercase;
        }

        .official-list ul {
            margin: 0;
            padding-left: 1.1rem;
            color: #334155;
        }

        .official-list li {
            margin-bottom: 0.25rem;
            font-size: 0.93rem;
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

        footer.site-footer {
            background: linear-gradient(145deg, #0f172a 0%, #1e3a8a 100%);
            color: #e2e8f0;
            padding: 3rem 0 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr 0.85fr 0.8fr;
            gap: 1.5rem;
            align-items: start;
        }

        .footer-title {
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.85rem;
            letter-spacing: 0.01em;
        }

        .footer-map-wrap {
            width: 100%;
            height: 210px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 12px 24px rgba(2, 6, 23, 0.35);
        }

        .footer-map-wrap iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .footer-contact-list,
        .footer-links {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .footer-contact-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin-bottom: 0.75rem;
            color: rgba(226, 232, 240, 0.95);
        }

        .footer-contact-list i {
            color: #93c5fd;
            margin-top: 2px;
            font-size: 1rem;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: rgba(226, 232, 240, 0.95);
            text-decoration: none;
            transition: color 0.2s ease, transform 0.2s ease;
            display: inline-block;
        }

        .footer-links a:hover,
        .footer-links a:focus {
            color: #ffffff;
            transform: translateX(3px);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .footer-logos {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .footer-logo-circle {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 18px rgba(2, 6, 23, 0.35);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .footer-logo-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .footer-logo-barangay {
            transform: scale(1.22);
            transform-origin: center;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 1.6rem;
            padding-top: 0.95rem;
            text-align: center;
            color: rgba(226, 232, 240, 0.92);
            font-size: 0.92rem;
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

            .hero-slide {
                transition: none;
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

            .navbar-gov .brand-subtitle {
                font-size: 0.68rem;
            }

            .navbar-gov .d-flex {
                margin-top: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: var(--nav-height-mobile);
            }

            .hero-section {
                margin-top: calc(-1 * var(--nav-height-mobile));
                min-height: 92vh;
                padding-top: calc(64px + var(--nav-height-mobile));
                padding-bottom: 64px;
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

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.2rem;
            }

            .footer-map-wrap {
                height: 190px;
            }

            .footer-logos {
                justify-content: center;
            }
        }
    </style>
</head>
<body class="bg-white">
    <!-- NAVIGATION BAR -->
    <nav class="navbar app-topbar navbar-gov navbar-expand-lg navbar-dark">
        <div class="container-lg">
            <!-- Brand / Logo -->
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>home.php">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo">
                <span class="brand-text">
                    <span class="brand-title">Barangay 219</span>
                    <span class="brand-subtitle"><?php echo htmlspecialchars($barangayNavName); ?></span>
                </span>
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
                        <a class="nav-link" href="#news">Announcements</a>
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
        <div class="hero-slideshow" id="heroSlideshow" aria-hidden="true">
            <?php foreach ($heroSlides as $index => $slideImage): ?>
                <div
                    class="hero-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
                    style="background-image: url('<?php echo htmlspecialchars($slideImage, ENT_QUOTES, 'UTF-8'); ?>');"
                ></div>
            <?php endforeach; ?>
            <div class="hero-overlay"></div>
        </div>

        <div class="container-lg">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Barangay 219 e-Portal</h1>
                    <p>
                        Access barangay services, announcements, and community updates through an official and user-friendly platform for every resident.
                    </p>
                    <div class="hero-actions">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn-hero btn-hero-primary">Go to Dashboard</a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>register.php" class="btn-hero btn-hero-primary">Create Account</a>
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
                <h2>About Barangay 219, Tondo, Manila</h2>
                <p>Maligayang pagdating sa e-Barangay Portal</p>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="about-panel">
                        <p>
                            Barangay 219 is a dynamic and close-knit community located in Tondo, Manila. Situated at Barangay 219 Zone 20, District II, Tindalo corner Cavite Street, Tondo, Manila, the barangay is part of one of the oldest and most culturally rich areas in the country.
                        </p>
                        <p>
                            Historically, Tondo has long been known as a center of trade, culture, and community life, and Barangay 219 continues to reflect that legacy. Through the years, it has evolved into a lively neighborhood where families, small businesses, and local traditions thrive side by side. Streets like Tindalo and Cavite are not just pathways but places where everyday stories unfold - children playing, neighbors sharing meals, and residents supporting one another in times of need.
                        </p>
                        <p class="mb-0">
                            Barangay 219 is a testament to the strength and resilience of its people. Despite the challenges that come with urban living, the community remains united and proactive. Local leaders and residents work hand in hand to promote peace and order, cleanliness, and social development programs that benefit everyone - from youth activities and education initiatives to health and safety efforts.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="about-panel about-tips-panel">
                            <h4 class="mb-3" style="font-weight: 700; color: var(--gov-blue);">Getting Started</h4>
                        <ul class="steps-list">
                            <li><i class="bi bi-check-circle-fill"></i><span><strong>Step 1:</strong> I-click ang Register upang gumawa ng account.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span><strong>Step 2:</strong> Kumpletuhin ang Resident Registration Form.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span><strong>Step 3:</strong> Hintayin ang verification at approval ng barangay.</span></li>
                            <li class="mb-0"><i class="bi bi-check-circle-fill"></i><span><strong>Step 4:</strong> Kapag approved na, maaari nang gamitin ang lahat ng barangay services.</span></li>
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
                <p>Mga online na serbisyo na available para sa mga residente</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Barangay Clearance">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-file-earmark-text"></i></div>
                            <h5>Barangay Clearance</h5>
                            <p>Katibayan na ang isang residente ay walang kaso o bad record sa barangay, kadalasang kailangan para sa trabaho o permit.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Barangay Residency">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-house-check"></i></div>
                            <h5>Barangay Residency</h5>
                            <p>Dokumentong nagpapatunay na ang isang tao ay residente ng barangay.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Barangay Indigency">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-heart"></i></div>
                            <h5>Barangay Indigency</h5>
                            <p>Ibinibigay sa mga low-income residents para sa tulong pinansyal, scholarship, o medical assistance.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Business Clearance">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-briefcase"></i></div>
                            <h5>Business Clearance</h5>
                            <p>Kailangan upang makapagsimula o makapag-operate ng negosyo sa loob ng barangay.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Blotter Reports">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-flag"></i></div>
                            <h5>Blotter Reports</h5>
                            <p>Makikita ang incident records at community safety reports mula sa barangay.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Vaccination Records">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-hospital"></i></div>
                            <h5>Vaccination Records</h5>
                            <p>Opisyal na vaccination certificates at health screening records ng residente.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Announcements service">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-megaphone"></i></div>
                            <h5>Announcements</h5>
                            <p>Manatiling updated sa pinakabagong balita, events, at community updates ng barangay.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to access Resident Directory">
                        <div class="service-card">
                            <div class="service-icon"><i class="bi bi-people"></i></div>
                            <h5>Community Records</h5>
                            <p>Centralized resident and community records para sa mas maayos na serbisyo ng barangay.</p>
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
                <h2>Announcements</h2>
                <p>Manatiling updated sa pinakabagong balita, events, at community updates ng barangay.</p>
            </div>

            <p class="text-center mb-4" style="color: var(--text-secondary); font-weight: 600;">Latest Announcements</p>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to view Barangay Assembly announcement">
                        <article class="news-card important">
                            <div class="notice-badge"><i class="bi bi-exclamation-circle-fill"></i>Important Notice</div>
                            <div class="news-meta"><i class="bi bi-calendar3"></i><span>March 2026</span></div>
                            <h3 class="news-title">Barangay Assembly and Community Consultation</h3>
                            <p>Inaanyayahan ang lahat ng residente na dumalo sa monthly barangay assembly upang pag-usapan ang mga programa, seguridad, at mga proyekto ng komunidad.</p>
                        </article>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to view Free Health Checkup announcement">
                        <article class="news-card">
                            <div class="news-meta"><i class="bi bi-calendar3"></i><span>March 2026</span></div>
                            <h3 class="news-title">Free Health Checkup Schedule</h3>
                            <p>Magkakaroon ng libreng basic consultation at blood pressure monitoring ang barangay health workers sa health center.</p>
                        </article>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo BASE_URL; ?>login.php" class="card-link" aria-label="Login to view Youth Skills announcement">
                        <article class="news-card">
                            <div class="news-meta"><i class="bi bi-calendar3"></i><span>March 2026</span></div>
                            <h3 class="news-title">Youth Skills and Employment Orientation</h3>
                            <p>Isang orientation para sa kabataan ng barangay tungkol sa employment readiness, skills development, at community volunteer opportunities.</p>
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
                <p>Naglilingkod nang may transparency, responsibilidad, at malasakit sa komunidad.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="official-card">
                        <div class="official-avatar">PB</div>
                        <p class="official-name">Fernando M. Legaspi</p>
                        <p class="official-role">Punong Barangay</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="official-card official-list">
                        <p class="official-list-title mb-2">Barangay Kagawad</p>
                        <ul>
                            <li>Eduardo R. Grande</li>
                            <li>Ferdinand W. Mejos</li>
                            <li>Anita B. Carretero</li>
                            <li>Luzviminda L. Lagman</li>
                            <li>June F. Bonagua</li>
                            <li>Joel T. Olitres</li>
                            <li>Emma T. Borilla</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="official-card">
                        <div class="official-avatar">SK</div>
                        <p class="official-name">Mico E. Soria</p>
                        <p class="official-role">SK Chairman</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-6">
                    <div class="official-card">
                        <div class="official-avatar">BS</div>
                        <p class="official-name">Adrian M. Rino</p>
                        <p class="official-role">Barangay Secretary</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-6">
                    <div class="official-card">
                        <div class="official-avatar">BT</div>
                        <p class="official-name">Katrina C. Chuidian</p>
                        <p class="official-role">Barangay Treasurer</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="contact" class="site-footer">
        <div class="container-lg">
            <div class="footer-grid">
                <div>
                    <h3 class="footer-title">Our Location</h3>
                    <div class="footer-map-wrap">
                        <iframe
                            src="https://www.google.com/maps?q=Barangay+219+Tondo+Manila&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Barangay 219, Tondo, Manila Map"
                            aria-label="Google Map showing Barangay 219, Tondo, Manila"
                            allowfullscreen>
                        </iframe>
                    </div>
                </div>

                <div>
                    <h3 class="footer-title">Contact Us</h3>
                    <ul class="footer-contact-list">
                        <li>
                            <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                            <span>barangay219@tondo.gov.ph</span>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                            <span>+63 9XX-XXX-XXXX</span>
                        </li>
                        <li>
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            <span>Barangay 219 Zone 20 District II Manila, Tindalo cor. Cavite St., Tondo, Manila
</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo BASE_URL; ?>home.php">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#news">Announcements</a></li>
                        <li><a href="#officials">Officials</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="footer-title">Official Seals</h3>
                    <div class="footer-logos" aria-label="Official barangay and city logos">
                        <div class="footer-logo-circle">
                            <img src="<?php echo ASSETS_URL; ?>img/brgy219logo.jpg" alt="Barangay 219 Logo" class="footer-logo-image footer-logo-barangay">
                        </div>
                        <div class="footer-logo-circle">
                            <img src="<?php echo ASSETS_URL; ?>img/manilalogo.png" alt="Manila City Seal" class="footer-logo-image" onerror="this.onerror=null;this.src='<?php echo ASSETS_URL; ?>img/manila-city-seal.svg';">
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                &copy; 2026 Barangay 219, Tondo, Manila. All Rights Reserved.
            </div>
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

        // Hero background slideshow (hero section only)
        (function() {
            const slides = document.querySelectorAll('#heroSlideshow .hero-slide');
            if (slides.length <= 1) {
                return;
            }

            let activeIndex = 0;
            const intervalMs = 7000;

            window.setInterval(() => {
                const nextIndex = (activeIndex + 1) % slides.length;
                slides[activeIndex].classList.remove('is-active');
                slides[nextIndex].classList.add('is-active');
                activeIndex = nextIndex;
            }, intervalMs);
        })();
    </script>
</body>
</html>
