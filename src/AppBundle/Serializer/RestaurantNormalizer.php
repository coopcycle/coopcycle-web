<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\OpeningHoursSpecification;
use AppBundle\Utils\PriceFormatter;
use Cocur\Slugify\SlugifyInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class RestaurantNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $urlGenerator;
    private $uploaderHelper;
    private $assets;
    private $currencyContext;
    private $priceFormatter;
    private $slugify;
    private $locale;

    public function __construct(
        ItemNormalizer $normalizer,
        UrlGeneratorInterface $urlGenerator,
        UploaderHelper $uploaderHelper,
        Packages $assets,
        CurrencyContextInterface $currencyContext,
        PriceFormatter $priceFormatter,
        SlugifyInterface $slugify,
        $locale)
    {
        $this->normalizer = $normalizer;
        $this->urlGenerator = $urlGenerator;
        $this->uploaderHelper = $uploaderHelper;
        $this->assets = $assets;
        $this->currencyContext = $currencyContext;
        $this->priceFormatter = $priceFormatter;
        $this->slugify = $slugify;
        $this->locale = $locale;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['openingHours'])) {
            $data['openingHoursSpecification'] = array_map(function (OpeningHoursSpecification $openingHoursSpecification) {
                return $openingHoursSpecification->jsonSerialize();
            }, OpeningHoursSpecification::fromOpeningHours($object->getOpeningHours()));
        }

        if (isset($data['closingRules'])) {
            $data['specialOpeningHoursSpecification'] = $data['closingRules'];
            unset($data['closingRules']);
        }

        // Stop now if this is for SEO
        // FIXME Stop checking groups manually
        if (isset($context['groups']) && in_array('restaurant_seo', $context['groups'])) {

            return $this->normalizeForSeo($object, $data);
        }

        if (isset($data['@id']) && $object->hasMenu()) {
            $data['hasMenu'] = $data['@id'] . '/menu';
        }

        $data['availabilities'] = $object->getAvailabilities();
        $data['minimumCartAmount'] = $object->getMinimumCartAmount();
        $data['flatDeliveryPrice'] = $object->getFlatDeliveryPrice();

        $imagePath = $this->uploaderHelper->asset($object, 'imageFile');
        if (empty($imagePath)) {
            $imagePath = 'img/cuisine/default.jpg';
        }
        $data['image'] = $this->assets->getUrl($imagePath);

        return $data;
    }

    private function normalizeForSeo($object, $data)
    {
        $data['@context'] = 'http://schema.org';

        if (isset($data['address'])) {
            $data['address']['@type'] = 'http://schema.org/PostalAddress';
        }
        if (isset($data['openingHours'])) {
            unset($data['openingHours']);
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
            'priceSpecification' => [
                '@type' => 'DeliveryChargeSpecification',
                'appliesToDeliveryMethod' => 'http://purl.org/goodrelations/v1#DeliveryModeOwnFleet',
                'priceCurrency' => $priceCurrency,
                'price' => $this->priceFormatter->format($object->getFlatDeliveryPrice()),
                'eligibleTransactionVolume' => [
                    '@type' => 'PriceSpecification',
                    'priceCurrency' => $priceCurrency,
                    'price' => $this->priceFormatter->format($object->getMinimumCartAmount())
                ]
            ]
        ];

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Restaurant;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type instanceof Restaurant;
    }
}
