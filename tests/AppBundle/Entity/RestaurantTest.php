<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Restaurant;
use PHPUnit\Framework\TestCase;

class RestaurantTest extends TestCase
{
    public function testGetAvailabilities() {
        $restaurant = new Restaurant();
        $restaurant->setOpeningHours(["Mo-Sa 10:00-19:00"]);

        $date = new \DateTime('2017-10-04T17:30:00+02:00');

        $availabilities = $restaurant->getAvailabilities($date);

        $this->assertEquals([
            "2017-10-04T17:30:00+02:00",
            "2017-10-04T18:00:00+02:00",
            "2017-10-04T18:30:00+02:00",
            "2017-10-04T19:00:00+02:00",
            "2017-10-05T10:00:00+02:00",
            "2017-10-05T10:30:00+02:00",
            "2017-10-05T11:00:00+02:00",
            "2017-10-05T11:30:00+02:00",
            "2017-10-05T12:00:00+02:00",
            "2017-10-05T12:30:00+02:00",
            "2017-10-05T13:00:00+02:00",
            "2017-10-05T13:30:00+02:00",
            "2017-10-05T14:00:00+02:00",
            "2017-10-05T14:30:00+02:00",
            "2017-10-05T15:00:00+02:00",
            "2017-10-05T15:30:00+02:00",
            "2017-10-05T16:00:00+02:00",
            "2017-10-05T16:30:00+02:00",
            "2017-10-05T17:00:00+02:00",
            "2017-10-05T17:30:00+02:00",
            "2017-10-05T18:00:00+02:00",
            "2017-10-05T18:30:00+02:00",
            "2017-10-05T19:00:00+02:00",
        ], $availabilities);

    }
}
