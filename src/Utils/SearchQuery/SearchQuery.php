<?php

namespace AppBundle\Utils\SearchQuery;

/**
 * The result of parsing a Sentry/Datadog-like search query string
 * (e.g. "date:2026-09-08 -state:cancelled foo").
 *
 * @see SearchQueryParser
 */
final class SearchQuery
{
    /**
     * @param SearchQueryFilter[] $filters
     * @param string[] $terms free-text terms, meant to be used for fuzzy matching
     */
    public function __construct(
        private readonly array $filters,
        private readonly array $terms,
    ) {
    }

    /**
     * Returns the first non-excluded filter matching $key, if any.
     */
    public function getFilter(string $key): ?SearchQueryFilter
    {
        foreach ($this->filters as $filter) {
            if ($filter->key === $key) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * Returns every filter matching $key (both included & excluded),
     * useful for repeatable keys like "state".
     *
     * @return SearchQueryFilter[]
     */
    public function getFilters(string $key): array
    {
        return array_values(array_filter(
            $this->filters,
            fn (SearchQueryFilter $filter) => $filter->key === $key
        ));
    }

    /**
     * @return string[]
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function getFullText(): string
    {
        return implode(' ', $this->terms);
    }

    public function isEmpty(): bool
    {
        return count($this->filters) === 0 && count($this->terms) === 0;
    }
}
