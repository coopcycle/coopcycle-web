<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Restaurant;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RestaurantNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ItemNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        $date = clone $object->getNextOpeningDate();
        $availabilities = [];
        $days = [];

        $date->modify('+30 minutes');
        while (count($days) < 3) {
            $availabilities[] = $date->format(\DateTime::ATOM);
            $date->modify('+30 minutes');

            $day = $date->format('Ymd');
            if (!in_array($day, $days)) {
                $days[] = $day;
            }
        }

        $data['availabilities'] = $availabilities;

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Restaurant;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type instanceof Restaurant;
    }
}
