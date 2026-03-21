<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$announcementId = (int)($_GET['id'] ?? 0);
$page_title = 'Announcement Details';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>resident_dashboard.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.css')); ?>">
<style>
    .announcement-view-shell {
      max-width: 980px;
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

<div class="main-content module-page" id="mainContent">
  <div class="container-fluid">
    <div class="module-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
          <p class="module-kicker text-uppercase small mb-1">Resident Portal</p>
          <h2 class="mb-1"><i class="bi bi-megaphone me-2"></i>Announcement Details</h2>
          <p class="module-subtitle mb-0">Read the full community update and related information.</p>
        </div>
      </div>
    </div>

    <section class="announcement-view-shell">
      <a href="resident_announcements.php" class="announcement-back-link">
        <i class="bi bi-arrow-left"></i> Back to Announcements
      </a>

      <div id="announcementViewRoot" class="announcement-view-empty">
        Loading announcement...
      </div>
    </section>
  </div>
</div>

<div id="imageLightbox" class="image-lightbox" aria-hidden="true">
  <div class="lightbox-content" role="dialog" aria-modal="true" aria-label="Announcement image preview">
    <button id="lightboxClose" class="lightbox-close" aria-label="Close full image">
      <i class="bi bi-x-lg"></i>
    </button>
    <img id="lightboxImage" class="lightbox-image" src="" alt="Full announcement image">
    <span class="lightbox-hint">Press Esc or click outside to close</span>
  </div>
</div>

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

<?php include __DIR__ . '/../includes/footer.php'; ?>
