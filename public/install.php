<?php
/**
 * ════════════════════════════════════════════════════════════
 *  LGPAS — Web Installer v2.0
 *  स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली
 * ════════════════════════════════════════════════════════════
 *
 *  STEPS:  1·Requirements  2·Database  3·App Config
 *          4·Install       5·Admin     6·Complete
 *
 *  DEPLOYMENT:
 *   1. Edit LARAVEL_ROOT_DEFAULT below (path where artisan lives)
 *   2. Upload to: public_html/<your-subdomain>/install.php
 *   3. Visit:  https://yourdomain.com/<subdomain>/install.php
 *   4. DELETE this file immediately after installation!
 * ════════════════════════════════════════════════════════════
 */

// ── Edit this if auto-detection fails ─────────────────────────
define('LARAVEL_ROOT_DEFAULT', dirname(dirname(__FILE__)));
// ──────────────────────────────────────────────────────────────

define('INS_VER',    '2.0');
define('MIN_PHP',    '8.2.0');
define('REQ_EXTS',   ['pdo','pdo_mysql','mbstring','openssl','tokenizer',
                      'json','curl','xml','fileinfo','ctype','bcmath','zip','gd']);
define('WRITE_DIRS', ['storage/','storage/app/','storage/app/public/',
                      'storage/framework/','storage/framework/cache/',
                      'storage/framework/sessions/','storage/framework/views/',
                      'storage/logs/','bootstrap/cache/']);

// ════════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════════

