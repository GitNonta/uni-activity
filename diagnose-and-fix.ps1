# Laravel Database Connection Diagnostic and Fix Script
# Run this with: powershell -ExecutionPolicy Bypass -File diagnose-and-fix.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Laravel Database Connection Diagnostic" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Check if PHP is available
Write-Host "Step 1: Checking PHP..." -ForegroundColor Yellow
try {
    $phpVersion = php -v 2>&1 | Select-String "PHP" | Select-Object -First 1
    Write-Host "✓ PHP found: $phpVersion" -ForegroundColor Green
} catch {
    Write-Host "✗ PHP not found in PATH" -ForegroundColor Red
    exit 1
}

# Step 2: Check .env file
Write-Host "`nStep 2: Checking .env configuration..." -ForegroundColor Yellow
if (Test-Path ".env") {
    $envContent = Get-Content ".env"
    $dbHost = ($envContent | Select-String "^DB_HOST=").ToString() -replace "DB_HOST=", ""
    $dbPort = ($envContent | Select-String "^DB_PORT=").ToString() -replace "DB_PORT=", ""
    $dbDatabase = ($envContent | Select-String "^DB_DATABASE=").ToString() -replace "DB_DATABASE=", ""
    $dbUsername = ($envContent | Select-String "^DB_USERNAME=").ToString() -replace "DB_USERNAME=", ""
    $dbPassword = ($envContent | Select-String "^DB_PASSWORD=").ToString() -replace "DB_PASSWORD=", ""
    
    Write-Host "  DB_HOST: $dbHost" -ForegroundColor White
    Write-Host "  DB_PORT: $dbPort" -ForegroundColor White
    Write-Host "  DB_DATABASE: $dbDatabase" -ForegroundColor White
    Write-Host "  DB_USERNAME: $dbUsername" -ForegroundColor White
    Write-Host "  DB_PASSWORD: $(if($dbPassword){'(set)'}else{'(empty)'})" -ForegroundColor White
} else {
    Write-Host "✗ .env file not found!" -ForegroundColor Red
    exit 1
}

# Step 3: Check MySQL service
Write-Host "`nStep 3: Checking MySQL service..." -ForegroundColor Yellow
$mysqlServices = Get-Service | Where-Object {$_.Name -like "*mysql*"}
if ($mysqlServices) {
    foreach ($service in $mysqlServices) {
        $status = if ($service.Status -eq 'Running') { '✓' } else { '✗' }
        $color = if ($service.Status -eq 'Running') { 'Green' } else { 'Red' }
        Write-Host "  $status $($service.Name): $($service.Status)" -ForegroundColor $color
    }
} else {
    Write-Host "  ⚠ No MySQL service found (might be using XAMPP)" -ForegroundColor Yellow
}

# Step 4: Find MySQL executable
Write-Host "`nStep 4: Looking for MySQL executable..." -ForegroundColor Yellow
$mysqlPaths = @(
    "C:\xampp\mysql\bin\mysql.exe",
    "C:\wamp\bin\mysql\mysql8.0.27\bin\mysql.exe",
    "C:\wamp64\bin\mysql\mysql8.0.27\bin\mysql.exe",
    "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe",
    "C:\Program Files\MySQL\MySQL Server 5.7\bin\mysql.exe"
)

$mysqlExe = $null
foreach ($path in $mysqlPaths) {
    if (Test-Path $path) {
        $mysqlExe = $path
        Write-Host "  ✓ Found MySQL at: $path" -ForegroundColor Green
        break
    }
}

if (-not $mysqlExe) {
    # Try to find mysql in PATH
    try {
        $mysqlExe = (Get-Command mysql -ErrorAction Stop).Source
        Write-Host "  ✓ Found MySQL in PATH: $mysqlExe" -ForegroundColor Green
    } catch {
        Write-Host "  ✗ MySQL executable not found" -ForegroundColor Red
    }
}

