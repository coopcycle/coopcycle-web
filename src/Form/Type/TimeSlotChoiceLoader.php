<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\TimeSlot;
use AppBundle\Utils\OpeningHoursSpecification;
use Carbon\Carbon;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Yasumi\Yasumi;

class TimeSlotChoiceLoader implements ChoiceLoaderInterface
{
    private $timeSlot;
    private $country;
    private $OHSToCarbon;
    private $openingHoursSpecifications = [];
    private $expectedCount = 0;
    private $now;
    private $workingDaysProviderClass;

    public function __construct(TimeSlot $timeSlot, string $country)
    {
        $this->timeSlot = $timeSlot;
        $this->country = $country;

        $carbonToOHS = [
            Carbon::MONDAY    => 'Monday',
            Carbon::TUESDAY   => 'Tuesday',
            Carbon::WEDNESDAY => 'Wednesday',
            Carbon::THURSDAY  => 'Thursday',
            Carbon::FRIDAY    => 'Friday',
            Carbon::SATURDAY  => 'Saturday',
            Carbon::SUNDAY    => 'Sunday',
        ];

        $this->OHSToCarbon = array_flip($carbonToOHS);

        $this->now = Carbon::now();

        $this->expectedCount =
            $this->now->diffInDays($this->now->copy()->add($timeSlot->getInterval()));

        $this->workingDaysOnly = !$timeSlot->hasOpeningHours() && $timeSlot->isWorkingDaysOnly();

        if ($timeSlot->hasOpeningHours()) {
            $this->openingHoursSpecifications =
                OpeningHoursSpecification::fromOpeningHours($timeSlot->getOpeningHours());
        }

        if ($this->workingDaysOnly) {
            $providers = Yasumi::getProviders();
            if (isset($providers[strtoupper($country)])) {
                $this->workingDaysProviderClass = $providers[strtoupper($country)];
            }
        }
    }

    private function countNumberOfDays(array $choices)
    {
        $days = [];
        foreach ($choices as $choice) {
            $days[] = $choice->getDate()->format('Y-m-d');
        }

        return count(array_unique($days));
    }

    private function getCursor(\DateTimeInterface $now)
    {
        if ($this->workingDaysOnly && null !== $this->workingDaysProviderClass) {
            $provider = Yasumi::create($this->workingDaysProviderClass, $now->format('Y'));
            if (!$provider->isWorkingDay($now)) {
                return Yasumi::nextWorkingDay($this->workingDaysProviderClass, $now);
            }
        }

        return clone $now;
    }

    private function moveCursor(\DateTime $cursor)
    {
        $newCursor = clone $cursor;

        if ($this->workingDaysOnly && null !== $this->workingDaysProviderClass) {
            return Yasumi::nextWorkingDay($this->workingDaysProviderClass, $cursor);

        }

        $newCursor->modify('+1 day');

        return $newCursor;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if ($this->expectedCount <= 0) {
            return new ArrayChoiceList([], $value);
        }

        if (!$this->timeSlot->hasOpeningHours() && count($this->timeSlot->getChoices()) === 0) {
            return new ArrayChoiceList([], $value);
        }

        $cursor = $this->getCursor($this->now);

        $choices = [];
        $count = 0;
        while ($count < $this->expectedCount) {

            if ($this->timeSlot->hasOpeningHours()) {
                foreach ($this->openingHoursSpecifications as $spec) {

                    $weekdays = array_map(function ($dayOfWeek) {
                        return $this->OHSToCarbon[$dayOfWeek];
                    }, $spec->dayOfWeek);

                    if (in_array($cursor->weekday(), $weekdays)) {
                        $choice = new TimeSlotChoice(
                            clone $cursor,
                            sprintf('%s-%s', $spec->opens, $spec->closes)
                        );

                        if (!empty($this->timeSlot->getSameDayCutoff())
                        && Carbon::instance($cursor)->isSameDay($this->now)) {
                            $cutoff = $this->now->copy()->setTimeFromTimeString(
                                $this->timeSlot->getSameDayCutoff()
                            );
                            if ($this->now > $cutoff) {
                                continue;
                            }
                        }

                        if (!$choice->hasFinished($this->now, $this->timeSlot->getPriorNotice())) {
                            $choices[] = $choice;
                        }
                    }
                }
            } else {
                foreach ($this->timeSlot->getChoices() as $timeSlotChoice) {
                    $choice = new TimeSlotChoice(
                        clone $cursor,
                        $timeSlotChoice->toTimeRange()
                    );

                    if (!$choice->hasFinished($this->now, $this->timeSlot->getPriorNotice())) {
                        $choices[] = $choice;
                    }
                }
            }

            $count = $this->countNumberOfDays($choices);
            $cursor = $this->moveCursor($cursor);
        }

        return new ArrayChoiceList($choices, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
