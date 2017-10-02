<?php

namespace AppBundle\Entity\DeliveryService;

use AppBundle\Entity\DeliveryService;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class AppliColis extends DeliveryService
{
    public function getToken()
    {
        return $this->options['token'];
    }

    public function setToken($token)
    {
        $this->options['token'] = $token;

        return $this;
    }

    public function getType()
    {
        return 'applicolis';
    }
}
