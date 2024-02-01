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
    public function __construct(
        private ItemNormalizer $normalizer,
        private ProductNormalizer $productNormalizer,
        private LocaleProvider $localeProvider,
        private ProductVariantResolverInterface $variantResolver)
    {
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['code'])) {
            $data['identifier'] = $data['code'];
            unset($data['code']);
        }

        $object->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        foreach ($object->getChildren() as $child) {
            $section = [
                'name' => $child->getName(),
                'hasMenuItem' => [],
            ];

            foreach ($child->getProducts() as $product) {

                $defaultVariant = $this->variantResolver->getVariant($product);

                if ($defaultVariant) {
                    $section['hasMenuItem'][] = $this->productNormalizer->normalize($product, $format, $context);
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
}
