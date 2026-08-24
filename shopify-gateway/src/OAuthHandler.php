<?php

declare(strict_types=1);

namespace CoopCycle\ShopifyGateway;

class OAuthHandler
{
    private const SCOPES = 'read_orders,write_fulfillments,read_fulfillments,write_shipping,'
                         . 'write_delivery_customizations,read_delivery_customizations,'
                         // Needed to read delivery_method.method_type off an order's
                         // fulfillment orders; read_fulfillments does not cover it.
                         . 'read_merchant_managed_fulfillment_orders';

    public const COMPLIANCE_TOPICS = [
        'customers/data_request',
        'customers/redact',
        'shop/redact',
    ];

    public function __construct(
        private readonly ShopStore $shopStore,
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

        // Only now that the tenant owns the shop is the mapping true. A failure
        // here must not fail the install: the merchant is set up either way, and
        // an unmapped shop degrades to a logged, unroutable compliance webhook.
        try {
            $this->shopStore->remember($shopDomain, $tenantUrl);
        } catch (\Throwable $e) {
            error_log(sprintf('shopify-gateway: could not record %s -> %s: %s',
                $shopDomain, $tenantUrl, $e->getMessage()));
        }
    }

    /**
     * Shopify's three mandatory compliance topics. They are app-level: Shopify
     * posts them to one URI for every shop that ever installed the app, so the
     * gateway is the only place that can receive them, and it must resolve the
     * shop to its cooperative before forwarding.
     *
     * Always answers 2xx once the HMAC checks out. Shopify retries on failure,
     * and an unroutable shop is not something a retry can fix — it is logged for
     * an operator instead.
     */
    public function compliance(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $hmac    = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
        $topic   = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';

        // Compliance webhooks are signed with the app's client secret.
        $computed = base64_encode(hash_hmac('sha256', $rawBody, $this->apiSecret, true));
        if (!$hmac || !hash_equals($computed, $hmac)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid HMAC']);
            return;
        }

        if (!in_array($topic, self::COMPLIANCE_TOPICS, true)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unsupported topic']);
            return;
        }

        $payload    = json_decode($rawBody, true) ?: [];
        $shopDomain = $payload['shop_domain'] ?? '';

        header('Content-Type: application/json');

        if ('' === $shopDomain) {
            error_log('shopify-gateway: compliance webhook without shop_domain');
            echo json_encode(['status' => 'ignored']);
            return;
        }

        $tenantUrl = null;
        try {
            $tenantUrl = $this->shopStore->tenantFor($shopDomain);
        } catch (\Throwable $e) {
            error_log('shopify-gateway: shop lookup failed: ' . $e->getMessage());
        }

        if (null === $tenantUrl) {
            // Never fan out to every tenant as a fallback: customers/* payloads
            // carry the customer's email and phone.
            error_log(sprintf(
                'shopify-gateway: no cooperative recorded for %s, cannot route "%s" — handle manually.',
                $shopDomain,
                $topic
            ));
            echo json_encode(['status' => 'unrouted']);
            return;
        }

        $response = $this->httpPost(
            $tenantUrl . '/connect/shopify/compliance',
            (string) json_encode(['topic' => $topic, 'payload' => $payload]),
            [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->gatewaySecret,
            ]
        );

        if ($response['code'] !== 200) {
            error_log(sprintf(
                'shopify-gateway: %s returned HTTP %d for "%s" (shop %s).',
                $tenantUrl, $response['code'], $topic, $shopDomain
            ));
            echo json_encode(['status' => 'forward_failed']);
            return;
        }

        // The shop is gone for good; drop the mapping so it does not linger.
        if ('shop/redact' === $topic) {
            try {
                $this->shopStore->forget($shopDomain);
            } catch (\Throwable $e) {
                error_log('shopify-gateway: could not forget ' . $shopDomain . ': ' . $e->getMessage());
            }
        }

        echo json_encode(['status' => 'ok']);
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
