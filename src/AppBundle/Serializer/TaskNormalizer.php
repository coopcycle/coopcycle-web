<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $iriConverter;

    public function __construct(ItemNormalizer $normalizer, IriConverterInterface $iriConverter)
    {
        $this->normalizer = $normalizer;
        $this->iriConverter = $iriConverter;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        $data['isAssigned'] = $object->isAssigned();
        $data['assignedTo'] = null;
        $data['position'] = 0;
        if ($object->isAssigned()) {
            $data['assignedTo'] = $object->getAssignment()->getCourier()->getUsername();
            $data['position'] = $object->getAssignment()->getPosition();
        }

        $data['previous'] = null;
        if ($object->hasPrevious()) {
            $data['previous'] = $this->iriConverter->getIriFromItem($object->getPrevious());
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Task;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type instanceof Task;
    }
}
