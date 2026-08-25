<?php

namespace AppBundle\Integration\Zelty;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Collects what a Zelty webhook actually changed on our side.
 *
 * Zelty broadcasts its webhooks to every integration, so most of what we receive
 * concerns dishes, options or orders we know nothing about. A webhook is only
 * worth keeping in the API log if it triggered something here, and the events
 * recorded along the way are what the "Activity" view renders in plain language.
 */
class ZeltyActivityRecorder implements ResetInterface
{
    // Incoming: something Zelty told us, that we acted upon
    const PRODUCT_ENABLED = 'product.enabled';
    const PRODUCT_DISABLED = 'product.disabled';
    const PRODUCT_DELETED = 'product.deleted';
    const PRODUCT_IN_STOCK = 'product.in_stock';
    const PRODUCT_OUT_OF_STOCK = 'product.out_of_stock';
    const OPTION_VALUE_UPDATED = 'option_value.updated';
    const OPTION_VALUE_IN_STOCK = 'option_value.in_stock';
    const OPTION_VALUE_OUT_OF_STOCK = 'option_value.out_of_stock';
    const ORDER_PREPARING = 'order.preparing';
    const ORDER_READY = 'order.ready';
    const CATALOG_RECEIVED = 'catalog.received';
    const CATALOG_IMPORTED = 'catalog.imported';
    const CATALOG_IMPORT_FAILED = 'catalog.import_failed';

    // Outgoing: something we asked Zelty to do
    const ORDER_PUSHED = 'order.pushed';
    const ORDER_PAYMENT_SENT = 'order.payment_sent';
    const ORDER_CLOSED = 'order.closed';
    const CATALOG_PULLED = 'catalog.pulled';
    const WEBHOOKS_REGISTERED = 'webhooks.registered';
    const DISH_CREATED = 'dish.created';

    /** @var array<int, array{type: string, params: array}> */
    private array $events = [];

    private ?int $restaurantId = null;

    /**
     * @param array<string, scalar|null> $params values interpolated into the sentence shown in the UI
     */
    public function record(string $type, array $params = []): void
    {
        $this->events[] = ['type' => $type, 'params' => $params];
    }

    /**
     * Most webhooks are brand-wide: Zelty does not say which shop they are about.
     * The entities we touch do, so whoever records an event can attribute it.
     */
    public function setRestaurantId(?int $restaurantId): void
    {
        if ($restaurantId !== null && $this->restaurantId === null) {
            $this->restaurantId = $restaurantId;
        }
    }

    public function getRestaurantId(): ?int
    {
        return $this->restaurantId;
    }

    /**
     * @return array<int, array{type: string, params: array}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function hasEvents(): bool
    {
        return count($this->events) > 0;
    }

    public function reset(): void
    {
        $this->events = [];
        $this->restaurantId = null;
    }
}
