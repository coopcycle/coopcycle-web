<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\LocalBusiness;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class AsapChoiceLoader implements ChoiceLoaderInterface, OpenCloseInterface
{
    use OpenCloseTrait;

    private $openingHours;
    private $closingRules;
    private $numberOfDays;

    public function __construct(
        array $openingHours,
        Collection $closingRules = null,
        ?int $numberOfDays = null,
        int $orderingDelayMinutes = 0,
        \DateTime $now = null)
    {
        $this->openingHours = $openingHours;
        $this->closingRules = $closingRules ?? new ArrayCollection();
        $this->orderingDelayMinutes = $orderingDelayMinutes;

        if (null === $numberOfDays) {
            $numberOfDays = 2;
        }

        if ($numberOfDays > 6) {
            $numberOfDays = 6;
        }

        $this->numberOfDays = $numberOfDays;
        $this->now = $now;
    }

    /**
     * @param int $shippingOptionsDays
     */
    public function setShippingOptionsDays(int $shippingOptionsDays)
    {
        $this->numberOfDays = $shippingOptionsDays;
    }

    public function getClosingRules()
    {
        return $this->closingRules;
    }

    public function getOpeningHours($method = 'delivery')
    {
        return $this->openingHours;
    }

    public function getOrderingDelayMinutes()
    {
        return $this->orderingDelayMinutes;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        $now = $this->now ?? Carbon::now();

        if ($this->getOrderingDelayMinutes() > 0) {
            $now->modify(sprintf('+%d minutes', $this->getOrderingDelayMinutes()));
        }

        $nextOpeningDate = $this->getNextOpeningDate($now);

        if (is_null($nextOpeningDate)) {

            return new ArrayChoiceList([], $value);
        }

        $availabilities = [];

        $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);

        if (!$nextClosingDate) { // It is open 24/7
            $nextClosingDate = Carbon::instance($now)->add($this->numberOfDays, 'days');

            $period = CarbonPeriod::create(
                $nextOpeningDate, '15 minutes', $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );
            foreach ($period as $date) {
                $availabilities[] = $date->format(\DateTime::ATOM);
            }

            return new ArrayChoiceList($availabilities, $value);
        }

        $numberOfDays = 0;
        $days = [];

        while ($numberOfDays < $this->numberOfDays) {

            $periods = $this->getPeriods($nextOpeningDate, $nextClosingDate);

            foreach ($periods as $period) {
                foreach ($period as $date) {
                    $availabilities[] = $date->format(\DateTime::ATOM);
                    $days[] = $date->format('Y-m-d');
                }
            }

            $numberOfDays = count(array_unique($days));

            $nextOpeningDate = $this->getNextOpeningDate($nextClosingDate);
            $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);
        }

        $availabilities = array_values(array_unique($availabilities));

        return new ArrayChoiceList($availabilities, $value);
    }

    /**
     * @param \DateTime $nextOpeningDate
     * @param \DateTime $nextClosingDate
     * @return CarbonPeriod[]
     */
    private function getPeriods(\DateTime $nextOpeningDate, \DateTime $nextClosingDate)
    {
        $periods = [];

        do {

            $periods[] = CarbonPeriod::create(
                $nextOpeningDate, '15 minutes', $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );

            $nextOpeningDate = $this->getNextOpeningDate($nextClosingDate);

            if (!Carbon::instance($nextOpeningDate)->isSameDay($nextClosingDate)) {
                break;
            }

            $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);

        } while (Carbon::instance($nextOpeningDate)->isSameDay($nextClosingDate));

        return $periods;
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
