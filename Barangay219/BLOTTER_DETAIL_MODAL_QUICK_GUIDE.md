# Blotter Detail Modal - Quick Start Guide

## What's New? 🎉

The blotter case detail modal now includes powerful admin tools for case processing:

### **View Mode** (Default)
When you open a blotter case, you see all information in read-only format:
- Case details (title, date, type, location, description)
- Complainants and respondents  
- Current status and settlement date
- Admin notes (if any)
- Hearing history
- **NEW:** Complete audit trail showing all changes

### **Edit Mode** (Click "Edit/Process" button)
Switch to editing mode to process and update cases:

#### 1. **Status Update**
Change case status from:
- ✅ Pending → Investigation (for active investigation)
- ✅ Pending → Mediation (for mediation attempts)
- ✅ Any → Settled (when case is closed)
- ✅ Any → Dismissed (when case is dismissed)

**Result:** Status change logged with timestamp and admin name

#### 2. **Respondent Linking** 
Link a resident from your system as the respondent:
1. Click in the "Respondent" search field
2. Type resident's name or resident code
3. Choose from dropdown results
4. Click "Clear" to remove link

**Result:** Respondent ID saved for future reference and audit logged

#### 3. **Admin Notes**
Add processing notes (mediation outcomes, investigation findings, etc.):
1. Type in the "Admin Notes" text area
2. These notes persist with the record
3. Visible to all future viewers of this case

**Result:** Notes saved and audit logged

### **Saving Changes**
- Click "Save Changes" to apply updates
- Modal automatically refreshes to show updated data
- Audit log updated on View Mode
- Click "Cancel" to abandon changes without saving

---

## Step-by-Step Example

### Scenario: Case Under Investigation

1. **View the case** (opens in View Mode)
   - See: "Status: Pending" with complainant "John Doe" and respondent "Jane Smith"

2. **Click "Edit/Process"** button
   - Modal switches to edit form

3. **Change Status**
   - Status dropdown: Select "Investigation"

4. **Link Resident** (if Jane Smith is not already linked)
   - Search field: Type "jane smith"
   - Dropdown shows: "Smith, Jane (RES-00125) - Purok 3, Sampaga St"
   - Click to select
   - Respondent link established

5. **Add Notes**
   - Type: "Investigation started. Witness interviews scheduled for Monday."

6. **Click Save Changes**
   - Modal refreshes to View Mode
   - Status now shows: "Investigation"
   - Admin Notes shows: "Investigation started..."
   - Audit Log shows: 
     - 🔄 Status changed from Pending to Investigation
     - 🔗 Respondent linked to: Jane Smith
     - 📝 Notes updated

---

## Audit Trail Features

Every change is automatically tracked with:
- ✅ Admin who made the change (username)
- ✅ Exact timestamp of change
- ✅ Type of change (status/respondent/notes)
- ✅ What changed (old value → new value)
- ✅ Description of the change

**View the Trail:**
- Scroll to "Audit Log" section in View Mode
- Shows all changes (newest first)
- Use icons to identify change type:
  - 🔄 = Status Changed
  - 🔗 = Respondent Linked
  - 📝 = Notes Updated

---

## Tips & Tricks 💡

### Resident Search Tips
- Search by first name: "john"
- Search by last name: "smith"
- Search by resident code: "RES-00125"
- Partial matches work ("joh" finds "John")

### Status Workflow Best Practices
1. **Pending** → Used when case just filed
2. **Investigation** → When you're actively investigating
3. **Mediation** → When you're trying mediation
4. **Settled** → When parties have agreed to resolution
5. **Dismissed** → When case is closed without settlement

### Multi-step Processing
- Don't try to do everything at once
- Update status first, then link respondent, then add notes
- Save after each significant update
- Use admin notes to track progress

### Finding Cases
- Use the search bar to find cases by title/name/status
- Use status tabs to view: Pending, Resolved, Settled cases
- Use Filter button for date range searches

---

## Permissions Required

You must have:
- ✅ Login account (barangay official or authorized user)
- ✅ Access to Blotter module (configured in admin panel)
- ✅ "Can Edit" permission on Blotter module

If you don't see the "Edit/Process" button, check your permissions with your system administrator.

---

## Common Questions

**Q: What happens if I click Cancel?**
A: No changes are saved. Modal returns to View Mode with original data.

**Q: Can I edit the complainant or incident details?**
A: No, those are locked. Use Edit Mode only for status, respondent linking, and notes.

**Q: What if the resident I need isn't in the system?**
A: You'll need to add them to the residents database first, then link them.

**Q: Can I undo a change?**
A: Changes are permanent, but the audit trail shows what changed and when. Contact your administrator if you need to revert changes.

**Q: Are my notes private?**
A: No, all admin notes are visible to anyone who can access this blotter record.

**Q: When do audit logs expire?**
A: Audit logs are permanent for record-keeping purposes.

---

## Support

For issues or feature requests related to the blotter detail modal:
1. Document the exact error or issue
2. Note the case ID and timestamp
3. Contact your system administrator
4. Check the browser console (F12) for any error messages

---

**Version:** 1.0 | **Date:** March 24, 2026 | **Feature:** Blotter Detail Modal Enhancement
