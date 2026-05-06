@echo off
echo ========================================
echo Laravel Database Connection Fix Script
echo ========================================
echo.

echo Step 1: Clearing Laravel caches...
call php artisan config:clear
call php artisan cache:clear
call php artisan view:clear
call php artisan route:clear
echo Caches cleared!
echo.

echo Step 2: Testing database connection...
echo.
call php artisan db:show
echo.

if %ERRORLEVEL% EQU 0 (
    echo ========================================
    echo SUCCESS! Database connection is working
    echo ========================================
) else (
    echo ========================================
    echo ERROR! Database connection failed
    echo ========================================
    echo.
    echo Please check:
    echo 1. MySQL service is running
    echo 2. Database credentials in .env file
    echo 3. Database 'uni_activity' exists
    echo.
    echo Common fixes:
    echo - Try DB_PASSWORD= (empty password for XAMPP)
    echo - Or run: mysql -u root -p
    echo   Then: ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
)

echo.
pause
