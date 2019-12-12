<?php

namespace AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\SettingsManager;
use Geocoder\Geocoder as GeocoderInterface;
use Geocoder\Location;
use Geocoder\Provider\Addok\Addok as AddokProvider;
use Geocoder\Provider\Chain\Chain as ChainProvider;
use Geocoder\Provider\GoogleMaps\GoogleMaps as GoogleMapsProvider;
use Geocoder\Provider\Nominatim\Nominatim as NominatimProvider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle6\Client;
use PredictHQ\AddressFormatter\Formatter as AddressFormatter;

class Geocoder
{
    private $geocoder;
    private $addressFormatter;

    /**
     * FIXME Inject providers through constructor (needs a CompilerPass)
     */
    public function __construct(SettingsManager $settingsManager, $country, $locale)
    {
        $httpClient = new Client();

        $providers = [];

        // For France only, use https://adresse.data.gouv.fr/
        if ('fr' === $country) {
            // TODO Create own provider to get results with a high score
            $providers[] = AddokProvider::withBANServer($httpClient);
        }

        // Add Google provider only if api key is configured
        $apiKey = $settingsManager->get('google_api_key');
        if (!empty($apiKey)) {
            $region = strtoupper($country);
            $providers[] = new GoogleMapsProvider($httpClient, $region, $apiKey);
        }

        $this->geocoder = new StatefulGeocoder(new ChainProvider($providers), $locale);
        $this->addressFormatter = new AddressFormatter();
    }

    /**
     * Setter injection, used for tests.
     */
    public function setGeocoder(GeocoderInterface $geocoder)
    {
        $this->geocoder = $geocoder;
    }

    /**
     * @return Address|null
     */
    public function geocode($value)
    {
        $results = $this->geocoder->geocode($value);

        if (count($results) > 0) {
            $result = $results->first();

            [ $longitude, $latitude ] = $result->getCoordinates()->toArray();

            $address = new Address();
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
            $address->setStreetAddress($this->formatAddress($result));
            $address->setAddressLocality($result->getLocality());
            $address->setPostalCode($result->getPostalCode());

            return $address;
        }

        return null;
    }

    public function reverse(float $latitude, float $longitude)
    {
        $results = $this->geocoder->reverse($latitude, $longitude);

        if (count($results) > 0) {
            $result = $results->first();

            [ $longitude, $latitude ] = $result->getCoordinates()->toArray();

            $address = new Address();
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
            $address->setStreetAddress($this->formatAddress($result));
            $address->setAddressLocality($result->getLocality());
            $address->setPostalCode($result->getPostalCode());

            return $address;
        }
    }

    private function formatAddress(Location $location)
    {
        $data = [
            'house_number' => $location->getStreetNumber(),
            'road' => $location->getStreetName(),
            'city' => $location->getLocality(),
            'postcode' => $location->getPostalCode(),
        ];

        if (null !== $location->getCountry() && null !== $location->getCountry()->getCode()) {
            $data['country_code'] = $location->getCountry()->getCode();
        }

        $streetAddress = $this->addressFormatter->formatArray($data);

        // Convert address to single line
        $lines = preg_split("/\r\n|\n|\r/", $streetAddress);
        $lines = array_filter($lines);

        return implode(', ', $lines);
    }
}
