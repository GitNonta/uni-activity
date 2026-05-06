# Setup Ngrok for HTTPS Tunneling
# Configures ngrok to provide HTTPS access to the application

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Setup Ngrok HTTPS Tunnel" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Check if NGROK_AUTHTOKEN exists in .env
$envContent = Get-Content .env -Raw

if ($envContent -match 'NGROK_AUTHTOKEN=(.+)') {
    $token = $matches[1].Trim()
    if ($token) {
        Write-Host "✓ Ngrok auth token found in .env`n" -ForegroundColor Green
    } else {
        Write-Host "⚠ NGROK_AUTHTOKEN is empty in .env`n" -ForegroundColor Yellow
        $needToken = $true
    }
} else {
    Write-Host "⚠ NGROK_AUTHTOKEN not found in .env`n" -ForegroundColor Yellow
    $needToken = $true
}

if ($needToken) {
    Write-Host "To use ngrok, you need an auth token:" -ForegroundColor Cyan
    Write-Host "  1. Sign up at: https://dashboard.ngrok.com/signup" -ForegroundColor White
    Write-Host "  2. Get your token at: https://dashboard.ngrok.com/get-started/your-authtoken" -ForegroundColor White
    Write-Host "  3. Add to .env: NGROK_AUTHTOKEN=your_token_here`n" -ForegroundColor White
    
    $addToken = Read-Host "Do you have a token to add now? (Y/N)"
    
    if ($addToken -eq "Y" -or $addToken -eq "y") {
        $token = Read-Host "Enter your ngrok auth token"
        
        if ($envContent -match 'NGROK_AUTHTOKEN=') {
            $envContent = $envContent -replace 'NGROK_AUTHTOKEN=.*', "NGROK_AUTHTOKEN=$token"
        } else {
            $envContent += "`nNGROK_AUTHTOKEN=$token"
        }
        
        Set-Content .env -Value $envContent -NoNewline
        Write-Host "✓ Token added to .env`n" -ForegroundColor Green
    } else {
        Write-Host "`n⚠ Ngrok will run in free mode (limited features)`n" -ForegroundColor Yellow
    }
}

Write-Host "[1/3] Starting ngrok container..." -ForegroundColor Yellow
docker-compose up -d ngrok

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Ngrok container started`n" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to start ngrok container`n" -ForegroundColor Red
    exit 1
}

Write-Host "[2/3] Waiting for ngrok to initialize..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

Write-Host "[3/3] Fetching ngrok URL..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" -TimeoutSec 10
    
    if ($response.tunnels -and $response.tunnels.Count -gt 0) {
        Write-Host "`n========================================" -ForegroundColor Cyan
        Write-Host "  Ngrok Tunnel Active!" -ForegroundColor Cyan
        Write-Host "========================================`n" -ForegroundColor Cyan
        
        foreach ($tunnel in $response.tunnels) {
            $proto = $tunnel.proto
            $publicUrl = $tunnel.public_url
            $name = $tunnel.name
            
            if ($proto -eq "https") {
                Write-Host "✓ HTTPS URL: $publicUrl" -ForegroundColor Green
            } else {
                Write-Host "  HTTP URL:  $publicUrl" -ForegroundColor White
            }
        }
        
        Write-Host "`nNgrok Dashboard: http://localhost:4040" -ForegroundColor Cyan
        Write-Host "`nYour application is now accessible via HTTPS!`n" -ForegroundColor Green
        
        # Update APP_URL in .env
        $httpsUrl = ($response.tunnels | Where-Object { $_.proto -eq "https" }).public_url
        if ($httpsUrl) {
            Write-Host "Updating APP_URL in .env..." -ForegroundColor Yellow
            $envContent = Get-Content .env -Raw
            $envContent = $envContent -replace 'APP_URL=.*', "APP_URL=$httpsUrl"
            Set-Content .env -Value $envContent -NoNewline
            
            Write-Host "✓ APP_URL updated to: $httpsUrl`n" -ForegroundColor Green
            
            Write-Host "Restarting app container to apply changes..." -ForegroundColor Yellow
            docker-compose restart app | Out-Null
            Write-Host "✓ App restarted`n" -ForegroundColor Green
        }
        
    } else {
        Write-Host "`n⚠ No tunnels found" -ForegroundColor Yellow
        Write-Host "Check ngrok logs: docker logs laravel-ngrok`n" -ForegroundColor White
    }
    
} catch {
    Write-Host "`n⚠ Could not fetch ngrok URL" -ForegroundColor Yellow
    Write-Host "Ngrok may still be starting up...`n" -ForegroundColor White
    Write-Host "Check status:" -ForegroundColor Cyan
    Write-Host "  Dashboard: http://localhost:4040" -ForegroundColor White
    Write-Host "  Logs: docker logs laravel-ngrok`n" -ForegroundColor White
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Quick Commands" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "View ngrok dashboard:" -ForegroundColor Cyan
Write-Host "  http://localhost:4040`n" -ForegroundColor White

Write-Host "View ngrok logs:" -ForegroundColor Cyan
Write-Host "  docker logs laravel-ngrok -f`n" -ForegroundColor White

Write-Host "Stop ngrok:" -ForegroundColor Cyan
Write-Host "  docker-compose stop ngrok`n" -ForegroundColor White

Write-Host "Restart ngrok:" -ForegroundColor Cyan
Write-Host "  docker-compose restart ngrok`n" -ForegroundColor White

Write-Host "========================================`n" -ForegroundColor Cyan
