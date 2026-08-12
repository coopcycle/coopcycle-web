<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ZeltyClient
{
    private ?string $authToken = null;

    public function __construct(
        private HttpClientInterface $zeltyClient,
        private \Psr\Log\LoggerInterface $logger,
        private ZeltyOrderNormalizer $orderNormalizer,
    ) {}

    public function setAuth(string $token): void
    {
        $this->authToken = $token;
    }

    private function authOptions(): array
    {
        return $this->authToken !== null ? ['auth_bearer' => $this->authToken] : [];
    }

    public function pushToZelty(OrderInterface $order): int
    {
        $payload = $this->orderNormalizer->normalize($order);

        $this->logger->info('Zelty order push payload', [
            'order_id' => $order->getId(),
            'payload'  => $payload,
        ]);

        try {
            $response = $this->zeltyClient->request('POST', 'orders', array_merge($this->authOptions(), [
                'json' => $payload,
            ]));
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
            $this->zeltyClient->request('POST', sprintf('orders/%d/transactions', $zeltyOrderId), array_merge($this->authOptions(), [
                'json' => [
                    'transactions'  => [['name' => 'CB', 'price' => $amount]],
                    'close_if_paid' => false,
                ],
            ]));
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
            $this->zeltyClient->request('POST', sprintf('orders/%d/closure', $zeltyOrderId), $this->authOptions());
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

        $response = $this->zeltyClient->request('POST', 'webhooks', array_merge($this->authOptions(), [
            'json' => ['webhooks' => $payload],
        ]));

        $data = json_decode($response->getContent(), true);

        return $data['secret_key'];
    }

    public function upsertWebhook(string $event, ?string $url): string
    {
        return $this->upsertWebhooks([$event => $url]);
    }

    public function getDishes(): array
    {
        $response = $this->zeltyClient->request('GET', 'catalog/dishes', array_merge($this->authOptions(), [
            'query' => ['limit' => '2500'],
        ]));
        $data = json_decode($response->getContent(), true);
        return $data['dishes'] ?? [];
    }

    public function createDish(array $fields): array
    {
        $response = $this->zeltyClient->request('POST', 'catalog/dishes', array_merge($this->authOptions(), [
            'json' => [$fields],
        ]));
        $data = json_decode($response->getContent(), true);
        return $data['dishes'][0] ?? [];
    }

    public function getTaxes(): array
    {
        $response = $this->zeltyClient->request('GET', 'catalog/taxes', $this->authOptions());
        return json_decode($response->getContent(), true);
    }
}
