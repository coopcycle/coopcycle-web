<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Utils\OpeningHoursSpecification;
use AppBundle\Utils\PriceFormatter;
use Cocur\Slugify\SlugifyInterface;
use Liip\ImagineBundle\Service\FilterService;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class RestaurantNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $urlGenerator;
    private $requestStack;
    private $uploaderHelper;
    private $currencyContext;
    private $priceFormatter;
    private $slugify;
    private $locale;

    public function __construct(
        ItemNormalizer $normalizer,
        UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
        UploaderHelper $uploaderHelper,
        CurrencyContextInterface $currencyContext,
        PriceFormatter $priceFormatter,
        SlugifyInterface $slugify,
        FilterService $imagineFilter,
        string $locale)
    {
        $this->normalizer = $normalizer;
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
        $this->uploaderHelper = $uploaderHelper;
        $this->currencyContext = $currencyContext;
        $this->priceFormatter = $priceFormatter;
        $this->slugify = $slugify;
        $this->imagineFilter = $imagineFilter;
        $this->locale = $locale;
    }

    private function containsGroups(array $context = [], array $groups)
    {
        if (!isset($context['groups'])) {

            return false;
        }

        foreach ($groups as $group) {
            if (in_array($group, $context['groups'])) {
                return true;
            }
        }

        return false;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $data['@type'] = $object->getType();

        if (isset($data['closingRules'])) {
            $data['specialOpeningHoursSpecification'] = $data['closingRules'];
            unset($data['closingRules']);
        }

        // Stop now if this is for SEO
        // FIXME Stop checking groups manually
        if (isset($context['groups']) && in_array('restaurant_seo', $context['groups'])) {

            return $this->normalizeForSeo($object, $data);
        }

        if (isset($data['activeMenuTaxon'])) {
            $data['hasMenu'] = $data['activeMenuTaxon'];
        }
        unset($data['activeMenuTaxon']);

        $imagePath = $this->uploaderHelper->asset($object, 'imageFile');
        if (empty($imagePath)) {
            $imagePath = '/img/cuisine/default.jpg';
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $data['image'] = $request->getUriForPath($imagePath);
            }
        } else {
            $data['image'] = $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail');
        }

        return $data;
    }

    private function normalizeForSeo(LocalBusiness $object, $data)
    {
        $data['@context'] = 'http://schema.org';

        if (isset($data['address'])) {
            $data['address']['@type'] = 'http://schema.org/PostalAddress';
        }

        if ($website = $object->getWebsite()) {
            $data['sameAs'] = $website;
        }

        if ($description = $object->getDescription()) {
            $data['description'] = $description;
        }

        // @see https://developers.google.com/search/docs/data-types/local-business#order-reservation-scenarios

        $urlTemplate = $this->urlGenerator->generate('restaurant', [
            'id' => $object->getId(),
            'slug' => $this->slugify->slugify($object->getName()),
            'type' => $object->getContext() === Store::class ? 'store' : 'restaurant',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $priceCurrency = $this->currencyContext->getCurrencyCode();

        $data['potentialAction'] = [
            '@type' => 'OrderAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $urlTemplate,
                'inLanguage' => $this->locale,
                'actionPlatform' => [
                    'http://schema.org/DesktopWebPlatform',
                ]
            ],
            'deliveryMethod' => [
                'http://purl.org/goodrelations/v1#DeliveryModeOwnFleet'
            ],
        ];

        $contract = $object->getContract();

        if ($object->isFulfillmentMethodEnabled('delivery') && !$contract->isVariableCustomerAmountEnabled()) {

            $fulfillmentMethod = $object->getFulfillmentMethod('delivery');

            $data['potentialAction']['priceSpecification'] = [
                '@type' => 'DeliveryChargeSpecification',
                'appliesToDeliveryMethod' => 'http://purl.org/goodrelations/v1#DeliveryModeOwnFleet',
                'priceCurrency' => $priceCurrency,
                'price' => $this->priceFormatter->format($contract->getCustomerAmount()),
                'eligibleTransactionVolume' => [
                    '@type' => 'PriceSpecification',
                    'priceCurrency' => $priceCurrency,
                    'price' => $this->priceFormatter->format($fulfillmentMethod->getMinimumAmount())
                ]
            ];
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof LocalBusiness;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $restaurant = $this->normalizer->denormalize($data, $class, $format, $context);

        return $restaurant;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === LocalBusiness::class;
    }
}
