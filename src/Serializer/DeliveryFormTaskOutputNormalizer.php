<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\DeliveryFormTaskOutput;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class DeliveryFormTaskOutputNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'DeliveryFormTaskOutputNormalizer_ALREADY_CALLED';

    /**
     * @param DeliveryFormTaskOutput $object
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        $data['@context'] = '/api/contexts/Task';
        $data['@type'] = 'Task';
        $data['@id'] = "/api/tasks/" . $object->id;

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof DeliveryFormTaskOutput;
    }
}
