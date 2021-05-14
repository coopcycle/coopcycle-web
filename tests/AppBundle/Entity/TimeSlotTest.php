<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness\ShippingOptionsInterface;
use AppBundle\Entity\TimeSlot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class TimeSlotTest extends TestCase
{
    use ProphecyTrait;

    public function testCreate()
    {
        $shippingOptions = $this->prophesize(ShippingOptionsInterface::class);
        $fulfillmentMethod = $this->prophesize(FulfillmentMethod::class);

        $shippingOptions->getShippingOptionsDays()->willReturn(2);

        $fulfillmentMethod->getOrderingDelayMinutes()->willReturn(120);
        $fulfillmentMethod->getOpeningHours()->willReturn(['Mo-Sa 10:00-19:00']);

        $timeSlot = TimeSlot::create(
            $fulfillmentMethod->reveal(),
            $shippingOptions->reveal()
        );

        $this->assertEquals(['Mo-Sa 10:00-19:00'], $timeSlot->getOpeningHours());
        $this->assertEquals('2 hours', $timeSlot->getPriorNotice());
        $this->assertEquals('2 days', $timeSlot->getInterval());
    }
}
