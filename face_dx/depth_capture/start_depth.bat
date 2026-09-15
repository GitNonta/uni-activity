@echo off
REM start_depth.bat - launch the live facial-depth 3D viewer on demand.
REM Does nothing costly if already running; opens the browser at the end.
REM Requires depth_capture\model-small.onnx (see README in the same folder).

setlocal
cd /d "%~dp0\.."

curl -s -m 2 http://127.0.0.1:8086/healthz >nul 2>&1
if not errorlevel 1 goto open

echo [depth] starting depth server on port 8086 (model load ~5s)...
start "face_dx depth" /min python depth_capture\depth_server.py --port 8086
timeout /t 4 /nobreak >nul

:open
start "" http://127.0.0.1:8086/
endlocal
