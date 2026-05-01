<?php
// ONE-TIME DEPLOY RUNNER — DELETE IMMEDIATELY AFTER USE
if (($_GET['token'] ?? '') !== 'deploy2026secret') {
    http_response_code(403);
    die('forbidden');
}

$php  = '/opt/cpanel/ea-php84/root/usr/bin/php';
$root = '/home2/prayasne/apprmp';

chdir($root);

// Fix public assets — symlink build/ and copy PWA/htaccess files
$webRoot    = __DIR__;                    // public_html/apprmp/
$laravelPub = $root . '/public';          // apprmp/public/

// Symlink build/
$buildLink = $webRoot . '/build';
if (!file_exists($buildLink)) {
    @symlink($laravelPub . '/build', $buildLink);
}

// Copy individual files
foreach (array_merge(
    glob($laravelPub . '/sw.js')               ?: [],
    glob($laravelPub . '/workbox-*.js')         ?: [],
    glob($laravelPub . '/manifest.webmanifest') ?: [],
    glob($laravelPub . '/offline.html')         ?: [],
    glob($laravelPub . '/pwa-192.png')          ?: [],
    glob($laravelPub . '/pwa-512.png')          ?: [],
    glob($laravelPub . '/.htaccess')            ?: [],
) as $src) {
    $dest = $webRoot . '/' . basename($src);
    if (!file_exists($dest)) @copy($src, $dest);
}

$commands = [
    'Migrate database'           => "$php artisan migrate --force",
    'Seed: NepalMasterData'      => "$php artisan db:seed --class=NepalMasterDataSeeder --force",
    'Seed: InstitutionTypes'     => "$php artisan db:seed --class=InstitutionTypesSeeder --force",
    'Seed: MasterData'           => "$php artisan db:seed --class=MasterDataSeeder --force",
    'Seed: DisasterOptions'      => "$php artisan db:seed --class=DisasterOptionSeeder --force",
    'Optimize (cache routes etc)'=> "$php artisan optimize",
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
  .warn{ color: #fb923c; }
  hr   { border-color: #333; }
</style>
</head>
<body>
<h1>⚠ Deploy Runner — DELETE THIS FILE AFTER USE</h1>
<p style="color:#f87171">Visit <strong>?token=deploy2026secret&delete=1</strong> to self-delete this file when done.</p>
<hr>
<?php

// Self-delete mode
if (($_GET['delete'] ?? '') === '1' && ($_GET['token'] ?? '') === 'deploy2026secret') {
    unlink(__FILE__);
    echo '<h2 style="color:#4ade80">✓ run.php deleted successfully.</h2>';
    echo '<p>Deploy complete. <a href="https://apprmp.prayasnepal.com/public" style="color:#60a5fa">Open site →</a></p>';
    exit;
}

$allOk = true;

foreach ($commands as $label => $cmd) {
    echo "<h3>▶ $label</h3>";
    echo "<pre><small style='color:#666'>$ $cmd</small>\n";

    $output   = shell_exec($cmd . ' 2>&1');
    $hasError = preg_match('/error|exception|fatal|fail/i', $output ?? '');

    $class = $hasError ? 'err' : 'ok';
    if ($hasError) $allOk = false;

    echo "<span class='$class'>" . htmlspecialchars($output ?? '(no output)') . "</span>";
    echo "</pre>";
}

echo "<hr>";
if ($allOk) {
    echo "<h2 class='ok'>✓ All commands completed successfully.</h2>";
} else {
    echo "<h2 class='err'>⚠ Some commands had errors — review output above.</h2>";
}

echo "<p><a href='?token=deploy2026secret&delete=1' style='background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;font-size:16px;'>🗑 Delete this file now</a></p>";
?>
</body>
</html>
