# =============================================================
#  LGPAS - Build Deployment ZIP for cPanel Upload
#  Run: powershell -ExecutionPolicy Bypass -File deploy\build-zip.ps1
# =============================================================

$ProjectRoot = Split-Path $PSScriptRoot -Parent
$ZipPath     = "$ProjectRoot\apprmp-deploy.zip"
$TempDir     = "$env:TEMP\apprmp-build-$(Get-Random)"

Write-Host ''
Write-Host '  LGPAS Deployment ZIP Builder' -ForegroundColor Cyan
Write-Host '  ============================================' -ForegroundColor Cyan
Write-Host "  Source : $ProjectRoot"
Write-Host "  Output : $ZipPath"
Write-Host ''

# --- Clean temp dir ---
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir | Out-Null

# --- Files/folders to EXCLUDE ---
$SkipNames = @(
    '.git', '.github', '.gitattributes', '.gitignore',
    '.claude', 'memory', 'Reference Files',
    'node_modules',
    '.env',
    'tests',
    'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
    'apprmp-deploy.zip',
    '.install_lock',
    'Makefile',
    'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
    'package.json', 'package-lock.json', 'bun.lockb',
    '.editorconfig', '.prettierrc', '.styleci.yml',
    'README.md', '.env.example',
    # Stray dev/data files
    'choices_data.json', 'choices_rows.tsv', 'extracted_data.json',
    'survey_rows.tsv', 'survey_rows_554_656.json'
)

# --- Copy project files ---
Write-Host '  Copying project files...' -ForegroundColor Yellow

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

# --- Clear storage runtime content ---
Write-Host '  Clearing storage runtime files...' -ForegroundColor Yellow

$storageClearDirs = @(
    'storage\logs',
    'storage\framework\cache\data',
    'storage\framework\cache\laravel-excel',   # Excel temp cache (~4.6 MB)
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\app\private\bulk-imports',        # Uploaded import files (~4.5 MB)
    'storage\app\private\livewire-tmp'         # Livewire temp uploads (~0.4 MB)
)

foreach ($rel in $storageClearDirs) {
    $dir = Join-Path $TempDir $rel
    if (Test-Path $dir) {
        Get-ChildItem -Path $dir -Recurse -File | Remove-Item -Force
    } else {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $keep = Join-Path $dir '.gitkeep'
    if (-not (Test-Path $keep)) { New-Item -ItemType File -Path $keep -Force | Out-Null }
}

# --- Remove stray .env files ---
Get-ChildItem -Path $TempDir -Filter '.env' -Recurse -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue

# --- Verify install.php ---
$installSrc  = Join-Path $ProjectRoot 'public\install.php'
$installDest = Join-Path $TempDir 'public\install.php'
if (Test-Path $installSrc) {
    if (-not (Test-Path $installDest)) { Copy-Item $installSrc $installDest -Force }
    Write-Host '    OK public/install.php included' -ForegroundColor Green
} else {
    Write-Host '    WARNING: public/install.php not found!' -ForegroundColor Red
}

# --- Verify Vite build ---
$buildDir = Join-Path $TempDir 'public\build'
if (Test-Path $buildDir) {
    $assetCount = (Get-ChildItem -Path $buildDir -Recurse -File).Count
    Write-Host "    OK public/build/ included ($assetCount files)" -ForegroundColor Green
} else {
    Write-Host '    WARNING: public/build/ missing - run npm run build first!' -ForegroundColor Red
}

# --- Verify vendor ---
$vendorDir = Join-Path $TempDir 'vendor'
if (Test-Path $vendorDir) {
    Write-Host '    OK vendor/ included' -ForegroundColor Green
} else {
    Write-Host '    WARNING: vendor/ missing - run composer install first!' -ForegroundColor Red
}

# --- Build the ZIP ---
Write-Host ''
Write-Host '  Creating ZIP archive...' -ForegroundColor Yellow

if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($TempDir, $ZipPath)

# --- Cleanup temp ---
Remove-Item $TempDir -Recurse -Force

# --- Report ---
$sizeMB = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)

Write-Host ''
Write-Host '  ============================================' -ForegroundColor Green
Write-Host '  ZIP created successfully!' -ForegroundColor Green
Write-Host "  File : $ZipPath" -ForegroundColor Green
Write-Host "  Size : $sizeMB MB" -ForegroundColor Green
Write-Host '  ============================================' -ForegroundColor Green
Write-Host ''
Write-Host '  DEPLOYMENT STEPS (cPanel):' -ForegroundColor Cyan
Write-Host '  1. cPanel -> MySQL Databases -> create DB + user + ALL PRIVILEGES'
Write-Host '  2. cPanel -> MultiPHP Manager -> set PHP 8.2 for subdomain'
Write-Host '  3. Upload apprmp-deploy.zip to /home/username/ via File Manager'
Write-Host '  4. Extract -> rename folder to: apprmp'
Write-Host '  5. Create folder: public_html/apprmp/'
Write-Host '  6. Copy apprmp/public/* into public_html/apprmp/'
Write-Host '  7. Copy deploy/cpanel-public-index.php to public_html/apprmp/index.php'
Write-Host '  8. Edit index.php: set LARAVEL_ROOT = /home/username/apprmp'
Write-Host '  9. Visit: https://yourdomain.com/apprmp/install.php'
Write-Host ' 10. Follow the 6-step web installer'
Write-Host ' 11. Delete install.php after completion (installer provides a button)'
Write-Host ''
