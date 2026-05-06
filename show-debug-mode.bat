@echo off
echo ========================================
echo   Show Debug Mode
echo ========================================
echo.

echo Enabling debug mode...
powershell -Command "(Get-Content .env) -replace 'APP_DEBUG=false', 'APP_DEBUG=true' | Set-Content .env"

echo Clearing cache...
php artisan config:clear >nul 2>&1
php artisan view:clear >nul 2>&1

echo.
echo ========================================
echo   Success!
echo ========================================
echo.
echo Debug mode is now enabled!
echo.
echo You will see detailed error pages
echo with stack traces for debugging.
echo.
echo To show custom error pages, run:
echo   show-custom-errors.bat
echo.
pause
