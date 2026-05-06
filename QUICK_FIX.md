# QUICK FIX - Admin Dashboard 500 Errors

## The Problem
All admin pages are returning 500 errors because MySQL is rejecting the database connection.

Error: `SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'`

## Quick Fix (Choose ONE method)

### Method 1: Fix MySQL Root User (Fastest)

1. Open Command Prompt as Administrator
2. Run MySQL:
```bash
mysql -u root
```

3. Run these commands:
```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
FLUSH PRIVILEGES;
EXIT;
```

4. Clear Laravel cache:
```bash
php artisan config:clear
```

5. Test:
```bash
php artisan db:show
```

### Method 2: Create New Database User (Recommended)

1. Open Command Prompt as Administrator
2. Run MySQL:
```bash
mysql -u root
```

3. Run these commands:
```sql
CREATE USER 'uni_user'@'localhost' IDENTIFIED BY 'root';
GRANT ALL PRIVILEGES ON uni_activity.* TO 'uni_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

4. Update `.env` file:
```
DB_USERNAME=uni_user
DB_PASSWORD=root
```

5. Clear Laravel cache:
```bash
php artisan config:clear
```

6. Test:
```bash
php artisan db:show
```

### Method 3: Use SQL File (Easiest)

1. Open Command Prompt
2. Navigate to project directory
3. Run:
```bash
mysql -u root < fix-mysql-auth.sql
```

4. Update `.env` to use `uni_user`:
```
DB_USERNAME=uni_user
DB_PASSWORD=root
```

5. Clear cache:
```bash
php artisan config:clear
```

### Method 4: If MySQL Requires Password

If MySQL asks for password when you run `mysql -u root`:

1. Try common passwords:
   - (empty - just press Enter)
   - root
   - password
   - admin

2. If none work, reset MySQL root password:
   - Stop MySQL service
   - Start MySQL with --skip-grant-tables
   - Reset password
   - Restart MySQL normally

## Verify Fix

After applying any method:

```bash
# Should show database information
php artisan db:show

# Should return a number
php artisan tinker
>>> \App\Models\User::count()
>>> exit
```

## Access Admin Dashboard

Once fixed, you can access:
- http://localhost:8000/admin/login
- http://localhost:8000/admin/dashboard
- http://localhost:8000/admin/activities
- http://localhost:8000/admin/students
- http://localhost:8000/admin/feedbacks
- http://localhost:8000/admin/exports
- http://localhost:8000/admin/audit-logs
- http://localhost:8000/admin/profile

## Still Not Working?

1. Check MySQL is running:
   - XAMPP: Check Control Panel
   - Windows Service: `sc query MySQL80`

2. Check database exists:
```bash
mysql -u root -p
SHOW DATABASES;
```

3. Check Laravel logs:
```bash
Get-Content storage/logs/laravel.log -Tail 50
```

4. Enable debug mode in `.env`:
```
APP_DEBUG=true
```

Then visit the failing page to see detailed error.
