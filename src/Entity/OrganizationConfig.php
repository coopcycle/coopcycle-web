<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

class OrganizationConfig
{
    public $id;
    private $name;
    private $addresses;
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
        string $name,
        Address $address,
        string $logo,
        string $deliveryPerimeterExpression,
        string $numberOfOrderAvailable,
        string $amountOfSubsidyPerEmployeeAndOrder,
        string $coverageOfDeliveryCostsByTheCompanyOrTheEmployee,
        \DateTimeInterface $orderLeadTime,
        int $limitHourOrder,
        int $startHourOrder,
        string $dayOfOrderAvailable
    ) {
        $this->name = $name;
        $this->addresses = new ArrayCollection([$address]);
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
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return ArrayCollection
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
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
