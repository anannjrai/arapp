<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$scriptDir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
$basePath = is_dir($scriptDir.'/bootstrap') ? $scriptDir : __DIR__;

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
