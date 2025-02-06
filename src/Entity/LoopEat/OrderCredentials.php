<?php

namespace AppBundle\Entity\LoopEat;

use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use Gedmo\Timestampable\Traits\Timestampable;

class OrderCredentials
{
    use LoopEatOAuthCredentialsTrait;
    use Timestampable;

    private $id;

    public function getId()
    {
        return $this->id;
    }
}

