<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\Collection;

class BusinessAccount
{
    private ?int $id;
    private string $name;
    private string $legalName;
    private string $vatNumber;
    private Address $address;
    private ?Address $billingAddress;
    private ?BusinessRestaurantGroup $businessRestaurantGroup;
    private Collection $employees;
    private Collection $orders;

    /**
     * Only to keep data in form flow
     */
    private $differentAddressForBilling;

    public function __construct()
    {
        $this->id = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): BusinessAccount
    {
        $this->name = $name;
        return $this;
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function setLegalName(string $legalName): BusinessAccount
    {
        $this->legalName = $legalName;
        return $this;
    }

    public function getVatNumber(): string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(string $vatNumber): BusinessAccount
    {
        $this->vatNumber = $vatNumber;
        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): BusinessAccount
    {
        $this->address = $address;
        return $this;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?Address $billingAddress): BusinessAccount
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    public function getBusinessRestaurantGroup(): ?BusinessRestaurantGroup
    {
        return $this->businessRestaurantGroup;
    }

    public function setBusinessRestaurantGroup(?BusinessRestaurantGroup $businessRestaurantGroup): BusinessAccount
    {
        $this->businessRestaurantGroup = $businessRestaurantGroup;
        return $this;
    }

    public function getEmployees(): Collection
    {
        return $this->employees;
    }

    public function setEmployees(Collection $employees): BusinessAccount
    {
        $this->employees = $employees;
        return $this;
    }


    public function addEmployee(User $employee): void
    {
        if (!$this->employees->contains($employee)) {
            $employee->setBusinessAccount($this);
            $this->employees->add($employee);
        }
    }

    public function removeEmployee(User $employee): void
    {
        $this->employees->removeElement($employee);
        $employee->setBusinessAccount(null);
    }

    public function getDifferentAddressForBilling()
    {
        return $this->differentAddressForBilling;
    }

    public function setDifferentAddressForBilling($differentAddressForBilling)
    {
        $this->differentAddressForBilling = $differentAddressForBilling;
        return $this;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function setOrders(Collection $orders): BusinessAccount
    {
        $this->orders = $orders;
        return $this;
    }
}
