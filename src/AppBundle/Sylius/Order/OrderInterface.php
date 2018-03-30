<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Address;
use AppBundle\Entity\Restaurant;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * @return int
     */
    public function getTaxTotal(): int;

    /**
     * @return Restaurant
     */
    public function getRestaurant(): ?Restaurant;

    /**
     * @return Address|null
     */
    public function getShippingAddress(): ?Address;

    /**
     * @return DateTime|null
     */
    public function getShippedAt(): ?\DateTime;
}
