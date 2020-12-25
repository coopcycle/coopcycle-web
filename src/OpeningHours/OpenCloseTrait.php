<?php

namespace AppBundle\OpeningHours;

use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Spatie\OpeningHours\OpeningHours;

trait OpenCloseTrait
{
    private $hasFutureClosingRulesCache = [];
    private $spatieOpeningHoursCache = [];

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

    public function isOpen(\DateTime $now = null): bool
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $openingHours = $this->getSpatieOpeningHours($this->getOpeningHours(), $this->getClosingRules());

        return $openingHours->isOpenAt($now);
    }

    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $openingHours = $this->getSpatieOpeningHours($this->getOpeningHours(), $this->getClosingRules());

        return $openingHours->nextOpen($now);
    }

    public function getNextClosingDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $openingHours = $this->getSpatieOpeningHours($this->getOpeningHours(), $this->getClosingRules());

        return $openingHours->nextClose($now);
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
