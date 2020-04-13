<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use Carbon\Carbon;

class DateUtils
{
    public static function dateTimeToTsRange(\DateTime $dateTime, int $round = 5): TsRange
    {
        $lower = clone $dateTime;
        $upper = clone $dateTime;

        $lower->modify(sprintf('-%d minutes', $round));
        $upper->modify(sprintf('+%d minutes', $round));

        $range = new TsRange();
        $range->setLower($lower);
        $range->setUpper($upper);

        return $range;
    }

    /**
     * @param mixed $date
     * @param \DateTime|null $now
     * @return bool
     */
    public static function isToday($date, \DateTime $now = null): bool
    {
        if (!$date instanceof TsRange && !$date instanceof \DateTime) {
            throw new \InvalidArgumentException(sprintf('$date should be an instance of %s or %s',
                \DateTime::class, TsRange::class
            ));
        }

        if (!$now) {
            $now = Carbon::now();
        }

        if (!$date instanceof \DateTime) {
            $date = Carbon::instance($date->getLower())->average($date->getUpper());
        }

        return $date->format('Y-m-d') === $now->format('Y-m-d');
    }
}
