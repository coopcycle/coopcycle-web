<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\MyTaskMetadataDto;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class MyTaskMetadataDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MyTaskMetadataDtoNormalizer_ALREADY_CALLED';

    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        unset($data['@context']);
        unset($data['@type']);
        unset($data['@id']);

        if (null === $data['has_loopeat_returns']) {
            unset($data['has_loopeat_returns']);
        }

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof MyTaskMetadataDto;
    }
}
