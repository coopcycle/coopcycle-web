<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Zelty\ApiLog;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ZeltyClient
{
    private ?string $authToken = null;

    private ?int $restaurantId = null;

    public function __construct(
        private HttpClientInterface $zeltyClient,
        private \Psr\Log\LoggerInterface $logger,
        private ZeltyOrderNormalizer $orderNormalizer,
        private ?\Psr\Log\LoggerInterface $zeltyLogger = null,
    ) {}

    public function setAuth(string $token): void
    {
        $this->authToken = $token;
    }

    /**
     * Same as setAuth(), but also tells the API log which shop the calls belong to.
     */
    public function setRestaurant(LocalBusiness $restaurant): void
    {
        $this->setAuth((string) $restaurant->getZeltyApiKey());
        $this->restaurantId = $restaurant->getId();
    }

    public function setRestaurantId(?int $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
    }

    private function authOptions(): array
    {
        return $this->authToken !== null ? ['auth_bearer' => $this->authToken] : [];
    }

    /**
     * Single entry point for every call to Zelty.
     *
     * A call is written to the API log when it did something worth reporting —
     * described by $events — or when it failed. Calls that merely read Zelty to
     * populate the admin UI (dish lists, catalog lists, taxes) are not logged:
     * they would bury the traffic that matters.
     *
     * @param array<int, array{type: string, params?: array}> $events
     */
    private function send(string $method, string $path, array $options = [], array $events = []): ResponseInterface
    {
        $startedAt = microtime(true);
        $requestBody = $options['json'] ?? null;

        try {
            $response = $this->zeltyClient->request($method, $path, array_merge($this->authOptions(), $options));

            // Reading the body here does not swallow errors: the caller's getContent()
            // still throws for a 4xx/5xx, it just replays the buffered response.
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (\Throwable $e) {
            $this->logApiCall($method, $path, $requestBody, null, null, $startedAt, $events, $e->getMessage());

            throw $e;
        }

        if (count($events) > 0 || $statusCode >= 400) {
            $this->logApiCall($method, $path, $requestBody, $statusCode, $responseBody, $startedAt, $events);
        }

        return $response;
    }

    private function logApiCall(
        string $method,
        string $path,
        mixed $requestBody,
        ?int $statusCode,
        ?string $responseBody,
        float $startedAt,
        array $events = [],
        ?string $error = null
    ): void {
        $this->zeltyLogger?->log($error !== null || ($statusCode !== null && $statusCode >= 400) ? 'error' : 'info',
            sprintf('%s %s', $method, $path), [
                'direction'     => ApiLog::DIRECTION_OUTGOING,
                'restaurant_id' => $this->restaurantId,
                'method'        => $method,
                'endpoint'      => $path,
                'status_code'   => $statusCode,
                'request_body'  => $requestBody,
                'response_body' => $responseBody,
                'duration_ms'   => (int) round((microtime(true) - $startedAt) * 1000),
                'error'         => $error,
                'events'        => $events,
            ]);
    }

    public function pushToZelty(OrderInterface $order): int
    {
        $payload = $this->orderNormalizer->normalize($order);

        $this->logger->info('Zelty order push payload', [
            'order_id' => $order->getId(),
            'payload'  => $payload,
        ]);

        try {
            $response = $this->send('POST', 'orders', ['json' => $payload], [[
                'type'   => ZeltyActivityRecorder::ORDER_PUSHED,
                'params' => ['id' => $order->getId(), 'number' => $order->getNumber()],
            ]]);
            $data = json_decode($response->getContent(), true);
            return $data['order']['id'];
        } catch (ClientExceptionInterface $e) {
            $body = $e->getResponse()->getContent(false);
            throw new \RuntimeException(sprintf('Zelty order push failed: %s', $body), 0, $e);
        }
    }

    public function addTransaction(int $zeltyOrderId, int $amount): void
    {
        $this->logger->info('Zelty add transaction', ['zelty_order_id' => $zeltyOrderId, 'amount' => $amount]);

        try {
            $this->send('POST', sprintf('orders/%d/transactions', $zeltyOrderId), [
                'json' => [
                    'transactions'  => [['name' => 'CB', 'price' => $amount]],
                    'close_if_paid' => false,
                ],
            ], [[
                'type'   => ZeltyActivityRecorder::ORDER_PAYMENT_SENT,
                'params' => ['zeltyOrderId' => $zeltyOrderId, 'amount' => $amount],
            ]]);
        } catch (ClientExceptionInterface $e) {
            $body = $e->getResponse()->getContent(false);
            $this->logger->error('Zelty add transaction failed', [
                'zelty_order_id' => $zeltyOrderId,
                'error'          => $body,
            ]);
        }
    }

    public function closeOrder(int $zeltyOrderId): void
    {
        $this->logger->info('Zelty close order', ['zelty_order_id' => $zeltyOrderId]);

        try {
            $this->send('POST', sprintf('orders/%d/closure', $zeltyOrderId), [], [[
                'type'   => ZeltyActivityRecorder::ORDER_CLOSED,
                'params' => ['zeltyOrderId' => $zeltyOrderId],
            ]]);
        } catch (ClientExceptionInterface $e) {
            $body = $e->getResponse()->getContent(false);
            $this->logger->error('Zelty close order failed', [
                'zelty_order_id' => $zeltyOrderId,
                'error'          => $body,
            ]);
        }
    }

    /**
     * Register multiple webhooks in a single API call.
     * Keys are Zelty event names, values are target URLs (or null to deregister).
     * Returns the shared webhook secret key.
     *
     * @param array<string, string|null> $webhooks
     */
    public function upsertWebhooks(array $webhooks): string
    {
        $payload = [];
        foreach ($webhooks as $event => $url) {
            $payload[$event] = $url !== null ? ['target' => $url, 'version' => 'v2'] : null;
        }

        $response = $this->send('POST', 'webhooks', ['json' => ['webhooks' => $payload]], [[
            'type'   => ZeltyActivityRecorder::WEBHOOKS_REGISTERED,
            'params' => ['count' => count($payload)],
        ]]);

        $data = json_decode($response->getContent(), true);

        return $data['secret_key'];
    }

    public function upsertWebhook(string $event, ?string $url): string
    {
        return $this->upsertWebhooks([$event => $url]);
    }

    public function getDishes(): array
    {
        $response = $this->send('GET', 'catalog/dishes', ['query' => ['limit' => '2500']]);
        $data = json_decode($response->getContent(), true);
        return $data['dishes'] ?? [];
    }

    public function createDish(array $fields): array
    {
        $response = $this->send('POST', 'catalog/dishes', ['json' => [$fields]], [[
            'type'   => ZeltyActivityRecorder::DISH_CREATED,
            'params' => ['name' => $fields['name'] ?? null],
        ]]);
        $data = json_decode($response->getContent(), true);
        return $data['dishes'][0] ?? [];
    }

    /**
     * List the catalogs available for the authenticated API key.
     */
    public function getCatalogs(): array
    {
        $response = $this->send('GET', 'catalogs');
        $data = json_decode($response->getContent(), true);
        return $data['catalogs'] ?? [];
    }

    /**
     * Fetch a full catalog, in the same shape as the "catalog.push" webhook payload.
     */
    public function getCatalog(string $catalogId): array
    {
        $response = $this->send('GET', sprintf('catalogs/%s', $catalogId), [], [[
            'type'   => ZeltyActivityRecorder::CATALOG_PULLED,
            'params' => ['catalogId' => $catalogId],
        ]]);
        $data = json_decode($response->getContent(), true);
        return $data['catalog'] ?? [];
    }

    public function getTaxes(): array
    {
        $response = $this->send('GET', 'catalog/taxes');
        return json_decode($response->getContent(), true);
    }
}
