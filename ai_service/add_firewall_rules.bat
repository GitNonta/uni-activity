@echo off
REM add_firewall_rules.bat - MUST run elevated (as Administrator).
REM Allows the app server (192.168.1.222) to reach this PC's AI face
REM service on TCP 8001, and this PC's AI log stream is outbound (no rule
REM needed); UDP 9997 is for the reverse direction (monitor probes).
netsh advfirewall firewall delete rule name="face_dx AI server 8001" >nul 2>&1
netsh advfirewall firewall add rule name="face_dx AI server 8001" dir=in action=allow protocol=TCP localport=8001 remoteip=192.168.1.222
netsh advfirewall firewall delete rule name="face_dx AI monitor UDP 9997" >nul 2>&1
netsh advfirewall firewall add rule name="face_dx AI monitor UDP 9997" dir=in action=allow protocol=UDP localport=9997 remoteip=192.168.1.222
echo.
echo Rules added. You can close this window.
pause
