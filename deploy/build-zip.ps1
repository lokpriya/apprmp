# =============================================================
#  LGPAS - Build Deployment ZIP for cPanel Upload
#
#  Usage:
#    powershell -ExecutionPolicy Bypass -File deploy\build-zip.ps1
#    powershell -ExecutionPolicy Bypass -File deploy\build-zip.ps1 -Target ajirkot
#    powershell -ExecutionPolicy Bypass -File deploy\build-zip.ps1 -Target apprmp
# =============================================================

param(
    [string]$Target = 'apprmp'   # 'apprmp' | 'ajirkot' | 'aamchowk' | 'aathrai' | 'rongdata'
)

# -- Per-target config --------------------------------------------------------
$Targets = @{
    'apprmp' = @{
        LaravelRoot = '/home2/prayasne/apprmp'
        WebRoot     = 'public_html/apprmp'
        AppUrl      = 'https://apprmp.prayasnepal.com'
        DbName      = 'prayasne_apprmp15'
        DbUser      = 'prayasne_bhagwanuser'
        ZipName     = 'apprmp-deploy.zip'
        InstallUrl  = 'https://apprmp.prayasnepal.com/install.php'
    }
    'ajirkot' = @{
        LaravelRoot = '/home2/prayaspra/ajirkot'
        WebRoot     = 'public_html/ajirkot'
        AppUrl      = 'https://ajirkot.prayasprarambha.com.np'
        DbName      = 'prayaspra_ajirkotprofile'
        DbUser      = 'prayaspra_lokpriya'
        ZipName     = 'ajirkot-deploy.zip'
        InstallUrl  = 'https://ajirkot.prayasprarambha.com.np/install.php'
    }
    'aamchowk' = @{
        LaravelRoot = '/home2/prayaspr/aamchowk'
        WebRoot     = 'public_html/aamchowk'
        AppUrl      = 'https://aamchowk.prayasprarambha.com.np'
        DbName      = 'prayaspra_aamchowk'
        DbUser      = 'prayaspra_aamchowk'
        ZipName     = 'aamchowk-deploy.zip'
        InstallUrl  = 'https://aamchowk.prayasprarambha.com.np/install.php'
    }
    'aathrai' = @{
        LaravelRoot = '/home2/prayaspr/aathrai'
        WebRoot     = 'public_html/aathrai'
        AppUrl      = 'https://aathrai.prayasprarambha.com.np'
        DbName      = 'prayaspr_aathrai'
        DbUser      = 'prayaspra_aamchowk'
        ZipName     = 'aathrai-deploy.zip'
        InstallUrl  = 'https://aathrai.prayasprarambha.com.np/install.php'
    }
    'rongdata' = @{
        LaravelRoot = '/home2/prayaspr/rong'
        WebRoot     = 'public_html/rong'
        AppUrl      = 'https://rong.prayasprarambha.com.np'
        DbName      = 'prayaspr_rongdata'
        DbUser      = 'prayaspr_rongdata'
        ZipName     = 'rongdata-deploy.zip'
        InstallUrl  = 'https://rong.prayasprarambha.com.np/install.php'
    }
}

if (-not $Targets.ContainsKey($Target)) {
    Write-Host "  ERROR: Unknown target '$Target'. Use 'apprmp', 'ajirkot', 'aamchowk', 'aathrai', or 'rongdata'." -ForegroundColor Red
    exit 1
}

$Cfg         = $Targets[$Target]
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$ZipPath     = "$ProjectRoot\$($Cfg.ZipName)"
$TempDir     = "$env:TEMP\apprmp-build-$(Get-Random)"

Write-Host ''
Write-Host '  LGPAS Deployment ZIP Builder' -ForegroundColor Cyan
Write-Host '  ============================================' -ForegroundColor Cyan
Write-Host "  Target  : $Target" -ForegroundColor Yellow
Write-Host "  App URL : $($Cfg.AppUrl)" -ForegroundColor Yellow
Write-Host "  Source  : $ProjectRoot"
Write-Host "  Output  : $ZipPath"
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
    'apprmp-deploy.zip', 'ajirkot-deploy.zip', 'aamchowk-deploy.zip', 'aathrai-deploy.zip', 'rongdata-deploy.zip',
    '.install_lock',
    'Makefile',
    'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
    'package.json', 'package-lock.json', 'bun.lockb',
    '.editorconfig', '.prettierrc', '.styleci.yml',
    'README.md', '.env.example',
    # Stray dev/data/temp files
    'choices_data.json', 'choices_rows.tsv', 'extracted_data.json',
    'survey_rows.tsv', 'survey_rows_554_656.json',
    'build.zip', 'build-only.zip',
    'database/sql',
    'run-apprmp.php', 'run-ajirkot.php', 'run-aamchowk.php'
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

# --- Prune sensitive / non-deployable content -------------------------------
# $SkipNames above is matched against top-level Get-ChildItem names only, so a
# path entry like 'database/sql' never matched anything and nested files were
# copied wholesale by -Recurse. Database dumps of real household data were
# reaching the servers inside the deploy ZIP as a result. Prune by pattern after
# the copy instead, which is independent of nesting depth.
Write-Host '  Pruning sensitive files...' -ForegroundColor Yellow

$PrunePatterns = @(
    '*.sql',        # raw schema/data dumps
    '*.sql.gz',     # compressed production dumps - real household PII
    '*.sqlite',     # local dev database
    '*.sqlite-journal'
)

