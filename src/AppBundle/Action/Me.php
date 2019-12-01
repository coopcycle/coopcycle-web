<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\ApiUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Me
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(): ApiUser
    {
        return $this->getUser();
    }
}
