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

        $this->unsetIfNull($data, [
            'delivery_position',
            'order_number',
            'payment_method',
            'order_total',
            'has_loopeat_returns',
            'zero_waste'
        ]);

        return $data;
    }

    private function unsetIfNull(&$data, $fields): void
    {
        foreach ($fields as $field) {
            if (null === $data[$field]) {
                unset($data[$field]);
            }
        }
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
