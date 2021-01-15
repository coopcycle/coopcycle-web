<?php

namespace AppBundle\Entity\LoopEat;

use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use Gedmo\Timestampable\Traits\Timestampable;

class CustomerCredentials
{
    use LoopEatOAuthCredentialsTrait;
    use Timestampable;

    private $id;
    private $customer;

    public function getId()
    {
        return $this->id;
    }

    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer($customer)
    {
        $this->customer = $customer;

        return $this;
    }
}
