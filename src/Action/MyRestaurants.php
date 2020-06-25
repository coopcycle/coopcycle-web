<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MyRestaurants
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke()
    {
        return $this->getUser()->getRestaurants();
    }
}
