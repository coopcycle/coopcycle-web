<?php

namespace AppBundle\Message;

use AppBundle\Entity\Delivery;

class DeliveryCreated
{
    private $deliveryId;

    public function __construct(Delivery $delivery)
    {
        $this->deliveryId = $delivery->getId();
    }

    public function getDeliveryId()
    {
        return $this->deliveryId;
    }
}
