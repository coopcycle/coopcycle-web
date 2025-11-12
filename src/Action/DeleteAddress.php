<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Address;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DeleteAddress
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke($data): Address
    {
        $user = $this->getUser();

        $address = $data;

        $user->removeAddress($address);

        return $data;
    }
}
