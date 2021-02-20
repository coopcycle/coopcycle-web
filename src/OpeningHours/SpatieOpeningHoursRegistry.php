<?php

namespace AppBundle\OpeningHours;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\Exceptions\OverlappingTimeRanges;

class SpatieOpeningHoursRegistry
{
    private static $instances = [];

    public static function get(array $openingHours, Collection $closingRules = null): OpeningHours
    {
        $closingRules = $closingRules ?? new ArrayCollection();

        $cacheKey = implode('|', $openingHours);

        if (count($closingRules) > 0) {
            $keys = [];
            foreach ($closingRules as $closingRule) {
                $keys[] = $closingRule->getStartDate()->format('Y-m-d H:i').'-'.$closingRule->getEndDate()->format('Y-m-d H:i');
            }
            $cacheKey .= '+'.implode('|', $keys);
        }

        if (!isset(self::$instances[$cacheKey])) {

            $data = SchemaDotOrgParser::parseCollection($openingHours);

            $data['overflow'] = true;
            $data['exceptions'] = SchemaDotOrgParser::parseExceptions($closingRules, $data);

            try {
                self::$instances[$cacheKey] = OpeningHours::create($data);
            } catch (OverlappingTimeRanges $e) {
                self::$instances[$cacheKey] = OpeningHours::createAndMergeOverlappingRanges($data);
            }
        }

        return self::$instances[$cacheKey];
    }
}
