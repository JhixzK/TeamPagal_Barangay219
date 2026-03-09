# Announcement Module - Quick Reference & Code Examples

## Quick Setup Checklist

- [x] Database table `announcements` exists
- [x] API endpoint `/api/announcements.php` created
- [x] Frontend module `announcement-manager.js` created
- [x] Styles added to `resident_dashboard.css`
- [x] Modal added to `resident_dashboard.php`
- [ ] Test API endpoint with sample data
- [ ] Verify announcements display on dashboard
- [ ] Test modal functionality

## Code Examples for Developers

### 1. API Endpoint Usage

#### Using cURL
```bash
# List all active announcements
curl -X GET "http://localhost/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=list" \
  -H "Cookie: PHPSESSID=your_session_id"

# Get single announcement
curl -X GET "http://localhost/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=get&id=1" \
  -H "Cookie: PHPSESSID=your_session_id"
```

#### Using JavaScript Fetch
```javascript
// List announcements
fetch('/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=list')
  .then(response => response.json())
  .then(data => {
    console.log('Announcements:', data.data);
  })
  .catch(error => console.error('Error:', error));

// Get single announcement
fetch('/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=get&id=1')
  .then(response => response.json())
  .then(data => {
    console.log('Announcement:', data.data);
  })
  .catch(error => console.error('Error:', error));
```

#### Using PHP
```php
<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

requireLogin();

try {
    $db = Database::getInstance();
    
    // Get all active announcements
    $sql = "SELECT id, title, content, date_posted 
            FROM announcements 
            WHERE status = 'active' 
            AND (expiration_date IS NULL OR expiration_date >= CURDATE()) 
            ORDER BY date_posted DESC";
    
    $announcements = $db->fetchAll($sql);
    
    foreach ($announcements as $announcement) {
        echo $announcement['title'] . ': ' . $announcement['content'];
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

### 2. Frontend Module Usage

#### Basic Integration
```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="resident_dashboard.css">
</head>
<body>
    <!-- Announcement container -->
    <div class="announcement-list">
        <!-- Announcements loaded here -->
    </div>

    <!-- Announcement modal -->
    <div id="announcementModal" class="modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 class="modal-title">Announcement</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-announcement-date"></p>
                <div class="modal-announcement-content"></div>
            </div>
        </div>
    </div>

    <script src="announcement-manager.js"></script>
</body>
</html>
```

#### Advanced: Custom Configuration
```javascript
// Create custom instance with different container
class CustomAnnouncementManager extends AnnouncementManager {
  constructor() {
    super();
    this.containerSelector = '#custom-announcements';
    this.apiUrl = '/custom/api/announcements.php';
    this.maxItems = 10; // Show only 10 announcements
  }

  renderAnnouncements(announcements) {
    // Custom rendering logic
    super.renderAnnouncements(announcements.slice(0, this.maxItems));
  }
}

// Initialize
const customManager = new CustomAnnouncementManager();
customManager.init();
```

### 3. Database Queries

#### Add Announcement (Admin Only)
```sql
INSERT INTO announcements (title, content, posted_by, date_posted, status)
VALUES (
  'Test Announcement',
  'This is a test announcement content.',
  1,
  CURDATE(),
  'active'
);
```

#### Get Active Announcements
```sql
SELECT 
  id,
  title,
  content,
  date_posted,
  expiration_date
FROM announcements
WHERE status = 'active'
AND (expiration_date IS NULL OR expiration_date >= CURDATE())
ORDER BY date_posted DESC
LIMIT 50;
```

#### Count Active Announcements
```sql
SELECT COUNT(*) as total_active
FROM announcements
WHERE status = 'active'
AND (expiration_date IS NULL OR expiration_date >= CURDATE());
```

#### Archive Expired Announcements
```sql
UPDATE announcements
SET status = 'expired'
WHERE expiration_date IS NOT NULL
AND expiration_date < CURDATE()
AND status = 'active';
```

### 4. HTML Elements Reference

#### Announcement Item (Auto-generated)
```html
<div class="announcement-item clickable" data-id="1">
    <h4>Announcement Title</h4>
    <p>Announcement snippet text...</p>
    <span class="announcement-date">Posted: Mar 05, 2026</span>
</div>
```

#### Modal Container
```html
<div id="announcementModal" class="modal">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title">Announcement</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-announcement-date">Posted: March 05, 2026</p>
            <div class="modal-announcement-content">Full announcement content</div>
        </div>
    </div>
