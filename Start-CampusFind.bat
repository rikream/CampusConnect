@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Start-CampusFind.ps1"
if errorlevel 1 (
    echo.
    echo CampusFind could not start. See the error above.
    pause
)
