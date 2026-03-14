<?php
/**
 * Terms of Use Page
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Use - <?php echo APP_NAME; ?></title>
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .policy-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            isolation: isolate;
            overflow-x: hidden;
            background: #ffffff;
        }

        .policy-stack {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            padding: 56px 16px 24px;
        }

        .policy-container::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background: url('<?php echo ASSETS_URL; ?>img/crop219logo.png') no-repeat 95% center;
            background-size: calc(900px * var(--bg-zoom-inverse, 1));
            opacity: 0.90;
            filter: blur(6px);
            transform: scale(1.03);
            pointer-events: none;
        }

        .policy-card {
            width: 100%;
            max-width: 920px;
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(236, 240, 226, 0.9);
            border-radius: 16px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            padding: 2rem 1.15rem;
        }

        .policy-brand-logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
        }

        .policy-title {
            font-size: clamp(1.65rem, 2.7vw, 2.25rem);
            font-weight: 800;
            letter-spacing: 0.01em;
            line-height: 1.25;
            margin-bottom: 0.4rem;
            display: inline-block;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .policy-subtitle {
            color: #475569;
            font-weight: 600;
            margin-bottom: 0;
        }

        .effective-date {
            font-size: 0.86rem;
            color: #64748b;
            margin: 0.25rem 0 1.1rem;
            text-align: center;
        }

        .policy-section {
            background: rgba(248, 250, 252, 0.84);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.9rem 1rem;
            margin-bottom: 0.75rem;
        }

        .policy-section h2 {
            font-size: 1.02rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: #0f172a;
        }

        .policy-section p {
            margin-bottom: 0;
            color: #334155;
        }

        .back-home-outside {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 2;
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.1rem;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            color: #ffffff;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.28);
            transition: filter 0.2s ease, transform 0.2s ease;
        }

        .back-home-outside:hover {
            filter: brightness(1.08);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .policy-footer-note {
            margin-top: 0.9rem;
            font-size: 0.82rem;
            color: rgba(15, 23, 42, 0.82);
            text-align: center;
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        @media (min-width: 992px) {
            .policy-card {
                padding: 2.25rem;
            }
        }
    </style>
</head>
<body>
    <a href="login.php" class="back-home-outside" aria-label="Back to Login" title="Back to Login">
        <i class="bi bi-arrow-left"></i>
    </a>

    <div class="policy-container">
        <div class="policy-stack">
            <div class="policy-card">
                <div class="text-center mb-3">
                    <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="policy-brand-logo">
                    <h1 class="policy-title mt-3">Terms of Use</h1>
                    <p class="policy-subtitle">Barangay 219 e-Portal</p>
                    <p class="effective-date">Effective date: March 14, 2026</p>
                </div>

                <div class="policy-section">
                    <h2>1. Acceptance of Terms</h2>
                    <p>By accessing and using the Barangay 219 e-Portal, you agree to follow these Terms of Use and applicable laws.</p>
                </div>

                <div class="policy-section">
                    <h2>2. Proper Use</h2>
                    <p>You agree to provide accurate information and use the portal only for lawful barangay-related services.</p>
                </div>

                <div class="policy-section">
                    <h2>3. Account Responsibility</h2>
                    <p>You are responsible for maintaining the confidentiality of your account credentials and for activities under your account.</p>
                </div>

                <div class="policy-section">
                    <h2>4. Service Availability</h2>
                    <p>The barangay may update, suspend, or improve portal features at any time to maintain security and reliability.</p>
                </div>

                <div class="policy-section mb-0">
                    <h2>5. Contact</h2>
                    <p>If you have questions about these Terms, please contact Barangay 219 through official channels.</p>
                </div>
            </div>
            <div class="policy-footer-note">Barangay 219 e-Portal v1.0</div>
        </div>
    </div>

    <script>
        (function () {
            var baseOuterInnerRatio = window.outerWidth && window.innerWidth ? window.outerWidth / window.innerWidth : 1;
            if (!isFinite(baseOuterInnerRatio) || baseOuterInnerRatio <= 0) {
                baseOuterInnerRatio = 1;
            }

            function syncBackgroundZoom() {
                var viewportScale = window.visualViewport && window.visualViewport.scale ? window.visualViewport.scale : 1;
                if (!isFinite(viewportScale) || viewportScale <= 0) {
                    viewportScale = 1;
                }

                var desktopScale = 1;
                if (window.outerWidth && window.innerWidth) {
                    desktopScale = (window.outerWidth / window.innerWidth) / baseOuterInnerRatio;
                }
                if (!isFinite(desktopScale) || desktopScale <= 0) {
                    desktopScale = 1;
                }

                var zoomScale = Math.max(viewportScale, desktopScale);
                document.documentElement.style.setProperty('--bg-zoom-inverse', (1 / zoomScale).toFixed(4));
            }

            syncBackgroundZoom();
            window.addEventListener('resize', syncBackgroundZoom, { passive: true });
            window.addEventListener('orientationchange', syncBackgroundZoom, { passive: true });

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', syncBackgroundZoom, { passive: true });
                window.visualViewport.addEventListener('scroll', syncBackgroundZoom, { passive: true });
            }
        })();
    </script>
</body>
</html>
