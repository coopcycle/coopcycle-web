<?php

declare(strict_types=1);

namespace AppBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * Symfony builds its attributes cache key by hashing `serialize($context)`, and
 * API Platform puts objects in that context: the operation, but also the entity
 * being normalized (`object`) and its parent's output (`data`). Serializing the
 * entity walks every object reachable from it.
 *
 * In an API request, API Platform's context builder excludes those keys. When we
 * call normalize() ourselves (live updates, webhooks, ...) nobody does, and
 * setting EXCLUDE_FROM_CACHE_KEY at all replaces the serializer's own defaults.
 * On lcr, a worker that held a store with all its deliveries ran out of memory
 * on each task:created.
 *
 * @see \ApiPlatform\Serializer\SerializerContextBuilder::createFromRequest()
 */
final class SerializerCacheKey
{
    private const EXCLUDED = [
        'root_operation',
        'operation',
        'object',
        'data',
        'property_metadata',
        'circular_reference_limit_counters',
        'debug_trace_id',
    ];

    public static function excludeObjects(array $context): array
    {
        $context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] = array_values(array_unique(array_merge(
            $context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] ?? [],
            self::EXCLUDED
        )));

        return $context;
    }
}
