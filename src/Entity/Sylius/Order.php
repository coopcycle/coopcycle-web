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
use AppBundle\Action\Order\Fulfill as OrderFulfill;
use AppBundle\Action\Order\Pay as OrderPay;
use AppBundle\Action\Order\Refuse as OrderRefuse;
use AppBundle\Action\MyOrders;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Filter\OrderDateFilter;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Validator\Constraints\IsOrderModifiable as AssertOrderIsModifiable;
use AppBundle\Validator\Constraints\Order as AssertOrder;
use AppBundle\Validator\Constraints\LoopEatOrder as AssertLoopEatOrder;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Order\Model\Order as BaseOrder;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
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
 *       "denormalization_context"={"groups"={"order_create", "address_create"}},
 *       "normalization_context"={"groups"={"cart_timing"}},
 *       "swagger_context"={
 *         "summary"="Retrieves timing information about a Order resource.",
 *         "responses"={
 *           "200"={
 *             "description"="Order timing information",
 *             "schema"=Order::SWAGGER_CONTEXT_TIMING_RESPONSE_SCHEMA
 *           }
 *         }
 *       }
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
 *       "access_control"="is_granted('view', object)"
 *     },
 *     "pay"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/pay",
 *       "controller"=OrderPay::class,
 *       "access_control"="object.getCustomer().hasUser() and object.getCustomer().getUser() == user",
 *       "swagger_context"={
 *         "summary"="Pays a Order resource."
 *       }
 *     },
 *     "accept"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/accept",
 *       "controller"=OrderAccept::class,
 *       "security"="is_granted('accept', object)",
 *       "deserialize"=false,
 *       "swagger_context"={
 *         "summary"="Accepts a Order resource."
 *       }
 *     },
 *     "refuse"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/refuse",
 *       "controller"=OrderRefuse::class,
 *       "security"="is_granted('refuse', object)",
 *       "swagger_context"={
 *         "summary"="Refuses a Order resource."
 *       }
 *     },
 *     "delay"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/delay",
 *       "controller"=OrderDelay::class,
 *       "security"="is_granted('delay', object)",
 *       "swagger_context"={
 *         "summary"="Delays a Order resource."
 *       }
 *     },
 *     "fulfill"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/fulfill",
 *       "controller"=OrderFulfill::class,
 *       "security"="is_granted('fulfill', object)",
 *       "swagger_context"={
 *         "summary"="Fulfills a Order resource."
 *       }
 *     },
 *     "cancel"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/cancel",
 *       "controller"=OrderCancel::class,
 *       "security"="is_granted('cancel', object)",
 *       "swagger_context"={
 *         "summary"="Cancels a Order resource."
 *       }
 *     },
 *     "assign"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/assign",
 *       "controller"=OrderAssign::class,
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "swagger_context"={
 *         "summary"="Assigns a Order resource to a User."
 *       }
 *     },
 *     "get_cart_timing"={
 *       "method"="GET",
 *       "path"="/orders/{id}/timing",
 *       "access_control"="(object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())",
 *       "swagger_context"={
 *         "summary"="Retrieves timing information about a Order resource.",
 *         "responses"={
 *           "200"={
 *             "description"="Order timing information",
 *             "schema"=Order::SWAGGER_CONTEXT_TIMING_RESPONSE_SCHEMA
 *           }
 *         }
 *       }
 *     },
 *     "validate"={
 *       "method"="GET",
 *       "path"="/orders/{id}/validate",
 *       "normalization_context"={"groups"={"cart"}},
 *       "access_control"="(object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
 *     },
 *     "put_cart"={
 *       "method"="PUT",
 *       "path"="/orders/{id}",
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "denormalization_context"={"groups"={"order_update"}},
 *       "security"="(object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
 *     },
 *     "post_cart_items"={
 *       "method"="POST",
 *       "path"="/orders/{id}/items",
 *       "input"=CartItemInput::class,
 *       "controller"=AddCartItem::class,
 *       "validation_groups"={"cart"},
 *       "denormalization_context"={"groups"={"cart"}},
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="(object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())",
 *       "swagger_context"={
 *         "summary"="Adds items to a Order resource."
 *       }
 *     },
 *     "put_item"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/items/{itemId}",
 *       "controller"=UpdateCartItem::class,
 *       "validation_groups"={"cart"},
 *       "denormalization_context"={"groups"={"cart"}},
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="(object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())"
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
 *       "security"="(object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())",
 *       "swagger_context"={
 *         "summary"="Deletes items from a Order resource."
 *       }
 *     }
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"order", "address"}}
 *   }
 * )
 * @ApiFilter(OrderDateFilter::class, properties={"date": "exact"})
 *
 * @AssertOrder(groups={"Default"})
 * @AssertOrderIsModifiable(groups={"cart"})
 * @AssertLoopEatOrder(groups={"loopeat"})
 */
class Order extends BaseOrder implements OrderInterface
{
    protected $customer;

    protected $restaurant;

    /**
     * @Assert\Valid(groups={"cart"})
     */
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

    protected $reusablePackagingPledgeReturn = 0;

    protected $receipt;

    /**
     * @var int|null
     */
    protected $tipAmount = null;

    protected $shippingTimeRange;

    /**
     * @Assert\Expression(
     *   "!this.isTakeaway() or (this.isTakeaway() and this.getRestaurant().isFulfillmentMethodEnabled('collection'))",
     *   message="order.collection.not_available",
     *   groups={"cart"}
     * )
     */
    protected $takeaway = false;

