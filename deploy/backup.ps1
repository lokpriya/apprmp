# ============================================================
# apprmp — Full Backup Script
# Creates: D:\Backups\apprmp\apprmp-backup-YYYY-MM-DD_HH-mm.zip
#   Contains:
#     apprmp_db.sql       — full MySQL dump
#     files/              — project files (vendor/node_modules/.git excluded)
# ============================================================

$ErrorActionPreference = 'Stop'

$timestamp   = Get-Date -Format "yyyy-MM-dd_HH-mm"
$backupDir   = "D:\Backups\apprmp"
$zipPath     = "$backupDir\apprmp-backup-$timestamp.zip"
$tempDir     = "$env:TEMP\apprmp-backup-$timestamp"
$projectPath = "D:\XAMPP\htdocs\apprmp"
$mysqldump   = "D:\XAMPP2\mysql\bin\mysqldump.exe"
$dbName      = "apprmp"
$dbUser      = "root"

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  apprmp Backup — $timestamp" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Create directories
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
New-Item -ItemType Directory -Path $tempDir   -Force | Out-Null
New-Item -ItemType Directory -Path "$tempDir\files" -Force | Out-Null

# --- 1. Database dump ---
Write-Host ""
Write-Host "[1/3] Dumping database '$dbName'..." -ForegroundColor Yellow
$sqlFile = "$tempDir\apprmp_db.sql"
& $mysqldump -u $dbUser --no-tablespaces --result-file="$sqlFile" $dbName
if ($LASTEXITCODE -ne 0) { throw "mysqldump failed (exit $LASTEXITCODE)" }
$sqlSizeMB = [math]::Round((Get-Item $sqlFile).Length / 1MB, 2)
Write-Host "    OK — apprmp_db.sql ($sqlSizeMB MB)" -ForegroundColor Green

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
robocopy @roboArgs | Out-Null
# robocopy exit codes 0-7 = success
if ($LASTEXITCODE -ge 8) { throw "robocopy failed (exit $LASTEXITCODE)" }
$fileCount = (Get-ChildItem "$tempDir\files" -Recurse -File).Count
Write-Host "    OK — $fileCount files copied" -ForegroundColor Green

# --- 3. Create zip ---
Write-Host ""
Write-Host "[3/3] Creating zip archive..." -ForegroundColor Yellow
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipPath -CompressionLevel Optimal
$zipSizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host "    OK — $zipSizeMB MB" -ForegroundColor Green

# Cleanup temp
Remove-Item -Path $tempDir -Recurse -Force

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Backup saved:" -ForegroundColor Green
Write-Host "  $zipPath" -ForegroundColor White
Write-Host "  Size: $zipSizeMB MB" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
