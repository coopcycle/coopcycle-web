<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Order\Model\Order as BaseOrder;

class Order extends BaseOrder
{
    protected $customer;
}
