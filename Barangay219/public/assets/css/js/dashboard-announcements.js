/**
 * Dashboard Announcements Widget
 * Displays pinned + latest 2 announcements on the resident dashboard
 */

(function() {
  'use strict';

  const API_URL = '/TeamPagal_Barangay219/Barangay219/api/announcements.php';
  const CONTAINER_ID = 'dashboardAnnouncementsContainer';

  /**
   * Load announcements for dashboard
   */
  async function loadDashboardAnnouncements() {
    try {
      const response = await fetch(`${API_URL}?action=list`);
      const data = await response.json();

      if (!data.success || !data.data) {
        showEmptyState();
        return;
      }

      // Announcements are already sorted by is_pinned DESC, then created_at DESC
      // Take only the pinned one (if exists) + 2 latest
      const announcements = data.data;
      let pinnedAnnouncement = null;
      let mainAnnouncements = [];

      announcements.forEach(ann => {
        if (ann.is_pinned && !pinnedAnnouncement) {
          pinnedAnnouncement = ann;
        } else if (!ann.is_pinned && mainAnnouncements.length < 2) {
          mainAnnouncements.push(ann);
        }
      });

      // If no non-pinned announcements, take from pinned as fallback
      if (mainAnnouncements.length < 2 && pinnedAnnouncement && announcements.length > 1) {
        announcements.forEach(ann => {
          if (!ann.is_pinned && mainAnnouncements.length < 2) {
            mainAnnouncements.push(ann);
          }
        });
      }

      renderAnnouncements(pinnedAnnouncement, mainAnnouncements);
    } catch (error) {
      console.error('Error loading dashboard announcements:', error);
      showErrorState();
    }
  }

  /**
   * Render announcements in the dashboard widget
   */
  function renderAnnouncements(pinned, latest) {
    const container = document.getElementById(CONTAINER_ID);
    if (!container) return;

    let html = '';

    // Render pinned announcement if exists
    if (pinned) {
      html += renderAnnouncementCard(pinned, true);
    }

    // Render latest announcements
    if (latest && latest.length > 0) {
      latest.forEach(ann => {
        html += renderAnnouncementCard(ann, false);
      });
    } else if (!pinned) {
      showEmptyState();
      return;
    }

    container.innerHTML = html;
  }

  /**
   * Render a single announcement card
   */
  function renderAnnouncementCard(announcement, isPinned) {
    const dateStr = formatAnnouncementDate(announcement.created_at, {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });

    const snippet = announcement.content.substring(0, 100);
    const hasMore = announcement.content.length > 100;

    let html = '<div class="announcement-dashboard-card';
    if (isPinned) html += ' pinned-card';
    if (announcement.priority === 'urgent') html += ' urgent-card';
    html += '">';

    // Header with badges
    html += '<div class="announcement-dashboard-header">';

    if (isPinned) {
      html += '<span class="badge-pinned"><i class="fa-solid fa-thumbtack"></i> Pinned</span>';
    }

    if (announcement.priority === 'urgent') {
      html += '<span class="badge-urgent"><i class="fa-solid fa-exclamation-triangle"></i> Urgent</span>';
    }

    html += '</div>';

    // Title
    html += `<h4 class="announcement-dashboard-title">${escapeHtml(announcement.title)}</h4>`;

    // Meta
    html += `<div class="announcement-dashboard-meta">
              <span class="badge-category">${escapeHtml(announcement.category || 'General')}</span>
              <span class="announcement-dashboard-date">${dateStr}</span>
            </div>`;

    // Preview
    html += `<p class="announcement-dashboard-preview">${escapeHtml(snippet)}${hasMore ? '...' : ''}</p>`;

    // Link to full announcements page
    html += `<a href="resident_announcements.php" class="announcement-dashboard-link">Read more →</a>`;

    html += '</div>';

    return html;
  }

  /**
   * Show empty state
   */
  function showEmptyState() {
    const container = document.getElementById(CONTAINER_ID);
    if (!container) return;

    container.innerHTML = `
      <div class="announcement-dashboard-empty">
        <i class="fa-regular fa-inbox"></i>
        <p>No announcements at this time</p>
        <a href="resident_announcements.php" class="btn-small">View all announcements</a>
      </div>
    `;
  }

  /**
   * Show error state
   */
  function showErrorState() {
    const container = document.getElementById(CONTAINER_ID);
    if (!container) return;

    container.innerHTML = `
      <div class="announcement-dashboard-error">
        <i class="fa-solid fa-exclamation-circle"></i>
        <p>Unable to load announcements</p>
      </div>
    `;
  }

  /**
   * Escape HTML
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Parse and format announcement dates safely across browsers.
   */
  function formatAnnouncementDate(rawValue, options) {
    const parsedDate = parseAnnouncementDate(rawValue);
    if (!parsedDate) {
      return 'Unknown Date';
    }

    return parsedDate.toLocaleDateString('en-US', options);
  }

  /**
   * Handle MySQL DATETIME/TIMESTAMP strings such as "YYYY-MM-DD HH:MM:SS".
   */
  function parseAnnouncementDate(rawValue) {
    if (!rawValue) {
      return null;
    }

    if (typeof rawValue === 'number') {
      const fromNumber = new Date(rawValue);
      return Number.isNaN(fromNumber.getTime()) ? null : fromNumber;
    }

    const asString = String(rawValue).trim();
    if (!asString) {
      return null;
    }

    const normalized = asString.includes(' ') && !asString.includes('T')
      ? asString.replace(' ', 'T')
      : asString;

    const parsed = new Date(normalized);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed;
    }

    const dateOnlyMatch = asString.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (dateOnlyMatch) {
      const [, y, m, d] = dateOnlyMatch;
      const fallback = new Date(Number(y), Number(m) - 1, Number(d));
      return Number.isNaN(fallback.getTime()) ? null : fallback;
    }

    return null;
  }

  /**
   * Inject CSS styles
   */
  function injectStyles() {
    if (document.getElementById('dashboard-announcements-styles')) return;

    const style = document.createElement('style');
    style.id = 'dashboard-announcements-styles';
    style.textContent = `
      .announcements-widget {
        margin-top: 24px;
      }

      .view-all-link {
        color: var(--blue-800, #2d53b9);
        font-size: 14px;
        text-decoration: none;
        font-weight: 500;
      }

      .view-all-link:hover {
        text-decoration: underline;
      }

      .announcements-list-dashboard {
        display: flex;
        flex-direction: column;
        gap: 12px;
      }

      .loading-placeholder {
        text-align: center;
        padding: 40px 20px;
        color: #999;
      }

      .announcement-dashboard-card {
        background: #f9f9f9;
        border-left: 4px solid #ddd;
        border-radius: 4px;
        padding: 16px;
        transition: all 0.2s ease;
      }

      .announcement-dashboard-card:hover {
        background: #f5f5f5;
        border-left-color: var(--blue-800, #2d53b9);
      }

      .announcement-dashboard-card.pinned-card {
        border-left-color: #ff9800;
        background: #fffde7;
      }

      .announcement-dashboard-card.urgent-card {
        border-left-color: #d32f2f;
        background: #ffebee;
      }

      .announcement-dashboard-header {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        flex-wrap: wrap;
      }

      .badge-pinned {
        background: #ff9800;
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 6px;
        border-radius: 3px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
      }

      .badge-pinned i {
        font-size: 9px;
      }

      .badge-urgent {
        background: #d32f2f;
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 6px;
        border-radius: 3px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
      }

      .badge-urgent i {
        font-size: 9px;
      }

      .badge-category {
        background: #e3f2fd;
        color: var(--blue-800, #2d53b9);
        font-size: 11px;
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 3px;
        white-space: nowrap;
      }

      .announcement-dashboard-title {
        margin: 0 0 8px 0;
        font-size: 15px;
        font-weight: 600;
        color: #1a1a1a;
        line-height: 1.4;
      }

      .announcement-dashboard-meta {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-bottom: 8px;
        flex-wrap: wrap;
      }

      .announcement-dashboard-date {
        font-size: 12px;
        color: #666;
      }

      .announcement-dashboard-preview {
        margin: 0 0 8px 0;
        font-size: 14px;
        color: #555;
        line-height: 1.4;
      }

      .announcement-dashboard-link {
        color: var(--blue-800, #2d53b9);
        font-weight: 500;
        font-size: 13px;
        text-decoration: none;
      }

      .announcement-dashboard-link:hover {
        text-decoration: underline;
      }

      .announcement-dashboard-empty,
      .announcement-dashboard-error {
        text-align: center;
        padding: 40px 20px;
        color: #999;
      }

      .announcement-dashboard-empty i,
      .announcement-dashboard-error i {
        font-size: 36px;
        margin-bottom: 12px;
        color: #ddd;
      }

      .announcement-dashboard-empty p,
      .announcement-dashboard-error p {
        margin: 0 0 12px 0;
        font-size: 14px;
      }

      .btn-small {
        display: inline-block;
        background: var(--blue-800, #2d53b9);
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
      }

      .btn-small:hover {
        background: var(--blue-900, #1e3a7f);
      }
    `;

    document.head.appendChild(style);
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      injectStyles();
      loadDashboardAnnouncements();
    });
  } else {
    injectStyles();
    loadDashboardAnnouncements();
  }
})();
