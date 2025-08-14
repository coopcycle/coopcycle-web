<?php

namespace AppBundle\Serializer;

use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * This normalizer is used to generate a temporary id for objects
 * that don't have one yet, but require one for serialization (API Platform; JSON-LD)
 */
class TemporaryIdNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private array $processedObjects = [];

    public function normalize($object, ?string $format = null, array $context = []): mixed
    {
        // Skip if already processed to avoid circular references
        $objectId = spl_object_id($object);
        if (isset($this->processedObjects[$objectId])) {
            return $this->processedObjects[$objectId];
        }

        $this->ensureTemporaryId($object);

        // Mark as processed
        $this->processedObjects[$objectId] = true;

        // Continue with normal normalization
        return $this->normalizer->normalize($object, $format, $context);
    }

    private function ensureTemporaryId(object $object): void
    {
        // Try standard method first
        if (method_exists($object, 'setId')) {
            $object->setId(Uuid::uuid4()->toString());

            return;
        }

        // Try reflection to check for id property
        try {
            $reflection = new ReflectionClass($object);
            if ($reflection->hasProperty('id')) {
                $property = $reflection->getProperty('id');
                if (null === $property->getValue($object)) {
                    $property->setValue($object, Uuid::uuid4()->toString());
                }
            }
        } catch (ReflectionException $e) {
            // Property doesn't exist or can't be accessed
        }
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        $isSupportedObject = is_object($data) && method_exists($data, 'getId') && null === $data->getId();

        $isEnabledForGroup = isset($context['groups']) && is_array($context['groups']) && in_array('pricing_deliveries', $context['groups']);

        return $isSupportedObject && $isEnabledForGroup;
    }
}
