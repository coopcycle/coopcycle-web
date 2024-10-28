<?php

namespace AppBundle\Sylius\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderEvent;
use AppBundle\Entity\Vendor;
use AppBundle\LoopEat\LoopeatAwareInterface;
use AppBundle\LoopEat\OAuthCredentialsInterface as LoopeatOAuthCredentialsInterface;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Customer\Model\CustomerAwareInterface;
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
    PromotionCouponAwarePromotionSubjectInterface,
    CustomerAwareInterface,
    OrderSupportInterface,
    LoopeatAwareInterface,
    LoopeatOAuthCredentialsInterface
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

    /**
     * As we've introduced the concept of Business Account for Orders associated with one of it
     * all the vendor information should be consumed from the Restaurant entity.
     *
     * Use this method to get vendor data like ID, name, address, etc.
     * @return Vendor|null
     */
    public function getVendor(): ?Vendor;

    /**
     * As we've introduced the concept of Business Account for Orders associated with one of it
     * all the setup information should be consumed from the BusinessRestaurantGroup entity.
     * For Orders without association with a Business Account the setup information should be consumed
     * as usual (from the Restaurant).
     *
     * Use this method to get vendor setup like contract, fulfillmentMethods, closingRules,
     * shippingOptionsDays, openingHours.
     * @return Vendor|null
     */
    public function getVendorConditions(): ?Vendor;

    /**
     * @return boolean
     */
    public function hasVendor(): bool;

    /**
     * @return Collection
     */
    public function getVendors(): Collection;

    /**
     * @return int
     */
    public function getTransferAmount(LocalBusiness $subVendor): int;

    /**
     * @return \SplObjectStorage
     */
    public function getItemsGroupedByVendor(): \SplObjectStorage;

    /**
     * @return int
     */
    public function getReusablePackagingPledgeReturn();

    /**
     * @param LocalBusiness $restaurant
     * @return float
     */
    public function getPercentageForRestaurant(LocalBusiness $restaurant): float;

    public function addRestaurant(LocalBusiness $restaurant, int $itemsTotal, int $transferAmount);

    public function getRestaurants(): Collection;

    public function isMultiVendor(): bool;

    /**
     * @return Address|null
     */
    public function getPickupAddress(): ?Address;

    public function getAlcoholicItemsTotal(): int;

    public function isLoopeat(): bool;

    public function getTipAmount();

    public function isFree(): bool;

    public function isReusablePackagingEnabled();

    public function getPaymentMethod(): ?string;

    public function hasEvent(string $type): bool;

    public function getBusinessAccount(): ?BusinessAccount;

    /**
     * @param BusinessAccount $businessAccount
     */
    public function setBusinessAccount(BusinessAccount $businessAccount): void;

    public function isBusiness(): bool;

    /**
     * @return Collection
     */
    public function getPickupAddresses(): Collection;

    /**
     * To get bookmarks that current user has access to use OrderManager::hasBookmark instead
     * @return Collection all bookmarks set by different users
     */
    public function getBookmarks(): Collection;

    /**
     * @param string|null $state
     *
     * @return PaymentInterface|null
     */
    public function getLastPaymentByMethod(string $method, ?string $state = null): ?PaymentInterface;
}
