@echo off
REM Wrapper for the Absensi Karyawan Laravel scheduler.
REM Invoked every 5 minutes by Windows Task Scheduler; runs the tasks
REM registered in routes/console.php (prune selfies 02:10, backup 02:40).

setlocal

set "PHP_BIN=C:\Users\User\.config\herd\bin\php84\php.exe"
set "APP_DIR=D:\absensi_api"
set "LOG_FILE=%APP_DIR%\storage\logs\scheduler.log"

cd /d "%APP_DIR%"

if not exist "%APP_DIR%\storage\logs" mkdir "%APP_DIR%\storage\logs"

echo [%date% %time%] scheduler tick >> "%LOG_FILE%"

"%PHP_BIN%" "%APP_DIR%\artisan" schedule:run >> "%LOG_FILE%" 2>&1

if errorlevel 1 echo [%date% %time%] schedule:run FAILED >> "%LOG_FILE%"

endlocal
