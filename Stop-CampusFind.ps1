$ErrorActionPreference = 'Stop'

$stateDirectory = Join-Path $env:LOCALAPPDATA 'CampusFind'
$stateFile = Join-Path $stateDirectory 'processes.json'
$php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$maria = Join-Path $env:ProgramFiles 'MariaDB 13.0\bin\mariadbd.exe'

if (-not (Test-Path -LiteralPath $stateFile)) {
    Write-Host 'CampusFind is not running, or its local server was started manually.'
    exit 0
}

$processState = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
foreach ($name in @('web', 'database')) {
    $processId = $processState.$name
    if (-not $processId) {
        continue
    }

    $process = Get-Process -Id ([int] $processId) -ErrorAction SilentlyContinue
    if (-not $process) {
        continue
    }

    $expectedPath = if ($name -eq 'web') { $php } else { $maria }
    if ($process.Path -and [string]::Equals($process.Path, $expectedPath, [System.StringComparison]::OrdinalIgnoreCase)) {
        Stop-Process -Id $process.Id
        Write-Host "Stopped CampusFind $name server."
    } else {
        Write-Warning "Skipped process $processId because it is not the expected CampusFind server."
    }
}

Remove-Item -LiteralPath $stateFile -Force
Write-Host 'CampusFind has stopped.'
