<?php

namespace AppBundle\Action;

use Symfony\Bundle\SecurityBundle\Security;

class MyRestaurants
{
    public function __construct(private Security $security)
    {
    }

    public function __invoke()
    {
        return $this->security->getUser()->getRestaurants();
    }
}
