<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Enum\Allergen;
use AppBundle\Enum\RestrictedDiet;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Liip\ImagineBundle\Service\FilterService;

class ProductNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private ProductVariantResolverInterface $variantResolver,
        private UploaderHelper $uploaderHelper,
        private FilterService $imagineFilter)
    {
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
        $context[AbstractObjectNormalizer::SKIP_NULL_VALUES] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['menuAddOn']) && is_array($data['menuAddOn'])) {
            if (empty($data['menuAddOn'])) {
                unset($data['menuAddOn']);
            }
        }

        if (isset($data['images']) && is_array($data['images'])) {
            $images = [];
            foreach ($data['images'] as $image) {
                $imagePath = $this->getProductImagePath($object, $image['ratio']);
                if ($imagePath) {
                    $images[] = [
                        'ratio' => $image['ratio'],
                        'url' => $imagePath,
                    ];
                }
            }
            $data['images'] = $images;
        }

        if (isset($data['offers'])) {
            $defaultVariant = $this->variantResolver->getVariant($object);
            if ($defaultVariant) {
                $data['offers']['price'] = $defaultVariant->getPrice();
            } else {
                unset($data['offers']);
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
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

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProductInterface::class => true, // supports*() call result is cached
        ];
    }
}
