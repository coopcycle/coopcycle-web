<?php

namespace Tests\AppBundle\Service\Routing;

use AppBundle\Service\Routing\Fallback;
use AppBundle\Entity\Base\GeoCoordinates;
use PHPUnit\Framework\TestCase;

class FallbackTest extends TestCase
{
    public function testGetDistance()
    {
        $fallback = new Fallback();

        $distance = $fallback->getDistance(
            new GeoCoordinates(48.895452, 2.362388),
            new GeoCoordinates(48.861305, 2.374576),
            new GeoCoordinates(48.857624, 2.384607)
        );

        $this->assertEquals(4745, $distance);
    }

    public function testGetDuration()
    {
        $fallback = new Fallback();

        // distance = 3904 m = 3,904 km =
        $duration = $fallback->getDuration(
            new GeoCoordinates(48.895452, 2.362388),
            new GeoCoordinates(48.861305, 2.374576)
        );

        $this->assertEquals(906, $duration);
    }
}
