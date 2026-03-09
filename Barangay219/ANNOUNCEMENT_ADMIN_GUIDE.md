# Announcement Module - Admin/Staff Management Guide

## Overview
This guide is for barangay officials and staff who need to create, edit, and manage announcements for residents.

## User Roles & Permissions

### Can Create/Edit/Delete Announcements:
- Barangay Captain
- Secretary
- Any user with `can_create`, `can_edit`, or `can_delete` permissions on the announcements module

### Can Only View Announcements:
- Residents (read-only access)
- SK Chairman (if permissions not explicitly granted for editing)

## Managing Announcements via API

### Create Announcement
```
POST /api/announcement.php
action=create
```

**Parameters:**
```json
{
  "title": "Community Clean-Up Drive",
  "content": "Join us on Saturday at 7:00 AM...",
  "date_posted": "2026-03-05",
  "expiration_date": "2026-03-31"
}
```

**Curl Example:**
```bash
curl -X POST "http://localhost/TeamPagal_Barangay219/Barangay219/api/announcement.php" \
  -d "action=create" \
  -d "title=Test Announcement" \
  -d "content=This is a test" \
  -d "date_posted=2026-03-10" \
  -H "Cookie: PHPSESSID=your_session_id"
```

### Update Announcement
```
POST /api/announcement.php
action=update
```

**Parameters:**
```json
{
  "id": 1,
  "title": "Updated Title",
  "content": "Updated content...",
  "status": "active"
}
```

### Delete Announcement
```
POST /api/announcement.php
action=delete
id=1
```

### List All Announcements (Admin View)
```
GET /api/announcement.php?action=list
```

Returns all announcements with poster information.

## Database Management (Direct SQL)

### Create Announcement
```sql
INSERT INTO announcements (
  title,
  content,
  posted_by,
  date_posted,
  expiration_date,
  status
) VALUES (
  'Community Event',
  'Details about the event...',
  1,
  '2026-03-10',
  '2026-03-31',
  'active'
);
```

### Update Announcement
```sql
UPDATE announcements
SET 
  title = 'Updated Title',
  content = 'Updated content',
  status = 'active'
WHERE id = 1;
```

### Change Status
```sql
UPDATE announcements
SET status = 'inactive'
WHERE id = 1;
```

### Bulk Expire Announcements
```sql
UPDATE announcements
SET status = 'expired'
WHERE expiration_date < CURDATE()
AND status = 'active';
```

### Delete Announcement
```sql
DELETE FROM announcements
WHERE id = 1;
```

## Best Practices

### 1. Announcement Content
- **Keep titles concise:** Max 100 characters for readability
- **Use clear language:** Avoid jargon; residents should understand immediately
- **Include key details:** Date, time, location, who should attend
- **Call-to-action:** Tell residents what to do (if applicable)

**Good Example:**
```
Title: Barangay Health Fair - March 15
Content:
Free medical consultations and health screening for all residents
Date: March 15, 2026
Time: 9:00 AM - 12:00 PM
Location: Barangay Hall, Tondo
No registration needed. Bring your ID.
```

**Poor Example:**
```
Title: Event
Content: We have an event
```

### 2. Frequency
- **Weekly:** 1-2 announcements optimal
- **During events:** Can increase to 3-5
- **Avoid spam:** Don't post redundant announcements

### 3. Expiration Dates
- **Set expiration for time-sensitive info:** Events, deadlines, applications
- **Leave blank for permanent announcements:** Policies, reminders
- **Review monthly:** Check for expired announcements that should be archived

### 4. Status Management
- **Active:** Currently visible to residents
- **Inactive:** Hidden but not deleted (archived)
- **Expired:** Automatically hidden when expiration date passes

### 5. Timing
- **Post early:** 1-2 weeks before event for maximum engagement
- **Reminders:** Post again 3-5 days before event
- **Post during work hours:** Residents more likely to see during business hours

## Sample Announcements to Create

### Use Case 1: Community Event
```
Title: Monthly Community Clean-Up (Every Saturday)
Content: Join us every Saturday morning at 7:00 AM for community service.
Meet at the barangay hall. Bring work gloves. Free refreshments provided.
Date Posted: 2026-03-10
Expiration: (Leave blank - recurring)
Status: Active
```

