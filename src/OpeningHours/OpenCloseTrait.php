<?php

namespace AppBundle\OpeningHours;

use AppBundle\Utils\TimeRange;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;

trait OpenCloseTrait
{
    private $nextOpeningDateCache = [];
    private $timeRanges = [];
    private $hasFutureClosingRulesCache = [];

    public function hasClosingRuleFor(\DateTime $date = null, \DateTime $now = null): bool
    {
        $date = $date ?? Carbon::now();
        $now = $now ?? Carbon::now();

        $closingRules = $this->getClosingRules();

        if (count($closingRules) === 0) {
            return false;
        }

        // Optimisation
        // When we look for a date in the future,
        // It's useless to loop over "past" closing rules
        if ($date >= $now && !$this->hasFutureClosingRules($closingRules, $now)) {

            return false;
        }

        // WARNING
        // This method may be called a *lot* of times
        // Thus, we avoid using Criteria, because it would trigger a query every time
        foreach ($closingRules as $closingRule) {
            if ($date >= $closingRule->getStartDate() && $date <= $closingRule->getEndDate()) {
                return true;
            }
        }

        return false;
    }

    private function computeTimeRanges(array $openingHours)
    {
        if (count($openingHours) === 0) {
            $this->timeRanges = [];
            return;
        }

        foreach ($openingHours as $openingHour) {
            $this->timeRanges[] = TimeRange::create($openingHour);
        }
    }

    private function getTimeRanges()
    {
        $openingHours = $this->getOpeningHours();
        if (count($openingHours) !== count($this->timeRanges)) {
            $this->computeTimeRanges($openingHours);
        }

        return $this->timeRanges;
    }

    private function _getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $dates = [];

        foreach ($this->getTimeRanges() as $timeRange) {
            $dates[] = $timeRange->getNextOpeningDate($now);
        }

        sort($dates);

        return array_shift($dates);
    }

    private function _getNextClosingDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $dates = [];

        foreach ($this->getTimeRanges() as $timeRange) {
            $dates[] = $timeRange->getNextClosingDate($now);
        }

        sort($dates);

        return array_shift($dates);
    }

    public function isOpen(\DateTime $now = null): bool
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if ($this->hasClosingRuleFor($now)) {

            return false;
        }

        foreach ($this->getTimeRanges() as $timeRange) {
            if ($timeRange->isOpen($now)) {

                return true;
            }
        }

        return false;
    }

    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if (!isset($this->nextOpeningDateCache[$now->getTimestamp()])) {

            $nextOpeningDate = null;

            if ($this->hasClosingRuleFor($now)) {
                foreach ($this->getClosingRules() as $closingRule) {
                    if ($now >= $closingRule->getStartDate() && $now <= $closingRule->getEndDate()) {

                        $nextOpeningDate = $this->_getNextOpeningDate($closingRule->getEndDate());
                        break;
                    }
                }
            }

            if (null === $nextOpeningDate) {
                $nextOpeningDate = $this->_getNextOpeningDate($now);
            }

            $this->nextOpeningDateCache[$now->getTimestamp()] = $nextOpeningDate;
        }

        return $this->nextOpeningDateCache[$now->getTimestamp()];
    }

    public function getNextClosingDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $nextClosingDates = [];
        if ($nextClosingDate = $this->_getNextClosingDate($now)) {
            $nextClosingDates[] = $nextClosingDate;
        }

        foreach ($this->getClosingRules() as $closingRule) {
            if ($closingRule->getEndDate() < $now) {
                continue;
            }
            $nextClosingDates[] = $closingRule->getStartDate();
        }

        $nextClosingDates = array_filter($nextClosingDates, function (\DateTime $date) use ($now) {
            return $date >= $now;
        });

        sort($nextClosingDates);

        return array_shift($nextClosingDates);
    }

    private function hasFutureClosingRules(Collection $closingRules, \DateTime $now)
    {
        $cacheKey = sprintf('%s.%s', spl_object_hash($closingRules), $now->format('YmdHi'));

        if (!isset($this->hasFutureClosingRulesCache[$cacheKey])) {

            $futureClosingRules = $closingRules->filter(function ($closingRule) use ($now) {

                if ($closingRule->getEndDate() <= $now) {
                    return false;
                }

                return true;
            });

            $this->hasFutureClosingRulesCache[$cacheKey] = count($futureClosingRules) > 0;
        }

        return $this->hasFutureClosingRulesCache[$cacheKey];
    }
}
