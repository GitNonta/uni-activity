# Admin Dashboard 500 Error - Troubleshooting Guide

## Root Cause Identified

The 500 Internal Server Errors across all admin pages are caused by **database authentication failure**:

```
SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'
```

## Problem

Your `.env` file is configured for local development with:
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uni_activity
DB_USERNAME=root
DB_PASSWORD=root
```

However, MySQL is rejecting the connection with these credentials.

## Solutions

### Solution 1: Fix MySQL Authentication (Recommended for Local Development)

#### Option A: Use Empty Password (Common in XAMPP)
1. Open `.env` file
2. Change `DB_PASSWORD=root` to `DB_PASSWORD=`
3. Clear config cache:
```bash
php artisan config:clear
php artisan cache:clear
```

#### Option B: Reset MySQL Root Password
```bash
# Stop MySQL service
# Open MySQL command line as administrator
mysql -u root

# Run these commands:
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
FLUSH PRIVILEGES;
EXIT;
```

#### Option C: Create New Database User
```bash
mysql -u root

# Create new user with password
CREATE USER 'uni_user'@'localhost' IDENTIFIED BY 'root';
GRANT ALL PRIVILEGES ON uni_activity.* TO 'uni_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Then update `.env`:
```
DB_USERNAME=uni_user
DB_PASSWORD=root
```

### Solution 2: Use Docker Configuration

If you want to use Docker (as configured in your docker-compose.yml):

1. Update `.env` to use Docker settings:
```
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=uni_activity
DB_USERNAME=uni_user
DB_PASSWORD=root
```

2. Start Docker containers:
```bash
docker-compose up -d
```

3. Run migrations:
```bash
docker-compose exec app php artisan migrate
```

## Verification Steps

After applying any solution:

1. Test database connection:
```bash
php artisan db:show
```

2. Clear all caches:
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

3. Test a simple query:
```bash
php artisan tinker
>>> \App\Models\User::count()
```

4. Access admin pages:
- http://localhost:8000/admin/dashboard
- http://localhost:8000/admin/activities
- http://localhost:8000/admin/students

## Additional Checks

### Check MySQL Service Status
```bash
# Windows (XAMPP)
# Check if MySQL is running in XAMPP Control Panel

# Windows (MySQL Service)
sc query MySQL80
```

### Check Database Exists
```bash
mysql -u root -p
SHOW DATABASES;
USE uni_activity;
SHOW TABLES;
```

### Check Laravel Logs
```bash
# View recent errors
tail -n 50 storage/logs/laravel.log

# Or on Windows
Get-Content storage/logs/laravel.log -Tail 50
```

## Common Issues After Fix

### Issue: "Base table or view not found"
**Solution:** Run migrations
```bash
php artisan migrate
```

### Issue: "Class not found" or "Target class does not exist"
**Solution:** Regenerate autoload files
```bash
composer dump-autoload
php artisan config:clear
```

### Issue: Still getting 500 errors
**Solution:** Check detailed error
1. Set `APP_DEBUG=true` in `.env`
2. Visit the failing page
3. Read the full error message
4. Check `storage/logs/laravel.log`

## Prevention

To avoid this issue in the future:

1. **Document your local setup** - Keep track of your MySQL credentials
2. **Use environment-specific configs** - Have separate `.env.local` and `.env.docker`
3. **Test database connection first** - Always run `php artisan db:show` after setup
4. **Keep logs clean** - Regularly check `storage/logs/laravel.log` for issues

## Quick Fix Script

Create a file `fix-db-connection.bat` (Windows) or `fix-db-connection.sh` (Linux/Mac):

```bash
@echo off
echo Clearing Laravel caches...
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo Testing database connection...
php artisan db:show

echo Done! If you see database info above, the connection is working.
pause
```

Run this script whenever you change database settings.
