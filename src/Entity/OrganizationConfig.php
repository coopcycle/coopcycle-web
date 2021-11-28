<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Sylius\Component\Customer\Model\CustomerGroup;

class OrganizationConfig
{
    private $id;

    private $organization;

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

    public function __construct(
        Organization $organization = null)
    {
        $this->organization = $organization;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
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
    public function getCoverageOfDeliveryCostsByTheCompanyOrTheEmployee(): ?string
    {
        return $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee;
    }

    public function isCoverageOfDeliveryCostsByTheCompanyOrTheEmployee()
    {
        return $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee !== null;
    }

    /**
     * @return Address|null
     */
    public function getAddress(): ?Address
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

    /**
     * @param mixed $group
     *
     * @return self
     */
    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
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
     * @param mixed $logo
     *
     * @return self
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * @param string $deliveryPerimeterExpression
     *
     * @return self
     */
    public function setDeliveryPerimeterExpression($deliveryPerimeterExpression)
    {
        $this->deliveryPerimeterExpression = $deliveryPerimeterExpression;

        return $this;
    }

    /**
     * @param mixed $numberOfOrderAvailable
     *
     * @return self
     */
    public function setNumberOfOrderAvailable($numberOfOrderAvailable)
    {
        $this->numberOfOrderAvailable = $numberOfOrderAvailable;

        return $this;
    }

    /**
     * @param mixed $amountOfSubsidyPerEmployeeAndOrder
     *
     * @return self
     */
    public function setAmountOfSubsidyPerEmployeeAndOrder($amountOfSubsidyPerEmployeeAndOrder)
    {
        $this->amountOfSubsidyPerEmployeeAndOrder = $amountOfSubsidyPerEmployeeAndOrder;

        return $this;
    }

    /**
     * @param mixed $coverageOfDeliveryCostsByTheCompanyOrTheEmployee
     *
     * @return self
     */
    public function setCoverageOfDeliveryCostsByTheCompanyOrTheEmployee($coverageOfDeliveryCostsByTheCompanyOrTheEmployee)
    {
        $this->coverageOfDeliveryCostsByTheCompanyOrTheEmployee = $coverageOfDeliveryCostsByTheCompanyOrTheEmployee;

        return $this;
    }

    /**
     * @param mixed $orderLeadTime
     *
     * @return self
     */
    public function setOrderLeadTime($orderLeadTime)
    {
        $this->orderLeadTime = $orderLeadTime;

        return $this;
    }

    /**
     * @param mixed $limitHourOrder
     *
     * @return self
     */
    public function setLimitHourOrder($limitHourOrder)
    {
        $this->limitHourOrder = $limitHourOrder;

        return $this;
    }

    /**
     * @param mixed $startHourOrder
     *
     * @return self
     */
    public function setStartHourOrder($startHourOrder)
    {
        $this->startHourOrder = $startHourOrder;

        return $this;
    }

    /**
     * @param mixed $dayOfOrderAvailable
     *
     * @return self
     */
    public function setDayOfOrderAvailable($dayOfOrderAvailable)
    {
        $this->dayOfOrderAvailable = $dayOfOrderAvailable;

        return $this;
    }
}
