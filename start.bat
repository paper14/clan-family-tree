@echo off
rem Clan Family Tree - starts the app on THIS computer only (127.0.0.1) and opens it in the browser.
rem To stop it, close the minimised "Clan Family Tree" window.
cd /d "%~dp0"
rem Raise the photo upload limit (php\clan-family-tree.ini): camera photos are larger than PHP allows by default.
set "PHP_INI_SCAN_DIR=%~dp0php"
start "Clan Family Tree" /min php artisan serve --host=127.0.0.1 --port=8000 --no-reload
timeout /t 3 /nobreak >nul
start "" "http://127.0.0.1:8000/"
