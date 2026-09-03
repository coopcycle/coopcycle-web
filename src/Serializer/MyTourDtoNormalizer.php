<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\MyTourDto;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class MyTourDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MyTourDtoNormalizer_ALREADY_CALLED';

    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        $data['@context'] = '/api/contexts/Tour';
        $data['@type'] = 'Tour';
        $data['@id'] = "/api/tours/" . $object->id;

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof MyTourDto;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MyTourDto::class => false, // supports*() call result is NOT cached
        ];
    }
}
