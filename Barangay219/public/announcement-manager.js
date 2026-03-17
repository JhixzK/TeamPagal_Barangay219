/**
 * Resident announcement list manager.
 */
class AnnouncementManager {
  constructor() {
    this.announcements = [];
    this.apiUrl = '/TeamPagal_Barangay219/Barangay219/api/announcements.php';
    this.containerSelector = '.announcement-list';
    this.searchInput = '#searchInput';
    this.defaultImagePath = 'assets/default-announcement.jpg';
    this.isLoading = false;
  }

  init() {
    this.attachEventListeners();
    this.loadAnnouncements();
  }

  attachEventListeners() {
    const searchInput = document.querySelector(this.searchInput);
    if (searchInput) {
      let searchTimeout;
      searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => this.loadAnnouncements(), 250);
      });
    }

    document.addEventListener('click', (event) => {
      const button = event.target.closest('.read-more-btn');
      if (!button) return;

      const announcementId = button.dataset.id;
      if (!announcementId) return;

      this.handleReadMore(announcementId);
    });
  }

  async loadAnnouncements() {
    if (this.isLoading) return;

    const container = document.querySelector(this.containerSelector);
    if (!container) return;

    this.isLoading = true;
    try {
      const searchTerm = (document.querySelector(this.searchInput)?.value || '').trim();
      const params = new URLSearchParams({ action: 'list' });
      if (searchTerm) {
        params.append('q', searchTerm);
      }

      const response = await fetch(`${this.apiUrl}?${params.toString()}`);
      if (!response.ok) {
        throw new Error(`API Error ${response.status}`);
      }

      const payload = await response.json();
      if (!payload.success || !Array.isArray(payload.data)) {
        this.showEmptyState(container);
        return;
      }

      this.announcements = payload.data;
      this.renderAnnouncements(payload.data);
    } catch (error) {
      console.error('Error loading announcements:', error);
      this.showErrorState(container, error.message);
    } finally {
      this.isLoading = false;
    }
  }

  renderAnnouncements(announcements) {
    const container = document.querySelector(this.containerSelector);
    if (!container) return;

    if (!announcements.length) {
      this.showEmptyState(container);
      return;
    }

    container.innerHTML = announcements.map((announcement) => this.buildCardMarkup(announcement)).join('');
  }

  buildCardMarkup(announcement) {
    const imageUrl = this.getImageUrl(announcement.image_path);
    const categoryClass = this.getCategoryClass(announcement.category);
    const dateLabel = this.formatAnnouncementDate(announcement.created_at, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
    const preview = this.getSnippet(announcement.content || '', 150);
    const hasMore = (announcement.content || '').length > preview.length;

    const urgentBadge = announcement.priority === 'urgent'
      ? '<span class="badge text-bg-danger me-1"><i class="bi bi-exclamation-triangle me-1"></i>Urgent</span>'
      : '';
    const pinnedBadge = announcement.is_pinned
      ? '<span class="badge text-bg-warning me-1"><i class="bi bi-pin-angle me-1"></i>Pinned</span>'
      : '';

    return `
      <article class="announcement-card" data-id="${announcement.id}">
        <div class="announcement-image-wrap">
          <img
            class="announcement-card-image"
            src="${this.escapeHtml(imageUrl)}"
            alt="${this.escapeHtml(announcement.title || 'Announcement image')}"
            loading="lazy"
            onerror="this.onerror=null;this.src='assets/default-announcement.jpg';"
          >
        </div>
        <div class="announcement-card-body">
          <div class="announcement-card-header">
            <span class="badge ${this.getCategoryBadgeClass(announcement.category)}">${this.escapeHtml(announcement.category || 'General')}</span>
            <span class="announcement-date">${dateLabel}</span>
          </div>
          <div class="announcement-chip-row">${urgentBadge}${pinnedBadge}</div>
          <h3 class="announcement-card-title">${this.escapeHtml(announcement.title || 'Untitled')}</h3>
          <p class="announcement-card-preview">${this.escapeHtml(preview)}${hasMore ? '...' : ''}</p>
          <div class="announcement-card-footer">
            <button class="read-more-btn" data-id="${announcement.id}">Read More <span aria-hidden="true">→</span></button>
          </div>
        </div>
      </article>
    `;
  }

  handleReadMore(announcementId) {
    const encodedId = encodeURIComponent(String(announcementId));
    window.location.href = `announcement_view.php?id=${encodedId}`;
  }

  getSnippet(text, length) {
    const safeText = String(text || '');
    if (safeText.length <= length) return safeText;
    return safeText.substring(0, length).trim();
  }

  getImageUrl(rawPath) {
    if (!rawPath) {
      return this.defaultImagePath;
    }

    const value = String(rawPath).trim();
    if (!value) {
      return this.defaultImagePath;
    }

    if (/^https?:\/\//i.test(value)) {
      return value;
    }

    const normalized = value.replace(/\\/g, '/').replace(/^\/+/, '');
    if (normalized.startsWith('uploads/')) {
      return `../${normalized}`;
    }

    if (normalized.startsWith('public/')) {
      return normalized.replace(/^public\//, '');
    }

    return normalized;
  }

  showEmptyState(container) {
    container.innerHTML = `
      <div class="announcement-empty">
        <i class="bi bi-newspaper"></i>
        <p>No announcements available at the moment.</p>
      </div>
    `;
  }

  showErrorState(container, message) {
    container.innerHTML = `
      <div class="announcement-error">
        <i class="bi bi-exclamation-circle"></i>
        <p>Unable to load announcements.</p>
        <small>${this.escapeHtml(message || 'Unexpected error')}</small>
      </div>
    `;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }

  getCategoryClass(category) {
    const normalized = String(category || '').toLowerCase();
    if (normalized === 'event') return 'category-event';
    if (normalized === 'advisory') return 'category-advisory';
    if (normalized === 'emergency') return 'category-emergency';
    return 'category-general';
  }

  getCategoryBadgeClass(category) {
    const normalized = String(category || '').toLowerCase();
    if (normalized === 'event') return 'text-bg-success';
    if (normalized === 'advisory') return 'text-bg-primary';
    if (normalized === 'emergency') return 'text-bg-danger';
    return 'text-bg-secondary';
  }

  formatAnnouncementDate(rawValue, options) {
    const parsedDate = this.parseAnnouncementDate(rawValue);
    if (!parsedDate) return 'Unknown Date';
    return parsedDate.toLocaleDateString('en-US', options);
  }

  parseAnnouncementDate(rawValue) {
    if (!rawValue) return null;

    const asString = String(rawValue).trim();
    if (!asString) return null;

    const normalized = asString.includes(' ') && !asString.includes('T')
      ? asString.replace(' ', 'T')
      : asString;

    const parsed = new Date(normalized);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed;
    }

    const dateOnly = asString.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!dateOnly) return null;

    const [, y, m, d] = dateOnly;
    const fallback = new Date(Number(y), Number(m) - 1, Number(d));
    return Number.isNaN(fallback.getTime()) ? null : fallback;
  }
}

