<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\ClosingRule;
use Carbon\Carbon;

class Close
{
    public function __invoke($data)
    {
        $today = Carbon::now();
        $today->setTime(00, 00, 00);

        $tomorrow = $today->copy()->add(1, 'day');

        $closingRule = new ClosingRule();
        $closingRule->setStartDate($today);
        $closingRule->setEndDate($tomorrow);

        $data->addClosingRule($closingRule);

        return $data;
    }
}