</div>
```

### 5. CSS Classes Reference

| Class | Purpose |
|-------|---------|
| `.announcement-list` | Container for announcements |
| `.announcement-item` | Individual announcement item |
| `.announcement-item.clickable` | Clickable announcement (hover effects) |
| `.announcement-date` | Posted date styling |
| `.announcement-empty` | Empty state styling |
| `.announcement-error` | Error state styling |
| `.modal` | Modal overlay container |
| `.modal-content` | Modal content box |
| `.modal-lg` | Large modal variant |
| `.modal-header` | Modal header section |
| `.modal-body` | Modal body section |
| `.modal-title` | Modal title styling |
| `.modal-close` | Close button |
| `.modal-backdrop` | Click-to-close overlay |

### 6. JavaScript API Reference

#### AnnouncementManager Class

**Constructor:**
```javascript
const manager = new AnnouncementManager();
```

**Methods:**

- `init()` - Initialize manager and load announcements
- `loadAnnouncements()` - Async fetch and display announcements
- `renderAnnouncements(announcements)` - Render announcement list
- `createAnnouncementElement(announcement)` - Create single item element
- `openModal(announcement)` - Open detail modal
- `closeModal()` - Close detail modal
- `attachEventListeners()` - Attach DOM event listeners
- `escapeHtml(text)` - Escape HTML to prevent XSS
- `getSnippet(text, length)` - Get text snippet

**Properties:**

- `announcements` - Array of announcement objects
- `apiUrl` - API endpoint URL
- `containerSelector` - Container element selector
- `modalSelector` - Modal element selector
- `isLoading` - Loading state flag

### 7. Event Flow Diagram

```
Page Load
    ↓
DOMContentLoaded
    ↓
AnnouncementManager.init()
    ↓
attachEventListeners()
loadAnnouncements() [API Call]
    ↓
fetch(/api/announcements.php?action=list)
    ↓
renderAnnouncements()
    ├─→ For each announcement:
    │   ├─→ createAnnouncementElement()
    │   └─→ attachClickListener()
    ↓
User clicks announcement
    ↓
openModal()
    ├─→ Fetch full content (already loaded)
    └─→ Display in modal
    ↓
User closes modal (click close, backdrop, or ESC)
    ↓
closeModal()
```

## Testing Checklist

### Backend API Tests
```javascript
// Test 1: List endpoint
fetch('/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=list')
  .then(r => r.json())
  .then(d => console.assert(d.success && Array.isArray(d.data), 'List test failed'));

// Test 2: Get endpoint with valid ID
fetch('/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=get&id=1')
  .then(r => r.json())
  .then(d => console.assert(d.success && d.data.id === 1, 'Get test failed'));

// Test 3: Invalid ID should return 404
fetch('/TeamPagal_Barangay219/Barangay219/api/announcements.php?action=get&id=99999')
  .then(r => r.json())
  .then(d => console.assert(!d.success && r.status === 404, 'Error handling failed'));
```

### Frontend Tests
```javascript
// Test 4: Manager initialization
const manager = new AnnouncementManager();
manager.init();
console.assert(manager.announcements.length > 0, 'Announcements not loaded');

// Test 5: Modal functionality
const testAnnouncement = manager.announcements[0];
manager.openModal(testAnnouncement);
const modal = document.querySelector('#announcementModal');
console.assert(modal.style.display === 'flex', 'Modal not opened');

// Test 6: Modal closing
manager.closeModal();
console.assert(modal.style.display === 'none', 'Modal not closed');

// Test 7: XSS prevention
const xssText = '<img src=x onerror="alert(1)">';
const escaped = manager.escapeHtml(xssText);
console.assert(!escaped.includes('onerror'), 'XSS prevention failed');
```

## Performance Metrics

- **API Response Time:** < 500ms
- **Frontend Render Time:** < 100ms
- **Modal Open Time:** < 50ms
- **Bundle Size:** ~4KB (minified)

## Migration Guide (If Updating)

If updating from previous version:

1. Backup database
2. Run: `ALTER TABLE announcements ADD COLUMN status ENUM(...)`
3. Clear browser cache
4. Invalidate CDN cache
5. Test thoroughly before production

## Support Resources

- API Documentation: See ANNOUNCEMENT_MODULE_SETUP.md
- Code Examples: This file
- Database Schema: /database/schema.sql
- Frontend Module: /public/announcement-manager.js
- API Endpoint: /api/announcements.php
