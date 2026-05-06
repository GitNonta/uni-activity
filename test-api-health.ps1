# API Health Check Script
# Tests all microservices running in Docker

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  University Activity System" -ForegroundColor Cyan
Write-Host "  API Health Check" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Define services to test
$services = @(
    @{Name="User Service"; Port=8001; Container="ms-user-service"},
    @{Name="Activity Service"; Port=8002; Container="ms-activity-service"},
    @{Name="Job Service"; Port=8003; Container="ms-job-service"},
    @{Name="Notification Service"; Port=8004; Container="ms-notification-service"},
    @{Name="Audit Service"; Port=8005; Container="ms-audit-service"}
)

$gateways = @(
    @{Name="Kong Gateway"; Port=8444},
    @{Name="Socket Server"; Port=3000},
    @{Name="Nginx Web Server"; Port=8000}
)

$databases = @(
    @{Name="User DB"; Port=33061},
    @{Name="Activity DB"; Port=33062},
    @{Name="Job DB"; Port=33063},
    @{Name="Audit DB"; Port=33064},
    @{Name="Notification DB (MongoDB)"; Port=27017},
    @{Name="Redis"; Port=6380}
)

# Test Microservices
Write-Host "Testing Microservices..." -ForegroundColor Yellow
Write-Host "------------------------`n" -ForegroundColor Yellow

$healthyCount = 0
$totalServices = $services.Count

foreach ($service in $services) {
    Write-Host "Testing $($service.Name) (Port $($service.Port))... " -NoNewline
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:$($service.Port)/health" -UseBasicParsing -TimeoutSec 5
        $status = [System.Text.Encoding]::UTF8.GetString($response.Content).Trim()
        
        if ($response.StatusCode -eq 200) {
            Write-Host "✓ $status" -ForegroundColor Green
            $healthyCount++
        } else {
            Write-Host "✗ Status: $($response.StatusCode)" -ForegroundColor Red
        }
    } catch {
        Write-Host "✗ Not responding" -ForegroundColor Red
    }
}

Write-Host "`nMicroservices Status: $healthyCount/$totalServices healthy`n" -ForegroundColor $(if ($healthyCount -eq $totalServices) { "Green" } else { "Yellow" })

# Test Gateways
Write-Host "Testing Gateways & Servers..." -ForegroundColor Yellow
Write-Host "-----------------------------`n" -ForegroundColor Yellow

foreach ($gateway in $gateways) {
    Write-Host "Testing $($gateway.Name) (Port $($gateway.Port))... " -NoNewline
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:$($gateway.Port)" -UseBasicParsing -TimeoutSec 5
        
        if ($response.StatusCode -eq 200) {
            Write-Host "✓ Running" -ForegroundColor Green
        } else {
            Write-Host "✗ Status: $($response.StatusCode)" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "✗ Not responding" -ForegroundColor Red
    }
}

# Test Database Connections
Write-Host "`nTesting Database Connections..." -ForegroundColor Yellow
Write-Host "-------------------------------`n" -ForegroundColor Yellow

foreach ($db in $databases) {
    Write-Host "Testing $($db.Name) (Port $($db.Port))... " -NoNewline
    
    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $tcpClient.Connect("localhost", $db.Port)
        
        if ($tcpClient.Connected) {
            Write-Host "✓ Connected" -ForegroundColor Green
            $tcpClient.Close()
        } else {
            Write-Host "✗ Cannot connect" -ForegroundColor Red
        }
    } catch {
        Write-Host "✗ Not responding" -ForegroundColor Red
    }
}

# Check Docker Containers
Write-Host "`nDocker Container Status..." -ForegroundColor Yellow
Write-Host "--------------------------`n" -ForegroundColor Yellow

try {
    $containers = docker ps --format "table {{.Names}}\t{{.Status}}" | Select-Object -Skip 1
    
    if ($containers) {
        foreach ($container in $containers) {
            $parts = $container -split '\t'
            $name = $parts[0]
            $status = $parts[1]
            
            if ($status -match "Up") {
                Write-Host "✓ $name : $status" -ForegroundColor Green
            } else {
                Write-Host "✗ $name : $status" -ForegroundColor Red
            }
        }
    } else {
        Write-Host "No containers running" -ForegroundColor Red
    }
} catch {
    Write-Host "Cannot access Docker. Is Docker running?" -ForegroundColor Red
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Test Summary" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

if ($healthyCount -eq $totalServices) {
    Write-Host "✓ All microservices are healthy!" -ForegroundColor Green
    Write-Host "✓ System is ready for API testing" -ForegroundColor Green
} else {
    Write-Host "⚠ Some services are not responding" -ForegroundColor Yellow
    Write-Host "  Check Docker logs for details" -ForegroundColor Yellow
}

Write-Host "`nFor detailed API documentation, see:" -ForegroundColor Cyan
Write-Host "  API_TESTING_GUIDE.md`n" -ForegroundColor White

# Offer to open API guide
$openGuide = Read-Host "Open API Testing Guide? (Y/N)"
if ($openGuide -eq "Y" -or $openGuide -eq "y") {
    Start-Process "API_TESTING_GUIDE.md"
}
