<?php

namespace AppBundle\Entity\Urbantz;

use Gedmo\Timestampable\Traits\Timestampable;

class Delivery
{
    use Timestampable;

    private $id;
    private $delivery;

    public function getId()
    {
        return $this->id;
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
}
