@echo off
echo ========================================
echo   Show Custom Error Pages
echo ========================================
echo.

echo Disabling debug mode...
powershell -Command "(Get-Content .env) -replace 'APP_DEBUG=true', 'APP_DEBUG=false' | Set-Content .env"

echo Clearing cache...
php artisan config:clear >nul 2>&1
php artisan view:clear >nul 2>&1

echo.
echo ========================================
echo   Success!
echo ========================================
echo.
echo Custom error pages are now enabled!
echo.
echo Test them at:
echo   http://localhost:8000/test-errors
echo.
echo To re-enable debug mode, run:
echo   show-debug-mode.bat
echo.
pause
