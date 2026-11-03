@echo off
"D:\Config_environment\laragon\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -d "extension=%~dp0..\.runtime\php-mongodb\php_mongodb-2.5.5-8.3-ts-vs16-x86_64.dll" -d extension=zip %*
exit /b %errorlevel%
