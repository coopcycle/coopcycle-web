<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;
use Gedmo\Timestampable\Traits\Timestampable;

class LoopeatOrderDetails
{
    use Timestampable;

    private $id;
    private $order;
    private $orderId;
    private array $returns = [];
    private array $deliver = [];
    private array $pickup = [];

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(OrderInterface $order): void
    {
        $this->order = $order;
    }

    /**
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param mixed $orderId
     *
     * @return self
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getReturns()
    {
        return $this->returns;
    }

    /**
     * @param mixed $returns
     *
     * @return self
     */
    public function setReturns($returns)
    {
        $this->returns = $returns;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDeliver()
    {
        return $this->deliver;
    }

    /**
     * @param mixed $deliver
     *
     * @return self
     */
    public function setDeliver($deliver)
    {
        $this->deliver = $deliver;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPickup()
    {
        return $this->pickup;
    }

    /**
     * @param mixed $pickup
     *
     * @return self
     */
    public function setPickup($pickup)
    {
        $this->pickup = $pickup;

        return $this;
    }

    public function hasReturns()
    {
        return count($this->returns) > 0;
    }
}
