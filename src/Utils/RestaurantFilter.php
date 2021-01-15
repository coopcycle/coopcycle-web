<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\RoutingInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Stopwatch\Stopwatch;

class RestaurantFilter
{
    private $routing;
    private $expressionLanguage;

    public function __construct(
        RoutingInterface $routing,
        ExpressionLanguage $expressionLanguage)
    {
        $this->routing = $routing;
        $this->expressionLanguage = $expressionLanguage;
    }

    public function matchingLatLng($restaurants, $latitude, $longitude)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('RestaurantFilter::matchingLatLng');

        if (count($restaurants) === 0) {

            $event = $stopwatch->stop('RestaurantFilter::matchingLatLng');

            return [];
        }

        $hash = new \SplObjectStorage();

        $source = new GeoCoordinates($latitude, $longitude);

        $destinations =
            array_map(fn($restaurant) => $restaurant->getAddress()->getGeo(), $restaurants);

        $matches = [];
        $distances = $this->routing->getDistances($source, ...$destinations);

        foreach ($distances as $i => $distance) {

            $address = new Address();
            $address->setGeo($source);

            $restaurant = $restaurants[$i];
            $hash[$restaurant] = $distance;

            if ($restaurant->canDeliverAddress($address, $distance, $this->expressionLanguage)) {
                $matches[] = $restaurant;
            }
        }

        // Sort by distance
        usort($matches, function (LocalBusiness $a, LocalBusiness $b) use ($hash) {
            return $hash[$a] < $hash[$b] ? -1 : 1;
        });

        $event = $stopwatch->stop('RestaurantFilter::matchingLatLng');

        return $matches;
    }
}
