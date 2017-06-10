<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Order;

class AppliColis extends Base
{
    public function getKey()
    {
        return 'applicolis';
    }

    public function create(Order $order)
    {

    }
}
