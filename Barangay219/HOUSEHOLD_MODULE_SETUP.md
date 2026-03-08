# Resident Household Information Module - Setup Guide

## Overview
This module enables residents to view and manage their household information including household details, family members, and statistics through the resident portal.

## Features
- ✅ Display complete household details (ID, head of household, address)
- ✅ Show all household members in a detailed table
- ✅ Add, edit, and delete household members
- ✅ Auto-calculate household statistics (total members, adults, minors, seniors)
- ✅ Emergency contact information
- ✅ Special notes for households
- ✅ Form validation (dates, phone numbers, IDs, emails)
- ✅ Responsive design matching existing resident portal
- ✅ Full CRUD operations via API

## Files Created

### Database
- `database/migrations/002_household_module_enhancement.sql` - Database migration for household_members table and extended household fields

### Frontend
- `public/resident_household.php` - Main household information page
- `public/resident_household.css` - Stylesheet for household module
- `public/resident_household.js` - JavaScript for CRUD operations and validation

### Backend
- `api/households.php` - Enhanced with new endpoints for household members management

## Installation Steps

### Step 1: Run Database Migration
Execute the migration script to create the household_members table and add new fields to households table:

```bash
# Using MySQL command line
mysql -u root -p barangay219 < database/migrations/002_household_module_enhancement.sql

# OR using phpMyAdmin
# 1. Open phpMyAdmin
# 2. Select the barangay219 database
# 3. Go to Import tab
# 4. Choose file: database/migrations/002_household_module_enhancement.sql
# 5. Click "Go" to execute
```

**Alternative: Manual SQL Execution**
```sql
-- Run this SQL in your MySQL client
SOURCE C:/xampp/htdocs/TeamPagal_Barangay219/Barangay219/database/migrations/002_household_module_enhancement.sql;
```

### Step 2: Verify Database Changes
Check that the following changes were applied:

```sql
-- Check if household_members table exists
SHOW TABLES LIKE 'household_members';

-- Check new columns in households table
DESCRIBE households;

-- Should see: house_number, street, purok_sitio, barangay, city, province, 
-- postal_code, emergency_contact_name, emergency_contact_phone, special_notes,
-- number_of_adults, number_of_minors, number_of_seniors
```

### Step 3: Access the Module
1. Login as a resident user
2. Navigate to the sidebar menu
3. Click on "Household Information" under the HOUSEHOLD section
4. View and manage your household details

## Usage Guide

### For Residents

#### Viewing Household Information
- Navigate to **Household Information** from the sidebar
- View household details including:
  - Household ID and registration date
  - Head of household information
  - Complete address details
  - Emergency contact information
  - Special notes

#### Managing Household Members
1. **Adding a Member:**
   - Click "Add Member" button
   - Fill in all required fields (marked with *)
   - Required: First Name, Last Name, Relationship, DOB, Gender, Civil Status, Voter Status
   - Click "Save Member"

2. **Editing a Member:**
   - Click the edit icon (pencil) next to the member
   - Update the information
   - Click "Save Member"

3. **Deleting a Member:**
   - Click the delete icon (trash) next to the member
   - Confirm the deletion
   - Note: Cannot delete the household head

#### Editing Household Details
- Click "Edit Details" button in the household panel
- Update address, emergency contact, or special notes
- Click "Save Changes"

### Field Validations

#### Required Fields
- First Name, Last Name
- Relationship to Head
- Date of Birth
- Gender
- Civil Status
- Voter Status

#### Format Validations
- **Phone Number:** 09XX-XXX-XXXX or +639XXXXXXXXX
- **Email:** Valid email format (user@example.com)
- **Date of Birth:** Cannot be future date, max 150 years ago
- **Voter ID:** Required if voter status is "Registered"

#### Auto-Calculations
- Age is automatically calculated from date of birth
- Voter status auto-sets to "N/A" for those under 18
- Household statistics update automatically after adding/editing/deleting members

## API Endpoints

### Household Member Operations

#### Get Member Details
```
GET /api/households.php?action=get_member&id={member_id}
```

#### Add Member
```
POST /api/households.php
action=add_member
+ all member fields
```

