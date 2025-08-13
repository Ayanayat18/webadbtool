@echo off
setlocal ENABLEDELAYEDEXPANSION
set SCRIPT_DIR=%~dp0
set BIN=%SCRIPT_DIR%adb.exe
if exist "%BIN%" (
	"%BIN%" %*
	exit /b %ERRORLEVEL%
)
where adb.exe >nul 2>nul
if %ERRORLEVEL%==0 (
	for /f "usebackq delims=" %%i in (`where adb.exe`) do (
		set FOUND=%%i
		goto :RUN
	)
)
echo adb not found. Place Platform-Tools adb.exe next to this script or install in PATH. 1>&2
exit /b 127
:RUN
"%FOUND%" %*
exit /b %ERRORLEVEL%