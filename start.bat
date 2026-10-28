@echo off
title Smart Farmer Server
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo.
  echo  PHP was not found on this computer.
  echo  Install PHP 8.1 or newer ^(https://windows.php.net/download^) or XAMPP,
  echo  then add PHP to your PATH and run this file again.
  echo.
  pause
  exit /b 1
)
echo.
echo  ==============  SMART FARMER  ==============
echo   This computer :  http://localhost:8000
echo   Same Wi-Fi    :  http://YOUR-IP:8000   ^(your IP is listed below^)
echo  --------------------------------------------
ipconfig | findstr /i "IPv4"
echo  --------------------------------------------
echo   Admin account : configured with SMARTFARMER_ADMIN_EMAIL and SMARTFARMER_ADMIN_PASSWORD
echo   Stop server   :  press Ctrl+C
echo  ============================================
echo.
php -S 0.0.0.0:8000 router.php
pause
