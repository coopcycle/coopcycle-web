<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Restaurant\Orders as RestaurantOrders;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Validator\Constraints\Order as AssertOrder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Order\Model\Order as BaseOrder;
use Sylius\Component\Payment\Model\PaymentInterface;

/**
 * @see http://schema.org/Order Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Order",
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "post"={
 *       "method"="POST",
 *       "denormalization_context"={"groups"={"order_create"}}
 *     },
 *     "my_orders"={"method"="GET", "route_name"="my_orders"},
 *     "restaurant_orders"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/orders",
 *       "controller"=RestaurantOrders::class,
 *       "defaults"={"_api_receive"=false}
 *     }
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "pay"={"route_name"="order_pay"},
 *     "accept"={"route_name"="order_accept"},
 *     "refuse"={"route_name"="order_refuse"},
 *     "ready"={"route_name"="order_ready"}
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"order", "place"}}
 *   }
 * )
 *
 * @AssertOrder
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

    public function __construct()
    {
        parent::__construct();

        $this->payments = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    /**
     * @return ApiUser
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

    public function getFeeTotal(): int
    {
        $feeTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT) as $feeAdjustment) {
            $feeTotal += $feeAdjustment->getAmount();
        }

        return $feeTotal;
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

    public function addEvent(OrderEvent $event)
    {
        $event->setOrder($this);
        $this->events->add($event);
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
}