#### Update Member
```
POST /api/households.php
action=update_member
member_id={id}
+ all member fields
```

#### Delete Member
```
POST /api/households.php
action=delete_member
member_id={id}
household_id={household_id}
```

#### Update Household Details
```
POST /api/households.php
action=update_household_details
household_id={id}
+ address and contact fields
```

#### Get Household Members
```
GET /api/households.php?action=members&id={household_id}
```

## Database Schema

### household_members Table
```sql
CREATE TABLE `household_members` (
  `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `household_id` INT(11) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100),
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10),
  `relationship_to_head` VARCHAR(50) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `age` INT(11) GENERATED ALWAYS AS (TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) STORED,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `civil_status` ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT 'Single',
  `occupation` VARCHAR(100),
  `government_id_type` VARCHAR(50),
  `government_id_number` VARCHAR(100),
  `voter_status` ENUM('Registered', 'Not Registered', 'N/A') DEFAULT 'Not Registered',
  `voter_id_number` VARCHAR(50),
  `contact_number` VARCHAR(20),
  `email` VARCHAR(100),
  `is_head` TINYINT(1) DEFAULT 0,
  `is_senior_citizen` TINYINT(1) DEFAULT 0,
  `is_pwd` TINYINT(1) DEFAULT 0,
  `is_4ps_beneficiary` TINYINT(1) DEFAULT 0,
  `remarks` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
);
```

### Extended households Table Fields
- `house_number` VARCHAR(50)
- `street` VARCHAR(100)
- `purok_sitio` VARCHAR(80)
- `barangay` VARCHAR(100) DEFAULT 'Barangay 219'
- `city` VARCHAR(100) DEFAULT 'Manila'
- `province` VARCHAR(100) DEFAULT 'Metro Manila'
- `postal_code` VARCHAR(10) DEFAULT '1013'
- `emergency_contact_name` VARCHAR(150)
- `emergency_contact_phone` VARCHAR(20)
- `special_notes` TEXT
- `number_of_adults` INT(11) DEFAULT 0
- `number_of_minors` INT(11) DEFAULT 0
- `number_of_seniors` INT(11) DEFAULT 0

## Security Features

### Authentication
- Requires resident login
- Session validation on each page load
- Role-based access control

### Data Validation
- Server-side input sanitization
- Client-side form validation
- SQL injection prevention via prepared statements
- XSS protection via htmlspecialchars()

### Authorization
- Residents can only view/edit their own household
- Household head cannot be deleted
- All modifications logged with timestamps

## Troubleshooting

### Common Issues

1. **"No Household Record Found" message:**
   - Resident is not linked to a household
   - Contact barangay office to create/link household record

2. **Cannot add member - validation errors:**
   - Check all required fields are filled
   - Verify date format (YYYY-MM-DD)
   - Verify phone number format (09XX-XXX-XXXX)

3. **Statistics not updating:**
   - Clear browser cache
   - Refresh the page
   - Check database connection

4. **Database errors:**
   - Verify migration was run successfully
   - Check MySQL user permissions
   - Review error logs in browser console

### Debug Mode
Enable PHP error reporting in development:
```php
// In config/database.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Responsive Breakpoints
- Desktop: > 768px
- Tablet: 481px - 768px
- Mobile: < 480px

## Future Enhancements
- [ ] Export household data to PDF
- [ ] Print household certificate
- [ ] Bulk import members from CSV
- [ ] Photo upload for members
- [ ] Document attachments (IDs, certificates)
- [ ] Household history/audit trail
- [ ] Email notifications for changes
- [ ] QR code for household verification

## Support
For issues or questions:
- Contact Barangay IT Administrator
- Email: support@barangay219.gov.ph
- Phone: (02) 1234-5678

## Version History
- **v1.0.0** (March 8, 2026) - Initial release
  - Household information display
  - Member CRUD operations
  - Auto-calculated statistics
  - Form validation
  - Responsive design

---

**Developer Notes:**
- All dates stored in MySQL DATE format (YYYY-MM-DD)
- Age calculation uses MySQL TIMESTAMPDIFF function
- Phone numbers stored with formatting
- Statistics auto-update via trigger function
- Modal forms use class-based show/hide (not display:none inline)
