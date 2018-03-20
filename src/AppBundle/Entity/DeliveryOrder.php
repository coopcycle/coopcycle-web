<?php

namespace AppBundle\Entity;

use Sylius\Component\Order\Model\OrderInterface;

/**
 * This is a table to associate an OrderInterface with a customer.
 * Once https://github.com/coopcycle/coopcycle-web/issues/155 is fixed, remove this class & properly extend OrderInterface.
 */
class DeliveryOrder
{
    protected $order;

    protected $user;

    public function __construct(OrderInterface $order = null, ApiUser $user = null)
    {
        $this->order = $order;
        $this->user = $user;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getUser()
    {
        return $this->user;
    }
}
