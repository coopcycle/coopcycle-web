<?php
declare(strict_types=1);

namespace AppBundle\Message\Request;

use AppBundle\Entity\Address;

class RequestRestaurant
{
    private string $name;
    /**
     * @var Address
     */
    private Address $address;
    private string $contact;
    private bool $b2b;

    public function __construct(string $name, Address $address, string $contact, bool $b2b)
    {
        $this->name = $name;
        $this->address = $address;
        $this->contact = $contact;
        $this->b2b = $b2b;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Address
     */
    public function getAddress(): Address
    {
        return $this->address;
    }

    /**
     * @return string
     */
    public function getContact(): string
    {
        return $this->contact;
    }

    /**
     * @return bool
     */
    public function isB2b(): bool
    {
        return $this->b2b;
    }
}
