<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\Woopit\QuoteRequest;
use Hashids\Hashids;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class WoopitDeliveryRequestNormalizer implements NormalizerInterface
{
    private $normalizer;
    private $hashids12;

    public function __construct(ObjectNormalizer $normalizer, Hashids $hashids12)
    {
        $this->normalizer = $normalizer;
        $this->hashids12 = $hashids12;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $object->deliveryId = $this->hashids12->encode($object->deliveryObject->getId());

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof QuoteRequest
            && $data->state === QuoteRequest::STATE_CONFIRMED;
    }
}
