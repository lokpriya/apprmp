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
    'storage\app\private\livewire-tmp',        # Livewire temp uploads (~0.4 MB)
    'storage\app\public'                       # Uploaded photos/logos — not deployed; re-upload on server
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

# --- Build webroot/ bundle (goes directly into public_html/apprmp/) ---
# This avoids the manual "copy public/* to webroot" step on cPanel.
Write-Host '  Building webroot/ bundle...' -ForegroundColor Yellow
$webrootDir = Join-Path $TempDir 'webroot'
New-Item -ItemType Directory -Path $webrootDir -Force | Out-Null

# Copy build/ (Vite assets)
$pubBuild = Join-Path $ProjectRoot 'public\build'
if (Test-Path $pubBuild) {
    Copy-Item -Path $pubBuild -Destination (Join-Path $webrootDir 'build') -Recurse -Force
    Write-Host '    OK webroot/build/ copied' -ForegroundColor Green
}

# Copy individual public files
$webrootFiles = @('.htaccess', 'sw.js', 'offline.html', 'manifest.webmanifest',
                  'pwa-192.png', 'pwa-512.png', 'robots.txt', 'favicon.ico')
foreach ($f in $webrootFiles) {
    $src = Join-Path $ProjectRoot "public\$f"
    if (Test-Path $src) {
        Copy-Item $src (Join-Path $webrootDir $f) -Force
        Write-Host "    OK webroot/$f copied" -ForegroundColor DarkGray
    }
}
# workbox file (hashed filename)
Get-ChildItem (Join-Path $ProjectRoot 'public') -Filter 'workbox-*.js' | ForEach-Object {
    Copy-Item $_.FullName (Join-Path $webrootDir $_.Name) -Force
    Write-Host "    OK webroot/$($_.Name) copied" -ForegroundColor DarkGray
}

# Copy index.php entry point and auto-fill LARAVEL_ROOT
$indexSrc = Join-Path $ProjectRoot 'deploy\cpanel-public-index.php'
$indexDst = Join-Path $webrootDir 'index.php'
if (Test-Path $indexSrc) {
    $indexContent = Get-Content $indexSrc -Raw -Encoding UTF8
    $indexContent = $indexContent -replace "define\('LARAVEL_ROOT', '[^']+'\)", "define('LARAVEL_ROOT', '/home2/prayasne/apprmp')"
    [System.IO.File]::WriteAllText($indexDst, $indexContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host '    OK webroot/index.php — LARAVEL_ROOT pre-set to /home2/prayasne/apprmp' -ForegroundColor Green
}

# Copy install.php (web installer — runs migrations + seeders)
$installSrc = Join-Path $ProjectRoot 'public\install.php'
if (Test-Path $installSrc) {
    Copy-Item $installSrc (Join-Path $webrootDir 'install.php') -Force
    Write-Host '    OK webroot/install.php copied' -ForegroundColor Green
} else {
    Write-Host '    WARNING: public/install.php not found!' -ForegroundColor Red
}

# run.php is NOT auto-bundled — upload deploy\run.php to webroot manually when needed
# and DELETE it immediately after use (token is in source; do not leave it on the server)

Write-Host '    webroot/ bundle ready — upload its contents to public_html/apprmp/' -ForegroundColor Green

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
Write-Host '  FRESH INSTALL STEPS (cPanel — apprmp.prayasnepal.com):' -ForegroundColor Cyan
Write-Host ''
Write-Host '  --- REMOTE CLEANUP ------------------------------------------' -ForegroundColor DarkGray
Write-Host '  1. MySQL Databases: DROP prayasne_apprmp15 + recreate it'
Write-Host '     Grant ALL PRIVILEGES to prayasne_bhagwanuser'
Write-Host '  2. File Manager: delete /home2/prayasne/apprmp/ entirely'
Write-Host '  3. File Manager: delete all files in /home2/prayasne/public_html/apprmp/'
Write-Host ''
Write-Host '  --- UPLOAD --------------------------------------------------' -ForegroundColor DarkGray
Write-Host '  4. Upload apprmp-deploy.zip to /home2/prayasne/ via File Manager'
Write-Host '  5. Extract zip, rename extracted folder to: apprmp'
Write-Host '  6. Upload ALL files inside webroot/ to public_html/apprmp/'
Write-Host '     (index.php has LARAVEL_ROOT pre-set, no editing needed)'
Write-Host '  6b. OPTIONAL: upload deploy\run.php manually if needed for post-install artisan'
Write-Host '      DELETE run.php via its own button immediately after use'
Write-Host ''
Write-Host '  --- INSTALL -------------------------------------------------' -ForegroundColor DarkGray
Write-Host '  7. Visit: https://apprmp.prayasnepal.com/install.php'
Write-Host '  8. Step 2: DB host=localhost  db=prayasne_apprmp15'
Write-Host '            user=prayasne_bhagwanuser  pass=prayasne_apprmp15'
Write-Host '     Step 3: APP_URL=https://apprmp.prayasnepal.com'
Write-Host '     Steps 4-7: migrations, seeders, admin user, done'
Write-Host '     Click "Delete install.php" on final step'
Write-Host ''
Write-Host '  --- POST-INSTALL --------------------------------------------' -ForegroundColor DarkGray
Write-Host '  9. MultiPHP Manager: confirm PHP 8.4 (ea-php84) is set'
Write-Host ' 10. Test login, PDF export, photo upload'
Write-Host ''
