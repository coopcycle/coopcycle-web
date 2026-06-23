<?php

declare(strict_types=1);

namespace CoopCycle\ShopifyGateway;

class OAuthHandler
{
    private const SCOPES = 'read_orders,write_fulfillments,read_fulfillments,write_shipping';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $gatewaySecret,
        private readonly string $appUrl,
    ) {}

    /**
     * Entry point from the Shopify App Store. Shows the cooperative picker form.
     * Shopify calls: GET {APP_URL}/shopify/install?shop=merchant.myshopify.com
     */
    public function install(): void
    {
        $shop = trim($_GET['shop'] ?? '');
        $this->render('install', ['shop' => $shop]);
    }

    /**
     * Receives the cooperative picker form submission and initiates the OAuth flow.
     */
    public function start(): void
    {
        $shop      = trim($_POST['shop'] ?? '');
        $tenantUrl = rtrim(trim($_POST['tenant_url'] ?? ''), '/');

        if (!$shop || !$this->isValidShopDomain($shop)) {
            $this->render('error', ['message' => 'Invalid Shopify shop domain. It must end with .myshopify.com.']);
            return;
        }

        if (!$tenantUrl || !filter_var($tenantUrl, FILTER_VALIDATE_URL)) {
            $this->render('error', ['message' => 'The CoopCycle URL you entered is not valid. It should look like https://paris.coopcycle.org.']);
            return;
        }

        // Embed the tenant URL in the OAuth state.
        // The state is protected against tampering by Shopify's HMAC on the callback.
        $state       = base64_encode(json_encode(['tenant' => $tenantUrl]));
        $callbackUrl = $this->appUrl . '/shopify/callback';

        $authUrl = sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shop,
            rawurlencode($this->apiKey),
            self::SCOPES,
            rawurlencode($callbackUrl),
            rawurlencode($state),
        );

        header('Location: ' . $authUrl, true, 302);
        exit;
    }

    /**
     * OAuth callback from Shopify. Exchanges the code for a token and provisions the tenant.
     * Shopify calls: GET {APP_URL}/shopify/callback?shop=...&code=...&state=...&hmac=...
     */
    public function callback(): void
    {
        $shop  = trim($_GET['shop'] ?? '');
        $code  = trim($_GET['code'] ?? '');
        $state = $_GET['state'] ?? '';
        $hmac  = $_GET['hmac'] ?? '';

        if (!$shop || !$code || !$state || !$hmac) {
            $this->render('error', ['message' => 'Missing required OAuth parameters.']);
            return;
        }

        if (!$this->verifyCallbackHmac($_GET, $hmac)) {
            http_response_code(403);
            $this->render('error', ['message' => 'HMAC verification failed. The request may have been tampered with.']);
            return;
        }

        $stateData = json_decode(base64_decode($state), true);
        $tenantUrl = $stateData['tenant'] ?? null;

        if (!$tenantUrl || !filter_var($tenantUrl, FILTER_VALIDATE_URL)) {
            $this->render('error', ['message' => 'Invalid or missing CoopCycle tenant URL in OAuth state.']);
            return;
        }

        $accessToken = $this->exchangeCodeForToken($shop, $code);
        if (!$accessToken) {
            $this->render('error', ['message' => 'Could not obtain an access token from Shopify. The authorisation code may have expired.']);
            return;
        }

        try {
            $this->provisionTenant($tenantUrl, $shop, $accessToken);
        } catch (\RuntimeException $e) {
            $this->render('error', ['message' => $e->getMessage()]);
            return;
        }

        $this->render('success', ['shop' => $shop, 'tenantUrl' => $tenantUrl]);
    }

    public function health(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->render('error', ['message' => 'Page not found.']);
    }

    // -------------------------------------------------------------------------

    private function exchangeCodeForToken(string $shop, string $code): ?string
    {
        $url  = sprintf('https://%s/admin/oauth/access_token', $shop);
        $body = json_encode([
            'client_id'     => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'code'          => $code,
        ]);

        $response = $this->httpPost($url, $body, ['Content-Type: application/json', 'Accept: application/json']);

        if ($response['code'] !== 200) {
            return null;
        }

        $data = json_decode($response['body'], true);
        return $data['access_token'] ?? null;
    }

    /**
     * Calls the CoopCycle tenant's /connect/shopify/provision endpoint to register the shop.
     */
    private function provisionTenant(string $tenantUrl, string $shopDomain, string $accessToken): void
    {
        $url  = $tenantUrl . '/connect/shopify/provision';
        $body = json_encode(['shop_domain' => $shopDomain, 'access_token' => $accessToken]);

        $response = $this->httpPost($url, $body, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->gatewaySecret,
        ]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException(sprintf(
                'The CoopCycle instance at %s returned HTTP %d when provisioning the shop. '
                . 'Make sure it is reachable and that SHOPIFY_GATEWAY_SECRET is configured correctly.',
                $tenantUrl,
                $response['code'],
            ));
        }
    }

    private function verifyCallbackHmac(array $params, string $hmac): bool
    {
        $data = $params;
        unset($data['hmac'], $data['signature']);
        ksort($data);
        $message  = http_build_query($data);
        $computed = hash_hmac('sha256', $message, $this->apiSecret);
        return hash_equals($computed, $hmac);
    }

    /**
     * Minimal HTTP POST using PHP stream wrappers — no curl extension required.
     *
     * @param string[] $headers Raw header lines, e.g. ['Content-Type: application/json']
     * @return array{code: int, body: string}
     */
    private function httpPost(string $url, string $body, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 30,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        $code = 0;
        if (!empty($http_response_header[0])
            && preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }

        return ['code' => $code, 'body' => $result ?: ''];
    }

    private function render(string $template, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        require __DIR__ . '/../templates/' . $template . '.php';
    }

    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop);
    }
}
