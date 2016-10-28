<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * A courier.
 *
 * @ORM\Entity
 * @ApiResource
 */
class Courier extends Person
{
}
