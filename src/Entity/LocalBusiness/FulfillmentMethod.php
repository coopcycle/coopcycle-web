<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Validator\Constraints\NotOverlappingOpeningHours as AssertNotOverlappingOpeningHours;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Symfony\Component\Validator\Constraints as Assert;

class FulfillmentMethod implements ToggleableInterface
{
    use ToggleableTrait;

    private $id;
    private $type = 'delivery';

    /**
     * @var array
     *
     * @AssertNotOverlappingOpeningHours
     */
    private $openingHours = [];
    private $openingHoursBehavior = 'asap';

    private $options = [];

    /**
     * @var int
     * @Assert\NotBlank
     */
    private $minimumAmount = 0;

    /**
     * @var int Additional time to delay ordering
     */
    protected $orderingDelayMinutes = 0;

    /**
     * @var boolean
     */
    protected $preOrderingAllowed = true;

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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     *
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getOpeningHours()
    {
        return $this->openingHours;
    }

    public function setOpeningHours($openingHours)
    {
        $this->openingHours = $openingHours;

        return $this;
    }

    public function addOpeningHour($openingHour)
    {
        $this->openingHours[] = $openingHour;
    }

    public function getOpeningHoursBehavior()
    {
        return $this->openingHoursBehavior;
    }

    public function setOpeningHoursBehavior($openingHoursBehavior)
    {
        $this->openingHoursBehavior = $openingHoursBehavior;
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return self
     */
    public function setOption(string $name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * @return mixed
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * @return int
     */
    public function getMinimumAmount()
    {
        return $this->minimumAmount;
    }

    /**
     * @param int $minimumAmount
     *
     * @return self
     */
    public function setMinimumAmount($minimumAmount)
    {
        $this->minimumAmount = $minimumAmount;

        return $this;
    }

    /**
     * @return int
     */
    public function getOrderingDelayMinutes()
    {
        return $this->orderingDelayMinutes;
    }

    /**
     * @param int $orderingDelayMinutes
     */
    public function setOrderingDelayMinutes(int $orderingDelayMinutes)
    {
        $this->orderingDelayMinutes = $orderingDelayMinutes;
    }

    /**
     * @return boolean
     */
    public function isPreOrderingAllowed(): bool
    {
        return $this->preOrderingAllowed;
    }

    /**
     * @param bool $preOrderingAllowed
     */
    public function setPreOrderingAllowed(bool $preOrderingAllowed)
    {
        $this->preOrderingAllowed = $preOrderingAllowed;
    }
}
