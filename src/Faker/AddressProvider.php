<?php

namespace AppBundle\Faker;

use AppBundle\Service\Geocoder;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;

class AddressProvider extends BaseProvider
{
    protected $geocoder;

    public function __construct(Generator $generator, Geocoder $geocoder)
    {
        parent::__construct($generator);

        $this->geocoder = $geocoder;
    }

    public function randomAddress()
    {
        $latMin = 48.831313;
        $latMax = 48.882699;

        $lngMin = 2.290198;
        $lngMax = 2.398345;

        for ($i = 0; $i < 10; $i++) {

            $latitude = $this->generator->latitude($latMin, $latMax);
            $longitude = $this->generator->longitude($lngMin, $lngMax);

            return $this->geocoder->reverse($latitude, $longitude);
        }

        throw new \Exception('Could not generate an address');
    }
}
