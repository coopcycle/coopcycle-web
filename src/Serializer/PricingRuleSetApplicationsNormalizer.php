<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Contract;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * A normalizer for objects related to PricingRuleSet
 */
class PricingRuleSetApplicationsNormalizer implements NormalizerInterface
{
    public function __construct(
        private ObjectNormalizer $normalizer,
        private IriConverterInterface $iriConverterInterface
    )
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        return [
            'entity' => $this->getClass($object),
            'name' => $this->getName($object),
            'id' => $object->getId()
        ];
    }

    public function getName($object) {
        if ($object instanceof Contract) {
            return $object->getContractor()->getName();
        } else if ($object instanceof Store) {
            return $object->getName();
        } else if ($object instanceof DeliveryForm) {
            return $object->getDisplayHash();
        }
    }

    public function getClass($object) {
        if ($object instanceof Contract) {
            return get_class($object->getContractor());
        } else {
            return get_class($object);
        }
    }

    public function supportsNormalization($object, $format = null)
    {
        return $this->normalizer->supportsNormalization($object, $format) && (
            $object instanceof Contract ||
            $object instanceof DeliveryForm ||
            $object instanceof Store
        );
    }
}
