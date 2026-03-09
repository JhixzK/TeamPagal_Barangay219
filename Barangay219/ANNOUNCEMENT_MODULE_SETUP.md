# Announcement Module Implementation Guide

## Overview
The Resident-Facing Announcement Module is a complete backend and frontend solution for displaying barangay announcements to residents in the E-Barangay e-Services system.

## Components

### 1. Database Table
The `announcements` table already exists in the schema with the following structure:
```sql
CREATE TABLE `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `posted_by` INT(11) NOT NULL,
  `date_posted` DATE NOT NULL,
  `expiration_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posted_by` (`posted_by`),
  KEY `idx_status` (`status`),
  KEY `idx_date_posted` (`date_posted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Backend API Endpoint

**File:** `api/announcements.php`

**Security Features:**
- Requires user login via `requireLogin()`
- Residents have read-only access (no create/edit/delete)
- Only shows active announcements
- Filters out expired announcements automatically
- Limits results to 50 most recent announcements

**Available Actions:**

#### List Announcements
```
GET /api/announcements.php?action=list
```

**Response:**
```json
{
  "success": true,
  "message": "Announcements retrieved",
  "data": [
    {
      "id": 1,
      "title": "Community Clean-Up Drive",
      "content": "Join us on Saturday...",
      "date_posted": "2026-03-05",
      "expiration_date": null,
      "created_at": "2026-03-01 10:00:00",
      "updated_at": "2026-03-01 10:00:00"
    }
  ]
}
```

#### Get Single Announcement
```
GET /api/announcements.php?action=get&id=1
```

**Response:**
```json
{
  "success": true,
  "message": "Announcement retrieved",
  "data": {
    "id": 1,
    "title": "Community Clean-Up Drive",
    "content": "Join us on Saturday...",
    "date_posted": "2026-03-05",
    "expiration_date": null,
    "created_at": "2026-03-01 10:00:00",
    "updated_at": "2026-03-01 10:00:00"
  }
}
```

### 3. Frontend Components

#### JavaScript Module: `announcement-manager.js`

**Features:**
- Automatic loading of announcements on page load
- Display announcements with title, snippet, and posted date
- Click-to-expand modal for full announcement details
- XSS protection through HTML escaping
- Responsive design for mobile and desktop
- Empty state and error handling
- Modal with keyboard support (ESC to close)

**Usage:**
```html
<!-- Include the script in the page -->
<script src="announcement-manager.js"></script>

<!-- Add the announcement container -->
<div class="announcement-list">
  <!-- Announcements dynamically loaded here -->
</div>

<!-- Add the modal for viewing full announcements -->
<div id="announcementModal" class="modal" style="display: none;">
  <div class="modal-backdrop"></div>
  <div class="modal-content modal-lg">
    <div class="modal-header">
      <h2 class="modal-title">Announcement</h2>
      <button class="modal-close" aria-label="Close announcement">&times;</button>
    </div>
    <div class="modal-body">
      <p class="modal-announcement-date"></p>
      <div class="modal-announcement-content"></div>
    </div>
  </div>
</div>
```

#### CSS Styles: Added to `resident_dashboard.css`

Includes styles for:
- Announcement list items with hover effects
- Modal overlay and content
- Empty/error states
- Responsive design
- Accessibility features

### 4. Integration with Dashboard

The announcement module is integrated into `resident_dashboard.php`:
- Displays announcements in a dedicated panel
- Shows 50 most recent announcements with pagination via scroll
- Click any announcement to view full content in modal
- Automatic date formatting
- Snippet preview (first 100 characters)

## Setup Instructions

### 1. Verify Database
Ensure the `announcements` table exists in your database. If not, run:
```sql
CREATE TABLE `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `posted_by` INT(11) NOT NULL,
  `date_posted` DATE NOT NULL,
  `expiration_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posted_by` (`posted_by`),
  KEY `idx_status` (`status`),
  KEY `idx_date_posted` (`date_posted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Add Sample Announcements
```sql
INSERT INTO announcements (title, content, posted_by, date_posted, status)
VALUES 
  (
    'Community Clean-Up Drive',
    'Join us on Saturday at 7:00 AM for the monthly clean-up activity around Purok 3 and nearby streets.',
    1,
    CURDATE(),
    'active'
  ),
  (
    'Free Medical Check-Up',
    'Barangay health workers will conduct free blood pressure and consultation services at the covered court.',
    1,
    DATE_SUB(CURDATE(), INTERVAL 2 DAY),
    'active'
  );
```

### 3. Verify Files Exist
- `/api/announcements.php` - API endpoint
- `/public/announcement-manager.js` - Frontend module
- Update `/public/resident_dashboard.php` to include:
  - The announcement modal HTML
  - Script tag for `announcement-manager.js`
- Update `/public/resident_dashboard.css` to include announcement styles

### 4. Test the Implementation
1. Log in as a resident user
2. Navigate to the dashboard
3. Verify announcements load in the announcements panel
4. Click on an announcement to view full content in modal
5. Verify modal closes properly (click close button, backdrop, or ESC key)

## Security Considerations

1. **Authentication Required:** All API calls require user login
2. **Read-Only Access:** Residents cannot modify announcements
3. **Expiration Filtering:** Expired announcements are automatically hidden
4. **Status Filtering:** Only active announcements are displayed
5. **XSS Prevention:** All user-displayed content is HTML-escaped
6. **Session Hijacking Prevention:** Uses standard session security

## API Error Handling

The API returns appropriate HTTP status codes:
- `200 OK` - Success
- `400 Bad Request` - Missing/invalid parameters
- `404 Not Found` - Announcement doesn't exist
- `500 Internal Server Error` - Unexpected error

## Frontend Error Handling

The announcement manager handles:
- Network errors (displays error state)
- No announcements (displays empty state)
- Missing DOM elements (logs to console)
- User actions outside modal (ESC, backdrop click, close button)

## Performance Optimization

- Fetches only necessary fields from database
- Limits results to 50 announcements
- Filters expired announcements at SQL level
- Lazy loads full content only when user requests (modal)
- Caches announcements in JavaScript memory

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Future Enhancements

1. Search/filter functionality
2. Pagination for announcements
3. Category tags
4. Announcement scheduling
5. Read receipts
6. Push notifications
7. Announcement reactions/feedback

## Troubleshooting

### Announcements not loading
- Check browser console for errors
- Verify API endpoint is accessible: `/api/announcements.php?action=list`
- Ensure user is logged in
- Check database connection

### Modal not opening
- Verify modal HTML exists in page
- Check modal ID matches in JavaScript (`#announcementModal`)
- Look for JavaScript errors in console

### Styling issues
- Verify CSS file is loaded
- Check for CSS conflicts with other stylesheets
- Clear browser cache

## Files Modified/Created

### Created:
- `/api/announcements.php` - Resident API endpoint
- `/public/announcement-manager.js` - Frontend module

### Modified:
- `/public/resident_dashboard.php` - Added modal and script tag
- `/public/resident_dashboard.css` - Added announcement styles

## Database Maintenance

### Create Index (if not exists)
```sql
CREATE INDEX idx_status_date ON announcements(status, date_posted DESC);
CREATE INDEX idx_expiration ON announcements(expiration_date);
```

### Archive Old Announcements (Optional)
```sql
UPDATE announcements 
SET status = 'expired' 
WHERE expiration_date IS NOT NULL 
AND expiration_date < CURDATE();
```

## Support & Contact

For issues or feature requests, please contact the development team or refer to the system documentation.
