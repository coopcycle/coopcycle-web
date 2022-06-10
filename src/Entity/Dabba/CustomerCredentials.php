<?php

namespace AppBundle\Entity\Dabba;

use AppBundle\OAuth\CredentialsTrait;
use Gedmo\Timestampable\Traits\Timestampable;

class CustomerCredentials
{
    use CredentialsTrait;
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
