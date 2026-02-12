<?php

namespace AppBundle\Serializer;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use AppBundle\Assets\PlaceholderImageResolver;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Utils\OpeningHoursSpecification;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Utils\RestaurantDecorator;
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
    public function __construct(
        private ItemNormalizer $normalizer,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private UploaderHelper $uploaderHelper,
        private CurrencyContextInterface $currencyContext,
        private PriceFormatter $priceFormatter,
        private SlugifyInterface $slugify,
        private FilterService $imagineFilter,
        private TranslatorInterface $translator,
        private PlaceholderImageResolver $placeholderImageResolver,
        private RestaurantDecorator $restaurantDecorator,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private string $locale)
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        // Since API Platform 2.7, IRIs for custom operations have changed
        // It means that when doing PUT /api/restaurants/{id}/close, the @id will be /api/orders/{id}/close, not /api/restaurants/{id} like before
        // In our JS code, we often override the state with the entire response
        // This custom code makes sure it works like before, by tricking IriConverter
        $context['operation'] = $this->resourceMetadataFactory->create(LocalBusiness::class)->getOperation();

        $data = $this->normalizer->normalize($object, $format, $context);

        if (is_string($data)) {
            return $data;
        }

        $data['@type'] = $object->getType();

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

        $bannerImagePath = $this->uploaderHelper->asset($object, 'bannerImageFile');
        if (empty($bannerImagePath)) {
            $data['bannerImage'] = $this->placeholderImageResolver->resolve(filter: 'restaurant_banner', obj: $object, referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            try {
                $data['bannerImage'] = $this->imagineFilter->getUrlOfFilteredImage($bannerImagePath, 'restaurant_banner');
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

        $data['isOpen'] = $object->isOpen();
        $data['nextOpeningDate'] = $object->getNextOpeningDate();

        if (isset($data['facets'])) {
            $cuisines =
                array_map(fn ($c) => $this->translator->trans($c, [], 'cuisines'), $data['facets']['cuisine']);
            sort($cuisines);
            $data['facets']['cuisine'] = $cuisines;

            $data['facets']['type'] =
                $this->translator->trans(LocalBusiness::getTransKeyForType($data['facets']['type']));

            $categories =
                array_map(fn ($c) => $this->translator->trans(sprintf('tags.%s', $c)), $data['facets']['category']);
            $data['facets']['category'] = $categories;
        }

        $data['tags'] = $this->restaurantDecorator->getTags($object);
        $data['badges'] = $this->restaurantDecorator->getBadges($object);

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

        $this->urlGenerator->generate('restaurant', [
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

    public function getSupportedTypes(?string $format): array
    {
        return [
            LocalBusiness::class => true, // supports*() call result is cached
        ];
    }
}