### Use Case 2: Time-Sensitive Deadline
```
Title: Scholarship Application Deadline Extended to March 31
Content: The deadline for scholarship applications has been extended to March 31, 2026.
Submit your requirements at the barangay office.
Requirements:
- Birth certificate
- Good moral character from school
- Proof of residency
- Recent grades
Date Posted: 2026-03-05
Expiration: 2026-03-31
Status: Active
```

### Use Case 3: Important Notice
```
Title: Barangay Hall Operating Hours Changed
Content: Effective March 15, 2026, the barangay hall will operate on new hours:
Monday-Friday: 8:00 AM - 5:00 PM
Saturday: 9:00 AM - 12:00 PM
Sunday: Closed

Certificate requests and other services available during these hours.
Date Posted: 2026-03-08
Expiration: (Leave blank)
Status: Active
```

## Moderation & Quality Assurance

### Before Publishing
- [ ] Check spelling and grammar
- [ ] Verify dates and times are correct
- [ ] Ensure contact information is accurate
- [ ] Confirm who needs to take action
- [ ] Check if expiration date is appropriate

### Monthly Review
- [ ] Archive expired announcements
- [ ] Review engagement (how many residents clicked)
- [ ] Update recurring announcements
- [ ] Remove duplicate information

### Resident Feedback
- Monitor resident inquiries about announcements
- Clarify confusing announcements
- Update announcements with additional information if needed

## Troubleshooting

### Announcement Not Showing
1. Check status is "active"
2. Verify expiration date is in future (or null)
3. Confirm `date_posted` is not in the future
4. Check user has `can_create` permission
5. Clear resident browser cache

### Display Issues
- Verify content doesn't contain unescaped HTML
- Check for very long text (break into paragraphs)
- Test on mobile devices
- Check for special characters that need encoding

### Permission Issues
```sql
-- Grant permissions to user
INSERT INTO role_permissions (role, module, action)
VALUES ('secretary', 'announcements', 'can_create');
VALUES ('secretary', 'announcements', 'can_edit');
VALUES ('secretary', 'announcements', 'can_delete');
```

## Security Considerations

### For Staff
- **Verify source:** Only authorized personnel create announcements
- **Review before publish:** Have second person review content
- **No sensitive data:** Never include resident personal information
- **Avoid promises:** Don't make commitments staff can't keep

### System Security
- All API calls require authentication
- Announcements are stored securely in database
- Content is HTML-escaped to prevent XSS
- Residents cannot modify announcements

## Analytics & Reporting

### Monitor Announcement Performance
```sql
-- Count total announcements
SELECT COUNT(*) as total FROM announcements;

-- Count active announcements
SELECT COUNT(*) as active FROM announcements WHERE status = 'active';

-- Get most recent
SELECT title, date_posted FROM announcements ORDER BY date_posted DESC LIMIT 10;

-- Find announcements by date range
SELECT title, date_posted FROM announcements 
WHERE date_posted BETWEEN '2026-01-01' AND '2026-03-31'
ORDER BY date_posted DESC;
```

## Training Resources for Staff

### Overview of Announcement System
1. Announcements are displayed on resident dashboard
2. Residents can click to view full details in modal
3. Announcements are sorted by most recent first
4. Only active, non-expired announcements show
5. Residents have read-only access

### Key Features
- **Easy to create:** Simple form with title, content, dates
- **Scheduled:** Set expiration date for time-sensitive info
- **Secure:** Only authorized staff can edit
- **Responsive:** Works on phones, tablets, desktop
- **Accessible:** Works for all residents regardless of tech skill

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Residents say they didn't see announcement | Post again as reminder, check expiration dates |
| Announcement text displays incorrectly | Use plain text, avoid special characters, test before publishing |
| Staff can't create announcements | Check user role has announcement permissions |
| Announcements disappear after deadline | This is expected; they're automatically hidden after expiration |
| Old announcements still showing | Update status to 'inactive' or set expiration date |

## Related Documentation

- [Announcement Module Setup](ANNOUNCEMENT_MODULE_SETUP.md)
- [Quick Reference Guide](ANNOUNCEMENT_QUICK_REFERENCE.md)
- [API Endpoint](api/announcement.php)
- [Database Schema](database/schema.sql)

## Support & Escalation

For technical issues:
1. Check database connection
2. Verify file permissions
3. Check error logs
4. Contact development team if needed

For content issues:
1. Review announcement guidelines
2. Edit and repost with corrections
3. Notify residents of changes via announcement
