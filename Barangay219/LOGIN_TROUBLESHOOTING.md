# Login Troubleshooting Guide

## Quick Fixes Applied

I've made the following improvements to help fix login issues:

1. **Auto-detecting URL paths** - The system now automatically detects the correct BASE_URL and API_URL based on your folder structure
2. **Improved error messages** - Better error handling in the login JavaScript to show specific issues
3. **Enhanced debugging** - More detailed console logging to help identify problems

## How to Test

1. **Access the diagnostic tool:**
   - Open: `http://localhost/Barangay219/Barangay219/diagnose-login.php`
   - This will show you:
     - Configuration settings
     - Database connection status
     - User account status
     - API endpoint accessibility
     - File paths

2. **Try logging in:**
   - Go to: `http://localhost/Barangay219/Barangay219/public/index.php`
   - Username: `admin`
   - Password: `admin123`
   - Open browser console (F12) to see any errors

## Common Issues and Solutions

### Issue 1: "Configuration error" or "API_URL is not defined"
**Solution:** 
- Check that Apache is running in XAMPP
- Verify the URL path matches your folder structure
- Check browser console (F12) for JavaScript errors

### Issue 2: "Cannot connect to server" or "Failed to fetch"
**Solution:**
- Make sure Apache is running in XAMPP Control Panel
- Check that the API URL is accessible: `http://localhost/Barangay219/Barangay219/api/auth.php?action=check`
- Verify your XAMPP Apache is listening on port 80

### Issue 3: "Invalid username or password"
**Solution:**
- Run the diagnostic tool to check if the admin user exists
- Verify the database `barangay219_db` exists and has been imported
- Check if password hash is correct (diagnostic tool will test this)
- If password hash is wrong, you may need to reset it

### Issue 4: Database connection error
**Solution:**
- Make sure MySQL is running in XAMPP
- Verify database name in `config/constants.php` matches your database
- Check database credentials (default: user=`root`, password=empty)
- Import the database schema if not done: `database/schema.sql` and `database/seeds.sql`

### Issue 5: "Server returned non-JSON response"
**Solution:**
- This usually means the API URL is wrong or the file path is incorrect
- Check that `api/auth.php` exists and is accessible
- Verify the API_URL in the diagnostic tool matches the actual file location

## Manual URL Configuration

If auto-detection doesn't work, you can manually set the URLs in `config/constants.php`:

```php
define('BASE_URL', 'http://localhost/Barangay219/Barangay219/public/');
define('API_URL', 'http://localhost/Barangay219/Barangay219/api/');
```

Replace the path parts (`Barangay219/Barangay219`) with your actual folder structure.

## Check Browser Console

Always check the browser console (F12 → Console tab) when login fails. It will show:
- The API URL being used
- Network errors
- JavaScript errors
- Response details

## Default Login Credentials

- **Username:** `admin`
- **Password:** `admin123`

If these don't work, use the diagnostic tool to check the database.

## Next Steps

1. Run the diagnostic tool first: `diagnose-login.php`
2. Check the browser console for errors
3. Verify Apache and MySQL are running
4. Test the API endpoint directly
5. Check database connection and user accounts

If issues persist, share the output from the diagnostic tool and browser console errors.
