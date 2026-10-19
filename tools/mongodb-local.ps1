param(
    [ValidateSet('Start', 'Status', 'Stop')]
    [string] $Action = 'Start'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimePath = Join-Path $projectRoot '.runtime\mongodb'
$dataPath = Join-Path $runtimePath 'data'
$pidPath = Join-Path $runtimePath 'mongod.pid'
$logPath = Join-Path $runtimePath 'mongod.log'
$mongoExecutable = 'C:\Program Files\MongoDB\Server\8.3\bin\mongod.exe'
$mongoShell = (Get-Command mongosh -ErrorAction Stop).Source
$directUri = 'mongodb://127.0.0.1:27028/admin?directConnection=true&serverSelectionTimeoutMS=3000'

$ownedProcess = $null
if (Test-Path -LiteralPath $pidPath) {
    $savedPid = [int](Get-Content -LiteralPath $pidPath -Raw)
    $candidateProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $savedPid" -ErrorAction SilentlyContinue
    if ($candidateProcess -and $candidateProcess.ExecutablePath -eq $mongoExecutable -and $candidateProcess.CommandLine.Contains($dataPath)) {
        $ownedProcess = $candidateProcess
    }
}

if ($Action -eq 'Stop') {
    if ($ownedProcess) {
        & $mongoShell $directUri --quiet --eval 'try { db.getSiblingDB("admin").runCommand({shutdown:1}); } catch (error) { if (error.name !== "MongoNetworkError") throw error; }'
        $processHandle = Get-Process -Id $ownedProcess.ProcessId -ErrorAction SilentlyContinue
        if ($processHandle -and -not $processHandle.WaitForExit(15000)) {
            throw 'MongoDB did not stop within 15 seconds; inspect its local log.'
        }
    }
    Remove-Item -LiteralPath $pidPath -ErrorAction SilentlyContinue
    Write-Output 'Isolated MongoDB is stopped.'
    exit 0
}

if ($Action -eq 'Start' -and -not $ownedProcess) {
    if (Get-NetTCPConnection -State Listen -LocalPort 27028 -ErrorAction SilentlyContinue) {
        throw 'Port 27028 is occupied by a process not owned by this clone.'
    }
    New-Item -ItemType Directory -Path $dataPath -Force | Out-Null
    $mongoArguments = @('--dbpath', "`"$dataPath`"", '--logpath', "`"$logPath`"", '--logappend', '--bind_ip', '127.0.0.1', '--port', '27028', '--replSet', 'cmcMongoRs')
    $startedProcess = Start-Process -FilePath $mongoExecutable -ArgumentList $mongoArguments -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath $pidPath -Value $startedProcess.Id -Encoding ascii
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if ($startedProcess.HasExited) {
            throw "MongoDB exited unexpectedly; inspect $logPath."
        }
        if (Get-NetTCPConnection -State Listen -LocalPort 27028 -ErrorAction SilentlyContinue) {
            break
        }
        Start-Sleep -Milliseconds 250
    }
}

if ($Action -eq 'Start') {
    $initializeReplicaSet = 'try { void rs.status(); } catch (error) { if (error.codeName !== "NotYetInitialized") throw error; rs.initiate({_id:"cmcMongoRs",members:[{_id:0,host:"127.0.0.1:27028"}]}); } for (let attempt=0; attempt<60; attempt++) { if (db.hello().isWritablePrimary) break; sleep(250); } if (!db.hello().isWritablePrimary) throw new Error("Replica set did not become primary");'
    & $mongoShell $directUri --quiet --eval $initializeReplicaSet
    if ($LASTEXITCODE -ne 0) {
        throw 'Replica set initialization failed.'
    }
}

& $mongoShell $directUri --quiet --eval 'const state=rs.status(); const hello=db.hello(); print(JSON.stringify({set:state.set,primary:hello.isWritablePrimary,address:hello.me,pid:Number(db.serverStatus().pid),uri:"mongodb://127.0.0.1:27028/?replicaSet=cmcMongoRs"}));'
if ($LASTEXITCODE -ne 0) {
    throw 'Isolated MongoDB status check failed.'
}
