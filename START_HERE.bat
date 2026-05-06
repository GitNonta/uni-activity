@echo off
color 0A
title Laravel Admin Dashboard - Fix 500 Errors

:menu
cls
echo ============================================================
echo    Laravel Admin Dashboard - Fix 500 Errors
echo ============================================================
echo.
echo Current Issue: Database authentication failure
echo Error: SQLSTATE[HY000] [1698] Access denied for user 'root'
echo.
echo ============================================================
echo    CHOOSE AN OPTION:
echo ============================================================
echo.
echo  1. Test database connection (check if it's working)
echo  2. Try empty password fix (XAMPP common)
echo  3. Open MySQL fix guide (recommended)
echo  4. Run diagnostic script
echo  5. Clear Laravel caches
echo  6. View Laravel logs
echo  7. Open all documentation
echo  8. Exit
echo.
echo ============================================================
set /p choice="Enter your choice (1-8): "

if "%choice%"=="1" goto test
if "%choice%"=="2" goto emptypass
if "%choice%"=="3" goto guide
if "%choice%"=="4" goto diagnostic
if "%choice%"=="5" goto clearcache
if "%choice%"=="6" goto logs
if "%choice%"=="7" goto docs
if "%choice%"=="8" goto end
goto menu

:test
cls
echo ============================================================
echo Testing Database Connection...
echo ============================================================
echo.
php artisan db:show
echo.
if %ERRORLEVEL% EQU 0 (
    echo ============================================================
    echo SUCCESS! Database is connected!
    echo ============================================================
    echo.
    echo You can now access:
    echo - http://localhost:8000/admin/dashboard
    echo - http://localhost:8000/admin/activities
    echo - http://localhost:8000/admin/students
    echo.
) else (
    echo ============================================================
    echo FAILED! Database connection is not working
    echo ============================================================
    echo.
    echo Please choose option 3 to see the fix guide
    echo.
)
pause
goto menu

:emptypass
cls
echo ============================================================
echo Trying Empty Password Fix...
echo ============================================================
echo.
echo This will set DB_PASSWORD to empty in .env file
echo (Common fix for XAMPP installations)
echo.
set /p confirm="Continue? (y/n): "
if /i not "%confirm%"=="y" goto menu

echo.
echo Updating .env file...
powershell -Command "(Get-Content .env) -replace '^DB_PASSWORD=.*', 'DB_PASSWORD=' | Set-Content .env"
echo Done!
echo.
echo Clearing config cache...
php artisan config:clear
echo.
echo Testing connection...
php artisan db:show
echo.
if %ERRORLEVEL% EQU 0 (
    echo ============================================================
    echo SUCCESS! Empty password worked!
    echo ============================================================
) else (
    echo ============================================================
    echo Empty password didn't work. Try option 3 for other solutions.
    echo ============================================================
)
echo.
pause
goto menu

:guide
cls
echo ============================================================
echo Opening MySQL Fix Guide...
echo ============================================================
echo.
if exist "SOLUTION_SUMMARY.md" (
    start notepad "SOLUTION_SUMMARY.md"
    echo Guide opened in Notepad
) else (
    echo Error: SOLUTION_SUMMARY.md not found
)
echo.
if exist "QUICK_FIX.md" (
    start notepad "QUICK_FIX.md"
    echo Quick fix guide opened in Notepad
) else (
    echo Error: QUICK_FIX.md not found
)
echo.
echo Please follow the instructions in the opened files
echo.
pause
goto menu

:diagnostic
cls
echo ============================================================
echo Running Diagnostic Script...
echo ============================================================
echo.
powershell -ExecutionPolicy Bypass -File diagnose-and-fix.ps1
echo.
pause
goto menu

:clearcache
cls
echo ============================================================
echo Clearing Laravel Caches...
echo ============================================================
echo.
echo Clearing config cache...
php artisan config:clear
echo.
echo Clearing application cache...
php artisan cache:clear
echo.
echo Clearing view cache...
php artisan view:clear
echo.
echo Clearing route cache...
php artisan route:clear
echo.
echo ============================================================
echo All caches cleared!
echo ============================================================
echo.
pause
goto menu

:logs
cls
echo ============================================================
echo Recent Laravel Logs (last 50 lines)
echo ============================================================
echo.
powershell -Command "Get-Content storage/logs/laravel.log -Tail 50"
echo.
echo ============================================================
echo End of logs
echo ============================================================
echo.
pause
goto menu

:docs
cls
echo ============================================================
echo Opening All Documentation...
echo ============================================================
echo.
if exist "SOLUTION_SUMMARY.md" start notepad "SOLUTION_SUMMARY.md"
if exist "QUICK_FIX.md" start notepad "QUICK_FIX.md"
if exist "TROUBLESHOOTING_GUIDE.md" start notepad "TROUBLESHOOTING_GUIDE.md"
echo.
echo All documentation files opened!
echo.
pause
goto menu

:end
cls
echo ============================================================
echo Thank you for using the fix tool!
echo ============================================================
echo.
echo If you fixed the issue, test it with:
echo   php artisan db:show
echo.
echo Then access your admin dashboard at:
echo   http://localhost:8000/admin/dashboard
echo.
pause
exit
