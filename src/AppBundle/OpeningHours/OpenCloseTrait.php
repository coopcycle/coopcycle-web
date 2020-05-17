<?php

namespace AppBundle\OpeningHours;

use AppBundle\Utils\TimeRange;
use Carbon\Carbon;

trait OpenCloseTrait
{
    private $nextOpeningDateCache = [];
    private $timeRanges = [];

    /**
     * @param \DateTime|null $now
     * @return boolean
     */
    public function hasClosingRuleForNow(\DateTime $now = null): bool
    {
        $closingRules = $this->getClosingRules();

        if (count($closingRules) === 0) {
            return false;
        }

        if (!$now) {
            $now = Carbon::now();
        }

        // WARNING
        // This method may be called a *lot* of times (see getAvailabilities)
        // Thus, we avoid using Criteria, because it would trigger a query every time
        foreach ($closingRules as $closingRule) {
            if ($now >= $closingRule->getStartDate() && $now <= $closingRule->getEndDate()) {
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
            $this->timeRanges[] = new TimeRange($openingHour);
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

        if ($this->hasClosingRuleForNow($now)) {

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

            if ($this->hasClosingRuleForNow($now)) {
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
}
