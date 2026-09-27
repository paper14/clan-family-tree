@echo off
rem Clan Family Tree - DEMO: the three sample clans (Santos, Dulnuan, Menis) in the clan_demo database.
rem The family registry is not touched. Reset the sample data with: php artisan app:demo
cd /d "%~dp0"
set "PHP_INI_SCAN_DIR=%~dp0php"
set "DB_DATABASE=clan_demo"
start "Clan Family Tree (demo)" /min php artisan serve --host=127.0.0.1 --port=8001 --no-reload
timeout /t 3 /nobreak >nul
start "" "http://127.0.0.1:8001/"
