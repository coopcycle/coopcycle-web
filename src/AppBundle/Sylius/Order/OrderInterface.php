<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Address;
use AppBundle\Entity\Restaurant;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_REFUSED = 'refused';

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
     * @return Address|null
     */
    public function getBillingAddress(): ?Address;

    /**
     * @return DateTime|null
     */
    public function getShippedAt(): ?\DateTime;

    /**
     * @return boolean
     */
    public function isFoodtech(): bool;
}
