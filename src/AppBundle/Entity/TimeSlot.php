<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Yasumi\Yasumi;

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
     * @var string
     * @Groups({"time_slot"})
     */
    private $choices;

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $interval = '2 days';

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $workingDaysOnly = true;

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
        $iterator = $this->choices->getIterator();
        $iterator->uasort(function ($a, $b) {

            $date = new \DateTime();

            [ $startA ] = $a->toDateTime($date);
            [ $startB ] = $b->toDateTime($date);

            return ($startA < $startB) ? -1 : 1;
        });

        return new ArrayCollection(iterator_to_array($iterator));
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
    }

    private function countNumberOfDays(array $items)
    {
        $days = [];
        foreach ($items as $item) {
            $days[] = $item->getDate()->format('Y-m-d');
        }

        return count(array_unique($days));
    }

    public function getChoicesWithDates($country)
    {
        $now = Carbon::now();

        $choices = $now->diffInDays($now->copy()->add($this->interval));

        $providers = Yasumi::getProviders();
        if ($this->workingDaysOnly && isset($providers[strtoupper($country)])) {
            $providerClass = $providers[strtoupper($country)];
            $provider = Yasumi::create($providerClass, date('Y'));
            if ($provider->isWorkingDay($now)) {
                $nextWorkingDay = clone $now;
            } else {
                $nextWorkingDay = Yasumi::nextWorkingDay($providerClass, $now);
            }


        } else {
            $nextWorkingDay = clone $now;
        }

        $items = [];

        $numberOfDays = 0;
        while ($numberOfDays < $choices) {

            foreach ($this->getChoices() as $choice) {
                [ $start, $end ] = $choice->toDateTime($nextWorkingDay);

                if ($end <= $now) {
                    continue;
                }

                $items[] = new TimeSlotChoiceWithDate($choice, clone $nextWorkingDay);
            }

            if ($this->workingDaysOnly && isset($providers[strtoupper($country)])) {
                $provider = $providers[strtoupper($country)];
                $nextWorkingDay = Yasumi::nextWorkingDay($provider, $nextWorkingDay);
            } else {
                $nextWorkingDay = clone $nextWorkingDay;
                $nextWorkingDay->modify('+1 day');
            }

            $numberOfDays = $this->countNumberOfDays($items);
        }

        return $items;
    }

    /**
     * @return mixed
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isWorkingDaysOnly(): bool
    {
        return $this->workingDaysOnly;
    }

    /**
     * @param mixed $workingDaysOnly
     *
     * @return self
     */
    public function setWorkingDaysOnly(bool $workingDaysOnly)
    {
        $this->workingDaysOnly = $workingDaysOnly;

        return $this;
    }
}
