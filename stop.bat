@echo off

taskkill /F /FI "WINDOWTITLE eq LARAVEL_SERVER_WINDOW" > nul 2>&1
taskkill /F /FI "WINDOWTITLE eq LARAVEL_QUEUE_WINDOW" > nul 2>&1
taskkill /F /IM php.exe > nul 2>&1

echo All stopped
pause