function injectAnnouncementStyles() {
  if (document.getElementById('announcement-styles')) return;

  const style = document.createElement('style');
  style.id = 'announcement-styles';
  style.textContent = `
    .announcement-card {
      display: flex;
      flex-direction: column;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid #e6ebf2;
      background: #fff;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      min-height: 100%;
    }

    .announcement-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.13);
    }

    .announcement-image-wrap {
      width: 100%;
      height: 180px;
      background: #f2f5fb;
      overflow: hidden;
    }

    .announcement-card-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .announcement-card-body {
      padding: 16px 18px 18px;
      display: flex;
      flex-direction: column;
      flex: 1;
    }

    .announcement-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
      flex-wrap: wrap;
    }

    .announcement-date {
      font-size: 12px;
      color: #64748b;
      font-weight: 500;
    }

    .badge-category {
      font-size: 11px;
      font-weight: 600;
      padding: 4px 9px;
      border-radius: 999px;
      text-transform: capitalize;
      letter-spacing: 0.01em;
    }

    .badge-category.category-general { background: #eceff3; color: #4b5563; }
    .badge-category.category-event { background: #e8f8ee; color: #1f7a3f; }
    .badge-category.category-advisory { background: #e8f0ff; color: #2553b8; }
    .badge-category.category-emergency { background: #fdecec; color: #b42318; }

    .announcement-chip-row {
      display: flex;
      gap: 8px;
      min-height: 24px;
      margin-bottom: 8px;
      flex-wrap: wrap;
    }

    .announcement-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 700;
      border-radius: 999px;
      padding: 4px 8px;
    }

    .announcement-pill.urgent { background: #fff1f1; color: #b42318; }
    .announcement-pill.pinned { background: #fff7e8; color: #b45309; }

    .announcement-card-title {
      font-size: 20px;
      line-height: 1.3;
      color: #0f172a;
      margin: 0 0 10px;
      font-weight: 700;
    }

    .announcement-card-preview {
      font-size: 14px;
      line-height: 1.55;
      color: #475569;
      margin: 0 0 16px;
      flex: 1;
    }

    .announcement-card-footer {
      display: flex;
      justify-content: flex-end;
    }

    .read-more-btn {
      border: none;
      background: transparent;
      color: #1d4ed8;
      font-size: 14px;
      font-weight: 600;
      padding: 6px 0;
      cursor: pointer;
    }

    .read-more-btn:hover {
      color: #1e40af;
      text-decoration: underline;
    }

    .announcement-empty,
    .announcement-error {
      grid-column: 1 / -1;
      text-align: center;
      border: 1px dashed #d3dbe8;
      border-radius: 12px;
      background: #fcfdff;
      padding: 48px 20px;
      color: #94a3b8;
    }

    .announcement-empty i,
    .announcement-error i {
      font-size: 40px;
      margin-bottom: 10px;
      color: #c3cddd;
    }

    .announcement-empty p,
    .announcement-error p {
      margin: 0;
      font-size: 16px;
      color: #64748b;
    }
  `;

  document.head.appendChild(style);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    injectAnnouncementStyles();
    const manager = new AnnouncementManager();
    manager.init();
  });
} else {
  injectAnnouncementStyles();
  const manager = new AnnouncementManager();
  manager.init();
}
