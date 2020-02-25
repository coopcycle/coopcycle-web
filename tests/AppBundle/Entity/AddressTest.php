<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function testGetFirstNameLastName()
    {
        $address = new Address();

        $this->assertEquals('', $address->getFirstName());
        $this->assertEquals('', $address->getLastName());

        $address->setContactName('John Doe');

        $this->assertEquals('John', $address->getFirstName());
        $this->assertEquals('Doe', $address->getLastName());
    }
}
