<?php

namespace AppBundle\Sylius\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderEvent;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentsSubjectInterface;
use Sylius\Component\Promotion\Model\PromotionCouponAwarePromotionSubjectInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface OrderInterface extends
    BaseOrderInterface,
    PaymentsSubjectInterface,
    ChannelAwareInterface,
    PromotionSubjectInterface,
    PromotionCouponAwarePromotionSubjectInterface
{
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_REFUSED = 'refused';

    /**
     * @var string
     * The order was cancelled because customer contacted us to cancel.
     */
    public const CANCEL_REASON_CUSTOMER = 'CUSTOMER';

    /**
     * @var string
     * The order was cancelled because the establishment is sold out.
     */
    public const CANCEL_REASON_SOLD_OUT = 'SOLD_OUT';

    /**
     * @var string
     * The order was cancelled because the establishment is in "rush" mode and can't cope.
     */
    public const CANCEL_REASON_RUSH_HOUR = 'RUSH_HOUR';

    /**
     * @var string
     * The order was cancelled because the customer didn't show up.
     */
    public const CANCEL_REASON_NO_SHOW = 'NO_SHOW';

    /**
     * @return int
     */
    public function getTaxTotal(): int;

    /**
     * @return int
     */
    public function getItemsTaxTotal(): int;

    /**
     * @return int
     */
    public function getTaxTotalByRate($taxRate): int;

    /**
     * @return int
     */
    public function getItemsTaxTotalByRate($taxRate): int;

    /**
     * @return int
     */
    public function getFeeTotal(): int;

    /**
     * @return int
     */
    public function getRevenue(): int;

    /**
     * @return LocalBusiness|null
     */
    public function getRestaurant(): ?LocalBusiness;

    /**
     * @return Address|null
     */
    public function getShippingAddress(): ?Address;

    /**
     * @return Address|null
     */
    public function getBillingAddress(): ?Address;

    /**
     * @return \DateTime|null
     */
    public function getShippedAt(): ?\DateTime;

    /**
     * @return TsRange|null
     */
    public function getShippingTimeRange(): ?TsRange;

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
     * @param Delivery $delivery
     */
    public function setDelivery(Delivery $delivery): void;

    /**
     * @return Collection|OrderEvent[]
     */
    public function getEvents(): Collection;

    /**
     * @return boolean
     */
    public function containsDisabledProduct(): bool;

    /**
     * @return boolean
     */
    public function isTakeaway(): bool;

    /**
     * @return string
     */
    public function getFulfillmentMethod(): string;

    /**
     * @return int
     */
    public function getItemsTotalExcludingTax(): int;

    /*
     * @return UserInterface|null
     */
    public function getUser(): ?UserInterface;
}
