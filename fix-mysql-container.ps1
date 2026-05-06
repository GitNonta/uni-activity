# Fix MySQL Container Error Script
# Fixes the "Database is uninitialized and password option is not specified" error

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Fix MySQL Container Error" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Problem: laravel-mysql container failing to start" -ForegroundColor Yellow
Write-Host "Error: Database is uninitialized and password option is not specified`n" -ForegroundColor Red

Write-Host "[1/5] Checking .env file..." -ForegroundColor Yellow

# Check if DB_ROOT_PASSWORD exists
$envContent = Get-Content .env -Raw
if ($envContent -match 'DB_ROOT_PASSWORD=') {
    Write-Host "✓ DB_ROOT_PASSWORD found in .env`n" -ForegroundColor Green
} else {
    Write-Host "✗ DB_ROOT_PASSWORD missing in .env" -ForegroundColor Red
    Write-Host "Adding DB_ROOT_PASSWORD to .env...`n" -ForegroundColor Yellow
    
    # Add DB_ROOT_PASSWORD after DB_PASSWORD
    $envContent = $envContent -replace '(DB_PASSWORD=.*)', "`$1`nDB_ROOT_PASSWORD=root"
    Set-Content .env -Value $envContent -NoNewline
    
    Write-Host "✓ DB_ROOT_PASSWORD added`n" -ForegroundColor Green
}

# Check MongoDB credentials
if ($envContent -match 'MONGODB_USERNAME=') {
    Write-Host "✓ MongoDB credentials found`n" -ForegroundColor Green
} else {
    Write-Host "Adding MongoDB credentials...`n" -ForegroundColor Yellow
    Add-Content .env "`nMONGODB_USERNAME=root`nMONGODB_PASSWORD=root`nMONGODB_DATABASE=uni_chat"
    Write-Host "✓ MongoDB credentials added`n" -ForegroundColor Green
}

Write-Host "[2/5] Stopping laravel-mysql container..." -ForegroundColor Yellow
docker stop laravel-mysql 2>$null
docker rm laravel-mysql 2>$null
Write-Host "✓ Container stopped and removed`n" -ForegroundColor Green

Write-Host "[3/5] Removing MySQL volume..." -ForegroundColor Yellow
docker volume rm mysql-data 2>$null
Write-Host "✓ Volume removed`n" -ForegroundColor Green

Write-Host "[4/5] Recreating MySQL container..." -ForegroundColor Yellow
docker-compose up -d mysql
Write-Host "✓ Container created`n" -ForegroundColor Green

Write-Host "[5/5] Waiting for MySQL to be healthy..." -ForegroundColor Yellow
$maxAttempts = 30
$attempt = 0

while ($attempt -lt $maxAttempts) {
    $attempt++
    Write-Host "Attempt $attempt/$maxAttempts..." -NoNewline
    
    $status = docker inspect laravel-mysql --format='{{.State.Health.Status}}' 2>$null
    
    if ($status -eq "healthy") {
        Write-Host " ✓ Healthy!" -ForegroundColor Green
        break
    } elseif ($status -eq "unhealthy") {
        Write-Host " ✗ Unhealthy" -ForegroundColor Red
        break
    } else {
        Write-Host " Waiting..." -ForegroundColor Yellow
        Start-Sleep -Seconds 2
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Status Check" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Check final status
$finalStatus = docker inspect laravel-mysql --format='{{.State.Health.Status}}' 2>$null

if ($finalStatus -eq "healthy") {
    Write-Host "✓ MySQL container is now healthy!" -ForegroundColor Green
    Write-Host "✓ Problem fixed successfully!`n" -ForegroundColor Green
    
    Write-Host "You can now:" -ForegroundColor Cyan
    Write-Host "  1. Start other containers: docker-compose up -d" -ForegroundColor White
    Write-Host "  2. Test database: php artisan db:test" -ForegroundColor White
    Write-Host "  3. Run migrations: php artisan migrate`n" -ForegroundColor White
    
} else {
    Write-Host "✗ MySQL container is still not healthy" -ForegroundColor Red
    Write-Host "`nChecking logs...`n" -ForegroundColor Yellow
    docker logs laravel-mysql --tail 20
    
    Write-Host "`nTroubleshooting:" -ForegroundColor Cyan
    Write-Host "  1. Check .env file has DB_ROOT_PASSWORD=root" -ForegroundColor White
    Write-Host "  2. Try: docker-compose down -v" -ForegroundColor White
    Write-Host "  3. Then: docker-compose up -d`n" -ForegroundColor White
}

Write-Host "========================================`n" -ForegroundColor Cyan
