<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

$frontendPath = dirname(__DIR__, 3);
$backendPath = dirname($frontendPath).DIRECTORY_SEPARATOR.'backend';
$presentationMode = getenv('E2E_PRESENTATION') === '1';
$mobileMode = getenv('E2E_MOBILE') === '1';
$expectedRoot = $frontendPath.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.($mobileMode ? 'mobile-https' : ($presentationMode ? 'presentation' : 'e2e'));
$runId = getenv('E2E_RUN_ID');
$expectedStorage = $expectedRoot.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runId.DIRECTORY_SEPARATOR.'storage';
$databaseName = getenv('E2E_DEMO') === '1' ? 'owner-demo.sqlite' : 'auth.sqlite';

if (($mobileMode && ! $presentationMode) || ($presentationMode && getenv('E2E_DEMO') !== '1') || getenv('APP_ENV') !== 'e2e' || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('DB_DATABASE') !== $expectedRoot.DIRECTORY_SEPARATOR.$databaseName
    || ! preg_match('/^[a-f0-9]{16}$/', $runId ?: '') || getenv('LARAVEL_STORAGE_PATH') !== $expectedStorage) {
    http_response_code(503);
    exit('The E2E server requires its isolated SQLite/storage environment.');
}

define('LARAVEL_START', microtime(true));
require $backendPath.'/vendor/autoload.php';

$app = require $backendPath.'/bootstrap/app.php';
$app->useStoragePath(getenv('LARAVEL_STORAGE_PATH'));
if ($mobileMode) {
    $origin = getenv('MOBILE_PUBLIC_ORIGIN') ?: '';
    if (! preg_match('#^https://[a-z0-9-]+\.trycloudflare\.com$#D', $origin) || getenv('SESSION_SECURE_COOKIE') !== 'true') {
        http_response_code(503);
        exit('Mobile demo requires its exact HTTPS origin and secure cookie.');
    }
    // Only the loopback gateway creates these headers; it replaces visitor-supplied values.
    TrustProxies::at(['127.0.0.1', '::1']);
    TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
}
$app->handleRequest(Request::capture());
