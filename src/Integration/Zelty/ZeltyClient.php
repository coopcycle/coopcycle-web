<?php

namespace AppBundle\Integration\Zelty;

use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for interacting with the Zelty API.
 */
class ZeltyClient
{
    private const DEFAULT_FULFILLMENT_TYPE = 'deliver_by_partner';
    private const DEFAULT_SOURCE = 'WEB|MOBILE';
    private const DEFAULT_MODE = 'delivery';

    public function __construct(
        private HttpClientInterface $zeltyClient
    ) {}

    /**
     * Set authentication token for API requests.
     */
    public function setAuth(string $token): void
    {
        $this->zeltyClient = $this->zeltyClient->withOptions([
            'auth_bearer' => $token
        ]);
    }

    /**
     * Push an order to Zelty.
     */
    public function pushToZelty(OrderInterface $order): void
    {
        $payload = $this->buildOrderPayload($order);

        $this->zeltyClient->request('POST', 'orders', [
            'body' => json_encode($payload),
        ]);
    }

    /**
     * Build the order payload for Zelty API.
     */
    private function buildOrderPayload(OrderInterface $order): array
    {
        return [
            'remote_id' => $order->getId(),
            'display_id' => $order->getNumber(),
            'fulfillment_type' => self::DEFAULT_FULFILLMENT_TYPE,
            'due_date' => 'ESTIMATED PICKUP TIME',
            'source' => self::DEFAULT_SOURCE,
            'mode' => self::DEFAULT_MODE,
            'customer' => null,
            'address' => [],
        ];
    }

    /**
     * Upsert a webhook configuration.
     *
     * @param string $event The webhook event type
     * @param string|null $url The target URL (null to disable webhook)
     * @param string $secretKey The secret key for webhook signing
     */
    public function upsertWebhook(string $event, ?string $url, string $secretKey): void
    {
        $payload = $this->buildWebhookPayload($event, $url, $secretKey);

        $this->zeltyClient->request('POST', 'webhooks', [
            'body' => json_encode($payload),
        ]);
    }

    /**
     * Build webhook payload based on whether URL is provided.
     */
    private function buildWebhookPayload(string $event, ?string $url, string $secretKey): array
    {
        $webhookConfig = $url !== null
            ? ['target' => $url, 'version' => 'v2']
            : null;

        return [
            'webhooks' => [$event => $webhookConfig],
            'secret_key' => $secretKey,
        ];
    }

    /**
     * Get catalog taxes from Zelty.
     *
     * @return array Parsed tax data from Zelty API
     */
    public function getTaxes(): array
    {
        $response = $this->zeltyClient->request('GET', 'catalog/taxes');
        return json_decode($response->getContent(), true);
    }
}
