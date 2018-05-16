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

        if (isset($data['taxons'])) {
            foreach ($data['taxons'] as $taxon) {
                if ($taxon['identifier'] === $object->getMenuTaxon()->getCode()) {
                    $data['hasMenu'] = $taxon;
                    break;
                }
            }
            unset($data['taxons']);
        }

        $data['availabilities'] = $object->getAvailabilities();
        $data['minimumCartAmount'] = $object->getMinimumCartAmount();
        $data['flatDeliveryPrice'] = $object->getFlatDeliveryPrice();

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
