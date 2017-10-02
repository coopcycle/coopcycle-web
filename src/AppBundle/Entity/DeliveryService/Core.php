<?php

namespace AppBundle\Entity\DeliveryService;

use AppBundle\Entity\DeliveryService;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Core extends DeliveryService
{
    public function getType()
    {
        return 'core';
    }
}
