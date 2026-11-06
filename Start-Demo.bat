@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-demo.ps1"
if errorlevel 1 (
  echo.
  echo Khoi dong that bai. Xem thong bao loi o tren.
  pause
)
endlocal
