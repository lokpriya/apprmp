<?php
/**
 * Verify a deploy ZIP by CONTENT before uploading it.
 *
 *   php deploy/verify-zip.php aamchowk-deploy.zip
 *   php deploy/verify-zip.php aamchowk-deploy.zip --target=aamchowk
 *
 * Why this exists: on 12 September 2026 the ZIPs shipped were built at 06:31
 * while the fixes they were supposed to carry were committed at 13:52 and
 * 14:10. Nothing in the process caught it, and a deploy went out without the
 * fixes. A ZIP's timestamp says when it was zipped, not what is inside it.
 *
 * Structural checks below are permanent. RELEASE_CHECKS is per release: replace
 * it each time with a few strings that exist only in the commits being shipped.
 * Vue changes are checked twice — once in the source and once inside the built
 * bundle, because forgetting `npm run build` produces a ZIP whose source is
 * right and whose behaviour is not.
 *
 * Exit code 0 = safe to upload, 1 = do not upload.
 */

// ── Per-release checks — edit these for each deploy ──────────────────────────
// Release of 2026-09-22: ee0558e health mapping, 21b2974 ward scoping,
// d82af2d pagination + institution filters.
const RELEASE_CHECKS = [
    'app/Services/GoogleSheetsSyncService.php'              => ['extractHealthData', 'हो अथवा छ'],
    'app/Console/Commands/BackfillHealthSection.php'        => ['health:backfill'],
    'database/seeders/FixCorruptedSurveyOptionsSeeder.php'  => ["'hospital'"],
    'app/Support/ScopesWardAccess.php'                      => ['wardRule', 'selectableWards'],
    'app/Http/Controllers/HouseholdController.php'          => ['ScopesWardAccess', 'wardRule('],
    'app/Http/Controllers/InstitutionController.php'        => ['ScopesWardAccess', 'settlement_id'],
    'app/Support/InertiaPaginator.php'                      => ['last_page'],
    'app/Http/Controllers/ReviewController.php'             => ['InertiaPaginator::format'],
    'app/Http/Controllers/Admin/PublishQueueController.php' => ['InertiaPaginator::format'],
    'resources/js/pages/Institutions/Index.vue'             => ['visibleSettlements'],
    'resources/js/pages/Households/Form.vue'                => ['hasSingleWard'],
    'deploy/run-health.php'                                 => ['health:backfill'],
    // ── 8fba0ca + 767f2c9  health facility report ────────────────────────────
    'app/Http/Controllers/Public/InstitutionReportController.php'
        => ['HEALTH_INSTITUTION_TYPES', 'facilities_with_detail'],
    'resources/js/pages/Public/InstitutionReports/Show.vue' => ['healthDetailMissing'],
    'resources/js/i18n/ne.js'                               => ['healthDetailMissingTitle'],
    // ── 1.8.0  one-file updater (must reach every site via this full ZIP) ────
    'VERSION'                                               => ['1.8.1'],
    'config/updates.php'                                    => ["'public_key' => 'lcQX"],
    'app/Services/Updates/UpdateApplier.php'                => ['stepSwap'],
    'app/Http/Controllers/Admin/SystemUpdateController.php' => ['setMaintenance', 'recordFromState'],
    'routes/web.php'                                        => ["Route::middleware('password.confirm')", 'system-update/step'],
    'database/migrations/2026_10_10_000001_create_system_updates_table.php' => ['system_updates'],
    // ── 1.8.1  सदस्य खोज member search ───────────────────────────────────────
    'app/Http/Controllers/MemberSearchController.php'       => ['members.search_all_wards', 'settlement_id'],
    'app/Support/RolePermissions.php'                       => ['INTRODUCED_LATER', 'forSaving'],
    'public/manifest.webmanifest'                           => ['/member-search'],
];

/** Strings that must appear in the COMPILED bundle, proving npm run build ran. */
const BUILT_BUNDLE_CHECKS = [
    'सबै टोल'                                      => 'institution tole filter',
    'तपाईंलाई तोकिएको वडा'                        => 'single-ward auto-select hint',
    'संस्थाको विस्तृत विवरण अभिलेख भएको छैन'      => 'health "not recorded" notice',
    'प्रणाली अद्यावधिक'                            => 'system update page',
    'टोलका सबै घरधुरी हेर्नुहोस्'                 => 'member search with tole filter',
];

// ── Arguments ────────────────────────────────────────────────────────────────
$zipPath = null;
$target  = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--target=')) { $target = substr($arg, 9); continue; }
    $zipPath ??= $arg;
}
if (!$zipPath) {
    fwrite(STDERR, "usage: php deploy/verify-zip.php <zip> [--target=aamchowk]\n");
    exit(1);
}
if ($target === null && preg_match('/^([a-z]+)-deploy\.zip$/i', basename($zipPath), $m)) {
    $target = strtolower($m[1]);
}

