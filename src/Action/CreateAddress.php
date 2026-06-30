<?php

namespace AppBundle\Action;

use AppBundle\Entity\Address;
use Symfony\Bundle\SecurityBundle\Security;

class CreateAddress
{
    public function __construct(private Security $security)
    {
    }

    public function __invoke($data): Address
    {
        $user = $this->security->getUser();

        $address = $data;

        $user->addAddress($address);

        return $data;
    }
}
