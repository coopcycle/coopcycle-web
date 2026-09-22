<?php

namespace AppBundle\Message;

use AppBundle\Entity\Delivery;

/**
 * Dispatched when a delivery has been updated by someone who is not a dispatcher,
 * i.e a store owner or an API client, so that dispatchers can be notified.
 */
class DeliveryUpdated
{
    private int $deliveryId;

    public function __construct(Delivery $delivery)
    {
        $this->deliveryId = $delivery->getId();
    }

    public function getDeliveryId(): int
    {
        return $this->deliveryId;
    }
}
