@echo off
REM start_dashboard.bat - launch the face_dx run dashboard on demand.
REM Does nothing costly if already running; opens the browser at the end.
REM Note: viewing a FINISHED run re-parses its state file on first load
REM (~516 MB; allow up to a minute before all charts are full).

setlocal EnableDelayedExpansion
cd /d "%~dp0"

curl -s -m 2 http://127.0.0.1:8787/healthz >nul 2>&1
if not errorlevel 1 goto open

echo [dashboard] starting monitor on port 8787 ...
start "face_dx monitor" /min cmd /c "python monitor_server.py --port 8787 --out-report reports/celeba_512d_full_fp16.json --state reports/celeba_512d_full_fp16.json.state.jsonl --total 202599 > reports\monitor_server.log 2>&1"

echo (first attach to a FINISHED run parses its ~516 MB state file -
echo  allow up to two minutes before the server responds)
set /a tries=0
:wait
REM ping-delay (PATH-safe; cmd's timeout is shadowed by GNU timeout in Git Bash)
ping -n 3 127.0.0.1 >nul
curl -s -m 2 http://127.0.0.1:8787/healthz >nul 2>&1
if not errorlevel 1 goto open
set /a tries+=1
if %tries% lss 60 goto wait

echo [dashboard] failed to start - check reports\monitor_server.log
pause
exit /b 1

:open
start "" http://127.0.0.1:8787/
echo [dashboard] ready at http://127.0.0.1:8787/
ping -n 3 127.0.0.1 >nul
