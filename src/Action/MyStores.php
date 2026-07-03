<?php

namespace AppBundle\Action;

use Symfony\Bundle\SecurityBundle\Security;

class MyStores
{
    public function __construct(private Security $security)
    {
    }

    public function __invoke()
    {
        return $this->security->getUser()->getStores();
    }
}
