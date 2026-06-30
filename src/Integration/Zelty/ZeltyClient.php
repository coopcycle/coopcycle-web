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

    private ?string $authToken = null;

    public function __construct(
        private HttpClientInterface $zeltyClient
    ) {}

    /**
     * Set authentication token for API requests.
     */
    public function setAuth(string $token): void
    {
        $this->authToken = $token;
    }

    private function authOptions(): array
    {
        return $this->authToken !== null ? ['auth_bearer' => $this->authToken] : [];
    }

    /**
     * Push an order to Zelty.
     */
    public function pushToZelty(OrderInterface $order): void
    {
        $payload = $this->buildOrderPayload($order);

        $this->zeltyClient->request('POST', 'orders', array_merge($this->authOptions(), [
            'body' => json_encode($payload),
        ]));
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
     * Upsert a webhook configuration and return the secret key provided by Zelty.
     *
     * @param string $event The webhook event type (e.g. "catalog.push")
     * @param string|null $url The target URL (null to disable the webhook)
     * @return string The secret key returned by Zelty for HMAC verification
     */
    public function upsertWebhook(string $event, ?string $url): string
    {
        $webhookConfig = $url !== null ? ['target' => $url, 'version' => 'v2'] : null;

        $response = $this->zeltyClient->request('POST', 'webhooks', array_merge($this->authOptions(), [
            'json' => ['webhooks' => [$event => $webhookConfig]],
        ]));

        $data = json_decode($response->getContent(), true);

        return $data['secret_key'];
    }

    /**
     * Get catalog taxes from Zelty.
     *
     * @return array Parsed tax data from Zelty API
     */
    public function getTaxes(): array
    {
        $response = $this->zeltyClient->request('GET', 'catalog/taxes', $this->authOptions());
        return json_decode($response->getContent(), true);
    }
}
