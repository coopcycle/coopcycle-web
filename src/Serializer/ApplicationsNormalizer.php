<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\ResourceApplication;
use AppBundle\Entity\Contract;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task\RecurrenceRule;
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
        $data = [
            'entity' => $this->getClass($object->resource),
            'name' => $this->getName($object->resource),
            'id' => $this->getId($object->resource)
        ];

        // A recurrence rule is only reachable through its store
        if ($object->resource instanceof RecurrenceRule) {
            $data['storeId'] = $object->resource->getStore()->getId();
        }

        return $data;
    }

    public function getName($object) {
        if ($object instanceof Contract) {
            return $object->getContractor()->getName();
        } else if ($object instanceof Store) {
            return $object->getName();
        } else if ($object instanceof DeliveryForm) {
            $hashids12 = new Hashids($this->secret, 12);
            return $hashids12->encode($object->getId());
        } else if ($object instanceof RecurrenceRule) {
            return $object->getName() ?? $object->getStore()->getName();
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
        return $this->normalizer->supportsNormalization($object, $format) && $object instanceof ResourceApplication;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ResourceApplication::class => true, // supports*() call result is cached
        ];
    }
}