# Step 5: Test database connection
Write-Host "`nStep 5: Testing database connection..." -ForegroundColor Yellow
Write-Host "  Running: php artisan db:show" -ForegroundColor Gray
$dbTest = php artisan db:show 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ Database connection successful!" -ForegroundColor Green
    Write-Host "`n$dbTest" -ForegroundColor White
    Write-Host "`n========================================" -ForegroundColor Green
    Write-Host "SUCCESS! Database is connected properly" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    exit 0
} else {
    Write-Host "  ✗ Database connection failed!" -ForegroundColor Red
    if ($dbTest -match "Access denied") {
        Write-Host "`n  Error: MySQL authentication failed" -ForegroundColor Red
        Write-Host "  This is the cause of your 500 errors!" -ForegroundColor Red
    }
}

# Step 6: Provide solutions
Write-Host "`n========================================" -ForegroundColor Yellow
Write-Host "SOLUTIONS TO FIX THE ISSUE" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Yellow

Write-Host "`nOption 1: Try empty password (common in XAMPP)" -ForegroundColor Cyan
Write-Host "  1. Open .env file" -ForegroundColor White
Write-Host "  2. Change DB_PASSWORD=root to DB_PASSWORD=" -ForegroundColor White
Write-Host "  3. Run: php artisan config:clear" -ForegroundColor White

Write-Host "`nOption 2: Fix MySQL root user authentication" -ForegroundColor Cyan
if ($mysqlExe) {
    Write-Host "  Run this command:" -ForegroundColor White
    Write-Host "  `"$mysqlExe`" -u root -e `"ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root'; FLUSH PRIVILEGES;`"" -ForegroundColor Green
} else {
    Write-Host "  1. Open MySQL command line" -ForegroundColor White
    Write-Host "  2. Run: ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';" -ForegroundColor White
    Write-Host "  3. Run: FLUSH PRIVILEGES;" -ForegroundColor White
}

Write-Host "`nOption 3: Create new database user (recommended)" -ForegroundColor Cyan
if ($mysqlExe) {
    Write-Host "  Run this command:" -ForegroundColor White
    Write-Host "  `"$mysqlExe`" -u root < fix-mysql-auth.sql" -ForegroundColor Green
    Write-Host "  Then update .env: DB_USERNAME=uni_user" -ForegroundColor White
} else {
    Write-Host "  1. Run the SQL commands in fix-mysql-auth.sql" -ForegroundColor White
    Write-Host "  2. Update .env: DB_USERNAME=uni_user" -ForegroundColor White
}

Write-Host "`nOption 4: Use Docker instead" -ForegroundColor Cyan
Write-Host "  1. Update .env: DB_HOST=mysql" -ForegroundColor White
Write-Host "  2. Run: docker-compose up -d" -ForegroundColor White

Write-Host "`n========================================" -ForegroundColor Yellow
Write-Host "After applying any fix, run:" -ForegroundColor Yellow
Write-Host "  php artisan config:clear" -ForegroundColor White
Write-Host "  php artisan db:show" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Yellow

# Offer to try empty password automatically
Write-Host "`n"
$response = Read-Host "Would you like to try Option 1 (empty password) now? (y/n)"
if ($response -eq 'y' -or $response -eq 'Y') {
    Write-Host "`nUpdating .env file..." -ForegroundColor Yellow
    $envContent = Get-Content ".env"
    $envContent = $envContent -replace "^DB_PASSWORD=.*", "DB_PASSWORD="
    $envContent | Set-Content ".env"
    Write-Host "✓ Updated DB_PASSWORD to empty" -ForegroundColor Green
    
    Write-Host "`nClearing config cache..." -ForegroundColor Yellow
    php artisan config:clear 2>&1 | Out-Null
    
    Write-Host "`nTesting connection..." -ForegroundColor Yellow
    $dbTest = php artisan db:show 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ SUCCESS! Database connection is now working!" -ForegroundColor Green
    } else {
        Write-Host "✗ Still not working. Try other options above." -ForegroundColor Red
    }
}

Write-Host "`nPress any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
