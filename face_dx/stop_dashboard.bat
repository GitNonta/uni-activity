@echo off
REM stop_dashboard.bat - stop the monitor started by start_dashboard.bat
REM (kills the minimized "face_dx monitor" console, plus any python process
REM  whose command line runs monitor_server.py, as a fallback).

taskkill /F /FI "WINDOWTITLE eq face_dx monitor*" >nul 2>&1
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='python.exe'\" | Where-Object { $_.CommandLine -match 'monitor_server' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
echo [dashboard] stopped.
ping -n 3 127.0.0.1 >nul
