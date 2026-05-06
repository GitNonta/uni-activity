# Docker Cache Clear and Live Build Script
# Clears all Docker cache and rebuilds containers from scratch

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Docker Cache Clear & Rebuild" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Check if Docker is running
Write-Host "[1/8] Checking Docker status..." -ForegroundColor Yellow
try {
    docker ps | Out-Null
    Write-Host "✓ Docker is running`n" -ForegroundColor Green
} catch {
    Write-Host "✗ Docker is not running!" -ForegroundColor Red
    Write-Host "Please start Docker Desktop and try again.`n" -ForegroundColor Yellow
    pause
    exit 1
}

# Stop all running containers
Write-Host "[2/8] Stopping all containers..." -ForegroundColor Yellow
docker-compose down
Write-Host "✓ Containers stopped`n" -ForegroundColor Green

# Remove all containers
Write-Host "[3/8] Removing all containers..." -ForegroundColor Yellow
$containers = docker ps -aq
if ($containers) {
    docker rm -f $containers
    Write-Host "✓ Containers removed`n" -ForegroundColor Green
} else {
    Write-Host "✓ No containers to remove`n" -ForegroundColor Green
}

# Remove all images
Write-Host "[4/8] Removing all images..." -ForegroundColor Yellow
$images = docker images -q
if ($images) {
    docker rmi -f $images
    Write-Host "✓ Images removed`n" -ForegroundColor Green
} else {
    Write-Host "✓ No images to remove`n" -ForegroundColor Green
}

# Remove all volumes
Write-Host "[5/8] Removing all volumes..." -ForegroundColor Yellow
$volumes = docker volume ls -q
if ($volumes) {
    docker volume rm -f $volumes
    Write-Host "✓ Volumes removed`n" -ForegroundColor Green
} else {
    Write-Host "✓ No volumes to remove`n" -ForegroundColor Green
}

# Remove all networks (except default)
Write-Host "[6/8] Removing custom networks..." -ForegroundColor Yellow
$networks = docker network ls --filter "type=custom" -q
if ($networks) {
    docker network rm $networks 2>$null
    Write-Host "✓ Networks removed`n" -ForegroundColor Green
} else {
    Write-Host "✓ No custom networks to remove`n" -ForegroundColor Green
}

# Prune system (remove all unused data)
Write-Host "[7/8] Pruning Docker system..." -ForegroundColor Yellow
docker system prune -af --volumes
Write-Host "✓ System pruned`n" -ForegroundColor Green

# Build and start containers
Write-Host "[8/8] Building and starting containers..." -ForegroundColor Yellow
Write-Host "This may take several minutes...`n" -ForegroundColor Cyan

docker-compose up -d --build

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✓ Build completed successfully!`n" -ForegroundColor Green
    
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Waiting for services to be healthy..." -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan
    
    Start-Sleep -Seconds 10
    
    # Check container status
    Write-Host "Container Status:" -ForegroundColor Yellow
    docker ps --format "table {{.Names}}\t{{.Status}}" | Select-Object -First 20
    
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "  Build Complete!" -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan
    
    Write-Host "Services are starting up..." -ForegroundColor Green
    Write-Host "Wait 30-60 seconds for all services to be healthy.`n" -ForegroundColor Yellow
    
    Write-Host "Test services with:" -ForegroundColor Cyan
    Write-Host "  .\test-api-health.ps1`n" -ForegroundColor White
    
    Write-Host "Access application at:" -ForegroundColor Cyan
    Write-Host "  http://localhost:8000`n" -ForegroundColor White
    
} else {
    Write-Host "`n✗ Build failed!" -ForegroundColor Red
    Write-Host "Check the error messages above.`n" -ForegroundColor Yellow
}

Write-Host "========================================`n" -ForegroundColor Cyan

# Ask if user wants to view logs
$viewLogs = Read-Host "View container logs? (Y/N)"
if ($viewLogs -eq "Y" -or $viewLogs -eq "y") {
    Write-Host "`nShowing logs (Ctrl+C to exit)...`n" -ForegroundColor Yellow
    docker-compose logs -f
}
