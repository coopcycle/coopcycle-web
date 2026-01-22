<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Sylius\Component\Locale\Provider\LocaleProvider;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * FIXME
 * Understand why the locale is not set correctly
 * We shouldn't need to call setCurrentLocale
 * It may happen only in Behat context
 */
class RestaurantMenuNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private ProductNormalizer $productNormalizer,
        private LocaleProvider $localeProvider,
        private ProductVariantResolverInterface $variantResolver,
        private UrlGeneratorInterface $urlGenerator,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory)
    {
    }

    private function normalizeRange($range)
    {
        return implode('', [
            '[',
            $range->getLower(),
            ',',
            $range->isUpperInfinite() ? '' : $range->getUpper(),
            ']',
        ]);
    }

    private function normalizeOptionValues(ProductOptionInterface $option, $optionValues)
    {
        $data = [];

        foreach ($optionValues as $optionValue) {

            $optionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

            $menuItem = [
                '@type' => 'MenuItem',
                'name' => $optionValue->getValue(),
                'identifier' => $optionValue->getCode(),
            ];

            $price = 0;
            switch ($option->getStrategy()) {
                case ProductOptionInterface::STRATEGY_OPTION_VALUE:
                    $price = $optionValue->getPrice();
                    break;
            }

            $menuItem['offers'] = [
                '@type' => 'Offer',
                'price' => $price,
            ];

            $data[] = $menuItem;
        }

        // Sort option values by name
        usort($data, function($a, $b) {
            return $a['name'] < $b['name'] ? -1 : 1;
        });

        return $data;
    }

    private function normalizeOptions($options)
    {
        $data = [];

        foreach ($options as $option) {

            $option->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

            $payload = [
                '@type' => 'MenuSection',
                'name' => $option->getName(),
                'identifier' => $option->getCode(),
                'additionalType' => $option->getStrategy(),
                'additional' => $option->isAdditional(),
                'hasMenuItem' => $this->normalizeOptionValues($option, $option->getValues()),
            ];

            if (null !== $option->getValuesRange()) {
                $payload['valuesRange'] = $this->normalizeRange($option->getValuesRange());
            }

            $data[] = $payload;
        }

        return $data;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        // Force the IRI of the root resource to correspond to the GET operation
        // If we don't do this, the IRI when doing PUT /api/restaurants/menus/1/sections/1 can be wrong
        $context['operation'] = $this->resourceMetadataFactory->create(Taxon::class)->getOperation();

        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['code'])) {
            $data['identifier'] = $data['code'];
            unset($data['code']);
        }

        $object->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        foreach ($object->getChildren() as $child) {

            $section = [
                // FIXME
                // Ugly but works and is tested in Behat
                // This should be handled by a custom normalizer for Taxon class
                '@id' => $this->urlGenerator->generate('_api_/restaurants/menus/{id}/sections/{sectionId}_get', [
                    'id' => $object->getId(),
                    'sectionId' => $child->getId(),
                ]),
                'name' => $child->getName(),
                'description' => $child->getDescription(),
                'hasMenuItem' => [],
            ];

            foreach ($child->getProducts() as $product) {

                $defaultVariant = $this->variantResolver->getVariant($product);

                if ($defaultVariant) {
                    $productData = $this->productNormalizer->normalize($product, $format, $context);

                    $productData['@type'] = 'MenuItem';

                    if ($product->hasOptions()) {
                        $productData['menuAddOn'] = $this->normalizeOptions($product->getOptions());
                    }

                    $section['hasMenuItem'][] = $productData;
                }
            }

            $data['hasMenuSection'][] = $section;
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Taxon;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return [];
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Taxon::class => true, // supports*() call result is cached
        ];
    }
}
