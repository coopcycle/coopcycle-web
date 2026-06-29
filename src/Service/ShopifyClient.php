<?php

namespace AppBundle\Service;

use AppBundle\Entity\Shopify\ShopifyShop;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ShopifyClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

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

    private function request(ShopifyShop $shop, string $method, string $path, array $body = []): ?array
    {
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