$pruned = 0
foreach ($pattern in $PrunePatterns) {
    Get-ChildItem -Path $TempDir -Recurse -File -Filter $pattern -Force -ErrorAction SilentlyContinue |
        ForEach-Object {
            Write-Host "    - $($_.FullName.Substring($TempDir.Length + 1))" -ForegroundColor DarkYellow
            Remove-Item $_.FullName -Force
            $script:pruned++
        }
}

# database/sql holds hand-written migration SQL; never needed at runtime.
$sqlDir = Join-Path $TempDir 'database\sql'
if (Test-Path $sqlDir) { Remove-Item $sqlDir -Recurse -Force; $pruned++ }

Write-Host "    OK $pruned sensitive item(s) removed" -ForegroundColor Green

# Fail loudly rather than ship PII if anything slipped through.
$leaked = Get-ChildItem -Path $TempDir -Recurse -File -Force -ErrorAction SilentlyContinue |
          Where-Object { $_.Name -match '\.(sql|sqlite)(\.gz)?$' }
if ($leaked) {
    $leaked | ForEach-Object { Write-Host "    LEAK: $($_.FullName)" -ForegroundColor Red }
    throw 'Refusing to build: database dumps would be shipped in the deploy ZIP.'
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
    'storage\app\public'                       # Uploaded photos/logos - not deployed; re-upload on server
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

# Copy .user.ini to webroot (PHP-FPM reads from document root, not app root)
$userIniSrc = Join-Path $ProjectRoot '.user.ini'
if (Test-Path $userIniSrc) {
    Copy-Item $userIniSrc (Join-Path $webrootDir '.user.ini') -Force
    Write-Host '    OK webroot/.user.ini copied' -ForegroundColor Green
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
    $indexContent = $indexContent -replace "define\('LARAVEL_ROOT', '[^']+'\)", "define('LARAVEL_ROOT', '$($Cfg.LaravelRoot)')"
    [System.IO.File]::WriteAllText($indexDst, $indexContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "    OK webroot/index.php - LARAVEL_ROOT pre-set to $($Cfg.LaravelRoot)" -ForegroundColor Green
}

# Copy install.php (web installer - runs migrations + seeders)
$installSrc = Join-Path $ProjectRoot 'public\install.php'
if (Test-Path $installSrc) {
    Copy-Item $installSrc (Join-Path $webrootDir 'install.php') -Force
    Write-Host '    OK webroot/install.php copied' -ForegroundColor Green
} else {
    Write-Host '    WARNING: public/install.php not found!' -ForegroundColor Red
}

# run.php - patch $root and copy to deploy/ as run-{target}.php (ready to upload manually)
$runSrc = Join-Path $ProjectRoot 'deploy\run.php'
if (Test-Path $runSrc) {
    $runContent = Get-Content $runSrc -Raw -Encoding UTF8
    $runContent  = $runContent -replace "\`$root\s*=\s*'[^']+'", "`$root    = '$($Cfg.LaravelRoot)'"
    $runPatched  = Join-Path $ProjectRoot "deploy\run-$Target.php"
    [System.IO.File]::WriteAllText($runPatched, $runContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "    OK deploy\run-$Target.php patched (root=$($Cfg.LaravelRoot))" -ForegroundColor Green
} else {
    Write-Host '    WARNING: deploy\run.php not found - skipping run patch' -ForegroundColor DarkGray
}
# Upload deploy\run-{target}.php to webroot manually when needed, DELETE immediately after use

Write-Host "    webroot/ bundle ready - upload its contents to $($Cfg.WebRoot)/" -ForegroundColor Green

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
$homeDir = ($Cfg.LaravelRoot -split '/')[0..2] -join '/'  # e.g. /home2/prayaspra

Write-Host "  DEPLOY STEPS (cPanel - $($Cfg.AppUrl)):" -ForegroundColor Cyan
Write-Host ''
Write-Host '  --- UPLOAD --------------------------------------------------' -ForegroundColor DarkGray
Write-Host "  1. Upload $($Cfg.ZipName) to $homeDir/ via File Manager"
Write-Host "  2. Extract zip - overwrite existing files in $($Cfg.LaravelRoot)/"
Write-Host '     (keep existing .env - do NOT overwrite it)'
Write-Host "  3. Upload ALL files inside webroot/ to $homeDir/$($Cfg.WebRoot)/"
Write-Host '     (index.php has LARAVEL_ROOT pre-set, no editing needed)'
Write-Host ''
Write-Host '  --- UPGRADE (existing install) --------------------------------' -ForegroundColor DarkGray
Write-Host '  4. Upload deploy\run.php to webroot manually'
Write-Host '  5. Visit run.php - run: php artisan migrate'
Write-Host '  6. Visit run.php - run: php artisan cache:clear'
Write-Host '  7. DELETE run.php via its own button immediately after'
Write-Host ''
Write-Host '  --- FRESH INSTALL (only if starting from scratch) -------------' -ForegroundColor DarkGray
Write-Host "  4. Visit: $($Cfg.InstallUrl)"
Write-Host "  5. Step 2: DB host=localhost  db=$($Cfg.DbName)"
Write-Host "            user=$($Cfg.DbUser)"
Write-Host "     Step 3: APP_URL=$($Cfg.AppUrl)"
Write-Host '     Steps 4-7: migrations, seeders, admin user, done'
Write-Host '     Click "Delete install.php" on final step'
Write-Host ''
Write-Host '  --- POST-DEPLOY ---------------------------------------------' -ForegroundColor DarkGray
Write-Host '  * MultiPHP Manager: confirm PHP 8.4 (ea-php84) is set'
Write-Host '  * Test login, survey form dropdowns, PDF export, photo upload'
Write-Host "  * Check $($Cfg.AppUrl)/admin/google-sync/manual loads correctly"
Write-Host ''
