<?php

namespace AppBundle\Utils\SearchQuery;

/**
 * A single "key:value" (or "-key:value") token parsed from a search query string.
 *
 * @see SearchQueryParser
 */
final class SearchQueryFilter
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly bool $exclude = false,
    ) {
    }
}
