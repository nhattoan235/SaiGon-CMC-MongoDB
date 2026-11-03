param([ValidateSet('Start','Status','Stop','Install','Uninstall','Watch','Tick')][string] $Action='Status')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$applicationPath=Join-Path $projectRoot 'laravel'
$runtimeRoot=Join-Path $projectRoot '.runtime\backups'
$watchPidPath=Join-Path $runtimeRoot 'watch.pid'
$workerPidPath=Join-Path $runtimeRoot 'worker.pid'
$scriptPath=$PSCommandPath
$phpExecutable='D:\Config_environment\laragon\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mongoExtension=Join-Path $projectRoot '.runtime\php-mongodb\php_mongodb-2.5.5-8.3-ts-vs16-x86_64.dll'
$artisanPath=Join-Path $applicationPath 'artisan'
$powerShellExecutable=Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$taskName='SaiGon-CMC-MongoDB-Backup'
New-Item -ItemType Directory -Path $runtimeRoot -Force | Out-Null
function Get-OwnedProcess([string]$PidFile,[string]$ExpectedExecutable,[string]$Marker) {
    if(-not(Test-Path -LiteralPath $PidFile)){return $null}
    $savedProcessId=0
    if(-not[int]::TryParse((Get-Content -LiteralPath $PidFile -Raw).Trim(),[ref]$savedProcessId)){return $null}
    $candidate=Get-CimInstance Win32_Process -Filter "ProcessId = $savedProcessId" -ErrorAction SilentlyContinue
    if($candidate -and $candidate.ExecutablePath -eq $ExpectedExecutable -and $candidate.CommandLine.Contains($Marker)){return $candidate}
    return $null
}
function Get-Watcher { Get-OwnedProcess $watchPidPath $powerShellExecutable $scriptPath }
function Get-Worker { Get-OwnedProcess $workerPidPath $phpExecutable $artisanPath }
function Invoke-BackupTick {
    $worker=Get-Worker
    if($worker){
        foreach($runPath in Get-ChildItem -LiteralPath $runtimeRoot -Filter run.json -Recurse -ErrorAction SilentlyContinue){
            $run=Get-Content -LiteralPath $runPath.FullName -Raw | ConvertFrom-Json
            if(@('capturing','packing','uploading','verifying') -contains $run.state){
                if($run.attempt_started_at -and ([datetimeoffset]::Now-[datetimeoffset]::Parse($run.attempt_started_at)).TotalSeconds -gt 1250){Stop-Process -Id $worker.ProcessId -Force; $worker=$null; break}
            }
        }
    }
    Push-Location $applicationPath
    try {
        & $phpExecutable -d "extension=$mongoExtension" -d extension=zip $artisanPath cmms:backup-tick
        if($LASTEXITCODE -ne 0){Write-Warning 'Backup tick failed; inspect admin status/configuration.'}
    } finally {Pop-Location}
    if(-not $worker){
        $arguments=@('-d',"`"extension=$mongoExtension`"",'-d','extension=zip',"`"$artisanPath`"",'queue:work','backup','--queue=backup','--sleep=3','--tries=1','--timeout=1200','--max-time=3600','--memory=192')
        $started=Start-Process -FilePath $phpExecutable -ArgumentList $arguments -WorkingDirectory $applicationPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeRoot 'worker.out.log') -RedirectStandardError (Join-Path $runtimeRoot 'worker.err.log')
        Set-Content -LiteralPath $workerPidPath -Value $started.Id -Encoding ascii
        $worker=Get-Worker
    }
    if($worker){
        $heartbeat=@{at=[datetimeoffset]::Now.ToString('o');pid=$worker.ProcessId}|ConvertTo-Json -Compress
        $temporary=Join-Path $runtimeRoot 'worker-heartbeat.tmp'
        Set-Content -LiteralPath $temporary -Value $heartbeat -Encoding ascii
        Move-Item -LiteralPath $temporary -Destination (Join-Path $runtimeRoot 'worker-heartbeat.json') -Force
    }
}
if($Action -eq 'Status'){
    [pscustomobject]@{WatcherRunning=[bool](Get-Watcher);WorkerRunning=[bool](Get-Worker);TaskInstalled=[bool](Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue);Runtime=$runtimeRoot}|ConvertTo-Json -Compress
    exit 0
}
if($Action -eq 'Stop' -or $Action -eq 'Uninstall'){
    foreach($owned in @((Get-Watcher),(Get-Worker))){if($owned){Stop-Process -Id $owned.ProcessId -Force}}
    Remove-Item -LiteralPath $watchPidPath,$workerPidPath -ErrorAction SilentlyContinue
    if($Action -eq 'Uninstall'){Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue}
    Write-Output 'Owned backup runner stopped; backup files kept.'
    exit 0
}
if($Action -eq 'Install'){
    $userIdentity=[System.Security.Principal.WindowsIdentity]::GetCurrent().Name
    $taskAction=New-ScheduledTaskAction -Execute $powerShellExecutable -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptPath`" -Action Start" -WorkingDirectory $projectRoot
    $trigger=New-ScheduledTaskTrigger -AtLogOn -User $userIdentity
    $principal=New-ScheduledTaskPrincipal -UserId $userIdentity -LogonType Interactive -RunLevel Limited
    $settings=New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
    Register-ScheduledTask -TaskName $taskName -Action $taskAction -Trigger $trigger -Principal $principal -Settings $settings -Description 'Local backup runner, no cloud Billing. Runs while user logged in.' -Force | Out-Null
    Write-Output 'Installed logon task; does not wake powered-off computer.'
    exit 0
}
if($Action -eq 'Tick'){Invoke-BackupTick;exit 0}
if($Action -eq 'Start'){
    if(Get-Watcher){Write-Output 'Backup watcher already running.';exit 0}
    $arguments=@('-NoProfile','-WindowStyle','Hidden','-ExecutionPolicy','Bypass','-File',"`"$scriptPath`"",'-Action','Watch')
    $started=Start-Process -FilePath $powerShellExecutable -ArgumentList $arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeRoot 'watch.out.log') -RedirectStandardError (Join-Path $runtimeRoot 'watch.err.log')
    Set-Content -LiteralPath $watchPidPath -Value $started.Id -Encoding ascii
    Write-Output 'Backup watcher started hidden; schedule stays as configured in admin.'
    exit 0
}
if($Action -eq 'Watch'){
    $mutexName='Local\CMCBackup-'+([System.BitConverter]::ToString([System.Security.Cryptography.SHA256]::Create().ComputeHash([System.Text.Encoding]::UTF8.GetBytes($projectRoot))).Replace('-','').Substring(0,16))
    $mutex=New-Object System.Threading.Mutex($false,$mutexName)
    if(-not $mutex.WaitOne(0)){exit 0}
    try {
        Set-Content -LiteralPath $watchPidPath -Value $PID -Encoding ascii
        & (Join-Path $PSScriptRoot 'mongodb-local.ps1') -Action Start
        while($true){try{Invoke-BackupTick}catch{Write-Warning 'Backup runner error; inspect local status.'};Start-Sleep -Seconds 60}
    } finally {$mutex.ReleaseMutex();$mutex.Dispose()}
}
