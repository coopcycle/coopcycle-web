<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CoopCycle\ShopifyGateway\OAuthHandler;

function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value !== false && $value !== null) ? (string) $value : $default;
}

$handler = new OAuthHandler(
    apiKey:        env('SHOPIFY_API_KEY'),
    apiSecret:     env('SHOPIFY_API_SECRET'),
    gatewaySecret: env('GATEWAY_SECRET'),
    appUrl:        rtrim(env('APP_URL'), '/'),
);

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

try {
    match (true) {
        $method === 'GET'  && $path === '/shopify/install'  => $handler->install(),
        $method === 'POST' && $path === '/shopify/start'    => $handler->start(),
        $method === 'GET'  && $path === '/shopify/callback' => $handler->callback(),
        $method === 'GET'  && $path === '/health'           => $handler->health(),
        default                                             => $handler->notFound(),
    };
} catch (\Throwable $e) {
    http_response_code(500);
    // In production, do not expose the exception message to the browser.
    $safe = env('APP_ENV') === 'dev' ? htmlspecialchars($e->getMessage()) : 'An unexpected error occurred.';
    require __DIR__ . '/../templates/error.php';
}
