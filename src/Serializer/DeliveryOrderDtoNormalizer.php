<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\DeliveryOrderDto;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class DeliveryOrderDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'DeliveryOrderDtoNormalizer_ALREADY_CALLED';

    /**
     * @param DeliveryOrderDto $object
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        if (isset($data['@context'])) {
            $data['@context'] = '/api/contexts/Order';
        }
        $data['@type'] = 'Order';
        $data['@id'] = "/api/orders/" . $object->id;

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof DeliveryOrderDto;
    }
}
