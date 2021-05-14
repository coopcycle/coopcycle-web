<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Task;
use AppBundle\DataType\TsRange;

class TsRangeChoice
{
    private $range;

    public function __construct(TsRange $range)
    {
        $this->range = $range;
    }

    public function toTsRange(): TsRange
    {
        return $this->range;
    }

    public function __toString()
    {
        return sprintf('%s - %s',
            $this->range->getLower()->format('Y-m-d H:i'),
            $this->range->getUpper()->format('Y-m-d H:i')
        );
    }
}
