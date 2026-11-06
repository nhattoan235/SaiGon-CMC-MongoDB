param([switch] $NoBrowser)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$composeFile = Join-Path $repoRoot 'compose.demo.yaml'
$envFile = Join-Path $repoRoot 'laravel\.env'
$exampleEnv = Join-Path $repoRoot 'laravel\.env.example'
$runtimeDirectory = Join-Path $repoRoot '.runtime'
$portFile = Join-Path $runtimeDirectory 'demo-port.txt'
$projectName = 'cmc-demo'

function Invoke-Docker {
    & docker $args
    if ($LASTEXITCODE -ne 0) {
        throw "Docker bị lỗi khi chạy: docker $($args -join ' ')"
    }
}

function Test-PortAvailable {
    param([int] $Port)
    $listener = $null
    try {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
        $listener.Start()
        return $true
    } catch {
        return $false
    } finally {
        if ($listener) { $listener.Stop() }
    }
}

function Get-DemoPort {
    $existingApp = (& docker compose -p $projectName -f $composeFile ps -q app-mongodb 2>$null | Select-Object -First 1)
    if ($existingApp) {
        $isRunning = (& docker inspect --format '{{.State.Running}}' $existingApp 2>$null | Select-Object -First 1)
        if ($isRunning -eq 'true' -and (Test-Path -LiteralPath $portFile)) {
            $runningPort = 0
            if ([int]::TryParse((Get-Content -LiteralPath $portFile -Raw).Trim(), [ref] $runningPort)) {
                return $runningPort
            }
        }
    }

    if (Test-Path -LiteralPath $portFile) {
        $savedPort = 0
        if ([int]::TryParse((Get-Content -LiteralPath $portFile -Raw).Trim(), [ref] $savedPort) -and (Test-PortAvailable $savedPort)) {
            return $savedPort
        }
    }

    for ($candidate = 8000; $candidate -le 8099; $candidate++) {
        if (Test-PortAvailable $candidate) {
            return $candidate
        }
    }
    throw 'Không tìm được cổng web trống trong khoảng 8000–8099.'
}

