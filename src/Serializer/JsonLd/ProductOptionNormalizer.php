<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProductOptionNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer)
    {
    }

    public function normalize($object, $format = null, array $context = array()): array
    {
        /** @var array{'@id': string, "@type": string, "hasMenuItem"?: array} */
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['hasMenuItem'])) {

            unset($data['@id']);
            $data['@type'] = 'MenuSection';

            // Sort option values by name
            usort($data['hasMenuItem'], function($a, $b) {
                return $a['name'] < $b['name'] ? -1 : 1;
            });
            foreach ($data['hasMenuItem'] as $i => $menuItem) {
                $data['hasMenuItem'][$i]['@type'] = 'MenuItem';
                if (isset($data['hasMenuItem'][$i]['dependsOn']) && is_array($data['hasMenuItem'][$i]['dependsOn'])) {
                    $data['hasMenuItem'][$i]['dependsOn'] = array_map(fn ($dependsOn) => $dependsOn['@id'],  $data['hasMenuItem'][$i]['dependsOn']);
                }
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof ProductOptionInterface;
    }

    public function denormalize($data, $class, $format = null, array $context = array()): array
    {
        return [];
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProductOptionInterface::class => true, // supports*() call result is cached
        ];
    }
}
