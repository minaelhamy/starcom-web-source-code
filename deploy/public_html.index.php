<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/../starcom-web-source-code/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__ . '/../starcom-web-source-code/vendor/autoload.php';

$app = require_once __DIR__ . '/../starcom-web-source-code/bootstrap/app.php';

$app->handleRequest(Request::capture());
