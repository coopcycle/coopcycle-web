<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\DeliveryFormDeliveryOutput;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class DeliveryFormDeliveryOutputNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'DeliveryFormDeliveryOutputNormalizer_ALREADY_CALLED';

    /**
     * @param DeliveryFormDeliveryOutput $object
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to maintain the existing API
        $data['@context'] = '/api/contexts/Delivery';
        $data['@id'] = "/api/deliveries/" . $object->id;

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof DeliveryFormDeliveryOutput;
    }
}
