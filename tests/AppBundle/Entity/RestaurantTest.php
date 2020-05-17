<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Restaurant;
use PHPUnit\Framework\TestCase;

class RestaurantTest extends TestCase
{
    public function testGetSetOpeningHours()
    {
        $restaurant = new Restaurant();
        $restaurant->setOpeningHours(["Mo-Su 00:00-23:59"]);
        $restaurant->setOpeningHours(["Mo-Su 12:00-14:00"], 'collection');

        $this->assertEquals(["Mo-Su 00:00-23:59"], $restaurant->getFulfillmentMethod('delivery')->getOpeningHours());
        $this->assertEquals(["Mo-Su 12:00-14:00"], $restaurant->getFulfillmentMethod('collection')->getOpeningHours());

        $this->assertEquals(["Mo-Su 00:00-23:59"], $restaurant->getOpeningHours());
        $this->assertEquals(["Mo-Su 00:00-23:59"], $restaurant->getOpeningHours('delivery'));
        $this->assertEquals(["Mo-Su 12:00-14:00"], $restaurant->getOpeningHours('collection'));
    }
}
