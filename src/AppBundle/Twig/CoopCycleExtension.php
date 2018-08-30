<?php

namespace AppBundle\Twig;

use AppBundle\Entity\Address;

class CoopCycleExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('meters_to_kilometers', array($this, 'metersToKilometers')),
            new \Twig_SimpleFilter('seconds_to_minutes', array($this, 'secondsToMinutes')),
            new \Twig_SimpleFilter('price_format', array(PriceFormatResolver::class, 'priceFormat')),
            new \Twig_SimpleFilter('order_can_transition', array(OrderStateResolver::class, 'orderCanTransitionFilter')),
            new \Twig_SimpleFilter('sylius_resolve_variant', array(SyliusVariantResolver::class, 'resolveVariant')),
            new \Twig_SimpleFilter('latlng', array($this, 'latLng'))
        );
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('coopcycle_setting', array(SettingResolver::class, 'resolveSetting')),
        );
    }

    public function metersToKilometers($meters)
    {
        return sprintf('%s km', number_format($meters / 1000, 2));
    }

    public function secondsToMinutes($seconds)
    {
        return sprintf('%d min', ceil($seconds / 60));
    }

    public function latLng($address)
    {
        if ($address instanceof Address) {
            return [
                $address->getGeo()->getLatitude(),
                $address->getGeo()->getLongitude(),
            ];
        }
    }
}
