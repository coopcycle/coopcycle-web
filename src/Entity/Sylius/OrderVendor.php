<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\LocalBusiness;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;

class OrderVendor implements OrderAwareInterface
{
    protected $order;
    protected $restaurant;
    protected $itemsTotal = 0;
    protected $transferAmount = 0;

    public function __construct(OrderInterface $order, LocalBusiness $restaurant)
    {
        $this->order = $order;
        $this->restaurant = $restaurant;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    public function setRestaurant(?LocalBusiness $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function getItemsTotal(): int
    {
        return $this->itemsTotal;
    }

    public function setItemsTotal(int $itemsTotal)
    {
        $this->itemsTotal = $itemsTotal;

        return $this;
    }

    public function getTransferAmount(): int
    {
        return $this->transferAmount;
    }

    public function setTransferAmount(int $transferAmount)
    {
        $this->transferAmount = $transferAmount;

        return $this;
    }
}
