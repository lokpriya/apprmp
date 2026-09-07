# ============================================================
# apprmp - Full Backup Script
# Creates: D:\Backups\apprmp\apprmp-backup-YYYY-MM-DD_HH-mm.zip
#   Contains:
#     apprmp_db.sql       - full MySQL dump
#     files/              - project files (vendor/node_modules/.git excluded)
# ============================================================

$ErrorActionPreference = 'Stop'

$timestamp   = Get-Date -Format "yyyy-MM-dd_HH-mm"
$backupDir   = "D:\Backups\apprmp"
$zipPath     = "$backupDir\apprmp-backup-$timestamp.zip"
$tempDir     = "$env:TEMP\apprmp-backup-$timestamp"
$projectPath = "D:\XAMPP\htdocs\apprmp"
$mysqldump   = "D:\MariaDB12\bin\mysqldump.exe"
$keepBackups = 14   # prune older archives so the backup dir cannot grow unbounded

# Read DB credentials from .env rather than hardcoding them - keeps this script in
# step with the app and keeps the password out of version control.
$envPath = Join-Path $projectPath '.env'
if (-not (Test-Path $envPath)) { throw ".env not found at $envPath" }

$envVars = @{}
foreach ($line in Get-Content $envPath) {
    if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$') {
        $envVars[$Matches[1]] = $Matches[2].Trim().Trim('"')
    }
}

$dbName = $envVars['DB_DATABASE']
$dbUser = $envVars['DB_USERNAME']
$dbHost = if ($envVars['DB_HOST']) { $envVars['DB_HOST'] } else { '127.0.0.1' }
$dbPort = if ($envVars['DB_PORT']) { $envVars['DB_PORT'] } else { '3306' }

if (-not $dbName -or -not $dbUser) { throw "DB_DATABASE / DB_USERNAME missing from .env" }
if (-not (Test-Path $mysqldump)) { throw "mysqldump not found at $mysqldump" }

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  apprmp Backup - $timestamp" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Create directories
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
New-Item -ItemType Directory -Path $tempDir   -Force | Out-Null
New-Item -ItemType Directory -Path "$tempDir\files" -Force | Out-Null

# --- 1. Database dump ---
Write-Host ""
Write-Host "[1/3] Dumping database '$dbName'..." -ForegroundColor Yellow
$sqlFile = "$tempDir\apprmp_db.sql"
# MYSQL_PWD keeps the password out of the command line and process list
$env:MYSQL_PWD = $envVars['DB_PASSWORD']
$prevEap = $ErrorActionPreference
try {
    # Native executables write warnings to stderr, which 'Stop' would treat as a
    # terminating error. Exit code is the only reliable success signal here.
    $ErrorActionPreference = 'Continue'
    $dumpArgs = @("--host=$dbHost", "--port=$dbPort", "-u", $dbUser, "--single-transaction", "--routines", "--triggers", "--no-tablespaces", "--result-file=$sqlFile", $dbName)
    & $mysqldump @dumpArgs 2>&1 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }
} finally {
    $ErrorActionPreference = $prevEap
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}
$sqlSizeMB = [math]::Round((Get-Item $sqlFile).Length / 1MB, 2)
Write-Host "    OK - apprmp_db.sql ($sqlSizeMB MB)" -ForegroundColor Green

# --- 2. Copy project files ---
Write-Host ""
Write-Host "[2/3] Copying project files (excluding vendor / node_modules / .git)..." -ForegroundColor Yellow
$roboArgs = @(
    $projectPath, "$tempDir\files",
    "/E",
    "/XD", "vendor", "node_modules", ".git", "storage\framework", "storage\logs",
    "/XF", "*.log",
    "/NFL", "/NDL", "/NP", "/NJH", "/NJS"
)
$prevEap = $ErrorActionPreference
try {
    $ErrorActionPreference = 'Continue'
    robocopy @roboArgs | Out-Null
} finally {
    $ErrorActionPreference = $prevEap
}
# robocopy exit codes 0-7 = success
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }
$fileCount = (Get-ChildItem "$tempDir\files" -Recurse -File).Count
Write-Host "    OK - $fileCount files copied" -ForegroundColor Green

# --- 3. Create zip ---
Write-Host ""
Write-Host "[3/3] Creating zip archive..." -ForegroundColor Yellow
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipPath -CompressionLevel Optimal
$zipSizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host "    OK - $zipSizeMB MB" -ForegroundColor Green

# Cleanup temp
Remove-Item -Path $tempDir -Recurse -Force

# Prune old archives so the backup directory cannot grow without bound
$stale = Get-ChildItem $backupDir -Filter 'apprmp-backup-*.zip' |
    Sort-Object LastWriteTime -Descending | Select-Object -Skip $keepBackups
if ($stale) {
    $stale | ForEach-Object { Remove-Item $_.FullName -Force }
    Write-Host "    Pruned $($stale.Count) old backup(s), keeping $keepBackups" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Backup saved:" -ForegroundColor Green
Write-Host "  $zipPath" -ForegroundColor White
Write-Host "  Size: $zipSizeMB MB" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

# robocopy returns 1-7 on success, which PowerShell would otherwise surface as the
# script's exit code and make every scheduled run look like a failure.
exit 0
