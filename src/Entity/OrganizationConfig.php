<?php

namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class OrganizationConfig
{
    private $name;
    private $address;
    private $logo;
    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    private $deliveryPerimeterExpression = 'distance < 3000';
    private $numberOfOrderAvailable;
    private $amountOfSubsidyPerEmployeeAndOrder;
    private $coverageOfDeliveryCostsByTheCompanyOrTheEmployee;
    private $orderLeadTime;
    private $limitHourOrder;
    private $startHourOrder;
    private $dayOfOrderAvailable;
    private $organization;

    /**
     * @return mixed
     */
    public function getOrderLeadTime()
    {
        return $this->orderLeadTime;
    }

    /**
     * @return mixed
     */
    public function getLimitHourOrder()
    {
        return $this->limitHourOrder;
    }

    /**
     * @return mixed
     */
    public function getDayOfOrderAvailable()
    {
        return $this->dayOfOrderAvailable;
    }

    /**
     * @return mixed
     */
    public function getStartHourOrder()
    {
        return $this->startHourOrder;
    }

    /**
     * @return mixed
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }

    private function isCoverageOfDeliveryCostsByTheCompanyOrTheEmployee()
    {
        return $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee !== null;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return mixed
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @return mixed
     */
    public function getNumberOfOrderAvailable()
    {
        return $this->numberOfOrderAvailable;
    }

    /**
     * @return mixed
     */
    public function getAmountOfSubsidyPerEmployeeAndOrder()
    {
        return $this->amountOfSubsidyPerEmployeeAndOrder;
    }

    /**
     * @return string
     */
    public function getDeliveryPerimeterExpression(): string
    {
        return $this->deliveryPerimeterExpression;
    }
}
