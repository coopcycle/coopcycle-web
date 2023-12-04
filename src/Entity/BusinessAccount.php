<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class BusinessAccount
{
    private $id;
    private $name;
    private $address;
    private $hub;
    private $employees;
    private $billingAddress;

    /**
     * Only to keep data in form flow
     */
    private $differentAddressForBilling;

    public function __construct()
    {
        $this->employees = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param mixed $address
     *
     * @return self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * @param mixed $billingAddress
     *
     * @return self
     */
    public function setBillingAddress($billingAddress)
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getHub()
    {
        return $this->hub;
    }

    /**
     * @param mixed $hub
     *
     * @return self
     */
    public function setHub($hub)
    {
        $this->hub = $hub;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmployees()
    {
        return $this->employees;
    }

    /**
     * @param mixed $employees
     *
     * @return self
     */
    public function setEmployees($employees)
    {
        $this->employees = $employees;

        return $this;
    }

    /**
     * @param User $employee
     */
    public function addEmployee(User $employee)
    {
        if (!$this->employees->contains($employee)) {
            $employee->setBusinessAccount($this);
            $this->employees->add($employee);
        }
    }

    /**
     * @param User $employee
     */
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

}
