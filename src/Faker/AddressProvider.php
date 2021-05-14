<?php

namespace AppBundle\Faker;

use AppBundle\Service\Geocoder;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;
use League\Geotools\Coordinate\Coordinate;
use AppBundle\Utils\GeoUtils;

class AddressProvider extends BaseProvider
{
    protected $geocoder;
    protected $center;

    /**
     * @param Generator $generator
     * @param Geocoder $geocoder
     * @param Coordinate $center
     * @param int $distance
     */
    public function __construct(Generator $generator, Geocoder $geocoder, Coordinate $center, int $distance = 6)
    {
        parent::__construct($generator);

        $this->geocoder = $geocoder;
        $this->center = $center;
        $this->distance = $distance;
    }

    public function randomAddress()
    {
        $lat = $this->center->getLatitude();
        $lng = $this->center->getLongitude();

        $viewbox = GeoUtils::getViewbox($lat, $lng, $this->distance);

        [ $lngMax, $latMax, $lngMin, $latMin ] = $viewbox;

        // Retry 10 times to generate an address
        for ($i = 0; $i < 10; $i++) {

            $latitude = $this->generator->latitude($latMin, $latMax);
            $longitude = $this->generator->longitude($lngMin, $lngMax);

            $address = $this->geocoder->reverse($latitude, $longitude);

            if (null !== $address) {

                return $address;
            }
        }

        throw new \Exception('Could not generate an address');
    }
}
