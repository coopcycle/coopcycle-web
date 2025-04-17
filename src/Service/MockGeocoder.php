<?php

namespace AppBundle\Service;

use Geocoder\Geocoder as GeocoderInterface;
use Geocoder\Collection;
use Geocoder\Model\Address;
use Geocoder\Model\AdminLevelCollection;
use Geocoder\Model\Coordinates;
use Geocoder\Model\Bounds;
use Geocoder\Model\AdminLevel;
use Geocoder\Model\Country;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

class MockGeocoder implements GeocoderInterface
{

    public function geocode(string $value): Collection
    {

        if ($value === '24 rue de rivoli paris') {
            $address = new Address(
                $this->getName(),
                new AdminLevelCollection([
                    new AdminLevel(1, 'Île-de-France', 'IDF')
                ]),
                new Coordinates(48.8566, 2.3522),
                new Bounds(48.815573, 2.224199, 48.902144, 2.469920),
                '24',
                'Rue de Rivoli',
                '75001',
                'Paris',
                null,
                new Country('France', 'FR'),
                null // Timezone, if applicable
            );

            return new AddressCollection([$address]);
        } else {
            return new AddressCollection([]);
        }
    }

    public function reverse(float $latitude, float $longitude): Collection
    {
        //FIXME: return only for a specific lat/lon

        $address = new Address(
            $this->getName(),
            new AdminLevelCollection([
                new AdminLevel(1, 'Île-de-France', 'IDF')
            ]),
            new Coordinates(48.8566, 2.3522),
            new Bounds(48.815573, 2.224199, 48.902144, 2.469920),
            '24',
            'Rue de Rivoli',
            '75001',
            'Paris',
            null,
            new Country('France', 'FR'),
            null // Timezone, if applicable
        );

        return new AddressCollection([$address]);
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        return $this->geocode($query->getText());
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        return $this->reverse($query->getCoordinates()->getLatitude(), $query->getCoordinates()->getLongitude());
    }

    public function getName(): string
    {
        return 'mock_geocoder';
    }
}
