<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use Geocoder\Geocoder as GeocoderInterface;
use Geocoder\Model\Address;
use Geocoder\Model\AddressBuilder;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\Coordinates;
use Geocoder\Model\Country;
use Geocoder\Query\GeocodeQuery;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\GuzzleRateLimiterMiddleware\Store;
use Geocoder\Provider\OpenCage\Model\OpenCageAddress;

class GeocoderTest extends TestCase
{
    use ProphecyTrait;

    private $innerGeocoder;

    public function setUp(): void
    {
        $this->innerGeocoder = $this->prophesize(GeocoderInterface::class);
        $this->rateLimiterStore = $this->prophesize(Store::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->settingsManager->get('latlng')->willReturn('48.856613,2.352222');

        $this->geocoder = new Geocoder(
            $this->rateLimiterStore->reveal(),
            $this->settingsManager->reveal(),
            '', 'fr', 'fr', 1
        );
        $this->geocoder->setGeocoder($this->innerGeocoder->reveal());
    }

    private function createLocation($streetNumber, $streetName, $postalCode, $locality, $countryCode, $latitude, $longitude,
        $providedBy, $formattedAddress = null): Address
    {
        $builder = new AddressBuilder($providedBy);

        if ('opencage' === $providedBy) {
            $addressClass = OpenCageAddress::class;
        } else {
            $addressClass = Address::class;
        }

        $builder->setLocality($locality);
        $builder->setStreetNumber((string) $streetNumber);
        $builder->setStreetName($streetName);
        $builder->setPostalCode($postalCode);
        $builder->setCoordinates($latitude, $longitude);
        $builder->setCountryCode($countryCode);

        $address = $builder->build($addressClass);

        if ('opencage' === $providedBy) {
            $address = $address->withFormattedAddress($formattedAddress);
        }

        return $address;
    }

    public function addressProvider()
    {
        return [
            [
                'Karl-Marx-Straße 23, Berlin',
                $this->createLocation(23, 'Karl-Marx-Straße', '12043', 'Berlin', 'DE', 52.485056, 13.428621, 'opencage',
                    'Karl-Marx-Straße 23, 12043 Berlin'),
                'Karl-Marx-Straße 23, 12043 Berlin'
            ],
            [
                'Calle del Gobernador 39, Madrid',
                $this->createLocation(39, 'Calle del Gobernador', '28014', 'Madrid', 'ES', 40.411725, -3.693385, 'opencage',
                    'Calle del Gobernador, 39, 28014 Madrid'),
                'Calle del Gobernador, 39, 28014 Madrid'
            ],
            [
                '11 Rue des Panoyaux, Paris',
                $this->createLocation(11, 'Rue des Panoyaux', '75020', 'Paris', 'FR', 48.867432, 2.385274, 'addok'),
                '11 Rue des Panoyaux, 75020 Paris'
            ],
        ];
    }

    /**
     * @dataProvider addressProvider
     */
    public function testAddressIsFormattedForCountry($text, Address $location, $expected)
    {
        $this->innerGeocoder
            ->geocodeQuery(Argument::that(function (GeocodeQuery $query) use ($text) {
                return $query->getText() === $text;
            }))
            ->willReturn(new AddressCollection([ $location ]));

        $address = $this->geocoder->geocode($text);

        $this->assertEquals($expected, $address->getStreetAddress());
    }
}
