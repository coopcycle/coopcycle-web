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

        $endOfTheDay = $today->copy()->setTime(23, 59, 59);

        $closingRule = new ClosingRule();
        $closingRule->setStartDate($today);
        $closingRule->setEndDate($endOfTheDay);

        $data->addClosingRule($closingRule);

        return $data;
    }
}
