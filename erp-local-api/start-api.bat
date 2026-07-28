@echo off
setlocal
cd /d "%~dp0"
if not exist ".env" copy ".env.example" ".env"
php -S 0.0.0.0:8088 -t public
