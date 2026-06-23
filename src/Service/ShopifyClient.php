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

    public function registerFulfillmentService(ShopifyShop $shop, string $callbackUrl): ?string
    {
        $response = $this->request($shop, 'POST', 'fulfillment_services.json', [
            'fulfillment_service' => [
                'name'                    => 'CoopCycle',
                'callback_url'            => $callbackUrl,
                'inventory_management'    => false,
                'tracking_support'        => true,
                'requires_shipping_method' => false,
                'format'                  => 'json',
            ],
        ]);

        if (!$response) {
            return null;
        }

        return (string) ($response['fulfillment_service']['id'] ?? null);
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

    public function createShippingZoneRate(ShopifyShop $shop, string $shippingZoneId, string $name, int $priceInCents): ?string
    {
        $priceInMajor = number_format($priceInCents / 100, 2, '.', '');

        $response = $this->request($shop, 'POST', "shipping_zones/{$shippingZoneId}/carrier_shipping_rates.json", [
            'carrier_shipping_rate' => [
                'name'  => $name,
                'price' => $priceInMajor,
            ],
        ]);

        if (!$response) {
            return null;
        }

        return $response['carrier_shipping_rate']['id'] ?? null;
    }

    /**
     * Syncs postal codes to a shop metafield read by the Delivery Customization Function.
     *
     * @param string[] $postalCodes
     */
    public function updateDeliveryPostalCodes(ShopifyShop $shop, array $postalCodes): bool
    {
        $value    = json_encode(array_values($postalCodes));
        $existing = $this->request(
            $shop,
            'GET',
            'metafields.json?namespace=coopcycle&key=delivery_postal_codes'
        );

        if ($existing !== null && !empty($existing['metafields'])) {
            $metafieldId = $existing['metafields'][0]['id'];
            $result      = $this->request($shop, 'PUT', "metafields/{$metafieldId}.json", [
                'metafield' => ['value' => $value, 'type' => 'json'],
            ]);
        } else {
            $result = $this->request($shop, 'POST', 'metafields.json', [
                'metafield' => [
                    'namespace' => 'coopcycle',
                    'key'       => 'delivery_postal_codes',
                    'value'     => $value,
                    'type'      => 'json',
                ],
            ]);
        }

        return $result !== null;
    }

    public function acceptFulfillmentRequest(ShopifyShop $shop, string $fulfillmentOrderId): bool
    {
        $response = $this->request(
            $shop,
            'POST',
            "fulfillment_orders/{$fulfillmentOrderId}/fulfillment_request/accept.json",
            ['message' => 'CoopCycle has accepted the fulfillment request.']
        );

        return $response !== null;
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
            $payload['fulfillment']['tracking_info'] = [
                'url' => $trackingUrl,
            ];
        }

        $response = $this->request($shop, 'POST', 'fulfillments.json', $payload);

        return $response !== null;
    }

    private function request(ShopifyShop $shop, string $method, string $path, array $body = []): ?array
    {
        $url = sprintf('https://%s/admin/api/2024-10/%s', $shop->getShopDomain(), $path);

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

            $response = $this->httpClient->request($method, $url, $options);
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
