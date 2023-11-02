<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class BusinessAccount
{
    private $id;
    private $name;
    private $address;
    private $restaurants;
    private $employees;
    private $billingAddress;

    public function __construct()
    {
        $this->restaurants = new ArrayCollection();
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
    public function getRestaurants()
    {
        return $this->restaurants;
    }

    /**
     * @param mixed $restaurants
     *
     * @return self
     */
    public function setRestaurants($restaurants)
    {
        $this->restaurants = $restaurants;

        return $this;
    }

    /**
     * @param LocalBusiness $restaurant
     */
    public function addRestaurant(LocalBusiness $restaurant)
    {
        if (!$this->restaurants->contains($restaurant)) {
            $restaurant->setBusinessAccount($this);
            $this->restaurants->add($restaurant);
        }
    }

    /**
     * @param LocalBusiness $restaurant
     */
    public function removeRestaurant(LocalBusiness $restaurant): void
    {
        $this->restaurants->removeElement($restaurant);
        $restaurant->setBusinessAccount(null);
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

}
