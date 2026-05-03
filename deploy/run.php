<?php
// ONE-TIME DEPLOY RUNNER — DELETE IMMEDIATELY AFTER USE
if (($_GET['token'] ?? '') !== 'deploy2026secret') {
    http_response_code(403);
    die('forbidden');
}

$root    = '/home2/prayasne/apprmp';
$webRoot = __DIR__;

// ── Auto-detect PHP 8.2+ binary ───────────────────────────────────────────────
$phpPaths = [
    '/opt/cpanel/ea-php84/root/usr/bin/php',
    '/opt/cpanel/ea-php83/root/usr/bin/php',
    '/opt/cpanel/ea-php82/root/usr/bin/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
];
$php = null;
foreach ($phpPaths as $p) {
    $ver = @shell_exec("$p -r 'echo PHP_VERSION;' 2>/dev/null");
    if ($ver && version_compare(trim($ver), '8.2.0', '>=')) {
        $php = $p;
        break;
    }
}
if (!$php) {
    die('<h2 style="color:red;font-family:monospace">No PHP 8.2+ binary found on this server.</h2>');
}

// ── Copy build/ and public assets to web root ─────────────────────────────────
$laravelPub = $root . '/public';

function copyDirRecursive($src, $dst) {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..') continue;
        $s = $src . '/' . $f;
        $d = $dst . '/' . $f;
        is_dir($s) ? copyDirRecursive($s, $d) : @copy($s, $d);
    }
}

$buildSrc = $laravelPub . '/build';
$buildDst = $webRoot . '/build';
if (is_dir($buildSrc) && !is_dir($buildDst)) {
    copyDirRecursive($buildSrc, $buildDst);
}

foreach (array_merge(
    glob($laravelPub . '/sw.js')               ?: [],
    glob($laravelPub . '/workbox-*.js')         ?: [],
    glob($laravelPub . '/manifest.webmanifest') ?: [],
    glob($laravelPub . '/offline.html')         ?: [],
    glob($laravelPub . '/pwa-192.png')          ?: [],
    glob($laravelPub . '/pwa-512.png')          ?: [],
    glob($laravelPub . '/.htaccess')            ?: []
) as $src) {
    $dest = $webRoot . '/' . basename($src);
    if (!file_exists($dest)) @copy($src, $dest);
}

// ── Artisan commands ──────────────────────────────────────────────────────────
$commands = [
    'Clear config cache'         => "$php artisan config:clear",
    'Migrate database'           => "$php artisan migrate --force",
    'Seed: NepalMasterData'      => "$php artisan db:seed --class=NepalMasterDataSeeder --force",
    'Seed: InstitutionTypes'     => "$php artisan db:seed --class=InstitutionTypesSeeder --force",
    'Seed: MasterData'           => "$php artisan db:seed --class=MasterDataSeeder --force",
    'Seed: DisasterOptions'      => "$php artisan db:seed --class=DisasterOptionSeeder --force",
    'Optimize'                   => "$php artisan optimize",
    'Clear cache'                => "$php artisan cache:clear",
];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Deploy Runner</title>
<style>
  body { font-family: monospace; background: #1a1a1a; color: #e0e0e0; padding: 20px; }
  h1   { color: #facc15; }
  h3   { color: #60a5fa; margin: 20px 0 5px; }
  pre  { background: #111; border-left: 3px solid #333; padding: 12px; white-space: pre-wrap; word-break: break-all; }
  .ok  { color: #4ade80; }
  .err { color: #f87171; }
  hr   { border-color: #333; }
</style>
</head>
<body>
<h1>Deploy Runner</h1>
<p style="color:#fb923c">DELETE THIS FILE after use — click the button at the bottom.</p>
<p class="ok">PHP binary: <?= htmlspecialchars($php) ?> (<?= trim(shell_exec("$php -r 'echo PHP_VERSION;'")) ?>)</p>
<hr>
<?php

if (($_GET['delete'] ?? '') === '1') {
    unlink(__FILE__);
    echo '<h2 class="ok">✓ run.php deleted.</h2>';
    echo '<p><a href="https://apprmp.prayasnepal.com/public" style="color:#60a5fa">Open site →</a></p>';
    exit;
}

chdir($root);
$allOk = true;

foreach ($commands as $label => $cmd) {
    echo "<h3>▶ $label</h3>";
    echo "<pre><span style='color:#555'>$ $cmd</span>\n";
    $output   = shell_exec($cmd . ' 2>&1');
    $hasError = preg_match('/error|exception|fatal|fail/i', $output ?? '');
    if ($hasError) $allOk = false;
    echo "<span class='" . ($hasError ? 'err' : 'ok') . "'>";
    echo htmlspecialchars($output ?? '(no output)');
    echo "</span></pre>";
}

echo '<hr>';
echo $allOk
    ? '<h2 class="ok">✓ All commands completed successfully.</h2>'
    : '<h2 class="err">⚠ Some commands had errors — review above.</h2>';

echo '<p><a href="?token=deploy2026secret&delete=1" style="background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;font-size:16px;">🗑 Delete this file</a></p>';
?>
</body>
</html>
