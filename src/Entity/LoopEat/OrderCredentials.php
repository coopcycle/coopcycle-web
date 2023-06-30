<?php

namespace AppBundle\Entity\LoopEat;

use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use Gedmo\Timestampable\Traits\Timestampable;

class OrderCredentials
{
    use LoopEatOAuthCredentialsTrait;
    use Timestampable;

    private $id;
    private $order;

    public function getId()
    {
        return $this->id;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }
}

