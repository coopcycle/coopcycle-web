<?php

namespace AppBundle\Form\Type;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use AppBundle\Service\TimeRegistry;
use AppBundle\Utils\DateUtils;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\Exceptions\MaximumLimitExceeded;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class AsapChoiceLoader implements ChoiceLoaderInterface
{
    private $openingHours;
    private $closingRules;
    private $orderingDelayMinutes;
    private $rangeDuration;

    public function __construct(
        array $openingHours,
        TimeRegistry $timeRegistry,
        Collection $closingRules = null,
        int $orderingDelayMinutes = 0,
        int $rangeDuration = 10,
        bool $preOrderingAllowed = true)
    {
        $this->openingHours = $openingHours;
        $this->timeRegistry = $timeRegistry;
        $this->closingRules = $closingRules ?? new ArrayCollection();
        $this->orderingDelayMinutes = $orderingDelayMinutes;
        $this->rangeDuration = $rangeDuration;
        $this->preOrderingAllowed = $preOrderingAllowed;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        $now = Carbon::now();

        if ($this->orderingDelayMinutes > 0) {
            $now->modify(sprintf('+%d minutes', $this->orderingDelayMinutes));
        }

        if (count($this->openingHours) === 0) {

            return new ArrayChoiceList([], $value);
        }

        $openingHours = SpatieOpeningHoursRegistry::get($this->openingHours, $this->closingRules);

        try {
            $nextOpeningDate = $openingHours->nextOpen($now);
            $nextClosingDate = $openingHours->nextClose($nextOpeningDate);
        } catch (MaximumLimitExceeded $e) {
            return new ArrayChoiceList([], $value);
        }

        $now = $this->roundToNext($now, $this->rangeDuration);

        $now->setTime($now->format('H'), $now->format('i'), 0);

        if ($this->preOrderingAllowed) {
            $max = Carbon::instance($now)->add(7, 'days');
        } else {
            $max = Carbon::instance($now)->setTime(23, 59);
        }

        $choices = [];

        $range = $openingHours->currentOpenRange($now);

        // We add the average preparation time + average shipping time
        $offset =
            $this->timeRegistry->getAveragePreparationTime() + $this->timeRegistry->getAverageShippingTime();

        if ($range) {

            $end = Carbon::instance($now)
                ->setTime(
                    $range->end()->hours(),
                    $range->end()->minutes()
                );

            $end->modify(
                sprintf('+%d minutes', $offset)
            );

            $period = CarbonPeriod::create(
                $now, sprintf('%d minutes', $this->rangeDuration), $end,
                CarbonPeriod::EXCLUDE_END_DATE
            );

            foreach ($period as $date) {
                $choices[] = new TsRangeChoice(
                    TsRange::create($date, $date->copy()->add($this->rangeDuration, 'minutes'))
                );
            }

            $nextClosingDate = $openingHours->nextClose($now);
        } else {
            if ($nextOpeningDate > $max) {
                return new ArrayChoiceList([], $value);
            }
        }

        $nextClosingDate->modify(
            sprintf('+%d minutes', $offset)
        );

        $cursor = Carbon::instance($now);
        while ($cursor < $max) {

            $period = CarbonPeriod::create(
                $this->roundToNext($nextOpeningDate, $this->rangeDuration), sprintf('%d minutes', $this->rangeDuration), $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );

            foreach ($period as $date) {

                $cursor = $date;
                if ($date < $max) {
                    $choices[] = new TsRangeChoice(
                        TsRange::create($date, $date->copy()->add($this->rangeDuration, 'minutes'))
                    );
                }
            }

            $nextOpeningDate = $openingHours->nextOpen($nextClosingDate);
            $nextClosingDate = $openingHours->nextClose($nextOpeningDate);

            $nextClosingDate->modify(
                sprintf('+%d minutes', $offset)
            );
        }

        return new ArrayChoiceList($choices, $value);
    }

    private function roundToNext(\DateTime $date, int $value = 10)
    {
        while (($date->format('i') % $value) !== 0) {
            $date->modify('+1 minute');
        }

        return $date;
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
