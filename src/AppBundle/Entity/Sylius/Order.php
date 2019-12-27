<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use AppBundle\Action\Cart\AddItem as AddCartItem;
use AppBundle\Action\Cart\DeleteItem as DeleteCartItem;
use AppBundle\Action\Cart\UpdateItem as UpdateCartItem;
use AppBundle\Action\Order\Accept as OrderAccept;
use AppBundle\Action\Order\Assign as OrderAssign;
use AppBundle\Action\Order\Cancel as OrderCancel;
use AppBundle\Action\Order\Delay as OrderDelay;
use AppBundle\Action\Order\Pay as OrderPay;
use AppBundle\Action\Order\Refuse as OrderRefuse;
use AppBundle\Action\MyOrders;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Filter\OrderDateFilter;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Validator\Constraints\IsOrderModifiable as AssertOrderIsModifiable;
use AppBundle\Validator\Constraints\Order as AssertOrder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Order\Model\Order as BaseOrder;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;

/**
 * @see http://schema.org/Order Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Order",
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "denormalization_context"={"groups"={"order_create", "address_create"}}
 *     },
 *     "timing"={
 *       "method"="POST",
 *       "path"="/orders/timing",
 *       "write"=false,
 *       "status"=200,
 *       "denormalization_context"={"groups"={"order_create", "address_create"}}
 *     },
 *     "my_orders"={
 *       "method"="GET",
 *       "path"="/me/orders",
 *       "controller"=MyOrders::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object.getRestaurant())) or object.getCustomer() == user"
 *     },
 *     "pay"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/pay",
 *       "controller"=OrderPay::class,
 *       "access_control"="object.getCustomer() == user"
 *     },
 *     "accept"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/accept",
 *       "controller"=OrderAccept::class,
 *       "access_control"="is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object.getRestaurant())",
 *       "deserialize"=false
 *     },
 *     "refuse"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/refuse",
 *       "controller"=OrderRefuse::class,
 *       "access_control"="is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object.getRestaurant())"
 *     },
 *     "delay"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/delay",
 *       "controller"=OrderDelay::class,
 *       "access_control"="is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object.getRestaurant())"
 *     },
 *     "cancel"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/cancel",
 *       "controller"=OrderCancel::class,
 *       "access_control"="is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object.getRestaurant())"
 *     },
 *     "assign"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/assign",
 *       "controller"=OrderAssign::class,
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}}
 *     },
 *     "get_cart_timing"={
 *       "method"="GET",
 *       "path"="/orders/{id}/timing",
 *       "access_control"="object.getCustomer() == user"
 *     },
 *     "validate"={
 *       "method"="GET",
 *       "path"="/orders/{id}/validate",
 *       "normalization_context"={"groups"={"cart"}},
 *       "access_control"="object.getCustomer() == user"
 *     },
 *     "put_cart"={
 *       "method"="PUT",
 *       "path"="/orders/{id}",
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "denormalization_context"={"groups"={"order_update"}},
 *       "security"="(object.getCustomer() != null and object.getCustomer() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
 *     },
 *     "post_cart_items"={
 *       "method"="POST",
 *       "path"="/orders/{id}/items",
 *       "input"=CartItemInput::class,
 *       "controller"=AddCartItem::class,
 *       "validation_groups"={"cart"},
 *       "denormalization_context"={"groups"={"cart"}},
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="(object.getCustomer() != null and object.getCustomer() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
 *     },
 *     "put_item"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/items/{itemId}",
 *       "controller"=UpdateCartItem::class,
 *       "validation_groups"={"cart"},
 *       "denormalization_context"={"groups"={"cart"}},
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="(object.getCustomer() != null and object.getCustomer() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
 *     },
 *     "delete_item"={
 *       "method"="DELETE",
 *       "path"="/orders/{id}/items/{itemId}",
 *       "controller"=DeleteCartItem::class,
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "validate"=false,
 *       "write"=false,
 *       "status"=200,
 *       "security"="(object.getCustomer() != null and object.getCustomer() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
 *     }
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"order", "place"}}
 *   }
 * )
 * @ApiFilter(OrderDateFilter::class, properties={"date": "exact"})
 *
 * @AssertOrder(groups={"Default"})
 * @AssertOrderIsModifiable(groups={"cart"})
 */
class Order extends BaseOrder implements OrderInterface
{
    protected $customer;

    protected $restaurant;

    protected $shippingAddress;

    protected $billingAddress;

    protected $shippedAt;

    protected $payments;

    protected $delivery;

    protected $events;

    protected $timeline;

    protected $channel;

    protected $promotionCoupon;

    protected $promotions;

    protected $reusablePackagingEnabled = false;

    protected $receipt;

    public function __construct()
    {
        parent::__construct();

        $this->payments = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->promotions = new ArrayCollection();
    }

