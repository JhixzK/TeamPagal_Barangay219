<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$currentRole = getCurrentUserRole();
if (normalizeRole($currentRole) !== normalizeRole(ROLE_RESIDENT)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$announcementId = (int)($_GET['id'] ?? 0);
$username = $_SESSION['username'] ?? 'Resident';
$residentId = $_SESSION['resident_id'] ?? null;

$db = Database::getInstance();
$residentName = $username;
if ($residentId) {
    $resident = $db->fetchOne("SELECT first_name, middle_name, last_name FROM residents WHERE id = ?", [$residentId]);
    if ($resident) {
        $residentName = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcement Details | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="resident_dashboard.css">
  <style>
    .announcement-view-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .announcement-back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      color: var(--blue-800, #2d53b9);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
    }

    .announcement-back-link:hover {
      text-decoration: underline;
    }

    .announcement-view-card {
      background: #fff;
      border: 1px solid #e6ebf2;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }

    .announcement-view-image {
      width: 100%;
      height: 360px;
      object-fit: cover;
      background: #f3f6fb;
      display: block;
      cursor: zoom-in;
    }

    .image-lightbox {
      position: fixed;
      inset: 0;
      z-index: 1200;
      background: rgba(10, 17, 33, 0.85);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .image-lightbox.open {
      display: flex;
    }

    .lightbox-content {
      position: relative;
      width: min(95vw, 1300px);
      max-height: 92vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .lightbox-image {
      max-width: 100%;
      max-height: 92vh;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 10px;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
      background: #10182b;
    }

    .lightbox-close {
      position: absolute;
      top: -14px;
      right: -14px;
      width: 38px;
      height: 38px;
      border: none;
      border-radius: 50%;
      background: #fff;
      color: #1e2a44;
      cursor: pointer;
      font-size: 18px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.25);
    }

    .lightbox-hint {
      position: absolute;
      left: 50%;
      bottom: -28px;
      transform: translateX(-50%);
      color: #d8e2f5;
      font-size: 12px;
      letter-spacing: 0.02em;
    }

    .announcement-view-body {
      padding: 24px;
    }

    .announcement-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
      flex-wrap: wrap;
    }

    .badge-category {
      font-size: 12px;
      font-weight: 600;
      padding: 5px 10px;
      border-radius: 999px;
      text-transform: capitalize;
    }

    .category-general { background: #eceff3; color: #4b5563; }
    .category-event { background: #e8f8ee; color: #1f7a3f; }
    .category-advisory { background: #e8f0ff; color: #2553b8; }
    .category-emergency { background: #fdecec; color: #b42318; }

    .announcement-date {
      color: #64748b;
      font-size: 13px;
      font-weight: 500;
    }

    .announcement-view-title {
      margin: 0 0 14px;
      font-size: 30px;
      font-weight: 700;
      line-height: 1.25;
      color: #0f172a;
    }

    .announcement-view-content {
      font-size: 16px;
      line-height: 1.75;
      color: #334155;
      white-space: pre-wrap;
    }

    .announcement-view-empty {
      border: 1px dashed #d3dbe8;
      border-radius: 12px;
      background: #fcfdff;
      color: #64748b;
      text-align: center;
      padding: 48px 16px;
    }

    @media (max-width: 768px) {
      .announcement-view-image {
        height: 220px;
      }

      .announcement-view-title {
        font-size: 24px;
      }

      .announcement-view-body {
        padding: 18px;
      }
    }
  </style>
</head>
<body>
  <header class="top-header">
    <div class="header-left">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="logo-wrap" aria-hidden="true">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <div class="system-text">
        <h1>E-Barangay Information Management System</h1>
        <p>Barangay 219, Tondo, Manila</p>
      </div>
    </div>

    <div class="header-right">
      <span class="date-badge" id="topDateBadge"><?php echo date('F d, Y'); ?></span>
      <button class="icon-btn" aria-label="Notifications">
        <i class="fa-regular fa-bell"></i>
      </button>
      <div class="profile-dropdown" id="profileDropdown">
        <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
          <img src="https://i.pravatar.cc/100?img=12" alt="Resident avatar">
          <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-menu" id="dropdownMenu" role="menu">
          <a href="resident_profile.php" role="menuitem">View Profile</a>
          <a href="#" role="menuitem">Account Settings</a>
          <a href="../api/auth.php?action=logout" role="menuitem">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-profile">
      <img src="https://i.pravatar.cc/120?img=12" alt="Resident profile image">
      <div class="profile-meta label">
        <h3><?php echo htmlspecialchars($residentName); ?></h3>
        <p>Resident</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-group">
        <p class="group-title label">ACCOUNT</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_profile.php">
          <i class="fa-regular fa-user"></i>
          <span class="label">My Profile</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">MAIN</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_dashboard.php">
          <i class="fa-solid fa-gauge-high"></i>
          <span class="label">Dashboard</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">SERVICES</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>request_certificate.php">
          <i class="fa-regular fa-file-lines"></i>
          <span class="label">Request Certificate</span>
        </a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>my_requests.php">
          <i class="fa-solid fa-list-check"></i>
          <span class="label">My Requests</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">HOUSEHOLD</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_household.php">
          <i class="fa-solid fa-house-user"></i>
          <span class="label">Household Information</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">COMMUNITY</p>
        <a class="nav-item active" href="<?php echo BASE_URL; ?>resident_announcements.php">
          <i class="fa-regular fa-newspaper"></i>
          <span class="label">Announcements</span>
        </a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>complaints/my_complaints.php">
          <i class="fa-regular fa-comment-dots"></i>
          <span class="label">Complaints / Reports</span>
        </a>
      </div>
    </nav>

    <div class="sidebar-bottom">
      <a class="nav-item logout" href="../api/auth.php?action=logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span class="label">Logout</span>
      </a>
    </div>
  </aside>

  <main class="main-content" id="mainContent">
    <section class="announcement-view-container">
      <a href="resident_announcements.php" class="announcement-back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Announcements
      </a>

      <div id="announcementViewRoot" class="announcement-view-empty">
        Loading announcement...
      </div>
    </section>
  </main>

  <div id="imageLightbox" class="image-lightbox" aria-hidden="true">
    <div class="lightbox-content" role="dialog" aria-modal="true" aria-label="Announcement image preview">
      <button id="lightboxClose" class="lightbox-close" aria-label="Close full image">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <img id="lightboxImage" class="lightbox-image" src="" alt="Full announcement image">
      <span class="lightbox-hint">Press Esc or click outside to close</span>
    </div>
  </div>

  <script src="resident_dashboard.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.js')); ?>"></script>
  <script>
    (function () {
      const id = <?php echo (int)$announcementId; ?>;
      const root = document.getElementById('announcementViewRoot');
      const apiUrl = '/TeamPagal_Barangay219/Barangay219/api/announcements.php';
      const lightbox = document.getElementById('imageLightbox');
      const lightboxImage = document.getElementById('lightboxImage');
      const lightboxClose = document.getElementById('lightboxClose');

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
      }

      function parseDate(rawValue) {
        if (!rawValue) return null;
        const raw = String(rawValue).trim();
        const normalized = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw;
        const parsed = new Date(normalized);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
      }

      function formatDate(rawValue) {
        const parsed = parseDate(rawValue);
        if (!parsed) return 'Unknown Date';
        return parsed.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
      }

      function categoryClass(category) {
        const normalized = String(category || '').toLowerCase();
        if (normalized === 'event') return 'category-event';
        if (normalized === 'advisory') return 'category-advisory';
        if (normalized === 'emergency') return 'category-emergency';
        return 'category-general';
      }

      function resolveImagePath(rawPath) {
        if (!rawPath) return 'assets/default-announcement.jpg';
        const value = String(rawPath).trim();
        if (!value) return 'assets/default-announcement.jpg';
        if (/^https?:\/\//i.test(value)) return value;

        const normalized = value.replace(/\\/g, '/').replace(/^\/+/, '');
        if (normalized.startsWith('uploads/')) return `../${normalized}`;
        if (normalized.startsWith('public/')) return normalized.replace(/^public\//, '');
        return normalized;
      }

      async function incrementViews(announcementId) {
        try {
          const fd = new FormData();
          fd.append('action', 'increment-views');
          fd.append('id', announcementId);
          await fetch(apiUrl, { method: 'POST', body: fd });
        } catch (error) {
          console.error('Error incrementing views:', error);
        }
      }

      async function loadAnnouncement() {
        if (!id) {
          root.innerHTML = 'Announcement not found.';
          return;
        }

        try {
          const response = await fetch(`${apiUrl}?action=get&id=${encodeURIComponent(id)}`);
          const data = await response.json();

          if (!response.ok || !data.success || !data.data) {
            root.innerHTML = 'Announcement not found or no longer available.';
            return;
          }

          const a = data.data;
          const badgeClass = categoryClass(a.category);
          const imageSrc = resolveImagePath(a.image_path);

          root.className = 'announcement-view-card';
          root.innerHTML = `
            <img
              id="announcementMainImage"
              class="announcement-view-image"
              src="${escapeHtml(imageSrc)}"
              alt="${escapeHtml(a.title || 'Announcement image')}"
              loading="lazy"
              onerror="this.onerror=null;this.src='assets/default-announcement.jpg';"
            >
            <div class="announcement-view-body">
              <div class="announcement-meta">
                <span class="badge-category ${badgeClass}">${escapeHtml(a.category || 'General')}</span>
                <span class="announcement-date">Posted: ${formatDate(a.created_at)}</span>
              </div>
              <h1 class="announcement-view-title">${escapeHtml(a.title || 'Announcement')}</h1>
              <div class="announcement-view-content">${escapeHtml(a.content || '').replace(/\n/g, '<br>')}</div>
            </div>
          `;

          attachLightboxToMainImage();

          incrementViews(id);
        } catch (error) {
          console.error(error);
          root.innerHTML = 'Unable to load announcement details.';
        }
      }

      function openLightbox(imageSrc, imageAlt) {
        if (!lightbox || !lightboxImage) return;
        lightboxImage.src = imageSrc;
        lightboxImage.alt = imageAlt || 'Full announcement image';
        lightbox.classList.add('open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      }

      function closeLightbox() {
        if (!lightbox || !lightboxImage) return;
        lightbox.classList.remove('open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        lightboxImage.src = '';
      }

      function attachLightboxToMainImage() {
        const mainImage = document.getElementById('announcementMainImage');
        if (!mainImage) return;
        mainImage.addEventListener('click', () => {
          openLightbox(mainImage.getAttribute('src') || '', mainImage.getAttribute('alt') || 'Announcement image');
        });
      }

      if (lightboxClose) {
        lightboxClose.addEventListener('click', closeLightbox);
      }

      if (lightbox) {
        lightbox.addEventListener('click', (event) => {
          if (event.target === lightbox) {
            closeLightbox();
          }
        });
      }

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && lightbox && lightbox.classList.contains('open')) {
          closeLightbox();
        }
      });

      loadAnnouncement();
    })();
  </script>
</body>
</html>
