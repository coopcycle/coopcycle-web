<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
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

        // Calculate distance for each restaurant
        $hash = new \SplObjectStorage();

        foreach ($restaurants as $restaurant) {
            $distance = $this->routing->getDistance($restaurant->getAddress()->getGeo(), new GeoCoordinates($latitude, $longitude));
            $hash[$restaurant] = $distance;
        }

        $matches = [];

        foreach ($hash as $restaurant) {
            $address = new Address();
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
            if ($restaurant->canDeliverAddress($address, $hash[$restaurant], $this->expressionLanguage)) {
                $matches[] = $restaurant;
            }
        }

        // Sort by distance
        usort($matches, function (Restaurant $a, Restaurant $b) use ($hash) {
            return $hash[$a] < $hash[$b] ? -1 : 1;
        });

        $event = $stopwatch->stop('RestaurantFilter::matchingLatLng');

        return $matches;
    }
}
