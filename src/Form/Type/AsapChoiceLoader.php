<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\LocalBusiness;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use AppBundle\Utils\DateUtils;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Spatie\OpeningHours\OpeningHours;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class AsapChoiceLoader implements ChoiceLoaderInterface
{
    private $openingHours;
    private $closingRules;
    private $orderingDelayMinutes;

    public function __construct(
        array $openingHours,
        Collection $closingRules = null,
        int $orderingDelayMinutes = 0,
        int $rounding = 5,
        bool $preOrderingAllowed = true)
    {
        $this->openingHours = $openingHours;
        $this->closingRules = $closingRules ?? new ArrayCollection();
        $this->orderingDelayMinutes = $orderingDelayMinutes;
        $this->rounding = $rounding;
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

        $availabilities = [];

        $openingHours = SpatieOpeningHoursRegistry::get($this->openingHours, $this->closingRules);

        $nextOpeningDate = $openingHours->nextOpen($now);
        $nextClosingDate = $openingHours->nextClose($nextOpeningDate);

        $now = $this->roundTo15($now);

        $now->setTime($now->format('H'), $now->format('i'), 0);

        if ($this->preOrderingAllowed) {
            $max = Carbon::instance($now)->add(7, 'days');
        } else {
            $max = Carbon::instance($now)->setTime(23, 59);
        }

        if ($nextOpeningDate > $max) {
            return new ArrayChoiceList([], $value);
        }

        $range = $openingHours->currentOpenRange($now);

        if ($range) {

            $end = Carbon::instance($now)
                ->setTime(
                    $range->end()->hours(),
                    $range->end()->minutes()
                );

            $period = CarbonPeriod::create(
                $now, '15 minutes', $end,
                CarbonPeriod::EXCLUDE_END_DATE
            );

            foreach ($period as $date) {
                $availabilities[] = $date->format(\DateTime::ATOM);
            }

            $nextClosingDate = $openingHours->nextClose($now);
        }

        $cursor = Carbon::instance($now);
        while ($cursor < $max) {

            $period = CarbonPeriod::create(
                $this->roundTo15($nextOpeningDate), '15 minutes', $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );

            foreach ($period as $date) {
                $cursor = $date;
                if ($date < $max) {
                    $availabilities[] = $date->format(\DateTime::ATOM);
                }
            }

            $nextOpeningDate = $openingHours->nextOpen($nextClosingDate);
            $nextClosingDate = $openingHours->nextClose($nextOpeningDate);
        }

        $availabilities = array_values(array_unique($availabilities));

        return new ArrayChoiceList($this->toChoices($availabilities), $value);
    }

    private function roundTo15(\DateTime $date)
    {
        while (($date->format('i') % 15) !== 0) {
            $date->modify('+1 minute');
        }

        return $date;
    }

    private function toChoices(array $availabilities)
    {
        return array_map(function (string $date) {

            $tsRange = DateUtils::dateTimeToTsRange(new \DateTime($date), $this->rounding);

            return new TsRangeChoice(
                $tsRange
            );
        }, $availabilities);
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
