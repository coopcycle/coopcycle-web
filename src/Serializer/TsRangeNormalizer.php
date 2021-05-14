<?php

namespace AppBundle\Serializer;

use AppBundle\DataType\TsRange;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TsRangeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        return [
            $object->getLower()->format(\DateTime::ATOM),
            $object->getUpper()->format(\DateTime::ATOM)
        ];
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof TsRange;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (is_array($data) && count($data) === 2) {
            $tsRange = new TsRange();
            $tsRange->setLower(new \DateTime($data[0]));
            $tsRange->setUpper(new \DateTime($data[1]));

            return $tsRange;
        }

        return [];
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === TsRange::class;
    }
}
