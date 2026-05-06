# Fix Docker Environment Configuration
# Updates .env for Docker compatibility

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Fix Docker Environment" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Problem: Laravel app can't connect to MySQL in Docker" -ForegroundColor Yellow
Write-Host "Cause: .env configured for local XAMPP, not Docker`n" -ForegroundColor Red

Write-Host "[1/4] Backing up current .env..." -ForegroundColor Yellow
Copy-Item .env .env.backup
Write-Host "✓ Backup created: .env.backup`n" -ForegroundColor Green

Write-Host "[2/4] Updating database configuration for Docker..." -ForegroundColor Yellow

$envContent = Get-Content .env -Raw

# Update DB_HOST for Docker
$envContent = $envContent -replace 'DB_HOST=127\.0\.0\.1', 'DB_HOST=mysql'

# Update DB_PASSWORD for Docker
$envContent = $envContent -replace 'DB_PASSWORD=\s*$', 'DB_PASSWORD=root'

# Ensure DB_ROOT_PASSWORD exists
if ($envContent -notmatch 'DB_ROOT_PASSWORD=') {
    $envContent = $envContent -replace '(DB_PASSWORD=.*)', "`$1`nDB_ROOT_PASSWORD=root"
}

Set-Content .env -Value $envContent -NoNewline

Write-Host "✓ Database configuration updated`n" -ForegroundColor Green

Write-Host "[3/4] Restarting Laravel app container..." -ForegroundColor Yellow
docker-compose restart app
Write-Host "✓ Container restarted`n" -ForegroundColor Green

Write-Host "[4/4] Waiting for app to be healthy..." -ForegroundColor Yellow
$maxAttempts = 30
$attempt = 0

while ($attempt -lt $maxAttempts) {
    $attempt++
    Write-Host "Attempt $attempt/$maxAttempts..." -NoNewline
    
    Start-Sleep -Seconds 2
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000" -UseBasicParsing -TimeoutSec 2 -ErrorAction SilentlyContinue
        if ($response.StatusCode -eq 200) {
            Write-Host " ✓ Healthy!" -ForegroundColor Green
            break
        }
    } catch {
        Write-Host " Waiting..." -ForegroundColor Yellow
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Configuration Summary" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Updated .env for Docker:" -ForegroundColor Green
Write-Host "  DB_HOST: mysql (was 127.0.0.1)" -ForegroundColor White
Write-Host "  DB_PASSWORD: root (was empty)" -ForegroundColor White
Write-Host "  DB_ROOT_PASSWORD: root`n" -ForegroundColor White

Write-Host "Test the application:" -ForegroundColor Cyan
Write-Host "  http://localhost:8000`n" -ForegroundColor White

Write-Host "To restore local configuration:" -ForegroundColor Cyan
Write-Host "  Copy-Item .env.backup .env`n" -ForegroundColor White

Write-Host "========================================`n" -ForegroundColor Cyan
