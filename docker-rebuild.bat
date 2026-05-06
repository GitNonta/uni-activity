@echo off
echo ========================================
echo   Docker Cache Clear and Rebuild
echo ========================================
echo.

echo [1/8] Checking Docker...
docker ps >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker is not running!
    echo Please start Docker Desktop and try again.
    pause
    exit /b 1
)
echo OK: Docker is running
echo.

echo [2/8] Stopping containers...
docker-compose down
echo.

echo [3/8] Removing containers...
for /f "tokens=*" %%i in ('docker ps -aq') do docker rm -f %%i
echo.

echo [4/8] Removing images...
for /f "tokens=*" %%i in ('docker images -q') do docker rmi -f %%i
echo.

echo [5/8] Removing volumes...
for /f "tokens=*" %%i in ('docker volume ls -q') do docker volume rm -f %%i
echo.

echo [6/8] Removing networks...
docker network prune -f
echo.

echo [7/8] Pruning system...
docker system prune -af --volumes
echo.

echo [8/8] Building and starting containers...
echo This may take several minutes...
echo.

docker-compose up -d --build

if errorlevel 1 (
    echo.
    echo ========================================
    echo   Build Failed!
    echo ========================================
    echo.
    echo Check the error messages above.
    pause
    exit /b 1
)

echo.
echo ========================================
echo   Build Complete!
echo ========================================
echo.

echo Waiting for services to start...
timeout /t 10 /nobreak >nul

echo.
echo Container Status:
docker ps
echo.

echo ========================================
echo   Success!
echo ========================================
echo.
echo Services are starting up...
echo Wait 30-60 seconds for all services to be healthy.
echo.
echo Test services with:
echo   test-api-health.ps1
echo.
echo Access application at:
echo   http://localhost:8000
echo.
pause
