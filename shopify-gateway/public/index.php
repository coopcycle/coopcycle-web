<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CoopCycle\ShopifyGateway\OAuthHandler;
use CoopCycle\ShopifyGateway\ShopStore;

function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value !== false && $value !== null) ? (string) $value : $default;
}

$handler = new OAuthHandler(
    // Records which cooperative each shop installed on, so app-level compliance
    // webhooks can be routed to exactly one tenant.
    shopStore:     new ShopStore(env('SHOPS_DB_PATH', '/data/shops/shops.sqlite')),
    apiKey:        env('SHOPIFY_API_KEY'),
    apiSecret:     env('SHOPIFY_API_SECRET'),
    gatewaySecret: env('GATEWAY_SECRET'),
    appUrl:        rtrim(env('APP_URL'), '/'),
    tenantsEnv:    env('TENANTS'),
);

// HEAD must route exactly like GET (RFC 9110): nginx health probes and uptime
// monitors use it, and an unrouted HEAD would report the gateway as down. The
// server strips the response body from a HEAD reply on its own.
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'HEAD') {
    $method = 'GET';
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

try {
    match (true) {
        $method === 'GET'  && $path === '/'                 => $handler->redirectToInstall(),
        $method === 'GET'  && $path === '/shopify/install'  => $handler->install(),
        $method === 'POST' && $path === '/shopify/start'    => $handler->start(),
        $method === 'GET'  && $path === '/shopify/oauth'    => $handler->oauth(),
        $method === 'GET'  && $path === '/shopify/callback' => $handler->callback(),
        $method === 'POST' && $path === '/shopify/compliance' => $handler->compliance(),
        $method === 'GET'  && $path === '/health'           => $handler->health(),
        default                                             => $handler->notFound(),
    };
} catch (\Throwable $e) {
    http_response_code(500);
    // In production, do not expose the exception message to the browser.
    $safe = env('APP_ENV') === 'dev' ? htmlspecialchars($e->getMessage()) : 'An unexpected error occurred.';
    require __DIR__ . '/../templates/error.php';
}