Push-Location $repoRoot
try {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Máy này chưa cài Docker Desktop. Cài Docker Desktop, khởi động nó rồi mở lại Start-Demo.bat.'
    }

    $dockerReady = $false
    try {
        & docker info --format '{{.ServerVersion}}' *> $null
        $dockerReady = ($LASTEXITCODE -eq 0)
    } catch {
        $dockerReady = $false
    }
    if (-not $dockerReady) {
        $dockerDesktop = @(
            (Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'),
            (Join-Path $env:LOCALAPPDATA 'Programs\Docker\Docker\Docker Desktop.exe')
        ) | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
        if ($dockerDesktop) {
            Write-Host 'Đang mở Docker Desktop và chờ máy chủ sẵn sàng...'
            Start-Process -FilePath $dockerDesktop -WindowStyle Hidden
        } else {
            throw 'Docker Desktop chưa chạy. Hãy mở Docker Desktop rồi chạy lại Start-Demo.bat.'
        }
        $ready = $false
        for ($attempt = 0; $attempt -lt 60; $attempt++) {
            Start-Sleep -Seconds 2
            try {
                & docker info --format '{{.ServerVersion}}' *> $null
                if ($LASTEXITCODE -eq 0) { $ready = $true; break }
            } catch { }
        }
        if (-not $ready) { throw 'Docker Desktop chưa sẵn sàng sau 2 phút.' }
    }

    if (-not (Test-Path -LiteralPath $envFile)) {
        Copy-Item -LiteralPath $exampleEnv -Destination $envFile
        Write-Host 'Đã tạo cấu hình riêng trên máy này từ laravel/.env.example.'
    }
    Write-Host 'Đang kiểm tra môi trường Laravel...'
    Invoke-Docker compose -p $projectName -f $composeFile build app-mongodb
    $composerManifest = Join-Path $repoRoot 'laravel\composer.json'
    $composerLock = Join-Path $repoRoot 'laravel\composer.lock'
    $composerMarker = Join-Path $repoRoot 'laravel\vendor\.cmc-demo-dependencies'
    $composerSignature = (Get-FileHash -LiteralPath $composerManifest).Hash + ':' + (Get-FileHash -LiteralPath $composerLock).Hash
    $installComposer = -not (Test-Path -LiteralPath (Join-Path $repoRoot 'laravel\vendor\autoload.php'))
    if (-not $installComposer) {
        $installComposer = -not (Test-Path -LiteralPath $composerMarker) -or (Get-Content -LiteralPath $composerMarker -Raw).Trim() -ne $composerSignature
    }
    if ($installComposer) {
        Write-Host 'Đang cài thư viện PHP lần đầu hoặc sau khi mã nguồn thay đổi...'
        Invoke-Docker compose -p $projectName -f $composeFile run --rm --no-deps app-mongodb composer install --no-interaction --prefer-dist
        Set-Content -LiteralPath $composerMarker -Value $composerSignature -NoNewline
    }

    $assetManifest = Join-Path $repoRoot 'laravel\public\build\manifest.json'
    $assetInputs = @(
        (Join-Path $repoRoot 'laravel\package.json'),
        (Join-Path $repoRoot 'laravel\package-lock.json'),
        (Join-Path $repoRoot 'laravel\vite.config.js'),
        (Join-Path $repoRoot 'laravel\resources\js'),
        (Join-Path $repoRoot 'laravel\resources\css')
    ) | Where-Object { Test-Path -LiteralPath $_ }
    $buildAssets = -not (Test-Path -LiteralPath $assetManifest)
    if (-not $buildAssets) {
        $manifestTime = (Get-Item -LiteralPath $assetManifest).LastWriteTimeUtc
        $buildAssets = [bool] ($assetInputs | ForEach-Object {
            if ((Get-Item -LiteralPath $_).PSIsContainer) {
                Get-ChildItem -LiteralPath $_ -File -Recurse
            } else {
                Get-Item -LiteralPath $_
            }
        } | Where-Object { $_.LastWriteTimeUtc -gt $manifestTime } | Select-Object -First 1)
    }
    if ($buildAssets) {
        Write-Host 'Đang tạo hoặc cập nhật giao diện web...'
        Invoke-Docker compose -p $projectName -f $composeFile --profile tools run --rm --no-deps assets
    }

    $appKeyLine = Get-Content -LiteralPath $envFile | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1
    if (-not $appKeyLine -or $appKeyLine -match '^APP_KEY=\s*$') {
        Write-Host 'Đang tạo khóa ứng dụng riêng cho máy này...'
        Invoke-Docker compose -p $projectName -f $composeFile run --rm --no-deps app-mongodb php artisan key:generate --no-interaction
    }

    New-Item -ItemType Directory -Path $runtimeDirectory -Force | Out-Null
    $port = Get-DemoPort
    Set-Content -LiteralPath $portFile -Value $port -NoNewline
    $env:CMC_DEMO_PORT = [string] $port

    Write-Host 'Đang khởi động MongoDB, Ollama và Qdrant...'
    Invoke-Docker compose -p $projectName -f $composeFile up -d mongo mongo-init ollama qdrant

    $replicaReady = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        try {
            & docker compose -p $projectName -f $composeFile exec -T mongo mongosh --quiet --eval "if (db.hello().isWritablePrimary) { print('ready') } else { quit(1) }" *> $null
            if ($LASTEXITCODE -eq 0) { $replicaReady = $true; break }
        } catch { }
        Start-Sleep -Seconds 2
    }
    if (-not $replicaReady) { throw 'MongoDB chưa sẵn sàng sau 2 phút.' }

    Write-Host 'Đang cài cấu trúc dữ liệu và tạo dữ liệu demo (không xóa dữ liệu đã có)...'
    Invoke-Docker compose -p $projectName -f $composeFile run --rm --no-deps app-mongodb php artisan cmms:mongo-install --no-interaction
    Invoke-Docker compose -p $projectName -f $composeFile run --rm --no-deps app-mongodb php artisan db:seed --force --no-interaction

    $ollamaReady = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        try {
            & docker compose -p $projectName -f $composeFile exec -T ollama ollama list *> $null
            if ($LASTEXITCODE -eq 0) { $ollamaReady = $true; break }
        } catch { }
        Start-Sleep -Seconds 2
    }

    $ragReady = $false
    if ($ollamaReady) {
        $installedModels = & docker compose -p $projectName -f $composeFile exec -T ollama ollama list
        $hasEmbeddingModel = [bool] ($installedModels | Where-Object { $_ -match '^\s*bge-m3(?::latest)?\s' } | Select-Object -First 1)
        if (-not $hasEmbeddingModel) {
            Write-Host 'Đang tải mô hình AI BGE-M3 lần đầu; có thể mất vài phút và cần Internet...'
            try {
                & docker compose -p $projectName -f $composeFile exec -T ollama ollama pull bge-m3
                $ragReady = ($LASTEXITCODE -eq 0)
            } catch {
                Write-Warning 'Không tải được mô hình BGE-M3.'
                $ragReady = $false
            }
        } else {
            $ragReady = $true
        }
    }

    if ($ragReady) {
        Write-Host 'Đang chờ Qdrant sẵn sàng cho RAG...'
        try {
            Invoke-Docker compose -p $projectName -f $composeFile --profile tools run --rm --no-deps qdrant-wait
            $ragReady = ($LASTEXITCODE -eq 0)
        } catch {
            $ragReady = $false
        }
    }

    Write-Host 'Đang mở trang web và các tiến trình nền...'
    Invoke-Docker compose -p $projectName -f $composeFile up -d app-mongodb queue-worker scheduler
    if ($ragReady) {
        Invoke-Docker compose -p $projectName -f $composeFile run --rm --no-deps app-mongodb php artisan rag:import-legacy --index --no-interaction
        Invoke-Docker compose -p $projectName -f $composeFile up -d rag-worker
    } else {
        Write-Warning 'RAG chưa sẵn sàng (mô hình Ollama hoặc Qdrant chưa chạy). Trang web vẫn mở; thử Start-Demo.bat lại sau khi có Internet.'
    }

    $url = "http://localhost:$port"
    $webReady = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 3
            if ($response.StatusCode -lt 400) { $webReady = $true; break }
            Start-Sleep -Seconds 2
        } catch {
            Start-Sleep -Seconds 2
        }
    }
    if (-not $webReady) {
        & docker compose -p $projectName -f $composeFile logs --tail 60 app-mongodb
        throw 'Ứng dụng chưa mở được sau 2 phút. Xem log Docker phía trên để biết nguyên nhân.'
    }

    Write-Host "Đã khởi động bản demo: $url"
    Write-Host 'Tài khoản và mật khẩu xem trong DEMO-GUIDE.md.'
    if (-not $NoBrowser) {
        Start-Process $url
    }
} finally {
    Pop-Location
}
