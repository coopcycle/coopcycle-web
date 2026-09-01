<?php

namespace AppBundle\Message;

use AppBundle\Entity\Delivery;

/**
 * Several deliveries were created at once, i.e when generating orders from recurrence rules.
 * A single recap notification is sent, instead of one notification per delivery.
 */
class DeliveriesCreated
{
    /**
     * @var int[]
     */
    private array $deliveryIds;

    /**
     * @param Delivery[] $deliveries
     */
    public function __construct(array $deliveries)
    {
        $this->deliveryIds = array_map(fn (Delivery $delivery) => $delivery->getId(), $deliveries);
    }

    /**
     * @return int[]
     */
    public function getDeliveryIds(): array
    {
        return $this->deliveryIds;
    }
}