function h($s): string  { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function v($k, $d = '') { return h($_POST[$k] ?? $d); }

function ins_lockFile(): string {
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgpas_ins_' . md5(__FILE__) . '.json';
}
function ins_lock(): array {
    $f = ins_lockFile();
    if (!file_exists($f)) return [];
    return json_decode(@file_get_contents($f), true) ?? [];
}
function ins_lockSave(array $d): void {
    file_put_contents(ins_lockFile(), json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function ins_findPhp(): string {
    // cPanel EA-PHP — try 8.4, 8.3, 8.2, 8.1 in order
    foreach ([84, 83, 82, 81, 80] as $v) {
        $p = "/opt/cpanel/ea-php{$v}/root/usr/bin/php";
        if (@is_executable($p)) return $p;
    }
    $found = glob('/opt/cpanel/ea-php*/root/usr/bin/php') ?: [];
    if ($found) { usort($found, 'strnatcmp'); $p = end($found); if (@is_executable($p)) return $p; }
    foreach (['/usr/local/bin/php82','/usr/local/bin/php8.2','/usr/local/bin/php',
              '/usr/bin/php82','/usr/bin/php8.2','/usr/bin/php'] as $p) {
        if (@is_executable($p)) return $p;
    }
    $w = trim(@shell_exec('which php 2>/dev/null') ?? '');
    return $w ?: 'php';
}

function ins_exec(string $cmd): array {
    if (function_exists('exec')) {
        $out = []; $code = 0; exec($cmd, $out, $code);
        return ['ok' => $code === 0, 'output' => implode("\n", $out), 'code' => $code];
    }
    if (function_exists('shell_exec')) {
        $out = trim(@shell_exec($cmd) ?? '');
        return ['ok' => true, 'output' => $out, 'code' => 0];
    }
    return ['ok' => false, 'output' => 'exec() and shell_exec() are disabled on this server.', 'code' => -1, 'disabled' => true];
}

function ins_artisan(string $root, string $cmd): array {
    $php = ins_findPhp();
    return ins_exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/artisan') . ' ' . $cmd . ' 2>&1');
}

function ins_checkReqs(string $root): array {
    $items = [];
    // PHP version
    $ok = version_compare(PHP_VERSION, MIN_PHP, '>=');
    $items[] = ['group' => 'PHP', 'label' => 'PHP Version >= ' . MIN_PHP,
                'ok' => $ok, 'note' => 'Installed: PHP ' . PHP_VERSION, 'critical' => true];
    // Extensions
    foreach (REQ_EXTS as $ext) {
        $ok = extension_loaded($ext);
        $items[] = ['group' => 'PHP', 'label' => "Extension: {$ext}",
                    'ok' => $ok, 'note' => $ok ? 'Loaded' : "Missing — enable php-{$ext}",
                    'critical' => in_array($ext, ['pdo','pdo_mysql','mbstring','openssl','json'])];
    }
    // CLI PHP
    $hasExec = function_exists('exec') || function_exists('shell_exec');
    $phpBin  = ins_findPhp();
    $items[] = ['group' => 'PHP', 'label' => 'CLI PHP (exec / shell_exec)',
                'ok' => $hasExec, 'note' => $hasExec ? 'Available — ' . $phpBin : 'Disabled — run artisan manually via SSH',
                'critical' => false];
    // Laravel root
    $hasArtisan = $root && file_exists($root . '/artisan');
    $items[] = ['group' => 'Path', 'label' => 'Laravel root (artisan exists)',
                'ok' => $hasArtisan, 'note' => $hasArtisan ? $root : 'artisan not found — check LARAVEL_ROOT',
                'critical' => true];
    // Writable dirs
    foreach (WRITE_DIRS as $dir) {
        $full = $root . '/' . $dir;
        if (!is_dir($full)) @mkdir($full, 0755, true);
        $ok = is_writable($full);
        $items[] = ['group' => 'Permissions', 'label' => 'Writable: ' . $dir,
                    'ok' => $ok, 'note' => $ok ? 'OK' : 'Run: chmod -R 755 ' . $dir,
                    'critical' => false];
    }
    return $items;
}

function ins_testDb(string $host, string $port, string $db, string $user, string $pass): array {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass,
                       [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'msg' => "Connected — MySQL {$v}"];
    } catch (Throwable $e) { return ['ok' => false, 'msg' => $e->getMessage()]; }
}

function ins_writeEnv(string $root, array $d): bool {
    $debug = $d['debug'] === 'true' ? 'true' : 'false';
    $level = $d['app_env'] === 'production' ? 'error' : 'debug';
    // Escape values that may contain special chars for .env format
    $esc  = fn($v) => '"' . str_replace(['"','\\','$'], ['\\"','\\\\','\\$'], $v) . '"';
    $env  = "APP_NAME=" . $esc($d['app_name']) . "\n";
    $env .= "APP_ENV={$d['app_env']}\n";
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $env .= "APP_KEY={$appKey}\n";
    $env .= "APP_DEBUG={$debug}\n";
    $env .= "APP_URL={$d['app_url']}\n";
    $env .= "APP_TIMEZONE={$d['timezone']}\n\n";
    $env .= "LOG_CHANNEL=stack\nLOG_LEVEL={$level}\n\n";
    $env .= "DB_CONNECTION=mysql\n";
    $env .= "DB_HOST={$d['db_host']}\n";
    $env .= "DB_PORT={$d['db_port']}\n";
    $env .= "DB_DATABASE={$d['db_name']}\n";
    $env .= "DB_USERNAME={$d['db_user']}\n";
    $env .= "DB_PASSWORD=" . $esc($d['db_pass']) . "\n\n";
    $env .= "BROADCAST_CONNECTION=log\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\n";
    $env .= "SESSION_DRIVER=file\nSESSION_LIFETIME=120\nSESSION_ENCRYPT=false\n\n";
    $env .= "MAIL_MAILER=smtp\nMAIL_HOST=127.0.0.1\nMAIL_PORT=1025\n";
    $env .= "MAIL_USERNAME=null\nMAIL_PASSWORD=null\nMAIL_ENCRYPTION=null\n";
    $env .= "MAIL_FROM_ADDRESS=\"noreply@example.com\"\nMAIL_FROM_NAME=\"\${APP_NAME}\"\n\n";
    $env .= "FILESYSTEM_DISK=local\n";
    $webRoot = $d['webroot'] ?? '';
    if ($webRoot) {
        $env .= "PUBLIC_DISK_ROOT={$webRoot}/uploads\n";
        $env .= "PUBLIC_DISK_URL=" . rtrim($d['app_url'], '/') . "/uploads\n";
    }
    return file_put_contents($root . '/.env', $env) !== false;
}

function ins_pdo(array $db): PDO {
    return new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4",
                   $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function ins_seedLg(array $db, string $npName, string $enName, string $type, int $wards): array {
    try {
        $pdo = ins_pdo($db);
        $now = date('Y-m-d H:i:s');
        // Check existing LG
        $lgId = $pdo->query("SELECT id FROM local_governments LIMIT 1")->fetchColumn();
        if ($lgId) return ['ok' => true, 'lg_id' => (int)$lgId, 'msg' => 'Using existing local government row.'];
        // Province
        $provId = $pdo->query("SELECT id FROM provinces LIMIT 1")->fetchColumn();
        if (!$provId) {
            $pdo->prepare("INSERT INTO provinces (name_np,name_en,code,created_at,updated_at) VALUES (?,?,?,?,?)")
                ->execute(['प्रदेश १','Province No. 1','P1',$now,$now]);
            $provId = $pdo->lastInsertId();
        }
        // District
        $distId = $pdo->query("SELECT id FROM districts LIMIT 1")->fetchColumn();
        if (!$distId) {
            $pdo->prepare("INSERT INTO districts (province_id,name_np,name_en,code,created_at,updated_at) VALUES (?,?,?,?,?,?)")
                ->execute([$provId,'जिल्ला','District','D1',$now,$now]);
            $distId = $pdo->lastInsertId();
        }
        // Local government
        $pdo->prepare("INSERT INTO local_governments (district_id,name_np,name_en,type,code,ward_count,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,1,?,?)")
            ->execute([$distId,$npName,$enName,$type,'LG001',$wards,$now,$now]);
        $lgId = (int)$pdo->lastInsertId();
        // Wards
        for ($i = 1; $i <= $wards; $i++) {
            $pdo->prepare("INSERT INTO wards (local_government_id,ward_number,created_at,updated_at) VALUES (?,?,?,?)")
                ->execute([$lgId,$i,$now,$now]);
        }
        return ['ok' => true, 'lg_id' => $lgId, 'msg' => "Created LG with {$wards} wards."];
    } catch (Throwable $e) { return ['ok' => false, 'lg_id' => 0, 'msg' => $e->getMessage()]; }
}

function ins_createAdmin(array $db, string $name, string $email, string $pass, int $lgId): array {
    try {
        $pdo = ins_pdo($db);
        $existing = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $existing->execute([$email]);
        if ($existing->fetchColumn()) return ['ok' => false, 'msg' => 'Email already registered.'];
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $now  = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO users (name,email,password,role,local_government_id,is_active,email_verified_at,created_at,updated_at) VALUES (?,?,?,'super_admin',?,1,?,?,?)")
            ->execute([$name,$email,$hash,$lgId,$now,$now,$now]);
        return ['ok' => true, 'msg' => 'Super Admin created.'];
    } catch (Throwable $e) { return ['ok' => false, 'msg' => $e->getMessage()]; }
}

// ════════════════════════════════════════════════════════════
//  BOOTSTRAP
// ════════════════════════════════════════════════════════════

// ?reset=1 clears all installer state and starts fresh
if (($_GET['reset'] ?? '') === '1') {
    @unlink(ins_lockFile());
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// Token stored in lock file — no session dependency (shared hosting safe)
$lock = ins_lock();
if (empty($lock['_token'])) {
    $lock['_token'] = bin2hex(random_bytes(16));
    ins_lockSave($lock);
}
$token = $lock['_token'];

$step = (int)($lock['step'] ?? 1);
$root = $lock['laravel_root'] ?? LARAVEL_ROOT_DEFAULT;
$errors  = [];
$notices = [];
$completed = !empty($lock['completed']);

// Permanent guard — survives temp-dir wipe on shared hosting server restarts
if (!$completed && $root && file_exists($root . '/.install_lock')) {
    $completed = true;
    $lock['completed'] = true;
}

// Auto-detect APP_URL
$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$autoUrl = rtrim($proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI'] ?? '/'), '/');

// ════════════════════════════════════════════════════════════
//  POST HANDLERS
// ════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$completed) {
    if (($_POST['_token'] ?? '') !== $token) {
        // Token mismatch — re-read lock in case it was just created
        $lock  = ins_lock();
        $token = $lock['_token'] ?? '';
        if (($_POST['_token'] ?? '') !== $token) die('Security token mismatch — please <a href="' . $_SERVER['PHP_SELF'] . '">click here to restart</a>.');
    }
    $action = $_POST['action'] ?? '';

    // ── STEP 1: Validate requirements ────────────────────────
    if ($action === 'step1') {
        $newRoot = rtrim(trim($_POST['laravel_root'] ?? $root), '/\\');
        if (!file_exists($newRoot . '/artisan')) {
            $errors[] = 'artisan not found at: <code>' . h($newRoot) . '</code> — check the path.';
        } else {
            $root = $newRoot;
            $reqs = ins_checkReqs($root);
            $critical = array_filter($reqs, fn($r) => $r['critical'] && !$r['ok']);
            if (empty($critical)) {
                $lock['laravel_root'] = $root;
                $lock['step'] = 2;
                ins_lockSave($lock); header('Location: '.$_SERVER['PHP_SELF']); exit;
            } else {
                $errors[] = 'Fix critical requirements (marked ✖) before continuing.';
            }
        }
    }

    // ── STEP 2: Database ─────────────────────────────────────
    if ($action === 'step2') {
        $db = [
            'host' => trim($_POST['db_host'] ?? 'localhost'),
            'port' => trim($_POST['db_port'] ?? '3306'),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => $_POST['db_pass'] ?? '',
        ];
        if (!$db['name'] || !$db['user']) {
            $errors[] = 'Database name and username are required.';
        } else {
            $test = ins_testDb($db['host'], $db['port'], $db['name'], $db['user'], $db['pass']);
            if (!$test['ok']) {
                $errors[] = 'Connection failed: ' . h($test['msg']);
            } else {
                $notices[] = $test['msg'];
                $lock['db'] = $db; $lock['step'] = 3;
                ins_lockSave($lock); header('Location: '.$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // ── STEP 3: App config ───────────────────────────────────
    if ($action === 'step3') {
        $app = [
            'app_name' => trim($_POST['app_name'] ?? ''),
            'app_url'  => rtrim(trim($_POST['app_url'] ?? ''), '/'),
            'app_env'  => in_array($_POST['app_env'] ?? '', ['production','local','staging'])
                          ? $_POST['app_env'] : 'production',
            'debug'    => ($_POST['app_debug'] ?? 'false') === 'true' ? 'true' : 'false',
            'timezone' => trim($_POST['timezone'] ?? 'Asia/Kathmandu'),
        ];
        if (!$app['app_name']) $errors[] = 'Application name is required.';
        if (!$app['app_url'])  $errors[] = 'APP_URL is required.';
        if (empty($errors)) {
            $lock['app'] = $app; $lock['step'] = 4;
            ins_lockSave($lock); header('Location: '.$_SERVER['PHP_SELF']); exit;
        }
    }

    // ── STEP 4: Run installation ─────────────────────────────
    if ($action === 'step4') {
        $db  = $lock['db']  ?? null;
        $app = $lock['app'] ?? null;
        $lRoot = $lock['laravel_root'] ?? LARAVEL_ROOT_DEFAULT;
        if (!$db || !$app) { $errors[] = 'Missing config — go back.'; }
        else {
            $log = [];
            // Write .env
            $envData = array_merge($app, ['db_host'=>$db['host'],'db_port'=>$db['port'],
                                          'db_name'=>$db['name'],'db_user'=>$db['user'],'db_pass'=>$db['pass'],
                                          'webroot'=>__DIR__]);
            if (!ins_writeEnv($lRoot, $envData)) {
                $errors[] = '.env write failed — ensure ' . h($lRoot) . ' is writable.';
            } else {
                $log[] = ['ok'=>true, 'label'=>'Write .env file (APP_KEY generated)'];
                // migrate
                $r = ins_artisan($lRoot, 'migrate --force');
                // Consider success if output has DONE lines even when exit code is non-zero
                // (some shared hosts return exit code 1 with deprecation warnings despite success)
                $hasError = preg_match('/FAILED|Exception|Error:|SQLSTATE/i', $r['output']);
                $hasDone  = str_contains($r['output'], 'DONE') || str_contains($r['output'], 'Nothing to migrate');
                $migOk    = $r['ok'] || ($hasDone && !$hasError);
                $log[] = ['ok'=>$migOk, 'label'=>'Run migrations ('.(count(explode("\n",$r['output']))).' lines)', 'out'=>$r['output']];
                // seed Nepal geo data
                $geoR = ins_artisan($lRoot, 'db:seed --class=NepalMasterDataSeeder --force');
                $log[] = ['ok'=>true, 'label'=>'Seed Nepal provinces / districts / LGs', 'out'=>$geoR['output']];
                // storage:link
                $r = ins_artisan($lRoot, 'storage:link --force');
                $log[] = ['ok'=>true, 'label'=>'Create storage symlink', 'out'=>$r['output']];
                // fix storage + bootstrap/cache permissions
                $permDirs = [
                    $lRoot.'/storage',
                    $lRoot.'/bootstrap/cache',
                ];
                foreach ($permDirs as $pd) {
                    if (is_dir($pd)) {
                        $iter = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($pd, FilesystemIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        @chmod($pd, 0755);
                        foreach ($iter as $item) {
                            @chmod($item->getPathname(), $item->isDir() ? 0755 : 0644);
                        }
                    }
                }
                $log[] = ['ok'=>true, 'label'=>'Set storage/bootstrap permissions (755/644)', 'out'=>'Done'];
                // create uploads/ folder in webroot for public disk
                $uploadsDir = __DIR__ . '/uploads';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0755, true);
                }
                $log[] = ['ok'=>true, 'label'=>'Create uploads/ folder for public storage', 'out'=>is_dir($uploadsDir) ? 'OK: '.$uploadsDir : 'Already exists or skipped'];
                // clear caches
                ins_artisan($lRoot, 'config:clear');
                ins_artisan($lRoot, 'route:clear');
                ins_artisan($lRoot, 'view:clear');
                // optimize
                $r = ins_artisan($lRoot, 'optimize');
                $log[] = ['ok'=>true, 'label'=>'Optimize application', 'out'=>$r['output']];
                $lock['install_log'] = $log;
                if ($migOk) {
                    $lock['step'] = 5;
                    ins_lockSave($lock); header('Location: '.$_SERVER['PHP_SELF']); exit;
                } else {
                    if (!empty($r['disabled'])) $errors[] = 'CLI PHP is disabled. Run artisan manually via SSH.';
                    else $errors[] = 'Migration failed. Check the log below.';
                    ins_lockSave($lock);
                }
            }
        }
    }

    // ── STEP 5: Admin + LG seed ──────────────────────────────
    if ($action === 'step5') {
        $db = $lock['db'] ?? null;
        $adminName  = trim($_POST['admin_name'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass  = $_POST['admin_pass'] ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';
        $lgNp       = trim($_POST['lg_name_np'] ?? '');
        $lgEn       = trim($_POST['lg_name_en'] ?? '');
        $lgType     = in_array($_POST['lg_type']??'', ['municipality','rural_municipality','sub_metropolitan','metropolitan'])
                      ? $_POST['lg_type'] : 'municipality';
        $lgWards    = max(1, min(33, (int)($_POST['lg_wards'] ?? 9)));

        if (!$adminName)  $errors[] = 'Admin name required.';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($adminPass) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($adminPass !== $adminPass2) $errors[] = 'Passwords do not match.';
        if (!$lgNp) $errors[] = 'Municipality Nepali name required.';
        if (!$lgEn) $errors[] = 'Municipality English name required.';

        if (empty($errors) && $db) {
            $lgRes = ins_seedLg($db, $lgNp, $lgEn, $lgType, $lgWards);
            if (!$lgRes['ok']) {
                $errors[] = 'LG seed failed: ' . h($lgRes['msg']);
            } else {
                $adminRes = ins_createAdmin($db, $adminName, $adminEmail, $adminPass, $lgRes['lg_id']);
                if (!$adminRes['ok']) {
                    $errors[] = $adminRes['msg'];
                } else {
                    $lock['step'] = 6; $lock['completed'] = true;
                    $lock['admin_email'] = $adminEmail;
                    $lock['app_url'] = $lock['app']['app_url'] ?? '';
                    // Write permanent lock so re-opening installer after temp-dir wipe is blocked
                    @file_put_contents($root . '/.install_lock', date('Y-m-d H:i:s') . ' installed by ' . $adminEmail . "\n");
                    ins_lockSave($lock); header('Location: '.$_SERVER['PHP_SELF']); exit;
                }
            }
        }
    }

    // ── Back navigation ───────────────────────────────────────
    if ($action === 'back' && $step > 1) {
        $lock['step'] = $step - 1;
        ins_lockSave($lock); header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    $lock = ins_lock(); $step = (int)($lock['step'] ?? 1); $root = $lock['laravel_root'] ?? LARAVEL_ROOT_DEFAULT;
}

$step = (int)($lock['step'] ?? 1);
$root = $lock['laravel_root'] ?? LARAVEL_ROOT_DEFAULT;
if ($completed) $step = 6;

// Pre-compute requirements for step 1 display
$reqs = ($step === 1) ? ins_checkReqs($root) : [];
$hasCriticalFail = !empty(array_filter($reqs, fn($r) => $r['critical'] && !$r['ok']));
?>
<!DOCTYPE html>
<html lang="ne" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LGPAS Installer v<?= INS_VER ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    body { font-family: 'Segoe UI', system-ui, sans-serif; }
    @keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    .fade-up { animation: fadeUp .35s ease both; }
    .req-row { display:flex; align-items:flex-start; gap:8px; padding:5px 0; border-bottom:1px solid #f0f0f0; font-size:12px; }
    .req-row:last-child { border-bottom:none; }
    code { background:#f3f4f6; padding:1px 5px; border-radius:3px; font-size:11px; font-family:monospace; }
    .log-line { font-size:11px; font-family:monospace; padding:2px 0; border-bottom:1px solid #f3f4f6; }
  </style>
</head>
<body class="min-h-full bg-gradient-to-br from-[#0f1c35] via-[#1a2e4a] to-[#0d2137] py-10 px-4">

<!-- Decorative rings -->
<div class="fixed inset-0 pointer-events-none overflow-hidden opacity-[0.04]">
  <div class="absolute top-0 left-0 w-96 h-96 rounded-full border border-white -translate-x-1/2 -translate-y-1/2"></div>
  <div class="absolute bottom-0 right-0 w-[600px] h-[600px] rounded-full border border-white translate-x-1/3 translate-y-1/3"></div>
</div>

<div class="relative max-w-2xl mx-auto fade-up">

  <!-- Header -->
  <div class="text-center mb-6">
    <div class="inline-flex items-center gap-3 mb-2">
      <div class="w-10 h-10 bg-[#206bc4] rounded-xl flex items-center justify-center">
        <i class="fa-solid fa-building-columns text-white text-lg"></i>
      </div>
      <div class="text-left">
        <h1 class="text-white font-bold text-lg leading-tight">LGPAS Installer</h1>
        <p class="text-white/40 text-xs">स्थानीय तह वस्तुगत विवरण प्रणाली · v<?= INS_VER ?></p>
      </div>
    </div>
  </div>

  <!-- Progress bar -->
  <?php if (!$completed): ?>
  <div class="bg-white/10 rounded-full h-1.5 mb-6 overflow-hidden">
    <div class="bg-[#206bc4] h-full rounded-full transition-all duration-500"
         style="width: <?= round(($step / 6) * 100) ?>%"></div>
  </div>
  <div class="flex justify-between mb-8 px-1">
    <?php
    $stepLabels = ['Requirements','Database','App Config','Install','Admin','Complete'];
    foreach ($stepLabels as $i => $label): $n = $i + 1;
      $done    = $n < $step;
      $current = $n === $step;
    ?>
    <div class="flex flex-col items-center gap-1">
      <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all
        <?= $done ? 'bg-green-500 text-white' : ($current ? 'bg-[#206bc4] text-white ring-2 ring-[#206bc4]/40' : 'bg-white/10 text-white/40') ?>">
        <?= $done ? '<i class="fa-solid fa-check text-[10px]"></i>' : $n ?>
      </div>
      <span class="text-[9px] font-medium hidden sm:block
        <?= $current ? 'text-white' : ($done ? 'text-green-400' : 'text-white/30') ?>">
        <?= $label ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Card -->
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="h-1 bg-gradient-to-r from-[#206bc4] via-sky-400 to-[#206bc4]"></div>

    <!-- Errors -->
    <?php if ($errors): ?>
    <div class="px-7 pt-5">
      <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
          <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
          <span class="text-[13px] font-semibold text-red-700">Please fix the following:</span>
        </div>
        <ul class="space-y-1">
          <?php foreach ($errors as $e): ?>
          <li class="text-[12px] text-red-600 flex gap-1.5"><span class="shrink-0">•</span><span><?= $e ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════ -->
    <!--  STEP 1 — Server Requirements               -->
    <!-- ════════════════════════════════════════════ -->
    <?php if ($step === 1): ?>
    <div class="px-7 py-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-server text-[#206bc4]"></i>
        </div>
        <div>
          <h2 class="text-[15px] font-bold text-[#1d273b]">Server Requirements</h2>
          <p class="text-[11px] text-gray-400">PHP, extensions, and writable directories</p>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="step1">

        <!-- Laravel Root Path -->
        <div class="mb-5">
          <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
            Laravel Root Path <span class="text-red-400">*</span>
          </label>
          <input type="text" name="laravel_root" value="<?= h($root) ?>"
            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] font-mono focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4] outline-none"
            placeholder="/home/username/apprmp" />
          <p class="text-[11px] text-gray-400 mt-1">
            Full path to the folder containing <code>artisan</code> and <code>composer.json</code>.
            On cPanel this is usually <code>/home/username/apprmp</code> (NOT inside public_html).
          </p>
        </div>

        <!-- Requirements table -->
        <?php
        $groups = ['PHP' => [], 'Path' => [], 'Permissions' => []];
        foreach ($reqs as $r) $groups[$r['group'] ?? 'PHP'][] = $r;
        ?>
        <?php foreach ($groups as $gName => $gItems): if (!$gItems) continue; ?>
        <div class="mb-4">
          <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-2"><?= $gName ?></h3>
          <div class="bg-gray-50 rounded-xl border border-gray-100 px-4 py-1">
            <?php foreach ($gItems as $r): ?>
            <div class="req-row">
              <span class="shrink-0 mt-0.5 <?= $r['ok'] ? 'text-green-500' : ($r['critical'] ? 'text-red-500' : 'text-amber-500') ?>">
                <i class="fa-solid <?= $r['ok'] ? 'fa-check-circle' : 'fa-times-circle' ?> text-xs"></i>
              </span>
              <span class="font-medium text-gray-700 flex-1"><?= h($r['label']) ?></span>
              <span class="text-gray-400 text-right max-w-[220px]"><?= h($r['note']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if ($hasCriticalFail): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-[12px] text-red-700">
          <i class="fa-solid fa-triangle-exclamation mr-1"></i>
          Critical requirements are not met. Contact your hosting provider to enable the required PHP extensions.
        </div>
        <?php endif; ?>

        <div class="flex justify-end mt-5">
          <button type="submit"
            class="inline-flex items-center gap-2 bg-[#206bc4] hover:bg-[#1a5ba8] text-white font-semibold px-6 py-2.5 rounded-lg text-[13px] transition-all">
            Continue <i class="fa-solid fa-arrow-right text-xs"></i>
          </button>
        </div>
      </form>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!--  STEP 2 — Database                          -->
    <!-- ════════════════════════════════════════════ -->
    <?php elseif ($step === 2): ?>
    <?php $savedDb = $lock['db'] ?? []; ?>
    <div class="px-7 py-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-9 h-9 bg-emerald-50 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-database text-emerald-500"></i>
        </div>
        <div>
          <h2 class="text-[15px] font-bold text-[#1d273b]">Database Configuration</h2>
          <p class="text-[11px] text-gray-400">MySQL / MariaDB connection details</p>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="step2">

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">DB Host</label>
            <input type="text" name="db_host" value="<?= h($savedDb['host'] ?? 'localhost') ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 outline-none" />
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">DB Port</label>
            <input type="text" name="db_port" value="<?= h($savedDb['port'] ?? '3306') ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 outline-none" />
          </div>
          <div class="col-span-2">
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Database Name <span class="text-red-400">*</span></label>
            <input type="text" name="db_name" value="<?= h($savedDb['name'] ?? '') ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 outline-none"
              placeholder="e.g. prayasne_lgpas" />
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">DB Username <span class="text-red-400">*</span></label>
            <input type="text" name="db_user" value="<?= h($savedDb['user'] ?? '') ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 outline-none"
              placeholder="e.g. prayasne_user" />
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">DB Password</label>
            <input type="password" name="db_pass" value=""
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 outline-none" />
          </div>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 mt-4 text-[11px] text-blue-700">
          <i class="fa-solid fa-circle-info mr-1"></i>
          Create the database in cPanel → MySQL Databases first, then enter credentials above.
        </div>

        <div class="flex justify-between mt-6">
          <button type="submit" name="action" value="back"
            class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-[13px] transition-all">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
          </button>
          <button type="submit"
            class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-6 py-2.5 rounded-lg text-[13px] transition-all">
            Test & Continue <i class="fa-solid fa-arrow-right text-xs"></i>
          </button>
        </div>
      </form>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!--  STEP 3 — App Configuration                 -->
    <!-- ════════════════════════════════════════════ -->
    <?php elseif ($step === 3): ?>
    <?php $savedApp = $lock['app'] ?? []; ?>
    <div class="px-7 py-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-9 h-9 bg-violet-50 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-gear text-violet-500"></i>
        </div>
        <div>
          <h2 class="text-[15px] font-bold text-[#1d273b]">Application Configuration</h2>
          <p class="text-[11px] text-gray-400">These values are written to your .env file</p>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="step3">

        <div class="space-y-4">
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Application Name <span class="text-red-400">*</span></label>
            <input type="text" name="app_name" value="<?= h($savedApp['app_name'] ?? 'स्थानीय तह विवरण प्रणाली') ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-violet-500/20 focus:border-violet-400 outline-none" />
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">APP_URL <span class="text-red-400">*</span></label>
            <input type="text" name="app_url" value="<?= h($savedApp['app_url'] ?? $autoUrl) ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] font-mono focus:ring-2 focus:ring-violet-500/20 focus:border-violet-400 outline-none"
              placeholder="https://yourdomain.com/apprmp" />
            <p class="text-[11px] text-gray-400 mt-1">No trailing slash. Must match exact URL where the app is accessible.</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Environment</label>
              <select name="app_env"
                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-violet-500/20 focus:border-violet-400 outline-none bg-white">
                <option value="production" <?= ($savedApp['app_env'] ?? 'production') === 'production' ? 'selected' : '' ?>>production</option>
                <option value="local"      <?= ($savedApp['app_env'] ?? '') === 'local'      ? 'selected' : '' ?>>local</option>
                <option value="staging"    <?= ($savedApp['app_env'] ?? '') === 'staging'    ? 'selected' : '' ?>>staging</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Debug Mode</label>
              <select name="app_debug"
                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] focus:ring-2 focus:ring-violet-500/20 focus:border-violet-400 outline-none bg-white">
                <option value="false" <?= ($savedApp['debug'] ?? 'false') === 'false' ? 'selected' : '' ?>>false (Production)</option>
                <option value="true"  <?= ($savedApp['debug'] ?? '') === 'true'  ? 'selected' : '' ?>>true (Development)</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Timezone</label>
            <input type="text" name="timezone" value="<?= h($savedApp['timezone'] ?? 'Asia/Kathmandu') ?>"
              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-[13px] font-mono focus:ring-2 focus:ring-violet-500/20 focus:border-violet-400 outline-none" />
          </div>
        </div>

        <div class="flex justify-between mt-6">
          <button type="submit" name="action" value="back"
            class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-[13px] transition-all">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
          </button>
          <button type="submit"
            class="inline-flex items-center gap-2 bg-violet-500 hover:bg-violet-600 text-white font-semibold px-6 py-2.5 rounded-lg text-[13px] transition-all">
            Continue <i class="fa-solid fa-arrow-right text-xs"></i>
          </button>
        </div>
      </form>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!--  STEP 4 — Run Installation                  -->
    <!-- ════════════════════════════════════════════ -->
    <?php elseif ($step === 4): ?>
    <?php $installLog = $lock['install_log'] ?? []; ?>
    <div class="px-7 py-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-9 h-9 bg-amber-50 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-bolt text-amber-500"></i>
        </div>
        <div>
          <h2 class="text-[15px] font-bold text-[#1d273b]">Run Installation</h2>
          <p class="text-[11px] text-gray-400">Write .env, generate key, run migrations, optimize</p>
        </div>
      </div>

      <!-- Config summary -->
      <div class="bg-gray-50 rounded-xl border border-gray-100 p-4 mb-5 text-[12px] space-y-2">
        <h3 class="font-semibold text-gray-500 text-[11px] uppercase tracking-wide mb-2">Configuration Summary</h3>
        <?php $a = $lock['app'] ?? []; $d = $lock['db'] ?? []; ?>
        <div class="grid grid-cols-2 gap-x-6 gap-y-1">
          <div><span class="text-gray-400">App Name:</span> <strong><?= h($a['app_name'] ?? '—') ?></strong></div>
          <div><span class="text-gray-400">APP_URL:</span> <code><?= h($a['app_url'] ?? '—') ?></code></div>
          <div><span class="text-gray-400">Environment:</span> <strong><?= h($a['app_env'] ?? '—') ?></strong></div>
          <div><span class="text-gray-400">Debug:</span> <strong><?= h($a['debug'] ?? 'false') ?></strong></div>
          <div><span class="text-gray-400">DB Host:</span> <code><?= h($d['host'] ?? '—') ?></code></div>
          <div><span class="text-gray-400">DB Name:</span> <code><?= h($d['name'] ?? '—') ?></code></div>
          <div><span class="text-gray-400">DB User:</span> <code><?= h($d['user'] ?? '—') ?></code></div>
          <div><span class="text-gray-400">PHP CLI:</span> <code><?= h(ins_findPhp()) ?></code></div>
        </div>
      </div>

      <!-- Previous install log (if failed) -->
      <?php if ($installLog): ?>
      <div class="mb-5">
        <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2">Last Run Log</h3>
        <div class="bg-gray-900 rounded-xl p-4 space-y-1 max-h-48 overflow-y-auto">
          <?php foreach ($installLog as $l): ?>
          <div class="log-line <?= $l['ok'] ? 'text-green-400' : 'text-red-400' ?>">
            <?= $l['ok'] ? '✓' : '✗' ?> <?= h($l['label']) ?>
            <?php if (!empty($l['out'])): ?>
            <pre class="text-gray-400 whitespace-pre-wrap text-[10px] mt-0.5 pl-4"><?= h(mb_substr($l['out'],0,4000)) ?></pre>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Manual fallback note -->
      <?php if (!function_exists('exec') && !function_exists('shell_exec')): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-[12px] text-amber-800">
        <p class="font-semibold mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>CLI PHP is disabled</p>
        <p class="mb-2">Run these commands manually via SSH or cPanel Terminal:</p>
        <pre class="bg-white rounded-lg p-3 text-[11px] font-mono border border-amber-200 overflow-x-auto">cd <?= h($lock['laravel_root'] ?? LARAVEL_ROOT_DEFAULT) ?>

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link --force
php artisan optimize</pre>
        <p class="mt-2">Then click the button below to write .env and continue.</p>
      </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="step4">
        <div class="flex justify-between mt-2">
          <button type="submit" name="action" value="back"
            class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-[13px] transition-all">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
          </button>
          <button type="submit" id="installBtn"
            class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-6 py-2.5 rounded-lg text-[13px] transition-all"
            onclick="this.innerHTML='<i class=\'fa-solid fa-spinner fa-spin text-xs\'></i> Installing…'; this.disabled=true; this.form.submit();">
            <i class="fa-solid fa-bolt text-xs"></i>
            <?= $installLog ? 'Retry Installation' : 'Run Installation' ?>
          </button>
        </div>
      </form>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!--  STEP 5 — Admin Account + LG Setup          -->
    <!-- ════════════════════════════════════════════ -->
    <?php elseif ($step === 5): ?>
    <div class="px-7 py-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-9 h-9 bg-rose-50 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-user-shield text-rose-500"></i>
        </div>
        <div>
          <h2 class="text-[15px] font-bold text-[#1d273b]">Admin Account & Municipality</h2>
          <p class="text-[11px] text-gray-400">Create the Super Admin and configure your municipality</p>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="step5">

        <!-- Municipality -->
        <div class="bg-gray-50 rounded-xl border border-gray-100 p-4 mb-5">
          <h3 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">
            <i class="fa-solid fa-building-columns mr-1"></i>Municipality Details
          </h3>
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nepali Name <span class="text-red-400">*</span></label>
                <input type="text" name="lg_name_np" value="<?= v('lg_name_np') ?>"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none"
                  placeholder="जस्तै: आमचोक गाउँपालिका" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">English Name <span class="text-red-400">*</span></label>
                <input type="text" name="lg_name_en" value="<?= v('lg_name_en') ?>"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none"
                  placeholder="e.g. Aamchowk Rural Municipality" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Type</label>
                <select name="lg_type"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none bg-white">
                  <option value="rural_municipality">Rural Municipality (गाउँपालिका)</option>
                  <option value="municipality">Municipality (नगरपालिका)</option>
                  <option value="sub_metropolitan">Sub-Metropolitan (उपमहानगर)</option>
                  <option value="metropolitan">Metropolitan (महानगर)</option>
                </select>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Number of Wards</label>
                <input type="number" name="lg_wards" value="<?= v('lg_wards', '9') ?>" min="1" max="33"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none" />
              </div>
            </div>
          </div>
        </div>

        <!-- Admin -->
        <div class="bg-gray-50 rounded-xl border border-gray-100 p-4 mb-5">
          <h3 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">
            <i class="fa-solid fa-user mr-1"></i>Super Admin Account
          </h3>
          <div class="space-y-3">
            <div>
              <label class="block text-[12px] font-semibold text-gray-600 mb-1">Full Name <span class="text-red-400">*</span></label>
              <input type="text" name="admin_name" value="<?= v('admin_name') ?>"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none" />
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-gray-600 mb-1">Email Address <span class="text-red-400">*</span></label>
              <input type="email" name="admin_email" value="<?= v('admin_email') ?>"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Password <span class="text-red-400">*</span></label>
                <input type="password" name="admin_pass"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none"
                  placeholder="Minimum 8 characters" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Confirm Password <span class="text-red-400">*</span></label>
                <input type="password" name="admin_pass2"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 outline-none" />
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-between">
          <button type="submit" name="action" value="back"
            class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-[13px] transition-all">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
          </button>
          <button type="submit"
            class="inline-flex items-center gap-2 bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-2.5 rounded-lg text-[13px] transition-all">
            <i class="fa-solid fa-rocket text-xs"></i> Complete Setup
          </button>
        </div>
      </form>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!--  STEP 6 — Complete                          -->
    <!-- ════════════════════════════════════════════ -->
    <?php elseif ($step === 6): ?>
    <?php $appUrl = $lock['app_url'] ?? ''; $adminEmail = $lock['admin_email'] ?? ''; ?>
    <div class="px-7 py-10 text-center">
      <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-check text-green-500 text-3xl"></i>
      </div>
      <h2 class="text-[20px] font-bold text-[#1d273b] mb-2">Installation Complete!</h2>
      <p class="text-[13px] text-gray-500 mb-6">
        LGPAS is ready. Log in as <strong><?= h($adminEmail) ?></strong> to get started.
      </p>

      <div class="flex flex-col sm:flex-row gap-3 justify-center mb-8">
        <a href="<?= h($appUrl) ?>/login"
           class="inline-flex items-center gap-2 bg-[#206bc4] hover:bg-[#1a5ba8] text-white font-semibold px-6 py-3 rounded-xl text-[13px] transition-all">
          <i class="fa-solid fa-right-to-bracket"></i> Admin Login
        </a>
        <a href="<?= h($appUrl) ?>/public"
           class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl text-[13px] transition-all">
          <i class="fa-solid fa-globe"></i> Public Dashboard
        </a>
      </div>

      <!-- Post-install checklist -->
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left mb-5">
        <h3 class="text-[12px] font-bold text-amber-800 mb-2 flex items-center gap-1.5">
          <i class="fa-solid fa-triangle-exclamation"></i>Post-Installation Checklist
        </h3>
        <ul class="space-y-1.5 text-[12px] text-amber-700">
          <li class="flex gap-2"><i class="fa-solid fa-trash-can mt-0.5 shrink-0"></i><span><strong>Delete install.php</strong> — Remove this file from your server immediately</span></li>
          <li class="flex gap-2"><i class="fa-solid fa-building-columns mt-0.5 shrink-0"></i><span>Go to <strong>Settings → सामान्य</strong> and complete your municipality profile</span></li>
          <li class="flex gap-2"><i class="fa-solid fa-map mt-0.5 shrink-0"></i><span>Go to <strong>Settings → नक्सा</strong> to configure the map center and GeoJSON URL</span></li>
          <li class="flex gap-2"><i class="fa-solid fa-file-import mt-0.5 shrink-0"></i><span>Go to <strong>Settings → सर्वेक्षण विकल्प</strong> to import survey dropdown options</span></li>
          <li class="flex gap-2"><i class="fa-solid fa-users mt-0.5 shrink-0"></i><span>Create ward reviewers and enumerator accounts</span></li>
        </ul>
      </div>

      <!-- Delete button -->
      <form method="POST" onsubmit="return confirm('Delete install.php permanently?')">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="delete_installer">
        <button type="submit"
          class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold px-5 py-2 rounded-lg text-[12px] transition-all">
          <i class="fa-solid fa-trash-can text-xs"></i> Delete install.php Now
        </button>
      </form>
    </div>
    <?php endif; ?>

  </div>

  <p class="text-center text-white/20 text-[10px] mt-5">LGPAS v<?= INS_VER ?> · Prayas Nepal · Lock: <?= h(basename(ins_lockFile())) ?></p>
</div>

<?php
// Handle installer self-deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_installer'
    && ($_POST['_token'] ?? '') === $token) {
    @unlink(__FILE__);
    @unlink(ins_lockFile());
    echo '<script>document.body.innerHTML=\'<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;background:#0f1c35;color:#fff;text-align:center"><div><h2 style="font-size:20px;margin-bottom:8px">✓ Installer Deleted</h2><p style="opacity:.6;font-size:13px">install.php has been removed. You\'re all set!</p></div></div>\';setTimeout(()=>location.href=\'' . addslashes(h($lock['app_url'] ?? '/')) . '/login\',3000);</script>';
    exit;
}
?>

</body>
</html>
