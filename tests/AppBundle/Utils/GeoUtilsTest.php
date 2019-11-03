<?php

namespace AppBundle\Utils;

use PHPUnit\Framework\TestCase;
use AppBundle\Utils\GeoUtils;
use AppBundle\Entity\Base\GeoCoordinates;

class GeoUtilsTest extends TestCase
{
    public function testAsGeoCoordinates()
    {
        $text = 'POINT(48.877821 2.3706188)';
        $geo = GeoUtils::asGeoCoordinates($text);

        $this->assertEquals(2.3706188, $geo->getLatitude());
        $this->assertEquals(48.877821, $geo->getLongitude());

        $text = 'POINT(-48.877821 2.3706188)';
        $geo = GeoUtils::asGeoCoordinates($text);

        $this->assertEquals(2.3706188, $geo->getLatitude());
        $this->assertEquals(-48.877821, $geo->getLongitude());

        $text = 'POINT(48.877821 -2.3706188)';
        $geo = GeoUtils::asGeoCoordinates($text);

        $this->assertEquals(-2.3706188, $geo->getLatitude());
        $this->assertEquals(48.877821, $geo->getLongitude());

        $text = 'POINT(-48.877821 -2.3706188)';
        $geo = GeoUtils::asGeoCoordinates($text);

        $this->assertEquals(-2.3706188, $geo->getLatitude());
        $this->assertEquals(-48.877821, $geo->getLongitude());
    }

    public function testAsPoint()
    {
        $geo = new GeoCoordinates(48.877821, 2.3706188);
        $this->assertEquals('POINT(2.3706188 48.877821)', GeoUtils::asPoint($geo));

        $geo = new GeoCoordinates(-48.877821, 2.3706188);
        $this->assertEquals('POINT(2.3706188 -48.877821)', GeoUtils::asPoint($geo));

        $geo = new GeoCoordinates(48.877821, -2.3706188);
        $this->assertEquals('POINT(-2.3706188 48.877821)', GeoUtils::asPoint($geo));

        $geo = new GeoCoordinates(-48.877821, -2.3706188);
        $this->assertEquals('POINT(-2.3706188 -48.877821)', GeoUtils::asPoint($geo));

        $geo = new GeoCoordinates(49.281441, -123.055913);
        $this->assertEquals('POINT(-123.055913 49.281441)', GeoUtils::asPoint($geo));
    }
}
