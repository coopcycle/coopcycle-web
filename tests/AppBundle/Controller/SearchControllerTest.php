<?php

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use AppBundle\Controller\SearchController;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\Geocoder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;

class SearchControllerTest extends TestCase
{
    use ProphecyTrait;

    public function blankAddressProvider(): array
    {
        return [
            [ [] ],
            [ ['address' => ''] ],
            [ ['address' => '   '] ],
        ];
    }

    /**
     * @dataProvider blankAddressProvider
     */
    public function testGeocodeWithBlankAddressReturnsBadRequest(array $query): void
    {
        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->geocode(Argument::any())->shouldNotBeCalled();

        $controller = new SearchController();
        $response = $controller->geocodeAction(new Request($query), $geocoder->reveal());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGeocode(): void
    {
        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.867432, 2.385274));

        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->geocode('11 Rue des Panoyaux, Paris')->willReturn($address);

        $controller = new SearchController();
        $response = $controller->geocodeAction(
            new Request(['address' => '11 Rue des Panoyaux, Paris']),
            $geocoder->reveal()
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            ['latitude' => 48.867432, 'longitude' => 2.385274],
            json_decode($response->getContent(), true)
        );
    }

    public function invalidCoordinatesProvider(): array
    {
        return [
            [ [] ],
            [ ['lat' => '', 'lng' => ''] ],
            [ ['lat' => '48.867432'] ],
            [ ['lat' => '48.867432', 'lng' => ''] ],
            [ ['lat' => 'foo', 'lng' => '2.385274'] ],
            [ ['lat' => '48.867432', 'lng' => 'undefined'] ],
        ];
    }

    /**
     * @dataProvider invalidCoordinatesProvider
     */
    public function testReverseWithInvalidCoordinatesReturnsBadRequest(array $query): void
    {
        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->reverse(Argument::any(), Argument::any())->shouldNotBeCalled();

        $controller = new SearchController();
        $response = $controller->reverseAction(new Request($query), $geocoder->reveal());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testReverse(): void
    {
        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.867432, 2.385274));
        $address->setStreetAddress('11 Rue des Panoyaux, 75020 Paris');
        $address->setAddressLocality('Paris');
        $address->setPostalCode('75020');

        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->reverse(48.867432, 2.385274)->willReturn($address);

        $controller = new SearchController();
        $response = $controller->reverseAction(
            new Request(['lat' => '48.867432', 'lng' => '2.385274']),
            $geocoder->reveal()
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [
                'address' => '11 Rue des Panoyaux, 75020 Paris',
                'locality' => 'Paris',
                'postalCode' => '75020',
            ],
            json_decode($response->getContent(), true)
        );
    }
}
