<?php

namespace AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use Geocoder\Geocoder as GeocoderInterface;
use Geocoder\Location;
use Geocoder\Provider\Addok\Addok as AddokProvider;
use Geocoder\Provider\Chain\Chain as ChainProvider;
use Geocoder\Provider\OpenCage\OpenCage as OpenCageProvider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Http\Adapter\Guzzle6\Client;
use PredictHQ\AddressFormatter\Formatter as AddressFormatter;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;
use Spatie\GuzzleRateLimiterMiddleware\Store as RateLimiterStore;

class Geocoder
{
    private $rateLimiterStore;
    private $settingsManager;
    private $openCageApiKey;
    private $country;
    private $locale;
    private $rateLimitPerSecond;

    private $geocoder;
    private $addressFormatter;

    /**
     * FIXME Inject providers through constructor (needs a CompilerPass)
     */
    public function __construct(
        RateLimiterStore $rateLimiterStore,
        SettingsManager $settingsManager,
        string $openCageApiKey,
        string $country,
        string $locale,
        int $rateLimitPerSecond)
    {
        $this->rateLimiterStore = $rateLimiterStore;
        $this->settingsManager = $settingsManager;
        $this->openCageApiKey = $openCageApiKey;
        $this->country = $country;
        $this->locale = $locale;
        $this->rateLimitPerSecond = $rateLimitPerSecond;
    }

    private function getGeocoder()
    {
        if (null === $this->geocoder) {
            $httpClient = new Client();

            $providers = [];

            // For France only, use https://adresse.data.gouv.fr/
            if ('fr' === $this->country) {
                // TODO Create own provider to get results with a high score
                $providers[] = AddokProvider::withBANServer($httpClient);
            }

            // Add OpenCage provider only if api key is configured
            if (!empty($this->openCageApiKey)) {
                $providers[] = $this->createOpenCageProvider();
            }

            $this->geocoder = new StatefulGeocoder(new ChainProvider($providers), $this->locale);
        }

        return $this->geocoder;
    }

    private function createOpenCageProvider()
    {
        // @see https://github.com/geocoder-php/Geocoder/blob/master/docs/cookbook/rate-limiting.md

        $rateLimiter =
            RateLimiterMiddleware::perSecond($this->rateLimitPerSecond, $this->rateLimiterStore);

        $stack = HandlerStack::create();
        $stack->push($rateLimiter);

        $httpClient  = new GuzzleClient(['handler' => $stack, 'timeout' => 30.0]);
        $httpAdapter = new Client($httpClient);

        return new OpenCageProvider($httpAdapter, $this->openCageApiKey);
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
        $query = GeocodeQuery::create($value)
            ->withData('proximity', $this->settingsManager->get('latlng'));

        $results = $this->getGeocoder()->geocodeQuery(
            $query
        );

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
        $results = $this->getGeocoder()->reverse($latitude, $longitude);

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

    private function getAddressFormatter()
    {
        if (null === $this->addressFormatter) {
            $this->addressFormatter = new AddressFormatter();
        }

        return $this->addressFormatter;
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

        $streetAddress = $this->getAddressFormatter()->formatArray($data);

        // Convert address to single line
        $lines = preg_split("/\r\n|\n|\r/", $streetAddress);
        $lines = array_filter($lines);

        return implode(', ', $lines);
    }
}
