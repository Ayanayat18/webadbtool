@echo off
setlocal
set SCRIPT_DIR=%~dp0
set BIN=%SCRIPT_DIR%mtp-detect.exe
if exist "%BIN%" (
	"%BIN%" %*
	exit /b %ERRORLEVEL%
)
where mtp-detect.exe >nul 2>nul
if %ERRORLEVEL%==0 (
	for /f "usebackq delims=" %%i in (`where mtp-detect.exe`) do (
		set FOUND=%%i
		goto :RUN
	)
)
echo mtp-detect not found. Place mtp-detect.exe next to this script or install in PATH. 1>&2
exit /b 127
:RUN
"%FOUND%" %*
exit /b %ERRORLEVEL%