    /**
     * @return ApiUser|null
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer(ApiUser $customer)
    {
        $this->customer = $customer;

        return $this;
    }

    public function getTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $taxTotal += $taxAdjustment->getAmount();
        }
        foreach ($this->items as $item) {
            $taxTotal += $item->getTaxTotal();
        }

        return $taxTotal;
    }

    public function getItemsTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->items as $item) {
            $taxTotal += $item->getTaxTotal();
        }

        return $taxTotal;
    }

    public function getItemsTaxTotalByRate($taxRate): int
    {
        if ($taxRate instanceof TaxRateInterface) {
            $taxRateCode = $taxRate->getCode();
        } else {
            $taxRateCode = $taxRate;
        }

        $taxTotal = 0;

        foreach ($this->items as $item) {
            foreach ($item->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
                if ($taxAdjustment->getOriginCode() === $taxRateCode) {
                    $taxTotal += $taxAdjustment->getAmount();
                }
            }
        }

        return $taxTotal;
    }

    public function getTaxTotalByRate($taxRate): int
    {
        if ($taxRate instanceof TaxRateInterface) {
            $taxRateCode = $taxRate->getCode();
        } else {
            $taxRateCode = $taxRate;
        }

        $taxTotal = 0;

        foreach ($this->items as $item) {
            foreach ($item->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
                if ($taxAdjustment->getOriginCode() === $taxRateCode) {
                    $taxTotal += $taxAdjustment->getAmount();
                }
            }
        }

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            if ($taxAdjustment->getOriginCode() === $taxRateCode) {
                $taxTotal += $taxAdjustment->getAmount();
            }
        }

        return $taxTotal;
    }

    public function getFeeTotal(): int
    {
        $feeTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT) as $feeAdjustment) {
            $feeTotal += $feeAdjustment->getAmount();
        }

        return $feeTotal;
    }

    public function getStripeFeeTotal(): int
    {
        $total = 0;
        foreach ($this->getAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT) as $adjustment) {
            $total += $adjustment->getAmount();
        }

        return $total;
    }

    public function getRevenue(): int
    {
        return $this->getTotal() - $this->getFeeTotal() - $this->getStripeFeeTotal();
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function setRestaurant(?Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    /**
     * {@inheritdoc}
     */
    public function isFoodtech(): bool
    {
        return null !== $this->getRestaurant();
    }

    /**
     * {@inheritdoc}
     */
    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    /**
     * {@inheritdoc}
     */
    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress($billingAddress)
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getShippedAt(): ?\DateTime
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTime $shippedAt): void
    {
        $this->shippedAt = $shippedAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPayments(): bool
    {
        return !$this->payments->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function addPayment(PaymentInterface $payment): void
    {
        if (!$this->hasPayment($payment)) {
            $this->payments->add($payment);
            $payment->setOrder($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePayment(PaymentInterface $payment): void
    {
        if ($this->hasPayment($payment)) {
            $this->payments->removeElement($payment);
            $payment->setOrder(null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasPayment(PaymentInterface $payment): bool
    {
        return $this->payments->contains($payment);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPayment(?string $state = null): ?PaymentInterface
    {
        if ($this->payments->isEmpty()) {
            return null;
        }

        $payment = $this->payments->filter(function (PaymentInterface $payment) use ($state): bool {
            return null === $state || $payment->getState() === $state;
        })->last();

        return $payment !== false ? $payment : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function setDelivery(Delivery $delivery): void
    {
        $delivery->setOrder($this);

        $this->delivery = $delivery;
    }

    public function getTimeline(): ?OrderTimeline
    {
        return $this->timeline;
    }

    public function setTimeline(OrderTimeline $timeline): void
    {
        $timeline->setOrder($this);

        $this->timeline = $timeline;
    }

    public function getPreparationExpectedAt()
    {
        if (null !== $this->timeline) {
            return $this->timeline->getPreparationExpectedAt();
        }
    }

    public function getPickupExpectedAt()
    {
        if (null !== $this->timeline) {
            return $this->timeline->getPickupExpectedAt();
        }
    }

    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getPromotionCoupon(): ?PromotionCouponInterface
    {
        return $this->promotionCoupon;
    }

    /**
     * {@inheritdoc}
     */
    public function setPromotionCoupon(?PromotionCouponInterface $coupon): void
    {
        $this->promotionCoupon = $coupon;
    }

    /**
     * {@inheritdoc}
     */
    public function getPromotionSubjectTotal(): int
    {
        return $this->getItemsTotal();
    }

    /**
     * {@inheritdoc}
     */
    public function hasPromotion(PromotionInterface $promotion): bool
    {
        return $this->promotions->contains($promotion);
    }

    /**
     * {@inheritdoc}
     */
    public function addPromotion(PromotionInterface $promotion): void
    {
        if (!$this->hasPromotion($promotion)) {
            $this->promotions->add($promotion);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePromotion(PromotionInterface $promotion): void
    {
        if ($this->hasPromotion($promotion)) {
            $this->promotions->removeElement($promotion);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPromotions(): Collection
    {
        return $this->promotions;
    }

    /**
     * {@inheritdoc}
     */
    public function containsDisabledProduct(): bool
    {
        foreach ($this->getItems() as $item) {
            if ($item instanceof OrderItemInterface && !$item->getVariant()->getProduct()->isEnabled()) {

                return true;
            }
        }

        return false;
    }

    public function isEligibleToReusablePackaging(): bool
    {
        $restaurant = $this->getRestaurant();

        if (null === $restaurant) {
            return false;
        }

        if (!$restaurant->isDepositRefundEnabled()) {
            return false;
        }

        foreach ($this->getItems() as $item) {
            if ($item instanceof OrderItemInterface
            &&  $item->getVariant()->getProduct()->isReusablePackagingEnabled()) {

                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function isReusablePackagingEnabled()
    {
        return $this->reusablePackagingEnabled;
    }

    /**
     * @param mixed $reusablePackagingEnabled
     *
     * @return self
     */
    public function setReusablePackagingEnabled($reusablePackagingEnabled)
    {
        $this->reusablePackagingEnabled = $reusablePackagingEnabled;

        return $this;
    }

    public function getReceipt()
    {
        return $this->receipt;
    }

    public function setReceipt($receipt)
    {
        $receipt->setOrder($this);

        $this->receipt = $receipt;
    }

    public function hasReceipt()
    {
        return null !== $this->receipt;
    }

    public function removeReceipt()
    {
        if ($this->hasReceipt()) {

            $receipt = $this->receipt;

            $this->receipt = null;

            $receipt->setOrder(null);

            return $receipt;
        }
    }
}
