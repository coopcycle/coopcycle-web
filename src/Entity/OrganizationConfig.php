<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Sylius\Component\Customer\Model\CustomerGroup;

class OrganizationConfig
{
    private $id;
    private $group;
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

    public function __construct(
        CustomerGroup $group,
        Address $address,
        string $logo,
        string $deliveryPerimeterExpression,
        string $numberOfOrderAvailable,
        string $amountOfSubsidyPerEmployeeAndOrder,
        string $coverageOfDeliveryCostsByTheCompanyOrTheEmployee,
        \DateTimeInterface $orderLeadTime,
        int $limitHourOrder,
        int $startHourOrder,
        string $dayOfOrderAvailable)
    {
        $this->group = $group;
        $this->address = $address;
        $this->logo = $logo;
        $this->deliveryPerimeterExpression = $deliveryPerimeterExpression;
        $this->numberOfOrderAvailable = $numberOfOrderAvailable;
        $this->amountOfSubsidyPerEmployeeAndOrder = $amountOfSubsidyPerEmployeeAndOrder;
        $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee = $coverageOfDeliveryCostsByTheCompanyOrTheEmployee;
        $this->orderLeadTime = $orderLeadTime;
        $this->limitHourOrder = $limitHourOrder;
        $this->startHourOrder = $startHourOrder;
        $this->dayOfOrderAvailable = $dayOfOrderAvailable;
    }

    public function setOrganization(Organization $organization)
    {
        $this->organization = $organization;
    }

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

    /**
     * @return string
     */
    public function getCoverageOfDeliveryCostsByTheCompanyOrTheEmployee(): string
    {
        return $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee;
    }


    private function isCoverageOfDeliveryCostsByTheCompanyOrTheEmployee()
    {
        return $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee !== null;
    }

    /**
     * @return Address
     */
    public function getAddress(): Address
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

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }
}
