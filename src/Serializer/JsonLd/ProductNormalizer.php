<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Enum\Allergen;
use AppBundle\Enum\RestrictedDiet;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Locale\Provider\LocaleProvider;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Liip\ImagineBundle\Service\FilterService;

class ProductNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private LocaleProvider $localeProvider,
        private ProductVariantResolverInterface $variantResolver,
        private UploaderHelper $uploaderHelper,
        private FilterService $imagineFilter, private LoggerInterface $checkoutLogger)
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

        $defaultVariant = $this->variantResolver->getVariant($object);

        if ($defaultVariant) {
            $object->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

            // @see https://github.com/coopcycle/coopcycle-app/issues/286
            $description = !empty(trim($object->getDescription())) ? $object->getDescription() : null;

            $data['@type'] = 'MenuItem';
            $data['name'] = $object->getName();
            $data['description'] = $description;
            $data['identifier'] = $object->getCode();
            $data['enabled'] = $object->isEnabled();
            $data['reusablePackagingEnabled'] = $object->isReusablePackagingEnabled();
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => $defaultVariant->getPrice(),
            ];

            if ($object->hasOptions()) {
                $data['menuAddOn'] = $this->normalizeOptions($object->getOptions());
            }

            $restrictedDiets = $object->getRestrictedDiets();
            if (count($restrictedDiets) > 0) {
                // https://schema.org/suitableForDiet
                $data['suitableForDiet'] = array_values(array_map(function ($constantName) {
                    $reflect = new \ReflectionClass(RestrictedDiet::class);
                    return $reflect->getConstant($constantName);
                }, $restrictedDiets));
            }

            $allergens = $object->getAllergens();
            if (count($allergens) > 0) {
                $data['allergens'] = array_values(array_map(function ($constantName) {
                    $reflect = new \ReflectionClass(Allergen::class);
                    return $reflect->getConstant($constantName);
                }, $allergens));
            }

            $images = [];
            foreach (['1:1', '16:9'] as $ratio) {
                $imagePath = $this->getProductImagePath($object, $ratio);
                if ($imagePath) {
                    $images[] = [
                        'ratio' => $ratio,
                        'url' => $imagePath,
                    ];
                }
            }

            $data['images'] = $images;
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        $this->checkoutLogger->info('Supports normalization', [
            'data' => $data,
        ]);
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof ProductInterface;
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
