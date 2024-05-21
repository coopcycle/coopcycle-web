<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\Contract;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use Hashids\Hashids;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * A normalizer for objects to which PricingRuleSet or PackageSet are applied
 */
class ApplicationsNormalizer implements NormalizerInterface
{
    public function __construct(
        private ObjectNormalizer $normalizer,
        private string $secret
    ) {}

    public function normalize($object, $format = null, array $context = array())
    {
        return [
            'entity' => $this->getClass($object),
            'name' => $this->getName($object),
            'id' => $this->getId($object)
        ];
    }

    public function getName($object) {
        if ($object instanceof Contract) {
            return $object->getContractor()->getName();
        } else if ($object instanceof Store) {
            return $object->getName();
        } else if ($object instanceof DeliveryForm) {
            $hashids12 = new Hashids($this->secret, 12);
            return $hashids12->encode($object->getId());
        }
    }

    public function getClass($object) {
        if ($object instanceof Contract) {
            return get_class($object->getContractor());
        } else {
            return get_class($object);
        }
    }

    public function getId($object) {
        if ($object instanceof Contract) {
            return $object->getContractor()->getId();
        } else {
            return $object->getId();
        }
    }

    public function supportsNormalization($object, $format = null, array $context = [])
    {
        return $this->normalizer->supportsNormalization($object, $format) &&
        (array_key_exists('item_operation_name', $context) && $context['item_operation_name'] =='applications');
    }
}
