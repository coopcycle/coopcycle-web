<?php

namespace AppBundle\Service;

use AppBundle\Entity\Order;
use AppBundle\Entity\Delivery;

interface DeliveryServiceInterface
{
    /**
     * Returns a unique key for this service.
     *
     * @return string
     */
    public function getKey();

    /**
     * Creates a Delivery.
     *
     * @param AppBundle\Entity\Order
     * @return AppBundle\Entity\Delivery
     */
    public function create(Order $order);

    /**
     * Calculates distance & duration.
     *
     * @param AppBundle\Entity\Delivery
     */
    public function calculate(Delivery $delivery);
}
