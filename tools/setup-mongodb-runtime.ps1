param(
    [string] $PhpBinary = 'D:\Config_environment\laragon\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe',
    [string] $ComposerPhar = 'D:\Config_environment\laragon\laragon\bin\composer\composer.phar'
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$extensionPath = Join-Path $projectRoot '.runtime\php-mongodb'
$archiveName = 'php_mongodb-2.5.5-8.3-ts-vs16-x86_64.zip'
$archivePath = Join-Path $extensionPath $archiveName
$expectedHash = 'F94D4DA6A8FB126FB2A234F0D309A20B5C2E69FBE1805A4DFD2FFE570389FC59'
$downloadUri = "https://github.com/mongodb/mongo-php-driver/releases/download/2.5.5/$archiveName"
$phpPath = (Resolve-Path -LiteralPath $PhpBinary -ErrorAction Stop).Path
$composerPath = (Resolve-Path -LiteralPath $ComposerPhar -ErrorAction Stop).Path
$phpAbi = & $phpPath -n -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION."|".PHP_ZTS."|".PHP_INT_SIZE;'
if ($LASTEXITCODE -ne 0 -or $phpAbi -ne '8.3|1|8') {
    throw 'The pinned Windows MongoDB extension requires PHP 8.3 thread-safe x64.'
}

New-Item -ItemType Directory -Path $extensionPath -Force | Out-Null
if (-not (Test-Path -LiteralPath $archivePath)) {
    Invoke-WebRequest -Uri $downloadUri -OutFile $archivePath
}
if ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash -ne $expectedHash) {
    throw 'The MongoDB extension archive does not match its official release SHA256.'
}
$dllPath = Join-Path $extensionPath 'php_mongodb-2.5.5-8.3-ts-vs16-x86_64.dll'
$expectedDllHash = 'BD7F354FC77D4C79CC1F188AC40AF5A17A366A7BB928E1164369C1D8C499E425'
if (-not (Test-Path -LiteralPath $dllPath) -or (Get-FileHash -LiteralPath $dllPath -Algorithm SHA256).Hash -ne $expectedDllHash) {
    try {
        Expand-Archive -LiteralPath $archivePath -DestinationPath $extensionPath -Force
    } catch {
        throw 'Could not install the local MongoDB DLL. Stop PHP processes using this clone before replacing a different DLL.'
    }
}
if ((Get-FileHash -LiteralPath $dllPath -Algorithm SHA256).Hash -ne $expectedDllHash) {
    throw 'The extracted MongoDB DLL does not match the pinned official release.'
}
$commandPhpPath = $phpPath.Replace('%', '%%')
$wrapper = @"
@echo off
"$commandPhpPath" -d "extension=%~dp0..\.runtime\php-mongodb\php_mongodb-2.5.5-8.3-ts-vs16-x86_64.dll" -d extension=zip %*
exit /b %errorlevel%
"@
Set-Content -LiteralPath "$PSScriptRoot\php-mongodb.cmd" -Value $wrapper -Encoding ascii
& "$PSScriptRoot\php-mongodb.cmd" --ri mongodb
if ($LASTEXITCODE -ne 0) {
    throw 'The MongoDB PHP extension could not load.'
}

Push-Location (Join-Path $projectRoot 'laravel')
try {
    & "$PSScriptRoot\php-mongodb.cmd" $composerPath install --no-scripts --no-interaction --no-progress
    if ($LASTEXITCODE -ne 0) {
        throw 'Composer dependency installation failed.'
    }
    & "$PSScriptRoot\php-mongodb.cmd" $composerPath check-platform-reqs
    if ($LASTEXITCODE -ne 0) {
        throw 'The PHP runtime does not meet the installed dependency requirements.'
    }
} finally {
    Pop-Location
}
