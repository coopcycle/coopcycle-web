<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Taxon;
use Sylius\Component\Locale\Provider\LocaleProvider;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
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
    private $normalizer;
    private $localeProvider;
    private $variantResolver;

    public function __construct(
        ItemNormalizer $normalizer,
        LocaleProvider $localeProvider,
        ProductVariantResolverInterface $variantResolver)
    {
        $this->normalizer = $normalizer;
        $this->localeProvider = $localeProvider;
        $this->variantResolver = $variantResolver;
    }

    private function normalizeOptions($options)
    {
        $data = [];

        foreach ($options as $option) {

            $option->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

            $data[] = [
                '@type' => 'MenuSection',
                'name' => $option->getName(),
                'identifier' => $option->getCode(),
                'hasMenuItem' => $this->normalizeOptionValues($option->getValues()),
            ];
        }

        return $data;
    }

    private function normalizeOptionValues($optionValues)
    {
        $data = [];

        foreach ($optionValues as $optionValue) {

            $optionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

            $data[] = [
                '@type' => 'MenuItem',
                'name' => $optionValue->getValue(),
                'identifier' => $optionValue->getCode(),
            ];
        }

        // Sort option values by name
        usort($data, function($a, $b) {
            return $a['name'] < $b['name'] ? -1 : 1;
        });

        return $data;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = [
            '@type' => 'Menu',
            'identifier' => $object->getCode(),
        ];

        $object->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        $sections = [];
        foreach ($object->getChildren() as $child) {
            $section = [
                'name' => $child->getName(),
                'hasMenuItem' => [],
            ];

            foreach ($child->getProducts() as $product) {

                $defaultVariant = $this->variantResolver->getVariant($product);

                if ($defaultVariant) {
                    $product->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

                    $item = [
                        '@type' => 'MenuItem',
                        'name' => $product->getName(),
                        'description' => $product->getDescription(),
                        'identifier' => $product->getCode(),
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => $defaultVariant->getPrice(),
                        ]
                    ];
                    if ($product->hasOptions()) {
                        $item['menuAddOn'] = $this->normalizeOptions($product->getOptions());
                    }
                    $section['hasMenuItem'][] = $item;
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
        return null;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
