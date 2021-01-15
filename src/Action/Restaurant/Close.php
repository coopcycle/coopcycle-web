<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\ClosingRule;

class Close
{
    public function __invoke($data)
    {
        $today = new \DateTime('today');
        $today->setTime(00, 00, 00);

        $tomorrow = new \DateTime('tomorrow');
        $tomorrow->setTime(00, 00, 00);

        $closingRule = new ClosingRule();
        $closingRule->setStartDate($today);
        $closingRule->setEndDate($tomorrow);

        $data->addClosingRule($closingRule);

        return $data;
    }
}
