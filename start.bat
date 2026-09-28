@echo off
REM Start API + queue worker + Laravel scheduler.
REM Always use XAMPP PHP with pdo_mysql (NOT windowsXamppPhp).
REM Usage: start.bat [host] [port]
REM Example: start.bat 192.168.1.6 8000

set HOST=%1
if "%HOST%"=="" set HOST=127.0.0.1

set PORT=%2
if "%PORT%"=="" set PORT=8000

set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

cd /d "%~dp0"

start "LARAVEL_SERVER" cmd /k ""%PHP%" artisan serve --host=%HOST% --port=%PORT%"
start "LARAVEL_QUEUE" cmd /k ""%PHP%" artisan queue:work --tries=3 --timeout=120 --sleep=1"
start "LARAVEL_SCHEDULER" cmd /k ""%PHP%" artisan schedule:work"

echo.
echo  MedFlow backend started:
echo    PHP      %PHP%
echo    Server   http://%HOST%:%PORT%
echo    Queue    queue:work
echo    Schedule schedule:work
echo.
echo  Close the three windows to stop everything.
echo.
pause
