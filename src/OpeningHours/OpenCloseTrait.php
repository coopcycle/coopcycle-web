<?php

namespace AppBundle\OpeningHours;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\Exceptions\MaximumLimitExceeded;

trait OpenCloseTrait
{
    private $hasFutureClosingRulesCache = [];
    private $spatieOpeningHoursCache = [];
    private $isOpenCache = [];
    private $nextOpeningDateCache = [];
    private $nextClosingDateCache = [];

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

    public function matchClosingRuleFor(\DateTime $date = null, \DateTime $now = null): ?ClosingRule
    {
        $date = $date ?? Carbon::now();
        $now = $now ?? Carbon::now();

        $closingRules = $this->getClosingRules();

        if (count($closingRules) === 0) {
            return null;
        }

        // Optimisation
        // When we look for a date in the future,
        // It's useless to loop over "past" closing rules
        if ($date >= $now && !$this->hasFutureClosingRules($closingRules, $now)) {

            return null;
        }

        // WARNING
        // This method may be called a *lot* of times
        // Thus, we avoid using Criteria, because it would trigger a query every time
        foreach ($closingRules as $closingRule) {
            if ($date >= $closingRule->getStartDate() && $date <= $closingRule->getEndDate()) {
                return $closingRule;
            }
        }

        return null;
    }

    public function isOpen(\DateTime $now = null): bool
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if (!isset($this->isOpenCache[$now->format(\DateTime::ATOM)])) {
            $openingHours = $this->getSpatieOpeningHours($this->getOpeningHours(), $this->getClosingRules());

            $this->isOpenCache[$now->format(\DateTime::ATOM)] = $openingHours->isOpenAt($now);
        }

        return $this->isOpenCache[$now->format(\DateTime::ATOM)];
    }

    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        try {
            if (!isset($this->nextOpeningDateCache[$now->format(\DateTime::ATOM)])) {
                $openingHours = $this->getSpatieOpeningHours($this->getOpeningHours(), $this->getClosingRules());

                $this->nextOpeningDateCache[$now->format(\DateTime::ATOM)] = $openingHours->nextOpen($now);
            }

            return $this->nextOpeningDateCache[$now->format(\DateTime::ATOM)];
        } catch (MaximumLimitExceeded $e) {
            return null;
        }
    }

    public function getNextClosingDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if (!isset($this->nextClosingDateCache[$now->format(\DateTime::ATOM)])) {
            $openingHours = $this->getSpatieOpeningHours($this->getOpeningHours(), $this->getClosingRules());

            $this->nextClosingDateCache[$now->format(\DateTime::ATOM)] = $openingHours->nextClose($now);
        }

        return $this->nextClosingDateCache[$now->format(\DateTime::ATOM)];
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

    private function getSpatieOpeningHours(array $openingHours, Collection $closingRules)
    {
        return SpatieOpeningHoursRegistry::get($openingHours, $closingRules);
    }
}
