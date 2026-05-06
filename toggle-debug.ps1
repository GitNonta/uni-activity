# Toggle Debug Mode Script
# Quickly switch between debug mode and custom error pages

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("on", "off", "status")]
    [string]$Action = "status"
)

$envFile = ".env"

function Get-DebugStatus {
    $content = Get-Content $envFile -Raw
    if ($content -match 'APP_DEBUG=true') {
        return "ON"
    } elseif ($content -match 'APP_DEBUG=false') {
        return "OFF"
    } else {
        return "UNKNOWN"
    }
}

function Set-Debug {
    param([bool]$Enable)
    
    $content = Get-Content $envFile -Raw
    $newValue = if ($Enable) { "true" } else { "false" }
    $content = $content -replace 'APP_DEBUG=(true|false)', "APP_DEBUG=$newValue"
    Set-Content $envFile -Value $content -NoNewline
    
    Write-Host "`nClearing cache..." -ForegroundColor Yellow
    php artisan config:clear | Out-Null
    php artisan view:clear | Out-Null
    
    Write-Host "✓ Cache cleared" -ForegroundColor Green
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Debug Mode Toggle" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$currentStatus = Get-DebugStatus

switch ($Action) {
    "status" {
        Write-Host "Current Status: " -NoNewline
        if ($currentStatus -eq "ON") {
            Write-Host "DEBUG MODE ON" -ForegroundColor Yellow
            Write-Host "`nYou will see:" -ForegroundColor White
            Write-Host "  • Laravel debug pages (Ignition)" -ForegroundColor Gray
            Write-Host "  • Detailed error messages" -ForegroundColor Gray
            Write-Host "  • Stack traces" -ForegroundColor Gray
            Write-Host "`nTo see custom error pages, run:" -ForegroundColor Cyan
            Write-Host "  .\toggle-debug.ps1 off" -ForegroundColor White
        } else {
            Write-Host "DEBUG MODE OFF" -ForegroundColor Green
            Write-Host "`nYou will see:" -ForegroundColor White
            Write-Host "  • Beautiful custom error pages" -ForegroundColor Gray
            Write-Host "  • User-friendly messages" -ForegroundColor Gray
            Write-Host "  • Professional appearance" -ForegroundColor Gray
            Write-Host "`nTo enable debug mode, run:" -ForegroundColor Cyan
            Write-Host "  .\toggle-debug.ps1 on" -ForegroundColor White
        }
    }
    
    "on" {
        if ($currentStatus -eq "ON") {
            Write-Host "Debug mode is already ON" -ForegroundColor Yellow
        } else {
            Write-Host "Enabling debug mode..." -ForegroundColor Yellow
            Set-Debug -Enable $true
            Write-Host "`n✓ Debug mode is now ON" -ForegroundColor Green
            Write-Host "`nYou will now see:" -ForegroundColor White
            Write-Host "  • Laravel debug pages" -ForegroundColor Gray
            Write-Host "  • Detailed error information" -ForegroundColor Gray
        }
    }
    
    "off" {
        if ($currentStatus -eq "OFF") {
            Write-Host "Debug mode is already OFF" -ForegroundColor Yellow
        } else {
            Write-Host "Disabling debug mode..." -ForegroundColor Yellow
            Set-Debug -Enable $false
            Write-Host "`n✓ Debug mode is now OFF" -ForegroundColor Green
            Write-Host "`nYou will now see:" -ForegroundColor White
            Write-Host "  • Beautiful custom error pages" -ForegroundColor Gray
            Write-Host "  • User-friendly messages" -ForegroundColor Gray
            Write-Host "`nTest your error pages at:" -ForegroundColor Cyan
            Write-Host "  http://localhost:8000/test-errors" -ForegroundColor White
        }
    }
}

Write-Host "`n========================================`n" -ForegroundColor Cyan
