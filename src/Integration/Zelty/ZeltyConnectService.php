<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Connects a restaurant to Zelty: validates the API key,
 * registers the webhooks, and stores the key + webhook secret.
 */
class ZeltyConnectService
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $webhookBaseUrl = '',
    ) {}

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface when the key is invalid or Zelty is unreachable
     */
    public function connect(LocalBusiness $restaurant, string $apiKey): void
    {
        // The key is not saved on the restaurant yet, so set both explicitly.
        $this->zeltyClient->setAuth($apiKey);
        $this->zeltyClient->setRestaurantId($restaurant->getId());

        // Cheap authenticated GET to reject a bad key before registering webhooks.
        $this->zeltyClient->getTaxes();

        $webhooks = [
            'catalog.push'                     => $this->webhookUrl('_api_/zelty/webhook/catalog/{restaurantId}_post', ['restaurantId' => $restaurant->getId()]),
            'dish.update'                      => $this->webhookUrl('_api_/zelty/webhook/dish.update_post'),
            'dish.delete'                      => $this->webhookUrl('_api_/zelty/webhook/dish.delete_post'),
            'dish.availability_update'         => $this->webhookUrl('_api_/zelty/webhook/dish.availability_update_post'),
            'menu.update'                      => $this->webhookUrl('_api_/zelty/webhook/menu.update_post'),
            'menu.delete'                      => $this->webhookUrl('_api_/zelty/webhook/menu.delete_post'),
            'menu.availability_update'         => $this->webhookUrl('_api_/zelty/webhook/menu.availability_update_post'),
            'option.update'                    => $this->webhookUrl('_api_/zelty/webhook/option.update_post'),
            'option_value.availability_update' => $this->webhookUrl('_api_/zelty/webhook/option_value.availability_update_post'),
            'order.status.update'              => $this->webhookUrl('_api_/zelty/webhook/order.status.update_post'),
        ];

        // The secret Zelty returns here is ignored: it is the same for the whole
        // instance and is configured as ZELTY_WEBHOOK_SECRET.
        $this->zeltyClient->upsertWebhooks($webhooks);

        $restaurant->setZeltyApiKey($apiKey);
    }

    private function webhookUrl(string $route, array $params = []): string
    {
        $path = $this->urlGenerator->generate($route, $params);

        if ($this->webhookBaseUrl !== '') {
            return rtrim($this->webhookBaseUrl, '/') . $path;
        }

        return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
