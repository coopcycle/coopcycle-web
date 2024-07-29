<?php

namespace AppBundle\Entity\Paygreen;

use Gedmo\Timestampable\Traits\Timestampable;

class CustomerDetails
{
    use Timestampable;

    private $id;
    private $customer;
    private $buyerId;

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
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param mixed $customer
     *
     * @return self
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * @param mixed $buyerId
     *
     * @return self
     */
    public function setBuyerId($buyerId)
    {
        $this->buyerId = $buyerId;

        return $this;
    }

    public function getBuyerId()
    {
        return $this->buyerId;
    }
}