    const SWAGGER_CONTEXT_TIMING_RESPONSE_SCHEMA = [
        "type" => "object",
        "properties" => [
            "preparation" => ['type' => 'string'],
            "shipping" => ['type' => 'string'],
            "asap" => ['type' => 'string', 'format' => 'date-time'],
            "today" => ['type' => 'boolean'],
            "fast" => ['type' => 'boolean'],
            "diff" => ['type' => 'string'],
            "choices" => ['type' => 'array', 'item' => ['type' => 'string', 'format' => 'date-time']],
        ]
    ];

    public function __construct()
    {
        parent::__construct();

        $this->payments = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->promotions = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
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

    public function getItemsTotalExcludingTax(): int
    {
        return $this->getItemsTotal() - $this->getItemsTaxTotal();
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
    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    public function setRestaurant(?LocalBusiness $restaurant): void
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

        // TODO Order payments by creation date

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

        if (!$restaurant->isDepositRefundEnabled() && !$restaurant->isLoopeatEnabled()) {
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

    public function setReusablePackagingPledgeReturn(int $reusablePackagingPledgeReturn)
    {
        $this->reusablePackagingPledgeReturn = $reusablePackagingPledgeReturn;
        return $this;
    }

    public function getReusablePackagingPledgeReturn()
    {
        return $this->reusablePackagingPledgeReturn;
    }

    public function getReusablePackagingQuantity(): int
    {
        $quantity = 0;
        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                $reusablePackaging = $product->getReusablePackaging();

                if (null === $reusablePackaging) {
                    continue;
                }

                $quantity += ceil($product->getReusablePackagingUnit() * $item->getQuantity());
            }
        }

        return $quantity;
    }

    public function getReusablePackagingAmount(): int
    {
        $amount = 0;
        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                $reusablePackaging = $product->getReusablePackaging();

                if (null === $reusablePackaging) {
                    continue;
                }

                $quantity = ceil($product->getReusablePackagingUnit() * $item->getQuantity());

                $amount += $reusablePackaging->getPrice() * $quantity;
            }
        }

        return $amount;
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

    public function getTipAmount()
    {
        return $this->tipAmount;
    }

    public function setTipAmount(int $tipAmount)
    {
        $this->tipAmount = $tipAmount;
    }

    public function getShippingTimeRange(): ?TsRange
    {
        return $this->shippingTimeRange;
    }

    public function setShippingTimeRange(?TsRange $shippingTimeRange)
    {
        $this->shippingTimeRange = $shippingTimeRange;

        // Legacy
        if (null !== $shippingTimeRange) {
            $this->shippedAt =
                Carbon::instance($shippingTimeRange->getLower())->average($shippingTimeRange->getUpper());
        } else {
            $this->shippedAt = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isTakeaway(): bool
    {
        // HOTFIX
        // Shit can happen when:
        // - only "collection" fulfillment method is available
        // - the order was saved in session with "delivery" fulfillment method

        if ($this->getState() === self::STATE_CART) {
            $restaurant = $this->getRestaurant();
            if (null !== $restaurant) {
                if (!$restaurant->isFulfillmentMethodEnabled('delivery') && $restaurant->isFulfillmentMethodEnabled('collection')) {
                    $this->setTakeaway(true);
                }
            }
        }

        return $this->takeaway;
    }

    public function setTakeaway(bool $takeaway): void
    {
        $this->takeaway = $takeaway;
    }

    /**
     * @SerializedName("fulfillmentMethod")
     */
    public function getFulfillmentMethod(): string
    {
        return $this->isTakeaway() ? 'collection' : 'delivery';
    }

    /**
     * @SerializedName("fulfillmentMethod")
     */
    public function setFulfillmentMethod(string $fulfillmentMethod)
    {
        $this->setTakeaway($fulfillmentMethod === 'collection');
    }

    public function getRefundTotal(): int
    {
        $refundTotal = 0;

        foreach ($this->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                $refundTotal += $payment->getRefundTotal();
            }
        }

        return $refundTotal;
    }

    public function hasRefunds(): bool
    {
        foreach ($this->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                if ($payment->hasRefunds()) {

                    return true;
                }
            }
        }

        return false;
    }

    public function getRefunds(): array
    {
        $refunds = [];
        foreach ($this->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                if ($payment->hasRefunds()) {
                    foreach ($payment->getRefunds() as $refund) {
                        $refunds[] = $refund;
                    }
                }
            }
        }

        return $refunds;
    }

    /**
     * @SerializedName("assignedTo")
     * @Groups({"dispatch"})
     */
    public function getAssignedTo()
    {
        if (null !== $this->getDelivery()) {
            $pickup = $this->getDelivery()->getPickup();

            if ($pickup->isAssigned()) {
                return $pickup->getAssignedCourier()->getUsername();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUser(): ?UserInterface
    {
        if (null === $this->customer) {
            return null;
        }

        if ($this->customer instanceof UserInterface) {
            return $this->customer;
        }

        return $this->customer->getUser();
    }

    public function getTarget(): ?OrderTarget
    {
        if (null !== $this->restaurant) {
            $target = new OrderTarget();
            $target->setRestaurant($this->restaurant);

            return $target;
        }

        return null;
    }
}
