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

class MockGeocoderProvider implements GeocoderInterface
{

    public function geocode(string $value): Collection
    {

        // remove all non-alphanumeric characters
        $value = preg_replace('/[^a-zA-Z0-9\s]/', '', $value);
        $value = strtolower($value);

        if (str_contains($value, '24 rue de la paix')) {
            $address = new Address(
                $this->getName(),
                new AdminLevelCollection([
                    new AdminLevel(1, 'Île-de-France', 'IDF')
                ]),
                new Coordinates(48.8566, 2.3522),
                new Bounds(48.815573, 2.224199, 48.902144, 2.469920),
                '24',
                'Rue de la Paix',
                '75001',
                'Paris',
                null,
                new Country('France', 'FR'),
                null // Timezone, if applicable
            );

            return new AddressCollection([$address]);
        } else if (str_contains($value, '44 rue de rivoli')) {
            $address = new Address(
                $this->getName(),
                new AdminLevelCollection([
                    new AdminLevel(1, 'Île-de-France', 'IDF')
                ]),
                new Coordinates(48.8566, 2.3522),
                new Bounds(48.815573, 2.224199, 48.902144, 2.469920),
                '44',
                'Rue de Rivoli',
                '75001',
                'Paris',
                null,
                new Country('France', 'FR'),
                null // Timezone, if applicable
            );

            return new AddressCollection([$address]);
        } else if (str_contains($value, '48 rue de rivoli')) {
            $address = new Address(
                $this->getName(),
                new AdminLevelCollection([
                    new AdminLevel(1, 'Île-de-France', 'IDF')
                ]),
                new Coordinates(48.8566, 2.3522),
                new Bounds(48.815573, 2.224199, 48.902144, 2.469920),
                '48',
                'Rue de Rivoli',
                '75001',
                'Paris',
                null,
                new Country('France', 'FR'),
                null // Timezone, if applicable
            );

            return new AddressCollection([$address]);
        } else if (str_contains($value, '64 rue alexandre dumas')) {
            $address = new Address(
                $this->getName(),
                new AdminLevelCollection([
                    new AdminLevel(1, 'Île-de-France', 'IDF')
                ]),
                new Coordinates(48.8566, 2.3522),
                new Bounds(48.815573, 2.224199, 48.902144, 2.469920),
                '64',
                'Rue Alexandre Dumas',
                '75001',
                'Paris',
                null,
                new Country('France', 'FR'),
                null // Timezone, if applicable
            );

            return new AddressCollection([$address]);
        }  {
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
