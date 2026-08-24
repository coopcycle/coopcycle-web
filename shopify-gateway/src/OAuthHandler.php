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
     *
     * This sends the merchant straight to Shopify's consent screen and renders
     * nothing. Shopify requires that an app "immediately authenticate using
     * OAuth before any other steps occur", with no UI beforehand, and enforces
     * it at review — which is why the cooperative picker now lives *after* the
     * callback rather than here. It re-authenticates unconditionally, including
     * for a shop that installed before: Shopify demands that too, and for an
     * already-approved shop the consent screen passes through without a prompt.
     */
    public function install(): void
    {
        $shop = trim($_GET['shop'] ?? '');

        if (!$shop || !$this->isValidShopDomain($shop)) {
            $this->render('error', ['message' => 'Invalid Shopify shop domain. It must end with .myshopify.com.']);
            return;
        }

        // Verify Shopify's install HMAC when present.
        if (isset($_GET['hmac'])) {
            if (!$this->verifyCallbackHmac($_GET, $_GET['hmac'])) {
                http_response_code(403);
                $this->render('error', ['message' => 'HMAC verification failed. This request did not come from Shopify.']);
                return;
            }
        }

        // `host` only identifies the admin page to link back to afterwards. It
        // rides through OAuth so the success page can still offer that link.
        $host = (string) ($_GET['host'] ?? '');

        $state = $this->signedState([
            'shop'  => $shop,
            'host'  => $host,
            'nonce' => bin2hex(random_bytes(8)),
            'ts'    => time(),
        ]);

        $authUrl = sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shop,
            rawurlencode($this->apiKey),
            self::SCOPES,
            rawurlencode($this->appUrl . '/shopify/callback'),
            rawurlencode($state),
        );

        header('Location: ' . $authUrl, true, 302);
        exit;
    }

    /**
     * Receives the cooperative picker form — which is now shown *after* OAuth —
     * and redirects the merchant to CoopCycle to authenticate and choose a store.
     */
    public function start(): void
    {
        $pendingId = trim($_POST['pending'] ?? '');
        $tenantUrl = rtrim(trim($_POST['tenant_url'] ?? ''), '/');

        $pending = $pendingId === '' ? null : $this->shopStore->pendingInstall($pendingId);

        if (null === $pending) {
            $this->render('error', ['message' => 'This installation has expired. Please start again from the Shopify App Store.']);
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

        // The state travels through CoopCycle unchanged. It carries the pending
        // install id, never the access token itself.
        $state = base64_encode(json_encode([
            'shop'      => $pending['shop_domain'],
            'tenant'    => $tenantUrl,
            'pending'   => $pendingId,
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
     * Return leg from CoopCycle, carrying the merchant's chosen store. This is
     * where the install is finally completed — OAuth already happened, so all
     * that is left is to hand the parked access token to the cooperative.
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
        $shop      = $stateData['shop']    ?? null;
        $tenant    = $stateData['tenant']  ?? null;
        $pendingId = $stateData['pending'] ?? null;

        if (!$shop || !$tenant || !$pendingId) {
            $this->render('error', ['message' => 'Malformed state token.']);
            return;
        }

        $pending = $this->shopStore->pendingInstall((string) $pendingId);

        if (null === $pending) {
            $this->render('error', ['message' => 'This installation has expired. Please start again from the Shopify App Store.']);
            return;
        }

        // The signature covers the state, but check anyway: the token must only
        // ever be handed out for the shop it was actually issued to.
        if (!hash_equals($pending['shop_domain'], (string) $shop)) {
            http_response_code(403);
            $this->render('error', ['message' => 'This installation does not match the shop it was started for.']);
            return;
        }

        try {
            $this->provisionTenant($tenant, $pending['shop_domain'], $pending['access_token'], $storeId);
        } catch (\RuntimeException $e) {
            $this->render('error', ['message' => $e->getMessage()]);
            return;
        }

        // The token now lives in the cooperative; the gateway has no use for it.
        $this->shopStore->finishInstall((string) $pendingId);

        // `home` is the post-install page: it confirms the link and carries the
        // delivery-zone setup steps, which the merchant still has to complete in
        // Shopify before any order can be dispatched.
        $host    = $pending['host'] ? base64_decode($pending['host'], strict: false) : '';
        $backUrl = $host ? 'https://' . $host . '/settings/shipping' : null;

        $this->render('home', [
            'shop'      => $pending['shop_domain'],
            'tenantUrl' => $tenant,
            'backUrl'   => $backUrl,
        ]);
    }

    /**
     * OAuth callback from Shopify. Exchanges the code for a token, parks it, and
     * only then shows the merchant the cooperative picker.
     *
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

        $stateData = $this->verifySignedState((string) $state);

        if (null === $stateData) {
            http_response_code(403);
            $this->render('error', ['message' => 'The OAuth state is invalid or has expired. Please start the installation again.']);
            return;
        }

        // Shopify signs the whole callback, so a mismatch here means the state
        // was minted for a different shop.
        if (!hash_equals((string) ($stateData['shop'] ?? ''), $shop)) {
            http_response_code(403);
            $this->render('error', ['message' => 'The OAuth state does not match the shop being installed.']);
            return;
        }

        $accessToken = $this->exchangeCodeForToken($shop, $code);
        if (!$accessToken) {
            $this->render('error', ['message' => 'Could not obtain an access token from Shopify. The authorisation code may have expired.']);
            return;
        }

        $host = (string) ($stateData['host'] ?? '');

        try {
            $pendingId = $this->shopStore->beginInstall($shop, $accessToken, $host !== '' ? $host : null);
        } catch (\Throwable $e) {
            error_log('shopify-gateway: could not park the install: ' . $e->getMessage());
            $this->render('error', ['message' => 'Could not start the installation. Please try again.']);
            return;
        }

        // OAuth is done; showing UI is allowed from here on.
        $this->render('install', [
            'shop'    => $shop,
            'pending' => $pendingId,
            'tenants' => $this->parseTenants(),
        ]);
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

    /**
     * State for the Shopify OAuth round-trip. Shopify's callback HMAC already
     * covers the state parameter, but that only proves Shopify echoed it back —
     * signing it ourselves is what proves *we* minted it.
     */
    private function signedState(array $data): string
    {
        $payload = base64_encode(json_encode($data));

        return $payload . '.' . hash_hmac('sha256', $payload, $this->gatewaySecret);
    }

    private function verifySignedState(string $state): ?array
    {
        $parts = explode('.', $state, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        if (!hash_equals(hash_hmac('sha256', $payload, $this->gatewaySecret), $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);

        if (!is_array($data)) {
            return null;
        }

        // Same window as a parked install: a stale authorisation is not one we
        // want to complete.
        if (!isset($data['ts']) || (time() - (int) $data['ts']) > 3600) {
            return null;
        }

        return $data;
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
