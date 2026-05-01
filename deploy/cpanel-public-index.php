<?php

/**
 * ============================================================
 *  cPanel Public Index - Laravel Entry Point
 * ============================================================
 *
 *  FOLDER STRUCTURE ON cPANEL:
 *
 *  /home/username/
 *    apprmp/                  <- Laravel root (PRIVATE - never web accessible)
 *      app/, config/, vendor/, artisan ...
 *
 *    public_html/
 *      apprmp/                <- Web accessible at: domain.com/apprmp/
 *        index.php            <- THIS FILE (rename cpanel-public-index.php to index.php)
 *        .htaccess            <- Copy from apprmp/public/.htaccess
 *        install.php          <- Copy from apprmp/public/install.php
 *        css/, js/, images/   <- Copy from apprmp/public/ assets
 *
 *  EDIT ONLY THE LINE BELOW - set your actual Laravel root path.
 * ============================================================
 */

// Full absolute path to your Laravel app (the folder with artisan + composer.json)
// Subdirectory example: '/home/username/apprmp'
// Root domain example:  '/home/username/apprmp'
define('LARAVEL_ROOT', '/home/username/apprmp');

// ============================================================
//  DO NOT EDIT BELOW THIS LINE
// ============================================================

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-create storage symlink if missing.
// __DIR__ is public_html/apprmp/ — the correct web-accessible location.
(function () {
    $link   = __DIR__ . '/storage';
    $target = LARAVEL_ROOT . '/storage/app/public';
    if (!file_exists($link) && is_dir($target)) {
        @symlink($target, $link);
    }
})();

// Maintenance mode check
if (file_exists($maintenance = LARAVEL_ROOT . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register Composer autoloader
require LARAVEL_ROOT . '/vendor/autoload.php';

// Bootstrap Laravel and handle the request
/** @var Application $app */
$app = require_once LARAVEL_ROOT . '/bootstrap/app.php';

$app->handleRequest(Request::capture());
