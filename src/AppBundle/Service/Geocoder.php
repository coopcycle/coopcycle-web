<?php

namespace AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\SettingsManager;
use Geocoder\Provider\Addok\Addok as AddokProvider;
use Geocoder\Provider\Chain\Chain as ChainProvider;
use Geocoder\Provider\GoogleMaps\GoogleMaps as GoogleMapsProvider;
use Geocoder\Provider\Nominatim\Nominatim as NominatimProvider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle6\Client;

class Geocoder
{
    private $geocoder;

    public function __construct(SettingsManager $settingsManager, $country, $locale)
    {
        $httpClient = new Client();

        $providers = [];

        // For France only, use https://adresse.data.gouv.fr/
        if ('fr' === $country) {
            $providers[] = AddokProvider::withBANServer($httpClient);
        }

        // Add Google provider only if api key is configured
        $apiKey = $settingsManager->get('google_api_key');
        if (!empty($apiKey)) {
            $region = strtoupper($country);
            $providers[] = new GoogleMapsProvider($httpClient, $region, $apiKey);
        }

        $this->geocoder = new StatefulGeocoder(new ChainProvider($providers), $locale);
    }

    /**
     * @return Address
     */
    public function geocode($value)
    {
        $results = $this->geocoder->geocode($value);

        if (count($results) > 0) {
            $result = $results->first();

            [ $longitude, $latitude ] = $result->getCoordinates()->toArray();

            $address = new Address();
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
            $address->setStreetAddress(sprintf('%s %s', $result->getStreetNumber(), $result->getStreetName()));
            $address->setAddressLocality($result->getLocality());
            $address->setPostalCode($result->getPostalCode());

            return $address;
        }
    }
}
