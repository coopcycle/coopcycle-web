<?php

declare(strict_types=1);

namespace Tests\AppBundle\Geocoder\Provider;

use AppBundle\Geocoder\Provider\HouseNumberAwareChain;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class HouseNumberAwareChainTest extends TestCase
{
    use ProphecyTrait;

    // As sent by the transporter, the BAN has no number 115 on this street
    const ADDRESS = '115, BOULEVARD DE LA MILLIERE MARSEILLE 11 13011';

    public function testNextProviderIsAskedWhenTheHouseNumberIsNotFound(): void
    {
        // The addok "street" result: the middle of the street
        $addok = $this->prophesize(Provider::class);
        $addok->getName()->willReturn('addok');
        $addok->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'addok',
                'latitude' => 43.283352,
                'longitude' => 5.498434,
                'streetName' => 'Boulevard de la milliere',
                'postalCode' => '13011',
                'locality' => 'Marseille',
            ]),
        ]));

        $opencage = $this->prophesize(Provider::class);
        $opencage->getName()->willReturn('opencage');
        $opencage->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'opencage',
                'latitude' => 43.2842729,
                'longitude' => 5.4988040,
                'streetNumber' => '115',
                'streetName' => 'Boulevard de la Millière',
                'postalCode' => '13011',
                'locality' => 'Marseille',
            ]),
        ]));

        $chain = new HouseNumberAwareChain([$addok->reveal(), $opencage->reveal()]);

        $result = $chain->geocodeQuery(GeocodeQuery::create(self::ADDRESS))->first();

        $this->assertEquals('opencage', $result->getProvidedBy());
        $this->assertEquals('115', $result->getStreetNumber());
        $this->assertEquals(43.2842729, $result->getCoordinates()->getLatitude());
    }

    public function testFirstStreetResultIsReturnedWhenNoProviderFindsTheHouseNumber(): void
    {
        $addok = $this->prophesize(Provider::class);
        $addok->getName()->willReturn('addok');
        $addok->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'addok',
                'latitude' => 43.283352,
                'longitude' => 5.498434,
                'streetName' => 'Boulevard de la milliere',
                'postalCode' => '13011',
                'locality' => 'Marseille',
            ]),
        ]));

        $opencage = $this->prophesize(Provider::class);
        $opencage->getName()->willReturn('opencage');
        $opencage->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([]));

        $google = $this->prophesize(Provider::class);
        $google->getName()->willReturn('google_maps');
        $google->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'google_maps',
                'latitude' => 43.2833,
                'longitude' => 5.4984,
                'streetName' => 'Boulevard de la Millière',
            ]),
        ]));

        $chain = new HouseNumberAwareChain([$addok->reveal(), $opencage->reveal(), $google->reveal()]);

        $result = $chain->geocodeQuery(GeocodeQuery::create(self::ADDRESS))->first();

        $this->assertEquals('addok', $result->getProvidedBy());
        $this->assertEquals(43.283352, $result->getCoordinates()->getLatitude());
    }

    public function testStreetResultIsReturnedWhenTheQueryHasNoHouseNumber(): void
    {
        $addok = $this->prophesize(Provider::class);
        $addok->getName()->willReturn('addok');
        $addok->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'addok',
                'latitude' => 43.283352,
                'longitude' => 5.498434,
                'streetName' => 'Boulevard de la milliere',
                'postalCode' => '13011',
                'locality' => 'Marseille',
            ]),
        ]));

        $opencage = $this->prophesize(Provider::class);
        $opencage->geocodeQuery(Argument::any())->shouldNotBeCalled();

        $chain = new HouseNumberAwareChain([$addok->reveal(), $opencage->reveal()]);

        $result = $chain->geocodeQuery(GeocodeQuery::create('BOULEVARD DE LA MILLIERE MARSEILLE 11 13011'))->first();

        $this->assertEquals('addok', $result->getProvidedBy());
    }

    public function testNextProviderIsAskedWhenAProviderFails(): void
    {
        $addok = $this->prophesize(Provider::class);
        $addok->getName()->willReturn('addok');
        $addok->geocodeQuery(Argument::type(GeocodeQuery::class))->willThrow(new \RuntimeException('Timeout'));

        $opencage = $this->prophesize(Provider::class);
        $opencage->getName()->willReturn('opencage');
        $opencage->geocodeQuery(Argument::type(GeocodeQuery::class))->willReturn(new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'opencage',
                'latitude' => 43.2842729,
                'longitude' => 5.4988040,
                'streetNumber' => '115',
                'streetName' => 'Boulevard de la Millière',
                'postalCode' => '13011',
                'locality' => 'Marseille',
            ]),
        ]));

        $chain = new HouseNumberAwareChain([$addok->reveal(), $opencage->reveal()]);

        $result = $chain->geocodeQuery(GeocodeQuery::create(self::ADDRESS))->first();

        $this->assertEquals('opencage', $result->getProvidedBy());
    }
}
