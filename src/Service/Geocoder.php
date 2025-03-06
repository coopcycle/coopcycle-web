<?php

namespace AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Utils\GeoUtils;
use Geocoder\Geocoder as GeocoderInterface;
use Geocoder\Location;
use Geocoder\Model\Bounds;
use Geocoder\Provider\Addok\Addok as AddokProvider;
use Geocoder\Provider\Chain\Chain as ChainProvider;
use Geocoder\Provider\GeocodeEarth\GeocodeEarth as GeocodeEarthProvider;
use Geocoder\Provider\GoogleMaps\GoogleMaps as GoogleMapsProvider;
use Geocoder\Provider\OpenCage\OpenCage as OpenCageProvider;
use Geocoder\Provider\OpenCage\Model\OpenCageAddress;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Http\Adapter\Guzzle7\Client;
use Http\Client\Exception\NetworkException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;
use Spatie\GuzzleRateLimiterMiddleware\Store as RateLimiterStore;
use Webmozart\Assert\Assert;

class Geocoder
{
    private ?GeocoderInterface $geocoder = null;

    /**
     * FIXME Inject providers through constructor (needs a CompilerPass)
     */
    public function __construct(
        private readonly RateLimiterStore $rateLimiterStore,
        private readonly SettingsManager $settingsManager,
        private readonly string $openCageApiKey,
        private readonly string $geocodeEarthApiKey,
        private readonly string $country,
        private readonly string $locale,
        private readonly int $rateLimitPerSecond,
        private readonly bool $autoconfigure = true,
        private readonly LoggerInterface $logger = new NullLogger())
    {
    }

    private function getGeocoder()
    {
        if (null === $this->geocoder) {
            $providers = [];

            if ($this->autoconfigure) {
                // For France only, use https://adresse.data.gouv.fr/
                if ('fr' === $this->country) {
                    // TODO Create own provider to get results with a high score
                    $providers[] = $this->createAddokProvider();
                }
            }

            $geocodingProvider = $this->settingsManager->get('geocoding_provider');
            $geocodingProvider = $geocodingProvider ?? 'opencage';
            
            // Add OpenCage provider only if api key is configured
            if ('opencage' === $geocodingProvider && !empty($this->openCageApiKey)) {
                $providers[] = $this->createOpenCageProvider();
            } elseif ('google' === $geocodingProvider) {
                $providers[] = $this->createGoogleMapsProvider();
            } else if (!empty($this->geocodeEarthApiKey)) { // cannot be set in the settings from the UI as provider, so we just need to check for key
                $providers[] = $this->createGeocodeEarthProvider();
            }

            $provider = new ChainProvider($providers);
            $provider->setLogger($this->logger);
            $this->geocoder = new StatefulGeocoder($provider, $this->locale);
        }

        return $this->geocoder;
    }

    private function createGeocodeEarthProvider() {
        $rateLimiter = RateLimiterMiddleware::perSecond($this->rateLimitPerSecond, $this->rateLimiterStore);

        $stack = HandlerStack::create();
        $stack->push($rateLimiter);

        $httpClient  = new GuzzleClient(['handler' => $stack, 'timeout' => 30.0]);
        $httpAdapter = new Client($httpClient);

        return new GeocodeEarthProvider($httpAdapter, $this->geocodeEarthApiKey);
    }

    private function createAddokProvider() {

        $rateLimiter =
            RateLimiterMiddleware::perSecond($this->rateLimitPerSecond, $this->rateLimiterStore);
        
        $decider = function ($retries, $request, $response, $exception) {
            // Limit the number
            if ($retries >= 10) {
                return false;
            }
            
            // Retry on network exceptions
            if ($exception instanceof NetworkException) {
                return true;
            }
    
            return false;
        };
        $retryMiddleware = Middleware::retry($decider);

        $stack = HandlerStack::create();
        $stack->push($rateLimiter);
        $stack->push($retryMiddleware);

        $httpClient  = new GuzzleClient(['handler' => $stack, 'timeout' => 30.0]);
        $httpAdapter = new Client($httpClient);

        return new AddokProvider($httpAdapter, 'https://data.geopf.fr/geocodage');
    }

    private function createGoogleMapsProvider()
    {
        $region = strtoupper($this->country);

        return new GoogleMapsProvider(new Client(), $region, $this->settingsManager->get('google_api_key_custom'));
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

    public function geocode($value, $address = null): ?Address
    {
        $query = GeocodeQuery::create($value);

        $latlng = $this->settingsManager->get('latlng');
        if ($latlng) {
            // The value of the bounds parameter should be specified as two coordinate points
            // forming the south-west and north-east corners of a bounding box (min lon, min lat, max lon, max lat).
            // @see https://opencagedata.com/api#forward-opt
            // @see https://opencagedata.com/bounds-finder
            [ $latitude, $longitude ] = explode(',', $latlng);
            $viewbox = GeoUtils::getViewbox(floatval($latitude), floatval($longitude), 50);
            [ $lngMax, $latMax, $lngMin, $latMin ] = $viewbox;
            $bounds = new Bounds($latMax, $lngMin, $latMin, $lngMax);

            $query = $query
                ->withData('proximity', $latlng)
                ->withBounds($bounds)
                ->withLocale($this->locale);
        }

        $results = [];
        try {
            $results = $this->getGeocoder()->geocodeQuery(
                $query
            );
        } catch(\Exception $e) {
            $this->logger->error(sprintf('Geocoder: %s', $e->getMessage()));
        }

        if (count($results) > 0) {
            $result = $results->first();

            [ $longitude, $latitude ] = $result->getCoordinates()->toArray();

            if (!$address) {
                $address = new Address();
            }
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
            $address->setStreetAddress($this->formatAddress($result));
            $address->setAddressLocality($result->getLocality());
            $address->setPostalCode($result->getPostalCode());

            return $address;
        }

        $this->logger->warning(sprintf('Geocoder: No results for "%s"', $query));
        return null;
    }

    public function reverse(float $latitude, float $longitude)
    {
        $query = ReverseQuery::fromCoordinates($latitude, $longitude);

        $results = $this->getGeocoder()->reverseQuery(
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
    }

    private function formatAddress(Location $location)
    {
        switch ($location->getProvidedBy()) {
            case 'addok':
                // If it's addok, we use French formatting
                return sprintf('%s %s, %s %s',
                    $location->getStreetNumber(), $location->getStreetName(), $location->getPostalCode(), $location->getLocality());

            case 'opencage':
                Assert::isInstanceOf($location, OpenCageAddress::class);

                return $location->getFormattedAddress();
        }

        return sprintf('%s %s, %s %s',
            $location->getStreetName(), $location->getStreetNumber(), $location->getPostalCode(), $location->getLocality());
    }
}