/** Laravel root each per-site run script must point at. */
const SITE_ROOTS = [
    'aamchowk' => '/home2/prayaspr/aamchowk',
    'aathrai'  => '/home2/prayaspr/aathrai',
    'rongdata' => '/home2/prayaspr/rong',
    'apprmp'   => '/home2/prayasne/apprmp',
    'ajirkot'  => '/home2/prayaspr/ajirkot',   // moved to the prayaspr account 2026-09-26
];

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "FAIL: cannot open {$zipPath}\n");
    exit(1);
}

printf("ZIP    : %s  (%.1f MB, %d entries)\n", basename($zipPath), filesize($zipPath) / 1048576, $zip->numFiles);
printf("Target : %s\n\n", $target ?? '(unknown — pass --target=)');

// Compress-Archive writes Windows separators, so index everything normalised.
$index = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $index[str_replace('\\', '/', $zip->getNameIndex($i))] = $i;
}
$read = fn(string $p) => isset($index[$p]) ? $zip->getFromIndex($index[$p]) : false;

$ok = true;
$fail = function (string $msg) use (&$ok) { $ok = false; return $msg; };

// ── 1. Release content ───────────────────────────────────────────────────────
echo "Release content\n";
foreach (RELEASE_CHECKS as $path => $needles) {
    $data = $read($path);
    if ($data === false) {
        printf("  %-5s %s\n", $fail('GONE'), $path);
        continue;
    }
    $missing = array_values(array_filter($needles, fn($n) => !str_contains($data, $n)));
    printf("  %-5s %-56s%s\n", $missing ? $fail('NO') : 'ok', $path,
        $missing ? '  missing: ' . implode(', ', $missing) : '');
}

// ── 2. Built frontend ────────────────────────────────────────────────────────
echo "\nBuilt frontend\n";
$publicBuild = $webBuild = 0;
foreach (array_keys($index) as $n) {
    if (str_starts_with($n, 'public/build/assets/'))  $publicBuild++;
    if (str_starts_with($n, 'webroot/build/assets/')) $webBuild++;
}
printf("  %-5s public/build=%d  webroot/build=%d\n",
    ($publicBuild && $webBuild) ? 'ok' : $fail('NO'), $publicBuild, $webBuild);

$bundle = '';
foreach (array_keys($index) as $n) {
    if (preg_match('#^public/build/assets/.*\.js$#', $n)) $bundle .= $zip->getFromIndex($index[$n]);
}
foreach (BUILT_BUNDLE_CHECKS as $needle => $label) {
    $found = str_contains($bundle, $needle);
    printf("  %-5s compiled bundle carries %s%s\n", $found ? 'ok' : $fail('NO'), $label,
        $found ? '' : '  <-- run npm run build, then rebuild the ZIP');
}

// ── 3. Nothing sensitive ─────────────────────────────────────────────────────
echo "\nSafety\n";
$leaks = array_values(array_filter(array_keys($index), fn($n) =>
    preg_match('/\.(sql|sqlite|sqlite-journal)(\.gz)?$/i', $n) || basename($n) === '.env'));
printf("  %-5s database dumps / .env: %s\n", $leaks ? $fail('NO') : 'ok',
    $leaks ? implode(' | ', $leaks) : 'none');

printf("  %-5s dev dependencies: %s\n",
    isset($index['vendor/phpunit/phpunit/composer.json']) ? $fail('NO') : 'ok',
    isset($index['vendor/phpunit/phpunit/composer.json'])
        ? 'phpunit present — rebuild after composer install --no-dev'
        : 'absent');

$installers = array_values(array_filter(array_keys($index), fn($n) => basename($n) === 'install.php'));
// Shipping it is normal; leaving it on the server is not, so this is a reminder.
printf("  note  install.php ships at: %s\n", $installers ? implode(', ', $installers) : 'none');
printf("        DELETE it from the web root after extracting — it can rewrite .env and\n");
printf("        regenerate APP_KEY, which destroys every stored 2FA secret.\n");

// ── 4. Per-site run script points at the right root ──────────────────────────
if ($target && isset(SITE_ROOTS[$target])) {
    echo "\nRun scripts\n";
    $expected = SITE_ROOTS[$target];
    $runFile  = "deploy/run-{$target}.php";
    $data     = $read($runFile);
    if ($data === false) {
        printf("  %-5s %s not in ZIP\n", $fail('NO'), $runFile);
    } else {
        $right = str_contains($data, $expected);
        printf("  %-5s %s -> %s\n", $right ? 'ok' : $fail('NO'), $runFile, $expected);
    }
    printf("  %-5s deploy/run-health.php (site-agnostic, works on every site)\n",
        $read('deploy/run-health.php') !== false ? 'ok' : $fail('NO'));
}

$zip->close();

echo "\n";
echo $ok
    ? "==> VERIFIED. Safe to upload.\n"
    : "==> PROBLEMS FOUND. Do not upload.\n";

exit($ok ? 0 : 1);
