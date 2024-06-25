<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Store;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @see https://github.com/api-platform/core/issues/4293
 */
class PatchAwareStoreNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ItemNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function supportsNormalization($data, string $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format);
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        return $this->normalizer->normalize($object, $format, $context);
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        unset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

        $object = $this->normalizer->denormalize($data, $class, $format, $context);

        return $object;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        return $type === Store::class
            && $context['operation_type'] === 'item'
            && $context['item_operation_name'] === 'patch'
            && $this->normalizer->supportsDenormalization($data, $type, $format);
    }
}

