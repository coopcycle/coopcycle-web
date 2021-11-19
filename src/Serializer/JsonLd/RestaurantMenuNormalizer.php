<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Enum\Allergen;
use AppBundle\Enum\RestrictedDiet;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Sylius\Component\Locale\Provider\LocaleProvider;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Liip\ImagineBundle\Service\FilterService;

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
        ProductVariantResolverInterface $variantResolver,
        UploaderHelper $uploaderHelper,
        FilterService $imagineFilter)
    {
        $this->normalizer = $normalizer;
        $this->localeProvider = $localeProvider;
        $this->variantResolver = $variantResolver;
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineFilter = $imagineFilter;
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

    private function getProductImagePath($product, $ratio) {
        try {
            $productImage = $product->getImages()->filter(function ($image) use($ratio) {
                return $image->getRatio() === $ratio;
            })->first();
            if ($productImage) {
                $imagePath = $this->uploaderHelper->asset($productImage, 'imageFile');
                if (!empty($imagePath)) {
                    $filterName = sprintf('product_thumbnail_%s', str_replace(':', 'x', $ratio));
                    return $this->imagineFilter->getUrlOfFilteredImage($imagePath, $filterName);
                }
            }
        } catch (\Exception $e) {
            // TODO add logger?
        }
        return null;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['code'])) {
            $data['identifier'] = $data['code'];
            unset($data['code']);
        }

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

                    // @see https://github.com/coopcycle/coopcycle-app/issues/286
                    $description = !empty(trim($product->getDescription())) ? $product->getDescription() : null;

                    $item = [
                        '@type' => 'MenuItem',
                        'name' => $product->getName(),
                        'description' => $description,
                        'identifier' => $product->getCode(),
                        'enabled' => $product->isEnabled(),
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => $defaultVariant->getPrice(),
                        ]
                    ];
                    if ($product->hasOptions()) {
                        $item['menuAddOn'] = $this->normalizeOptions($product->getOptions());
                    }

                    $restrictedDiets = $product->getRestrictedDiets();
                    if (count($restrictedDiets) > 0) {
                        // https://schema.org/suitableForDiet
                        $item['suitableForDiet'] = array_values(array_map(function ($constantName) {
                            $reflect = new \ReflectionClass(RestrictedDiet::class);
                            return $reflect->getConstant($constantName);
                        }, $restrictedDiets));
                    }

                    $allergens = $product->getAllergens();
                    if (count($allergens) > 0) {
                        $item['allergens'] = array_values(array_map(function ($constantName) {
                            $reflect = new \ReflectionClass(Allergen::class);
                            return $reflect->getConstant($constantName);
                        }, $allergens));
                    }

                    $images = [];
                    foreach (['1:1', '16:9'] as $ratio) {
                        $imagePath = $this->getProductImagePath($product, $ratio);
                        if ($imagePath) {
                            $images[] = [
                                'ratio' => $ratio,
                                'url' => $imagePath,
                            ];
                        }
                    }

                    $item['images'] = $images;

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
        return [];
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
