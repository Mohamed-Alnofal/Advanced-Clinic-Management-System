@echo off
echo Creating ACMS test database (acms_testing)...
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS acms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if %ERRORLEVEL% NEQ 0 (
    echo Failed. Make sure XAMPP MySQL is running.
    exit /b 1
)
echo Done. Run: composer test
