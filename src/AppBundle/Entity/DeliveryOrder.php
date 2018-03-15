<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * This is a table to associate an OrderInterface with a customer.
 * Once https://github.com/coopcycle/coopcycle-web/issues/155 is fixed, remove this class & properly extend OrderInterface.
 * @ORM\Entity
 */
class DeliveryOrder
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Sylius\Component\Order\Model\Order")
     */
    protected $order;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    protected $user;

    public function __construct(OrderInterface $order = null, ApiUser $user = null)
    {
        $this->order = $order;
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }
}
