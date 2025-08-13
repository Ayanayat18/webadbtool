@echo off
setlocal
set SCRIPT_DIR=%~dp0
set BIN=%SCRIPT_DIR%fastboot.exe
if exist "%BIN%" (
	"%BIN%" %*
	exit /b %ERRORLEVEL%
)
where fastboot.exe >nul 2>nul
if %ERRORLEVEL%==0 (
	for /f "usebackq delims=" %%i in (`where fastboot.exe`) do (
		set FOUND=%%i
		goto :RUN
	)
)
echo fastboot not found. Place fastboot.exe next to this script or install in PATH. 1>&2
exit /b 127
:RUN
"%FOUND%" %*
exit /b %ERRORLEVEL%