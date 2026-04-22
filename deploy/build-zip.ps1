# =============================================================
#  LGPAS - Build Deployment ZIP for cPanel Upload
#  Run: powershell -ExecutionPolicy Bypass -File deploy\build-zip.ps1
# =============================================================

$ProjectRoot = Split-Path $PSScriptRoot -Parent
$ZipPath     = "$ProjectRoot\apprmp-deploy.zip"
$TempDir     = "$env:TEMP\apprmp-build-$(Get-Random)"

Write-Host ""
Write-Host "  LGPAS Deployment ZIP Builder" -ForegroundColor Cyan
Write-Host "  ============================================" -ForegroundColor Cyan
Write-Host "  Source : $ProjectRoot"
Write-Host "  Output : $ZipPath"
Write-Host ""

# --- Clean temp dir ---
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir | Out-Null

# --- Files/folders to EXCLUDE from the deployment package ---
$SkipNames = @(
    '.git', '.github', '.gitattributes', '.gitignore',
    '.claude',                # Claude AI session data - dev only
    'memory',                 # Claude memory files - dev only
    'Reference Files',        # Local reference docs - dev only
    'node_modules',
    '.env',                   # Installer will create this on the server
    'tests',
    'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
    'apprmp-deploy.zip',      # Previous build
    '.install_lock',
    'Makefile',
    'vite.config.js',
    'package.json', 'package-lock.json', 'bun.lockb',
    '.editorconfig', '.prettierrc', '.styleci.yml'
)

# --- Copy top-level items ---
Write-Host "  Copying files..." -ForegroundColor Yellow

$items = Get-ChildItem -Path $ProjectRoot -Force |
         Where-Object { $_.Name -notin $SkipNames }

foreach ($item in $items) {
    $dest = Join-Path $TempDir $item.Name
    if ($item.PSIsContainer) {
        Copy-Item -Path $item.FullName -Destination $dest -Recurse -Force
    } else {
        Copy-Item -Path $item.FullName -Destination $dest -Force
    }
    Write-Host "    + $($item.Name)" -ForegroundColor DarkGray
}

# --- Clear storage runtime content (keep folder structure only) ---
Write-Host "  Clearing storage runtime files..." -ForegroundColor Yellow

$storageClearDirs = @(
    'storage\logs',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views'
)

foreach ($rel in $storageClearDirs) {
    $dir = Join-Path $TempDir $rel
    if (Test-Path $dir) {
        Get-ChildItem -Path $dir -Recurse -File | Remove-Item -Force
    } else {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $keep = Join-Path $dir ".gitkeep"
    if (-not (Test-Path $keep)) { New-Item -ItemType File -Path $keep -Force | Out-Null }
}

# --- Remove any stray .env files ---
Get-ChildItem -Path $TempDir -Filter '.env' -Recurse -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue

# --- Ensure install.php is present in public/ ---
$installSrc  = Join-Path $ProjectRoot "public\install.php"
$installDest = Join-Path $TempDir "public\install.php"
if ((Test-Path $installSrc) -and -not (Test-Path $installDest)) {
    Copy-Item $installSrc $installDest -Force
    Write-Host "    + public/install.php" -ForegroundColor DarkGray
}

# --- Build the ZIP ---
Write-Host ""
Write-Host "  Creating ZIP archive (this may take a moment)..." -ForegroundColor Yellow

if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($TempDir, $ZipPath)

# --- Cleanup temp ---
Remove-Item $TempDir -Recurse -Force

# --- Report ---
$sizeMB = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)

Write-Host ""
Write-Host "  ============================================" -ForegroundColor Green
Write-Host "  ZIP created successfully!" -ForegroundColor Green
Write-Host "  File : $ZipPath" -ForegroundColor Green
Write-Host "  Size : $sizeMB MB" -ForegroundColor Green
Write-Host "  ============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Subdirectory Deployment (public_html/apprmp/):" -ForegroundColor Cyan
Write-Host "  1. Upload apprmp-deploy.zip to /home/username/"
Write-Host "  2. Extract it, rename folder to 'apprmp' (private app files)"
Write-Host "  3. Create folder: public_html/apprmp/"
Write-Host "  4. Copy apprmp/public/* into public_html/apprmp/"
Write-Host "  5. Copy deploy/cpanel-public-index.php to public_html/apprmp/index.php"
Write-Host "  6. Edit that index.php - set LARAVEL_ROOT = /home/username/apprmp"
Write-Host "  7. Visit https://yourdomain.com/apprmp/install.php"
Write-Host "  8. Set APP_URL = https://yourdomain.com/apprmp  (in installer step 4)"
Write-Host ""
