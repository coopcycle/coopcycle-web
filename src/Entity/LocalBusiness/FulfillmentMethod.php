<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Validator\Constraints\TimeRange as AssertTimeRange;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Symfony\Component\Validator\Constraints as Assert;

class FulfillmentMethod implements ToggleableInterface
{
    use ToggleableTrait;

    private $id;
    private $restaurant;
    private $type = 'delivery';

    /**
     * @var array
     * @Assert\All({
     *   @AssertTimeRange()
     * })
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
    public function getOption($name)
    {
        return isset($this->options[$name]) ? $this->options[$name] : null;
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
}
