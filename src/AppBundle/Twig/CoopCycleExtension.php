<?php

namespace AppBundle\Twig;

use AppBundle\Entity\Address;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CoopCycleExtension extends AbstractExtension
{
    public function getFilters()
    {
        return array(
            new TwigFilter('meters_to_kilometers', array($this, 'metersToKilometers')),
            new TwigFilter('seconds_to_minutes', array($this, 'secondsToMinutes')),
            new TwigFilter('price_format', array(PriceFormatResolver::class, 'priceFormat')),
            new TwigFilter('order_can_transition', array(OrderStateResolver::class, 'orderCanTransitionFilter')),
            new TwigFilter('sylius_resolve_variant', array(SyliusVariantResolver::class, 'resolveVariant')),
            new TwigFilter('latlng', array($this, 'latLng')),
            new TwigFilter('coopcycle_markup', array(MarkupRuntime::class, 'parse')),
            new TwigFilter('floatval', 'floatval'),
            new TwigFilter('sort_options', array($this, 'sortOptions'))
        );
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('coopcycle_setting', array(SettingResolver::class, 'resolveSetting')),
            new TwigFunction('coopcycle_maintenance', array(MaintenanceResolver::class, 'isEnabled')),
            new TwigFunction('coopcycle_banner', array(BannerResolver::class, 'isEnabled')),
            new TwigFunction('coopcycle_banner_message', array(BannerResolver::class, 'getMessage')),
            new TwigFunction('stripe_is_livemode', array(StripeResolver::class, 'isLivemode')),
            new TwigFunction('stripe_can_enable_livemode', array(StripeResolver::class, 'canEnableLivemode')),
            new TwigFunction('stripe_can_enable_testmode', array(StripeResolver::class, 'canEnableTestmode')),
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

    public function sortOptions($options)
    {
        // FIXME Make sure $options is an array of ProductOptionInterface
        if ($options instanceof Collection) {

            $iterator = $options->getIterator();

            // Mandatory options first, then sort by priority
            $iterator->uasort(function (ProductOptionInterface $a, ProductOptionInterface $b) {
                if ($a->isAdditional() === $b->isAdditional()) {

                    return $a->getPosition() - $b->getPosition();
                }

                return $a->isAdditional() - $b->isAdditional();
            });

            return new ArrayCollection(iterator_to_array($iterator));
        }

        return $options;
    }
}
