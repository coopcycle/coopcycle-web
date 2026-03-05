<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;

class AddressResolver
{
    private const DAYS_OF_WEEK = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    public static function resolveAddress(LocalBusiness $shop, \DateTimeInterface $date): ?Address
    {
        $dayOfWeekAddresses = $shop->getDayOfWeekAddresses();

        if (count($dayOfWeekAddresses) > 0) {
            // ISO weekday: 1=Monday … 7=Sunday → array index 0…6
            $currentDow = self::DAYS_OF_WEEK[(int) $date->format('N') - 1];

            foreach ($dayOfWeekAddresses as $dowAddress) {
                if (in_array($currentDow, explode(',', $dowAddress->getDaysOfWeek()))) {
                    return $dowAddress->getAddress();
                }
            }
        }

        return $shop->getAddress();
    }
}
