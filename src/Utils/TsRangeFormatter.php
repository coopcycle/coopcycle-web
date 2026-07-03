<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use Carbon\Carbon;

class TsRangeFormatter
{
    public function __construct(private string $locale) {}

    public function formatShort(TsRange $range): string
    {
        $lower = Carbon::instance($range->getLower())->locale($this->locale);
        $rangeAsText = implode(' - ', [
            $lower->isoFormat('LT'),
            Carbon::instance($range->getUpper())->locale($this->locale)->isoFormat('LT'),
        ]);

        return sprintf('%s %s', $lower->isoFormat('L'), $rangeAsText);
    }
}
