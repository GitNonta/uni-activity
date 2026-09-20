@echo off
REM start_ai_lan.bat - run the AI face server LAN-accessible (0.0.0.0:8001).
REM Binds all interfaces so the web/app server (192.168.1.222) can reach
REM /extract and /verify. Requires the firewall rule for TCP 8001 inbound.
REM Log: server_lan.log in this directory (appended, never truncated).

setlocal
cd /d "%~dp0"

REM friendly node name shown in monitor log lines ([node:gpu-pc])
set AI_NODE_NAME=gpu-pc

curl -s -m 2 http://127.0.0.1:8001/health >nul 2>&1
if not errorlevel 1 (
    echo [ai] already running on port 8001 - nothing to do.
    exit /b 0
)

echo [ai] starting server on 0.0.0.0:8001 (models load ~10s)...
start "face_dx_ai_lan" /min cmd /c "python run_server_cpu.py >> server_lan.log 2>&1"

REM wait for the health endpoint to come up
set /a tries=0
:wait
timeout /t 2 /nobreak >nul
curl -s -m 2 http://127.0.0.1:8001/health >nul 2>&1
if not errorlevel 1 goto up
set /a tries+=1
if %tries% lss 15 goto wait
echo [ai] ERROR: server did not come up - check server_lan.log
exit /b 1

:up
for /f "delims=" %%i in ('powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -like '192.168.*' } | Select-Object -First 1).IPAddress"') do set LANIP=%%i
if "%LANIP%"=="" set LANIP=127.0.0.1
echo [ai] UP: http://%LANIP%:8001/health
exit /b 0
