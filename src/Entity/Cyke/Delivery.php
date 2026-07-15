<?php

namespace AppBundle\Entity\Cyke;

use Gedmo\Timestampable\Traits\Timestampable;

class Delivery
{
    use Timestampable;

    private $id;
    private $delivery;
    private $cykeId;

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

    public function getCykeId()
    {
        return $this->cykeId;
    }

    /**
     * @param mixed $cykeId
     *
     * @return self
     */
    public function setCykeId($cykeId)
    {
        $this->cykeId = $cykeId;

        return $this;
    }
}
