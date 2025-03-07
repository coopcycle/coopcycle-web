<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use AppBundle\Entity\Address;
use Symfony\Component\Serializer\Annotation\Groups;

final class AddressInput
{
    /**
     * @var Store
     * @Groups({"address_create"})
     */
    public $store;

    /**
     * @var Address
     * @Groups({"address_create"})
     */
    public $address;
}
