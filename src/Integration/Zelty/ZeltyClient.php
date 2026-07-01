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

    public function upsertWebhook(string $event, ?string $url): string
    {
        $webhookConfig = $url !== null ? ['target' => $url, 'version' => 'v2'] : null;

        $response = $this->zeltyClient->request('POST', 'webhooks', array_merge($this->authOptions(), [
            'json' => ['webhooks' => [$event => $webhookConfig]],
        ]));

        $data = json_decode($response->getContent(), true);

        return $data['secret_key'];
    }

    public function getTaxes(): array
    {
        $response = $this->zeltyClient->request('GET', 'catalog/taxes', $this->authOptions());
        return json_decode($response->getContent(), true);
    }
}
