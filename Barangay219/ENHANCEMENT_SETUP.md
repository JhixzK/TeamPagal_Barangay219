# E-Barangay System Enhancements - Setup Guide

## Overview

This document describes the enhancements made to the E-Barangay Management Information System and how to set them up.

## Database Migration (Required)

Before using the new features, run the enhancement migration to add new columns and tables:

1. **Option A - PHP Migration Script (Recommended)**
   - Visit: `http://localhost/TeamPagal_Barangay219/Barangay219/run-enhancement-migration.php`
   - This safely adds new columns and creates new tables
   - Skips steps that are already applied

2. **Option B - Manual SQL**
   - Run the SQL in `database/migration_enhancements.sql` via phpMyAdmin
   - Note: May fail if columns already exist

## New/Enhanced Modules

### 1. Dashboard Module
- **Real-time statistics**: Residents, households, issued certificates, pending applications, complaints, announcements
- **Charts**: Bar chart overview (Chart.js)
- **Recent activities**: User activity feed
- **Quick navigation**: Links to all modules

### 2. Applications Module (`applications.php`)
- Encode and manage certificate applications
- Auto-generated application reference numbers (APP-YYYY-NNNNN)
- Status tracking: Pending → Approved → Released / Rejected
- Links to resident records
- Create new applications by selecting resident and certificate type

### 3. Certificates Module (`certificates.php`)
- Certificate types: Barangay Clearance, Residency, Indigency, **Good Moral**, Transfer Request
- Control numbers for issued certificates (CTRL-YYYY-NNNNN)
- Issuance history in `certificates_issued` table
- **Print/PDF**: Use certificate-print.php?id=X and browser Print → Save as PDF

### 4. Complaints Module (`complaints.php`)
- Full CRUD with create, edit, view, delete
- Optional `resident_id` link to residents table
- Remarks field
- Status: pending, under_review, resolved, dismissed

### 5. Announcements Module (`announcement.php`)
- Create, edit, publish, archive
- Public API: `announcement.php?action=public_list` (no auth) for resident portal
- Status: active, inactive, archived

### 6. Reports Module (`reports.php`)
- Reports: Population, Certificates, Applications, Blotters, Complaints, Announcements
- **Date filtering**: From/To for all reports
- **Print/PDF**: Use browser Print → Save as PDF

### 7. Users Module (`users.php`)
- Role-based access (Barangay Captain, Secretary, Treasurer, Kagawad, SK Chairman)
- **Activity Logs**: View user actions (login, create, update, etc.)
- Secure authentication with session management

## New Database Tables/Columns

| Table | New Columns/Tables |
|-------|-------------------|
| certificate_requests | application_ref, control_number, remarks; certificate_type + good_moral |
| complaints | resident_id, remarks |
| announcements | status + 'archived' |
| activity_logs | New table - user activity audit |
| certificates_issued | New table - issuance history |

## Default Login

- **Username**: admin
- **Password**: admin123 (run fix-password.php if login fails)

## File Structure

- `run-enhancement-migration.php` - Run once to apply DB changes
- `public/certificate-print.php` - Printable certificate (opens in new tab)
- `public/applications.php` - Certificate applications (replaces resident registration applications)
- API enhancements in `api/certificates.php`, `api/complaints.php`, `api/announcement.php`, `api/reports.php`, `api/users.php`
