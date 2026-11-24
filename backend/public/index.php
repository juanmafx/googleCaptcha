<?php

declare(strict_types=1);

use SecureContact\Config\AppConfig;
use SecureContact\Http\Bootstrap;
use SecureContact\Http\Request;
use SecureContact\Http\Response;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

ini_set('display_errors', '0');

try {
    $application = Bootstrap::application(AppConfig::fromEnvironment(getenv()), dirname(__DIR__, 2));
    $response = $application->handle(Request::fromGlobals());
} catch (Throwable $exception) {
    error_log('Unhandled ' . $exception::class . ' at ' . basename($exception->getFile()) . ':' . $exception->getLine());
    $response = new Response('Service temporarily unavailable.', 500, [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
    ]);
}

$response->send();
