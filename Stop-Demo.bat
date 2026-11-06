@echo off
setlocal
pushd "%~dp0"
docker compose -p cmc-demo -f compose.demo.yaml down --remove-orphans
if errorlevel 1 (
  echo Khong dung duoc ung dung. Hay kiem tra Docker Desktop.
  pause
)
popd
endlocal
