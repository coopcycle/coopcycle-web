<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;

class Package
{
    protected $id;
    protected $package;
    protected $delivery;
    protected $quantity = 0;

    public function __construct(Delivery $delivery = null)
    {
        $this->delivery = $delivery;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * @param mixed $package
     *
     * @return self
     */
    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDelivery()
    {
        return $this->delivery;
    }

    /**
     * @param mixed $delivery
     *
     * @return self
     */
    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param mixed $quantity
     *
     * @return self
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }
}
