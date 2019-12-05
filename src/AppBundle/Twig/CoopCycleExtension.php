<?php

namespace AppBundle\Twig;

use AppBundle\Entity\Address;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Hashids\Hashids;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CoopCycleExtension extends AbstractExtension
{
    private $serializer;
    private $secret;

    public function __construct(SerializerInterface $serializer, string $secret)
    {
        $this->serializer = $serializer;
        $this->secret = $secret;
    }

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
            new TwigFilter('coopcycle_normalize', array($this, 'normalize')),
            new TwigFilter('split_tax_rates', array(TaxRateRuntime::class, 'split')),
            new TwigFilter('split_items_tax_rates', array(TaxRateRuntime::class, 'splitItems')),
            new TwigFilter('tax_rate_name', array(TaxRateRuntime::class, 'name')),
            new TwigFilter('date_calendar', array($this, 'dateCalendar'), ['needs_context' => true]),
            new TwigFilter('hashid', array($this, 'hashid')),
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
            new TwigFunction('coopcycle_logo', array(AppearanceRuntime::class, 'logo')),
            new TwigFunction('coopcycle_company_logo', array(AppearanceRuntime::class, 'companyLogo')),
            new TwigFunction('coopcycle_asset', array(AssetsRuntime::class, 'asset')),
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

    public function normalize($object)
    {
        if ($object instanceof PersistentCollection) {

            $collection = [];

            foreach ($object as $item) {
                if ($item instanceof Address) {
                    $normalized = $this->serializer->normalize($item, 'jsonld', [
                        'resource_class' => Address::class,
                        'operation_type' => 'item',
                        'item_operation_name' => 'get',
                        'groups' => ['address', 'postal_address', 'place']
                    ]);

                    $collection[] = $normalized;
                }
            }

            return $collection;
        }

        return $object;
    }

    public function dateCalendar($context, $date)
    {
        $locale = $context['app']->getRequest()->getLocale();

        $carbon = Carbon::parse($date);

        return strtolower($carbon->locale($locale)->calendar());
    }

    public function hashid(object $object)
    {
        $hashids = new Hashids($this->secret, 8);

        if (is_callable([$object, 'getId'])) {
            $id = $object->getId();

            return $hashids->encode($id);
        }

        throw new \InvalidArgumentException(sprintf('Object of class %s has no method getId()', get_class($object)));
    }
}
