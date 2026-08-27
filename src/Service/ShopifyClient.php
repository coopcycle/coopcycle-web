<?php

namespace AppBundle\Service;

use AppBundle\Entity\Shopify\ShopifyShop;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ShopifyClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private string $shopifyApiKey,
        private string $shopifyApiSecret,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Offline access tokens expire after an hour, so every call has to be
     * prepared to renew one. This runs server-side with no merchant present,
     * which is the whole point — webhooks and cron commands must keep working.
     *
     * Returns false when the token is expired and cannot be renewed; callers
     * then fail as they would for any other API error.
     */
    private function ensureFreshToken(ShopifyShop $shop): bool
    {
        if (!$shop->isAccessTokenExpired()) {
            return true;
        }

        $refreshToken = $shop->getRefreshToken();

        if (!$refreshToken) {
            $this->logger->error(sprintf(
                'Shopify access token for %s has expired and there is no refresh token. '
                . 'The shop must reinstall the app.',
                $shop->getShopDomain()
            ));

            return false;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                sprintf('https://%s/admin/oauth/access_token', $shop->getShopDomain()),
                [
                    'body' => [
                        'client_id'     => $this->shopifyApiKey,
                        'client_secret' => $this->shopifyApiSecret,
                        'grant_type'    => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                $this->logger->error(sprintf(
                    'Refreshing the Shopify access token for %s returned HTTP %d: %s',
                    $shop->getShopDomain(),
                    $response->getStatusCode(),
                    $response->getContent(false)
                ));

                return false;
            }

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error(sprintf(
                'Refreshing the Shopify access token for %s failed: %s',
                $shop->getShopDomain(),
                $e->getMessage()
            ));

            return false;
        }

        if (empty($data['access_token'])) {
            $this->logger->error(sprintf(
                'Refresh response for %s contained no access token.',
                $shop->getShopDomain()
            ));

            return false;
        }

        $this->applyTokenResponse($shop, $data);
        $this->entityManager->flush();

        $this->logger->info(sprintf('Refreshed the Shopify access token for %s.', $shop->getShopDomain()));

        return true;
    }

    /**
     * Copies an /admin/oauth/access_token response onto the shop. Shared by the
     * refresh above and by the install flow, so both record expiry the same way.
     * A response without `expires_in` is a non-expiring token, which the Admin
     * API no longer accepts — recorded as such rather than silently trusted.
     */
    public function applyTokenResponse(ShopifyShop $shop, array $data): void
    {
        $shop->setAccessToken($data['access_token']);

        if (isset($data['refresh_token'])) {
            $shop->setRefreshToken($data['refresh_token']);
        }

        $shop->setAccessTokenExpiresAt(
            isset($data['expires_in'])
                ? new \DateTime(sprintf('@%d', time() + (int) $data['expires_in']))
                : null
        );
    }

    public function registerWebhook(ShopifyShop $shop, string $topic, string $callbackUrl): ?string
    {
        $response = $this->request($shop, 'POST', 'webhooks.json', [
            'webhook' => [
                'topic'   => $topic,
                'address' => $callbackUrl,
                'format'  => 'json',
            ],
        ]);

        if (!$response) {
            return null;
        }

        return (string) ($response['webhook']['id'] ?? null);
    }

    /** @return string[] existing webhook IDs for the given topic */
    public function getWebhookIds(ShopifyShop $shop, string $topic): array
    {
        $response = $this->request($shop, 'GET', 'webhooks.json?topic=' . urlencode($topic));

        if (!$response) {
            return [];
        }

        return array_map(
            fn($w) => (string) $w['id'],
            $response['webhooks'] ?? []
        );
    }

    public function deleteWebhook(ShopifyShop $shop, string $id): bool
    {
        $url = sprintf('https://%s/admin/api/2025-10/webhooks/%s.json', $shop->getShopDomain(), $id);

        try {
            $response = $this->httpClient->request('DELETE', $url, [
                'headers' => ['X-Shopify-Access-Token' => $shop->getAccessToken()],
            ]);

            return $response->getStatusCode() === 200;
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Shopify API error: %s', $e->getMessage()));

            return false;
        }
    }

    public function setShopMetafield(ShopifyShop $shop, string $namespace, string $key, string $value): bool
    {
        $existing = $this->request($shop, 'GET', "metafields.json?namespace={$namespace}&key={$key}");

        if ($existing !== null && !empty($existing['metafields'])) {
            $id = $existing['metafields'][0]['id'];
            return $this->request($shop, 'PUT', "metafields/{$id}.json", [
                'metafield' => ['value' => $value, 'type' => 'single_line_text_field'],
            ]) !== null;
        }

        return $this->request($shop, 'POST', 'metafields.json', [
            'metafield' => [
                'namespace' => $namespace,
                'key'       => $key,
                'value'     => $value,
                'type'      => 'single_line_text_field',
            ],
        ]) !== null;
    }

    public function syncTenantUrl(ShopifyShop $shop, string $tenantUrl): bool
    {
        return $this->setShopMetafield($shop, 'coopcycle', 'tenant_url', $tenantUrl);
    }

    public function syncSlotsSpec(ShopifyShop $shop, array $openingHoursSpec): bool
    {
        $value    = json_encode($openingHoursSpec);
        $existing = $this->request($shop, 'GET', 'metafields.json?namespace=coopcycle&key=slots_spec');

        if ($existing !== null && !empty($existing['metafields'])) {
            $id = $existing['metafields'][0]['id'];
            return $this->request($shop, 'PUT', "metafields/{$id}.json", [
                'metafield' => ['value' => $value, 'type' => 'json'],
            ]) !== null;
        }

        return $this->request($shop, 'POST', 'metafields.json', [
            'metafield' => [
                'namespace' => 'coopcycle',
                'key'       => 'slots_spec',
                'value'     => $value,
                'type'      => 'json',
            ],
        ]) !== null;
    }

    public function updateFulfillment(ShopifyShop $shop, string $fulfillmentOrderId, string $status, ?string $trackingUrl = null): bool
    {
        $payload = [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => [
                    ['fulfillment_order_id' => $fulfillmentOrderId],
                ],
            ],
        ];

        if ($trackingUrl) {
            $payload['fulfillment']['tracking_info'] = ['url' => $trackingUrl];
        }

        return $this->request($shop, 'POST', 'fulfillments.json', $payload) !== null;
    }

    /**
     * Returns the delivery method types of an order's fulfillment orders,
     * e.g. ["local"] for local delivery, ["shipping"] for regular shipping.
     *
     * The orders/create webhook payload carries no delivery method, so this
     * has to be looked up. Returns null when the lookup fails, which callers
     * must distinguish from an empty list (order has no fulfillment orders).
     */
    public function getDeliveryMethodTypes(ShopifyShop $shop, string $orderId): ?array
    {
        $response = $this->request($shop, 'GET', sprintf('orders/%s/fulfillment_orders.json', $orderId));

        if (null === $response) {
            return null;
        }

        $types = [];
        foreach ($response['fulfillment_orders'] ?? [] as $fulfillmentOrder) {
            $type = $fulfillmentOrder['delivery_method']['method_type'] ?? null;
            if ($type) {
                $types[] = strtolower($type);
            }
        }

        return array_values(array_unique($types));
    }

    private function request(ShopifyShop $shop, string $method, string $path, array $body = []): ?array
    {
        if (!$this->ensureFreshToken($shop)) {
            return null;
        }

        $url = sprintf('https://%s/admin/api/2025-10/%s', $shop->getShopDomain(), $path);

        try {
            $options = [
                'headers' => [
                    'X-Shopify-Access-Token' => $shop->getAccessToken(),
                    'Content-Type'           => 'application/json',
                ],
            ];

            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response   = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return $response->toArray(false);
            }

            $this->logger->error(sprintf(
                'Shopify API %s %s returned HTTP %d: %s',
                $method, $path, $statusCode, $response->getContent(false)
            ));

            return null;
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Shopify API error: %s', $e->getMessage()));

            return null;
        }
    }
}
