<?php

use Illuminate\Http\Request;

$frontendPath = dirname(__DIR__, 3);
$backendPath = dirname($frontendPath).DIRECTORY_SEPARATOR.'backend';
$presentationMode = getenv('E2E_PRESENTATION') === '1';
$expectedRoot = $frontendPath.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.($presentationMode ? 'presentation' : 'e2e');
$runId = getenv('E2E_RUN_ID');
$expectedStorage = $expectedRoot.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runId.DIRECTORY_SEPARATOR.'storage';
$databaseName = getenv('E2E_DEMO') === '1' ? 'owner-demo.sqlite' : 'auth.sqlite';

if (($presentationMode && getenv('E2E_DEMO') !== '1') || getenv('APP_ENV') !== 'e2e' || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('DB_DATABASE') !== $expectedRoot.DIRECTORY_SEPARATOR.$databaseName
    || ! preg_match('/^[a-f0-9]{16}$/', $runId ?: '') || getenv('LARAVEL_STORAGE_PATH') !== $expectedStorage) {
    http_response_code(503);
    exit('The E2E server requires its isolated SQLite/storage environment.');
}

define('LARAVEL_START', microtime(true));
require $backendPath.'/vendor/autoload.php';

$app = require $backendPath.'/bootstrap/app.php';
$app->useStoragePath(getenv('LARAVEL_STORAGE_PATH'));
$app->handleRequest(Request::capture());
