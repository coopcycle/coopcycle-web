<?php

namespace AppBundle\Faker;

use AppBundle\Service\Geocoder;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;

class AddressProvider extends BaseProvider
{
    protected $geocoder;
    protected $center;

    const RADIUS_KM = 6378.1;

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

    /**
     * @see https://gist.github.com/marcus-at-localhost/39a346e7d7f872187124af9cd582f833
     */
    private function getBoundingBoxCoords($latitude, $longitude, $bearing, $distance)
    {
        $radius = self::RADIUS_KM;

        //  New latitude in degrees.
        $new_latitude = rad2deg(asin(sin(deg2rad($latitude)) * cos($distance / $radius) + cos(deg2rad($latitude)) * sin($distance / $radius) * cos(deg2rad($bearing))));

        //  New longitude in degrees.
        $new_longitude = rad2deg(deg2rad($longitude) + atan2(sin(deg2rad($bearing)) * sin($distance / $radius) * cos(deg2rad($latitude)), cos($distance / $radius) - sin(deg2rad($latitude)) * sin(deg2rad($new_latitude))));

        return [
            $new_latitude,
            $new_longitude
        ];
    }

    public function randomAddress()
    {
        $geotools = new Geotools();

        $lat = $this->center->getLatitude();
        $lng = $this->center->getLongitude();

        // We generate a bounding box around the center
        // the adresses will be generated inside this bounding box
        $northEast = new Coordinate($this->getBoundingBoxCoords($lat, $lng, 45,  $this->distance));
        $southEast = new Coordinate($this->getBoundingBoxCoords($lat, $lng, 135, $this->distance));
        $southWest = new Coordinate($this->getBoundingBoxCoords($lat, $lng, 225, $this->distance));
        $northWest = new Coordinate($this->getBoundingBoxCoords($lat, $lng, 315, $this->distance));

        // Long = X, Lat = Y

        $latMin = $northWest->getLatitude();
        $latMax = $southWest->getLatitude();

        $lngMin = $northWest->getLongitude();
        $lngMax = $northEast->getLongitude();

        for ($i = 0; $i < 10; $i++) {

            $latitude = $this->generator->latitude($latMin, $latMax);
            $longitude = $this->generator->longitude($lngMin, $lngMax);

            return $this->geocoder->reverse($latitude, $longitude);
        }

        throw new \Exception('Could not generate an address');
    }
}
