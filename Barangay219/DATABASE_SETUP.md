# Database Setup Guide

## Quick Setup Steps

1. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache
   - Start MySQL

2. **Create the Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "New" to create a new database
   - Database name: `ebarangay_db`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"

3. **Import Database Schema**
   - In phpMyAdmin, select the `ebarangay_db` database
   - Click "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

4. **Import Seed Data**
   - Still in phpMyAdmin with `ebarangay_db` selected
   - Click "Import" tab again
   - Choose file: `database/seeds.sql`
   - Click "Go"

5. **Fix Password Hash (if needed)**
   - If login doesn't work, access: http://localhost/e-barangay-system/e-barangay-system/fix-password.php
   - Copy the generated SQL
   - Run it in phpMyAdmin SQL tab

## Default Login Credentials

- **Username:** `admin`
- **Password:** `admin123`

## Database Configuration

The database configuration is in `config/constants.php`:
- Host: `localhost`
- Database: `ebarangay_db`
- User: `root`
- Password: `` (empty - default XAMPP)

If your MySQL has a password, update `DB_PASS` in `config/constants.php`.

## Troubleshooting

### Can't connect to database
1. Make sure MySQL is running in XAMPP
2. Check database name matches: `ebarangay_db`
3. Verify credentials in `config/constants.php`

### Login fails with "Invalid username or password"
1. Run the test script: http://localhost/e-barangay-system/e-barangay-system/test-db.php
2. Check if admin user exists in database
3. Regenerate password hash using `fix-password.php`
4. Update the password in database using the generated SQL

### Password hash issues
- Access `fix-password.php` in your browser
- Copy the generated SQL
- Run it in phpMyAdmin

## Testing Database Connection

Access: http://localhost/e-barangay-system/e-barangay-system/test-db.php

This will show:
- Database connection status
- Table existence
- Admin user status
- Password hash verification
