<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class AddressTest extends TestCase
{
    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();
    }

    public function testGetFirstNameLastName()
    {
        $address = new Address();

        $this->assertEquals('', $address->getFirstName());
        $this->assertEquals('', $address->getLastName());

        $address->setContactName('John Doe');

        $this->assertEquals('John', $address->getFirstName());
        $this->assertEquals('Doe', $address->getLastName());

        $address->setContactName('John');

        $this->assertEquals('John', $address->getFirstName());
        $this->assertEquals('John', $address->getLastName());
    }

    public function testValidation()
    {
        $address = new Address();
        $address->setStreetAddress('23, Rue de Rivoli');

        $violations = $this->validator->validate($address);

        $this->assertCount(1, $violations);

        $propertyPaths = [];
        foreach ($violations as $violation) {
            $propertyPaths[] = $violation->getPropertyPath();
        }

        $this->assertContains('geo', $propertyPaths);
    }
}
