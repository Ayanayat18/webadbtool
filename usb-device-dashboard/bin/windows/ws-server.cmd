@echo off
setlocal
set SCRIPT_DIR=%~dp0
set ROOT=%SCRIPT_DIR%..\..
php "%ROOT%\ws-server.php" %*
exit /b %ERRORLEVEL%