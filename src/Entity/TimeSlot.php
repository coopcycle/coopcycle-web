<?php

namespace AppBundle\Entity;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Utils\OpeningHoursSpecification;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *   normalizationContext={"groups"={"time_slot"}},
 *   itemOperations={
 *     "get"={"method"="GET"}
 *   }
 * )
 */
class TimeSlot
{
    use Timestampable;

    private $id;

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $name;

    /**
     * @Groups({"time_slot"})
     */
    private $choices;

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $interval = '2 days';

    /**
     * @var bool
     * @Groups({"time_slot"})
     */
    private $workingDaysOnly = true;

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $priorNotice;

    /**
     * @var string
     */
    private $sameDayCutoff = null;

    /**
     * @var array
     */
    private $openingHours = [];

    public function __construct()
    {
    	$this->choices = new ArrayCollection();
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
    public function getChoices()
    {
        return $this->choices;
    }

    /**
     * @param mixed $choices
     *
     * @return self
     */
    public function setChoices($choices)
    {
        $this->choices = $choices;

        return $this;
    }

    public function addChoice($choice)
    {
        $choice->setTimeSlot($this);

        $this->choices->add($choice);
    }

    public function removeChoice($choice)
    {
        $this->choices->removeElement($choice);

        $choice->setTimeSlot(null);
    }

    /**
     * @return mixed
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * @param mixed $interval
     *
     * @return self
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getOpeningHours()
    {
        return $this->openingHours;
    }

    /**
     * @param array $openingHours
     *
     * @return self
     */
    public function setOpeningHours($openingHours)
    {
        $this->openingHours = $openingHours;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasOpeningHours()
    {
        return null !== $this->openingHours && !empty($this->openingHours);
    }

    /**
     * @return bool
     */
    public function isWorkingDaysOnly(): bool
    {
        return $this->workingDaysOnly;
    }

    /**
     * @param bool $workingDaysOnly
     *
     * @return self
     */
    public function setWorkingDaysOnly(bool $workingDaysOnly)
    {
        $this->workingDaysOnly = $workingDaysOnly;

        return $this;
    }

    /**
     * @Groups({"time_slot"})
     */
    public function getOpeningHoursSpecification()
    {
        if ($this->hasOpeningHours()) {
            return array_map(function (OpeningHoursSpecification $spec) {
                return $spec->jsonSerialize();
            }, OpeningHoursSpecification::fromOpeningHours($this->getOpeningHours()));
        }

        return [];
    }

    /**
     * @return string
     */
    public function getPriorNotice()
    {
        return $this->priorNotice;
    }

    /**
     * @param string $priorNotice
     *
     * @return self
     */
    public function setPriorNotice($priorNotice)
    {
        $this->priorNotice = $priorNotice;

        return $this;
    }

    /**
     * @return string
     */
    public function getSameDayCutoff()
    {
        return $this->sameDayCutoff;
    }

    /**
     * @param string $sameDayCutoff
     *
     * @return self
     */
    public function setSameDayCutoff($sameDayCutoff)
    {
        $this->sameDayCutoff = $sameDayCutoff;

        return $this;
    }

    public static function fromLocalBusiness(LocalBusiness $business, FulfillmentMethod $fulfillmentMethod): TimeSlot
    {
        $timeSlot = new self();
        $timeSlot->setWorkingDaysOnly(false);

        $minutes = $business->getOrderingDelayMinutes();
        if ($minutes > 0) {
            $hours = (int) $minutes / 60;
            $timeSlot->setPriorNotice(sprintf('%d %s', $hours, ($hours > 1 ? 'hours' : 'hour')));
        }

        $shippingOptionsDays = $business->getShippingOptionsDays();
        if ($shippingOptionsDays > 0) {
            $timeSlot->setInterval(sprintf('%d days', $shippingOptionsDays));
        }

        $timeSlot->setOpeningHours(
            $fulfillmentMethod->getOpeningHours()
        );

        return $timeSlot;
    }
}
