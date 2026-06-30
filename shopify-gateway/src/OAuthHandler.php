<?php

declare(strict_types=1);

namespace CoopCycle\ShopifyGateway;

class OAuthHandler
{
    private const SCOPES = 'read_orders,write_fulfillments,read_fulfillments,write_shipping,'
                         . 'write_delivery_customizations,read_delivery_customizations';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $gatewaySecret,
        private readonly string $appUrl,
        private readonly string $tenantsEnv = '',
    ) {}

    /**
     * Parse TENANTS env var into [{name, url}] pairs.
     * Format: "Name:https://url.org,Name+with+spaces:https://url2.org"
     * Returns empty array when env var is not set.
     *
     * @return array<array{name: string, url: string}>
     */
    private function parseTenants(): array
    {
        if ($this->tenantsEnv === '') {
            return [];
        }

        $tenants = [];
        foreach (explode(',', $this->tenantsEnv) as $entry) {
            $entry = trim($entry);
            if ($entry === '') continue;
            // Split on first colon only so the URL's "https://" is preserved.
            $pos = strpos($entry, ':');
            if ($pos === false) continue;
            $name = urldecode(substr($entry, 0, $pos));
            $url  = rtrim(substr($entry, $pos + 1), '/');
            if ($name !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $tenants[] = ['name' => $name, 'url' => $url];
            }
        }

        return $tenants;
    }

    /**
     * Entry point from the Shopify App Store.
     * Shopify calls: GET {APP_URL}/shopify/install?shop=merchant.myshopify.com&hmac=...
     */
    public function install(): void
    {
        $shop = trim($_GET['shop'] ?? '');

        // Verify Shopify's install HMAC when present.
        if ($shop && isset($_GET['hmac'])) {
            if (!$this->verifyCallbackHmac($_GET, $_GET['hmac'])) {
                http_response_code(403);
                $this->render('error', ['message' => 'HMAC verification failed. This request did not come from Shopify.']);
                return;
            }
        }

        // 'session' is the App Bridge session token — only present when the merchant opens
        // the app from within the Shopify admin, not during a fresh install.
        if (!empty($_GET['session'])) {
            $host    = base64_decode($_GET['host'] ?? '', strict: false);
            $backUrl = $host ? 'https://' . $host . '/settings/shipping' : null;
            $this->render('home', ['shop' => $shop, 'backUrl' => $backUrl]);
            return;
        }

        $this->render('install', ['shop' => $shop, 'tenants' => $this->parseTenants()]);
    }

    /**
     * Receives the cooperative picker form and redirects the merchant to
     * CoopCycle to authenticate and choose which store to connect.
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

        $tenants = $this->parseTenants();
        if ($tenants !== []) {
            $allowed = array_column($tenants, 'url');
            if (!in_array($tenantUrl, $allowed, true)) {
                $this->render('error', ['message' => 'The selected CoopCycle cooperative is not allowed.']);
                return;
            }
        }

        // Build a signed state token embedding shop, tenant, and the gateway's
        // OAuth entry-point URL. The token travels through CoopCycle unchanged.
        $state = base64_encode(json_encode([
            'shop'      => $shop,
            'tenant'    => $tenantUrl,
            'nonce'     => bin2hex(random_bytes(8)),
            'return_to' => $this->appUrl . '/shopify/oauth',
        ]));
        $sig = hash_hmac('sha256', $state, $this->gatewaySecret);

        $chooseStoreUrl = $tenantUrl . '/connect/shopify/choose-store?' . http_build_query([
            'state' => $state,
            'sig'   => $sig,
        ]);

        header('Location: ' . $chooseStoreUrl, true, 302);
        exit;
    }

    /**
     * Called after CoopCycle redirects back with the merchant's chosen store.
     * Verifies CoopCycle's signature then launches the Shopify OAuth flow.
     *
     * CoopCycle calls: GET {APP_URL}/shopify/oauth?state=...&store_id=42&return_sig=...
     */
    public function oauth(): void
    {
        $state     = $_GET['state']      ?? '';
        $storeId   = (int) ($_GET['store_id']   ?? 0);
        $returnSig = $_GET['return_sig'] ?? '';

        if (!$state || !$storeId || !$returnSig) {
            $this->render('error', ['message' => 'Missing required parameters from CoopCycle.']);
            return;
        }

        // The return_sig proves CoopCycle generated this response.
        $expected = hash_hmac('sha256', $state . ':' . $storeId, $this->gatewaySecret);
        if (!hash_equals($expected, $returnSig)) {
            http_response_code(403);
            $this->render('error', ['message' => 'Invalid signature from CoopCycle. The response may have been tampered with.']);
            return;
        }

        $stateData = json_decode(base64_decode($state), true);
        $shop      = $stateData['shop']   ?? null;
        $tenant    = $stateData['tenant'] ?? null;

        if (!$shop || !$tenant) {
            $this->render('error', ['message' => 'Malformed state token.']);
            return;
        }

        // Encode {tenant, store_id} into the Shopify OAuth state.
        // Shopify's HMAC on the callback guarantees this cannot be tampered with.
        $shopifyState = base64_encode(json_encode([
            'tenant'   => $tenant,
            'store_id' => $storeId,
        ]));

        $callbackUrl = $this->appUrl . '/shopify/callback';

        $authUrl = sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shop,
            rawurlencode($this->apiKey),
            self::SCOPES,
            rawurlencode($callbackUrl),
            rawurlencode($shopifyState),
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
        $shop  = trim($_GET['shop']  ?? '');
        $code  = trim($_GET['code']  ?? '');
        $state = $_GET['state'] ?? '';
        $hmac  = $_GET['hmac']  ?? '';

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
        $tenantUrl = $stateData['tenant']   ?? null;
        $storeId   = isset($stateData['store_id']) ? (int) $stateData['store_id'] : null;

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
            $this->provisionTenant($tenantUrl, $shop, $accessToken, $storeId);
        } catch (\RuntimeException $e) {
            $this->render('error', ['message' => $e->getMessage()]);
            return;
        }

        $this->render('success', ['shop' => $shop, 'tenantUrl' => $tenantUrl]);
    }

    /**
     * Shopify sometimes calls the root URL instead of /shopify/install.
     * Redirect preserving all query parameters.
     */
    public function redirectToInstall(): void
    {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $location = $this->appUrl . '/shopify/install' . ($qs ? '?' . $qs : '');
        header('Location: ' . $location, true, 302);
        exit;
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
     * Calls the CoopCycle tenant's provision endpoint to register the shop
     * and link it to the chosen Store.
     */
    private function provisionTenant(string $tenantUrl, string $shopDomain, string $accessToken, ?int $storeId): void
    {
        $payload = [
            'shop_domain'  => $shopDomain,
            'access_token' => $accessToken,
        ];
        if ($storeId !== null) {
            $payload['store_id'] = $storeId;
        }

        $url      = $tenantUrl . '/connect/shopify/provision';
        $body     = json_encode($payload);
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

        // Build the message from raw (URL-decoded) values — NOT http_build_query,
        // which re-encodes characters like = and + that Shopify leaves unencoded
        // when it computes the HMAC.
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $message  = implode('&', $pairs);
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
