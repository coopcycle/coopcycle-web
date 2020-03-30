<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;

class PayPalAccount
{
    use Timestampable;

    /**
     * @var int
     */
    private $id;

    private $payerId;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getPayerId()
    {
        return $this->payerId;
    }

    /**
     * @param mixed $payerId
     *
     * @return self
     */
    public function setPayerId($payerId)
    {
        $this->payerId = $payerId;

        return $this;
    }
}
