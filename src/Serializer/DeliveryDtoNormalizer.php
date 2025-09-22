<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\DeliveryInputDto;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class DeliveryDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'DeliveryDtoNormalizer_ALREADY_CALLED';

    /**
     * @param DeliveryInputDto $object
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        if (null === $object->id) {
            $context['iri'] = false;
            $context['force_iri_generation'] = false;
        }

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to maintain the existing API
        if (isset($data['@context'])) {
            $data['@context'] = '/api/contexts/Delivery';
        }
        $data['@id'] = "/api/deliveries/" . $object->id;

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof DeliveryInputDto;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            DeliveryInputDto::class => false, // supports*() call result is NOT cached
        ];
    }
}
