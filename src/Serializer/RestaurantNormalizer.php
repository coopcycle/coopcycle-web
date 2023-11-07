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
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Carbon\Carbon;

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
        TranslatorInterface $translator,
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
        $this->translator = $translator;
        $this->locale = $locale;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $data['@type'] = $object->getType();

        if (isset($data['closingRules'])) {
            $data['specialOpeningHoursSpecification'] = $data['closingRules'];
            unset($data['closingRules']);
        }

        $imagePath = $this->uploaderHelper->asset($object, 'imageFile');
        if (empty($imagePath)) {
            $imagePath = '/img/cuisine/default.jpg';
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $data['image'] = $request->getUriForPath($imagePath);
            }
        } else {
            try {
                $data['image'] = $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail');
            } catch (NotLoadableException $e) {}
        }

        // Stop now if this is for SEO
        // FIXME Stop checking groups manually
        if (isset($context['groups']) && in_array('restaurant_seo', $context['groups'])) {

            return $this->normalizeForSeo($object, $data);
        }

        if (isset($context['groups']) && in_array('restaurant_potential_action', $context['groups'])) {

            $data['potentialAction'] = $this->normalizePotentialAction($object);
        }

        if (isset($data['activeMenuTaxon'])) {
            $data['hasMenu'] = $data['activeMenuTaxon'];
        }
        unset($data['activeMenuTaxon']);

        $isOpen = $object->isOpen();
        $data['isOpen'] = $isOpen;
        if (!$isOpen) {
            $data['nextOpeningDate'] = $object->getNextOpeningDate();
        }

        if (isset($data['facets'])) {
            $cuisines =
                array_map(fn ($c) => $this->translator->trans($c, [], 'cuisines'), $data['facets']['cuisine']);
            $data['facets']['cuisine'] = $cuisines;

            $data['facets']['type'] =
                $this->translator->trans(LocalBusiness::getTransKeyForType($data['facets']['type']));

            $categories =
                array_map(fn ($c) => $this->translator->trans(sprintf('homepage.%s', $c)), $data['facets']['category']);
            $data['facets']['category'] = $categories;
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

        $data['potentialAction'] = $this->normalizePotentialAction($object);

        $contract = $object->getContract();
        $priceCurrency = $this->currencyContext->getCurrencyCode();

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

    private function normalizePotentialAction(LocalBusiness $object): array
    {
        // @see https://developers.google.com/search/docs/data-types/local-business#order-reservation-scenarios

        $urlTemplate = $this->urlGenerator->generate('restaurant', [
            'id' => $object->getId(),
            'slug' => $this->slugify->slugify($object->getName()),
            'type' => $object->getContext() === Store::class ? 'store' : 'restaurant',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
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
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof LocalBusiness;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === LocalBusiness::class;
    }
}
