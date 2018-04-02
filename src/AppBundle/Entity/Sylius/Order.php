<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Validator\Constraints\Order as AssertOrder;
use Sylius\Component\Order\Model\Order as BaseOrder;

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
 *     "my_orders"={"method"="GET", "route_name"="my_orders"}
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

    protected $shippedAt;

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
    public function getShippedAt(): ?\DateTime
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTime $shippedAt): void
    {
        $this->shippedAt = $shippedAt;
    }
}
