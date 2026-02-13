<?php

namespace AppBundle\Twig;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Address;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Twig\CacheExtension\KeyGenerator;
use Carbon\Carbon;
use Carbon\Translator as CarbonTranslator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Hashids\Hashids;
use Spatie\OpeningHours\OpeningHoursForDay;
use Spatie\OpeningHours\Time;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class CoopCycleExtension extends AbstractExtension
{
    private $serializer;
    private $iriConverter;
    private $secret;

    public function __construct(SerializerInterface $serializer, IriConverterInterface $iriConverter, string $secret)
    {
        $this->serializer = $serializer;
        $this->iriConverter = $iriConverter;
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
            new TwigFilter('tax_rate_name', array(TaxRateRuntime::class, 'name')),
            new TwigFilter('date_calendar', array($this, 'dateCalendar'), ['needs_context' => true]),
            new TwigFilter('hashid', array($this, 'hashid')),
            new TwigFilter('local_business_type', array(LocalBusinessRuntime::class, 'type')),
            new TwigFilter('local_business_type_key', array(LocalBusinessRuntime::class, 'typeKey')),
            new TwigFilter('time_range_for_humans', array(OrderRuntime::class, 'timeRangeForHumans')),
            new TwigFilter('time_range_for_humans_short', array(OrderRuntime::class, 'timeRangeForHumansShort')),
            new TwigFilter('promotion_rule_for_humans', array(PromotionRuntime::class, 'ruleForHumans')),
            new TwigFilter('promotion_action_for_humans', array(PromotionRuntime::class, 'actionForHumans')),
            new TwigFilter('get_iri_from_item', array($this, 'getIriFromResource')),
            new TwigFilter('oauth2_proxy', array(OAuthRuntime::class, 'modifyUrl')),
            new TwigFilter('restaurant_microdata', array(LocalBusinessRuntime::class, 'seo')),
            new TwigFilter('delay_for_humans', array(LocalBusinessRuntime::class, 'delayForHumans')),
            new TwigFilter('grams_to_kilos', array($this, 'gramsToKilos')),
            new TwigFilter('opening_hours', array($this, 'openingHours')),
            new TwigFilter('day_localized', array($this, 'dayLocalized')),
            new TwigFilter('opening_hours_for_day_matches', array($this, 'openingHoursForDayMatches')),
            new TwigFilter('cache_key', array(KeyGenerator::class, 'generateKey')),
            new TwigFilter('parse_rule_expression', array(ExpressionLanguageRuntime::class, 'parseRuleExpression')),
            new TwigFilter('parse_price', array(ExpressionLanguageRuntime::class, 'parsePrice')),
            new TwigFilter('expand_tags', array(TagsRuntime::class, 'expandTags')),
            new TwigFilter('placeholder_image', array(AssetsRuntime::class, 'placeholderImage')),
            new TwigFilter('recurr_rule', array(RecurrRuleFormatResolver::class, 'format'), ['needs_context' => true]),
            new TwigFilter('humanize_promotion', array(LocalBusinessRuntime::class, 'humanizePromotion')),
            new TwigFilter('is_promotion_not_expired', array(LocalBusinessRuntime::class, 'isPromotionNotExpired')),
            new TwigFilter('json_decode', array($this, 'jsonDecode')),
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
            new TwigFunction('coopcycle_asset_base64', array(AssetsRuntime::class, 'assetBase64')),
            new TwigFunction('local_business_path', array(UrlGeneratorRuntime::class, 'localBusinessPath')),
            new TwigFunction('coopcycle_has_about_us', array(AppearanceRuntime::class, 'hasAboutUs')),
            new TwigFunction('coopcycle_has_banner', array(AssetsRuntime::class, 'hasCustomBanner')),
            new TwigFunction('coopcycle_restaurants_suggestions', array(LocalBusinessRuntime::class, 'restaurantsSuggestions')),
            new TwigFunction('coopcycle_has_ordering_delay', array(OrderRuntime::class, 'hasDelayConfigured')),
            new TwigFunction('coopcycle_bounding_rect', array(SettingResolver::class, 'getBoundingRect')),
            new TwigFunction('coopcycle_checkout_suggestions', array(LocalBusinessRuntime::class, 'getCheckoutSuggestions')),
            new TwigFunction('coopcycle_zone_names', array(LocalBusinessRuntime::class, 'getZoneNames')),
            new TwigFunction('mercadopago_can_enable_livemode', array(MercadopagoResolver::class, 'canEnableLivemode')),
            new TwigFunction('mercadopago_can_enable_testmode', array(MercadopagoResolver::class, 'canEnableTestmode')),
            new TwigFunction('should_show_pre_order', array(LocalBusinessRuntime::class, 'shouldShowPreOrder')),
            new TwigFunction('loopeat_authorization_url', array(LoopeatRuntime::class, 'getAuthorizationUrl')),
            new TwigFunction('loopeat_name', array(LoopeatRuntime::class, 'getName')),
            new TwigFunction('loopeat_returns_fee', array(LoopeatRuntime::class, 'getReturnsFee')),
            new TwigFunction('restaurant_tags', array(LocalBusinessRuntime::class, 'tags')),
            new TwigFunction('restaurant_badges', array(LocalBusinessRuntime::class, 'badges')),
            new TwigFunction('coopcycle_configtest', array(SettingResolver::class, 'configTest')),
            new TwigFunction('coopcycle_lat_lng_bounds', array(SettingResolver::class, 'getLatLngBounds')),
            new TwigFunction('restaurant_menu', array(LocalBusinessRuntime::class, 'resolveMenu')),
            new TwigFunction('order_phone_number', array(LocalBusinessRuntime::class, 'resolvePhoneNumber')),
            new TwigFunction('restaurant_opening_hours', array(LocalBusinessRuntime::class, 'openingHours')),
            new TwigFunction('add_title_prefix', array(PageTitlePrefixResolver::class, 'addTitlePrefix')),
            new TwigFunction('coopcycle_version', array(SettingResolver::class, 'getVersion')),
            new TwigFunction('coopcycle_github_release_link', array(SettingResolver::class, 'getGithubReleaseLink')),
            new TwigFunction('addresses_normalized', array(UserRuntime::class, 'getUserAddresses')),
        );
    }

    public function getTests()
    {
        return [
            new TwigTest('instanceof', [$this, 'isInstanceof'])
        ];
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

    public function normalize($object, $resourceClass = Address::class, $groups = [], $format = 'jsonld')
    {
        if ($resourceClass === Address::class && empty($groups)) {
            $groups = ['address'];
        }

        $context = [];

        if (!empty($groups)) {
           $context['groups'] = $groups;
        }

        if ('jsonld' === $format) {
            $context = array_merge($context, [
                'resource_class' => $resourceClass,
            ]);
        }

        if ($object instanceof Collection) {

            $collection = [];
            foreach ($object as $item) {
                $collection[] =
                    $this->serializer->normalize($item, $format, $context);
            }

            return $collection;
        }

        return $this->serializer->normalize($object, $format, $context);
    }

    public function dateCalendar($context, $date)
    {
        $locale = $context['app']->getRequest()->getLocale();

        $carbon = Carbon::parse($date);

        return strtolower($carbon->locale($locale)->calendar());
    }

    public function hashid(object $object, $minHashLength = 8)
    {
        $hashids = new Hashids($this->secret, $minHashLength ?? 8);

        if (is_callable([$object, 'getId'])) {
            $id = $object->getId();

            return $hashids->encode($id);
        }

        throw new \InvalidArgumentException(sprintf('Object of class %s has no method getId()', get_class($object)));
    }

    public function isInstanceof($var, $class)
    {
        return $var instanceof $class;
    }

    public function getIriFromResource($item)
    {
        return $this->iriConverter->getIriFromResource($item);
    }

    public function gramsToKilos($grams)
    {
        return sprintf('%s kg', number_format($grams / 1000, 2));
    }

    public function openingHours(array $openingHours)
    {
        return SpatieOpeningHoursRegistry::get($openingHours);
    }

    public function dayLocalized(string $day, string $locale)
    {
        return Carbon::instance(
            new \DateTime(ucfirst($day))
        )->locale($locale)->dayName;
    }

    public function openingHoursForDayMatches(OpeningHoursForDay $openingHoursForDay, string $day)
    {
        $now = Carbon::now();

        return $day === strtolower($now->englishDayOfWeek) && $openingHoursForDay->isOpenAt(Time::fromDateTime($now));
    }

    function jsonDecode($json)
    {
        return json_decode($json, true);
    }
}
