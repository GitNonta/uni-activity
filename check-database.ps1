# Database Information Check Script
# Checks all tables and displays record counts

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Database Information Check" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$dbHost = "laravel-mysql"
$dbUser = "root"
$dbPass = "root"
$dbName = "uni_activity"

Write-Host "Database: $dbName" -ForegroundColor Yellow
Write-Host "Container: $dbHost`n" -ForegroundColor Yellow

# Get all tables
Write-Host "Fetching table list..." -ForegroundColor Cyan
$tables = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -N -e "SHOW TABLES;" 2>$null

if ($tables) {
    Write-Host "Found $($tables.Count) tables`n" -ForegroundColor Green
    
    Write-Host "Table Statistics:" -ForegroundColor Yellow
    Write-Host ("-" * 60) -ForegroundColor Gray
    Write-Host ("{0,-30} {1,10} {2,15}" -f "Table Name", "Records", "Status") -ForegroundColor White
    Write-Host ("-" * 60) -ForegroundColor Gray
    
    $totalRecords = 0
    
    foreach ($table in $tables) {
        $table = $table.Trim()
        if ($table) {
            try {
                $count = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -N -e "SELECT COUNT(*) FROM ``$table``;" 2>$null
                $count = [int]$count
                $totalRecords += $count
                
                $status = if ($count -eq 0) { "Empty" } elseif ($count -lt 10) { "Few" } elseif ($count -lt 100) { "Some" } else { "Many" }
                $color = if ($count -eq 0) { "Gray" } elseif ($count -lt 10) { "Yellow" } else { "Green" }
                
                Write-Host ("{0,-30} {1,10} {2,15}" -f $table, $count, $status) -ForegroundColor $color
            } catch {
                Write-Host ("{0,-30} {1,10} {2,15}" -f $table, "ERROR", "Failed") -ForegroundColor Red
            }
        }
    }
    
    Write-Host ("-" * 60) -ForegroundColor Gray
    Write-Host ("{0,-30} {1,10}" -f "TOTAL RECORDS", $totalRecords) -ForegroundColor Cyan
    Write-Host ("-" * 60) -ForegroundColor Gray
    
} else {
    Write-Host "✗ No tables found or connection failed" -ForegroundColor Red
}

# Check specific important tables
Write-Host "`nDetailed Information:" -ForegroundColor Yellow
Write-Host ("-" * 60) -ForegroundColor Gray

# Users
Write-Host "`n[Users Table]" -ForegroundColor Cyan
$userCount = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -N -e "SELECT COUNT(*) FROM users;" 2>$null
Write-Host "  Total Users: $userCount" -ForegroundColor White

if ([int]$userCount -gt 0) {
    Write-Host "  Roles breakdown:" -ForegroundColor White
    $roles = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -e "SELECT role, COUNT(*) as count FROM users GROUP BY role;" 2>$null
    Write-Host $roles -ForegroundColor Gray
} else {
    Write-Host "  ⚠ Database is empty - no users found" -ForegroundColor Yellow
}

# Activities
Write-Host "`n[Activities Table]" -ForegroundColor Cyan
$activityCount = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -N -e "SELECT COUNT(*) FROM activities;" 2>$null
Write-Host "  Total Activities: $activityCount" -ForegroundColor White

# Job Listings
Write-Host "`n[Job Listings Table]" -ForegroundColor Cyan
$jobCount = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -N -e "SELECT COUNT(*) FROM job_listings;" 2>$null
Write-Host "  Total Job Listings: $jobCount" -ForegroundColor White

# Announcements
Write-Host "`n[Announcements Table]" -ForegroundColor Cyan
$announcementCount = docker exec laravel-mysql mysql -u $dbUser -p$dbPass $dbName -N -e "SELECT COUNT(*) FROM announcements;" 2>$null
Write-Host "  Total Announcements: $announcementCount" -ForegroundColor White

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Summary" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

if ([int]$userCount -eq 0) {
    Write-Host "⚠ Database is empty!" -ForegroundColor Yellow
    Write-Host "`nTo populate the database:" -ForegroundColor Cyan
    Write-Host "  1. Run seeders: docker exec laravel-app php artisan db:seed" -ForegroundColor White
    Write-Host "  2. Or import backup: docker exec -i laravel-mysql mysql -u root -proot uni_activity < backup.sql" -ForegroundColor White
    Write-Host "  3. Or create admin user manually`n" -ForegroundColor White
} else {
    Write-Host "✓ Database has data" -ForegroundColor Green
    Write-Host "  Users: $userCount" -ForegroundColor White
    Write-Host "  Activities: $activityCount" -ForegroundColor White
    Write-Host "  Jobs: $jobCount" -ForegroundColor White
    Write-Host "  Announcements: $announcementCount`n" -ForegroundColor White
}

Write-Host "========================================`n" -ForegroundColor Cyan
