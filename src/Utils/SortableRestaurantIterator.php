<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use Carbon\Carbon;

class SortableRestaurantIterator extends \ArrayIterator
{
    public function __construct($array = [])
    {
        // 0 - featured & opened restaurants
        // 1 - opened restaurants
        // 2 - closed restaurants
        // 3 - disabled restaurants

        $now = Carbon::now();

        $nextOpeningComparator = function (LocalBusiness $a, LocalBusiness $b) use ($now) {

            $aNextOpening = $a->getNextOpeningDate($now);
            $bNextOpening = $b->getNextOpeningDate($now);

            $compareNextOpening = $aNextOpening === $bNextOpening ?
                0 : ($aNextOpening < $bNextOpening ? -1 : 1);

            return $compareNextOpening;
        };

        usort($array, $nextOpeningComparator);

        $opened = array_filter($array, function (LocalBusiness $lb) use ($now) {
            return $lb->isOpen($now);
        });

        $featuredComparator = function (LocalBusiness $a, LocalBusiness $b) {
            if ($a->isFeatured() && $b->isFeatured()) {
                return 0;
            }

            return $a->isFeatured() ? -1 : 1;
        };

        usort($opened, $featuredComparator);

        $closed = array_filter($array, function (LocalBusiness $lb) use ($now) {
            return !$lb->isOpen($now);
        });

        parent::__construct(array_merge($opened, $closed));
    }
}
