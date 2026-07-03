<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\LocalBusiness;

class DeliveryPerimeterExpressionResolver
{
    private const DAYS_OF_WEEK = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    public static function resolve(LocalBusiness $shop, ?\DateTimeInterface $date = null): string
    {
        $date = $date ?? new \DateTimeImmutable();

        $entries = $shop->getDayOfWeekDeliveryPerimeterExpressions();

        if (count($entries) > 0) {
            // ISO weekday: 1=Monday … 7=Sunday → array index 0…6
            $currentDow = self::DAYS_OF_WEEK[(int) $date->format('N') - 1];

            foreach ($entries as $entry) {
                if (in_array($currentDow, explode(',', $entry->getDaysOfWeek()))) {
                    return $entry->getExpression();
                }
            }
        }

        return $shop->getDeliveryPerimeterExpression();
    }
}
