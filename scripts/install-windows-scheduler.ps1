# Install a Windows Task Scheduler job that runs `php artisan schedule:run` every minute.
# This is the production-style alternative to keeping `schedule:work` open in a terminal.
#
# Run once (PowerShell as your user):
#   Set-ExecutionPolicy -Scope Process Bypass
#   .\scripts\install-windows-scheduler.ps1
#
# Remove later:
#   Unregister-ScheduledTask -TaskName "MedFlow-Laravel-Scheduler" -Confirm:$false

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$php = (Get-Command php -ErrorAction SilentlyContinue)?.Source
if (-not $php) {
    $phpCandidates = @(
        "C:\xampp\php\php.exe",
        "C:\php\php.exe"
    )
    foreach ($c in $phpCandidates) {
        if (Test-Path $c) { $php = $c; break }
    }
}

if (-not $php) {
    Write-Error "php.exe not found. Add PHP to PATH or edit this script."
}

$taskName = "MedFlow-Laravel-Scheduler"
$action = New-ScheduledTaskAction -Execute $php -Argument "artisan schedule:run" -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew

Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Description "Runs Laravel schedule:run every minute for MedFlow (ambulance SOS expiry, appointments)." | Out-Null

Write-Host "Installed scheduled task '$taskName'."
Write-Host "PHP: $php"
Write-Host "Dir: $projectRoot"
Write-Host "You can close schedule:work terminals; Windows will run the schedule every minute while you are logged in."
