<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentsSubjectInterface;

interface OrderInterface extends BaseOrderInterface, PaymentsSubjectInterface
{
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_REFUSED = 'refused';

    /**
     * @return int
     */
    public function getTaxTotal(): int;

    /**
     * @return int
     */
    public function getFeeTotal(): int;

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

    /**
     * @param string|null $state
     *
     * @return PaymentInterface|null
     */
    public function getLastPayment(?string $state = null): ?PaymentInterface;

    /**
     * @return Delivery|null
     */
    public function getDelivery(): ?Delivery;

    /**
     * @param Delivery
     */
    public function setDelivery(Delivery $delivery): void;
}
