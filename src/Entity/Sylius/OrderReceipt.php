<?php

namespace AppBundle\Entity\Sylius;

class OrderReceipt extends FrozenOrder
{
    protected $billingAddress;

    /**
     * @return mixed
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * @param mixed $billingAddress
     *
     * @return self
     */
    public function setBillingAddress($billingAddress)
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }
}
