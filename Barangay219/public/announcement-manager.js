/**
 * E-Barangay Announcements Module
 * Handles loading, displaying, and interacting with announcements
 */

class AnnouncementManager {
  constructor() {
    this.announcements = [];
    this.apiUrl = '/TeamPagal_Barangay219/Barangay219/api/announcements.php';
    this.containerSelector = '.announcement-list';
    this.modalSelector = '#announcementModal';
    this.isLoading = false;
  }

  /**
   * Initialize the announcement manager
   * Attaches event listeners and loads announcements
   */
  init() {
    this.attachEventListeners();
    this.loadAnnouncements();
  }

  /**
   * Attach event listeners to modal and buttons
   */
  attachEventListeners() {
    // Close modal on close button
    const closeBtn = document.querySelector(`${this.modalSelector} .modal-close`);
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.closeModal());
    }

    // Close modal on backdrop click
    const backdrop = document.querySelector(`${this.modalSelector} .modal-backdrop`);
    if (backdrop) {
      backdrop.addEventListener('click', () => this.closeModal());
    }

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeModal();
      }
    });
  }

  /**
   * Load announcements from the API
   */
  async loadAnnouncements() {
    if (this.isLoading) return;
    
    this.isLoading = true;
    const container = document.querySelector(this.containerSelector);
    
    if (!container) {
      console.error(`Announcement container not found: ${this.containerSelector}`);
      return;
    }

    try {
      const response = await fetch(`${this.apiUrl}?action=list`);
      
      if (!response.ok) {
        throw new Error(`API Error: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success && data.data) {
        this.announcements = data.data;
        this.renderAnnouncements(data.data);
      } else {
        console.error('Failed to load announcements:', data.message);
        this.showEmptyState(container);
      }
    } catch (error) {
      console.error('Error loading announcements:', error);
      this.showErrorState(container, error.message);
    } finally {
      this.isLoading = false;
    }
  }

  /**
   * Render announcements in the container
   * @param {Array} announcements - Array of announcement objects
   */
  renderAnnouncements(announcements) {
    const container = document.querySelector(this.containerSelector);
    
    if (!container) return;

    // Clear existing content
    container.innerHTML = '';

    if (announcements.length === 0) {
      this.showEmptyState(container);
      return;
    }

    // Create announcement items
    announcements.forEach(announcement => {
      const item = this.createAnnouncementElement(announcement);
      container.appendChild(item);
    });
  }

  /**
   * Create a single announcement DOM element
   * @param {Object} announcement - Announcement data
   * @returns {HTMLElement} - Announcement item element
   */
  createAnnouncementElement(announcement) {
    const div = document.createElement('div');
    div.className = 'announcement-item clickable';
    div.setAttribute('data-id', announcement.id);
    
    // Format date
    const postDate = new Date(announcement.date_posted);
    const formattedDate = postDate.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });

    // Create snippet (first 100 characters)
    const snippet = this.getSnippet(announcement.content, 100);

    div.innerHTML = `
      <h4>${this.escapeHtml(announcement.title)}</h4>
      <p>${this.escapeHtml(snippet)}${snippet.length < announcement.content.length ? '...' : ''}</p>
      <span class="announcement-date">Posted: ${formattedDate}</span>
    `;

    // Add click event to open modal
    div.addEventListener('click', () => {
      this.openModal(announcement);
    });

    return div;
  }

  /**
   * Get a snippet of text up to a certain length
   * @param {string} text - Full text
   * @param {number} length - Desired length
   * @returns {string} - Truncated text
   */
  getSnippet(text, length = 100) {
    if (text.length <= length) {
      return text;
    }
    return text.substring(0, length).trim();
  }

  /**
   * Open the announcement detail modal
   * @param {Object} announcement - Announcement data
   */
  openModal(announcement) {
    const modal = document.querySelector(this.modalSelector);
    
    if (!modal) {
      console.error(`Modal not found: ${this.modalSelector}`);
      return;
    }

    // Format date
    const postDate = new Date(announcement.date_posted);
    const formattedDate = postDate.toLocaleDateString('en-US', {
      year: 'long',
      month: 'long',
      day: 'numeric'
    });

    // Update modal content
    const titleEl = modal.querySelector('.modal-title');
    const contentEl = modal.querySelector('.modal-announcement-content');
    const dateEl = modal.querySelector('.modal-announcement-date');

    if (titleEl) titleEl.textContent = announcement.title;
    if (contentEl) contentEl.innerHTML = this.escapeHtml(announcement.content).replace(/\n/g, '<br>');
    if (dateEl) dateEl.textContent = `Posted: ${formattedDate}`;

    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  /**
   * Close the announcement detail modal
   */
  closeModal() {
    const modal = document.querySelector(this.modalSelector);
    if (modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  }

  /**
   * Show empty state when no announcements exist
   * @param {HTMLElement} container - Container element
   */
  showEmptyState(container) {
    container.innerHTML = `
      <div class="announcement-empty">
        <i class="fa-regular fa-newspaper"></i>
        <p>No announcements at this time</p>
      </div>
    `;
  }

  /**
   * Show error state
   * @param {HTMLElement} container - Container element
   * @param {string} error - Error message
   */
  showErrorState(container, error) {
    container.innerHTML = `
      <div class="announcement-error">
        <i class="fa-solid fa-exclamation-circle"></i>
        <p>Unable to load announcements</p>
        <small>${this.escapeHtml(error)}</small>
      </div>
    `;
  }

  /**
   * Escape HTML special characters to prevent XSS
   * @param {string} text - Text to escape
   * @returns {string} - Escaped text
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    const announcementManager = new AnnouncementManager();
    announcementManager.init();
  });
} else {
  const announcementManager = new AnnouncementManager();
  announcementManager.init();
}
