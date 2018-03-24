<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\ApiUser;
use Sylius\Component\Order\Model\Order as BaseOrder;

class Order extends BaseOrder
{
    protected $customer;

    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer(ApiUser $customer)
    {
        $this->customer = $customer;

        return $this;
    }
}
