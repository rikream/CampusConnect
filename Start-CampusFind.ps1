$ErrorActionPreference = 'Stop'

$phpRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe'
$php = Join-Path $phpRoot 'php.exe'
$phpExt = Join-Path $phpRoot 'ext'
$mariaRoot = Join-Path $env:ProgramFiles 'MariaDB 13.0'
$maria = Join-Path $mariaRoot 'bin\mariadbd.exe'
$mariaConfig = Join-Path $mariaRoot 'data\my.ini'
$stateDirectory = Join-Path $env:LOCALAPPDATA 'CampusFind'
$logDirectory = Join-Path $stateDirectory 'logs'
$stateFile = Join-Path $stateDirectory 'processes.json'
$phpIni = Join-Path $stateDirectory 'php.ini'
$baseUrl = 'http://127.0.0.1:8000/'

foreach ($requiredFile in @($php, (Join-Path $phpExt 'php_pdo_mysql.dll'), (Join-Path $phpExt 'php_fileinfo.dll'), (Join-Path $phpExt 'php_mbstring.dll'), $maria, $mariaConfig)) {
    if (-not (Test-Path -LiteralPath $requiredFile)) {
        throw "Required runtime file was not found: $requiredFile"
    }
}

New-Item -ItemType Directory -Force -Path $logDirectory | Out-Null
@"
extension_dir="$phpExt"
extension=pdo_mysql
extension=fileinfo
extension=mbstring
date.timezone=Asia/Kolkata
"@ | Set-Content -LiteralPath $phpIni -Encoding ASCII

$modules = & $php -c $phpIni -m
if ($LASTEXITCODE -ne 0 -or $modules -notcontains 'pdo_mysql' -or $modules -notcontains 'fileinfo' -or $modules -notcontains 'mbstring') {
    throw 'PHP is missing a required extension. Check the PHP installation.'
}

function Test-LocalPort([int] $Port) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $result = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (-not $result.AsyncWaitHandle.WaitOne(400)) {
            return $false
        }
        $client.EndConnect($result)
        return $true
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

$ownedProcesses = [ordered]@{}
if (Test-Path -LiteralPath $stateFile) {
    try {
        $existingState = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
        foreach ($name in @('database', 'web')) {
            if ($existingState.$name) {
                $existingProcess = Get-Process -Id ([int] $existingState.$name) -ErrorAction SilentlyContinue
                if ($existingProcess) {
                    $ownedProcesses[$name] = [int] $existingState.$name
                }
            }
        }
    } catch {
        Remove-Item -LiteralPath $stateFile -Force
    }
}

if (Test-LocalPort 8000) {
    if ($ownedProcesses.Contains('web')) {
        Start-Process $baseUrl
        Write-Host "CampusFind is already running: $baseUrl"
        exit 0
    }
    throw 'Port 8000 is already in use. Close the other app using it, then try again.'
}

if (-not (Test-LocalPort 3306) -and -not $ownedProcesses.Contains('database')) {
    $databaseProcess = Start-Process -FilePath $maria `
        -ArgumentList "--defaults-file=`"$mariaConfig`" --console --innodb-buffer-pool-size=128M --bind-address=127.0.0.1" `
        -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $logDirectory 'database.log') `
        -RedirectStandardError (Join-Path $logDirectory 'database-error.log')
    $ownedProcesses['database'] = $databaseProcess.Id
    $ownedProcesses | ConvertTo-Json | Set-Content -LiteralPath $stateFile -Encoding ASCII

    $databaseReady = $false
    for ($attempt = 0; $attempt -lt 45; $attempt++) {
        Start-Sleep -Seconds 1
        if (Test-LocalPort 3306) {
            $databaseReady = $true
            break
        }
        if (-not (Get-Process -Id $databaseProcess.Id -ErrorAction SilentlyContinue)) {
            throw "MariaDB stopped during startup. Check $logDirectory\database-error.log"
        }
    }
    if (-not $databaseReady) {
        throw "MariaDB did not start in time. Check $logDirectory\database-error.log"
    }
}

if ($ownedProcesses.Contains('database') -or (Test-LocalPort 3306)) {
    $database = & (Join-Path $mariaRoot 'bin\mysql.exe') --protocol=tcp -h 127.0.0.1 -u root -N -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'campusfind'"
    if ($LASTEXITCODE -ne 0 -or $database -ne 'campusfind') {
        throw 'The campusfind database is missing. Import database\schema.sql into MariaDB first.'
    }
}

$phpProcess = Start-Process -FilePath $php `
    -ArgumentList "-c `"$phpIni`" -S 127.0.0.1:8000 -t `"$PSScriptRoot`"" `
    -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $logDirectory 'web.log') `
    -RedirectStandardError (Join-Path $logDirectory 'web-error.log')
$ownedProcesses['web'] = $phpProcess.Id
$ownedProcesses | ConvertTo-Json | Set-Content -LiteralPath $stateFile -Encoding ASCII

$webReady = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    Start-Sleep -Milliseconds 500
    if (Test-LocalPort 8000) {
        $webReady = $true
        break
    }
    if (-not (Get-Process -Id $phpProcess.Id -ErrorAction SilentlyContinue)) {
        throw "The PHP web server stopped during startup. Check $logDirectory\web-error.log"
    }
}

if (-not $webReady) {
    throw "The PHP web server did not start in time. Check $logDirectory\web-error.log"
}

Start-Process $baseUrl
Write-Host "CampusFind is running at $baseUrl"
Write-Host 'To stop it, double-click Stop-CampusFind.bat.'
