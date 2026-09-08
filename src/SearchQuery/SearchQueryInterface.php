<?php

namespace AppBundle\SearchQuery;

use Doctrine\ORM\QueryBuilder;

/**
 * Applies a "key:value" search query string (parsed via
 * AppBundle\Utils\SearchQuery\SearchQueryParser) as filters on a
 * Doctrine QueryBuilder, for a given searchable resource.
 *
 * Implementations own the mapping between query keys (e.g. "state",
 * "owner") and the actual DQL clauses for their resource.
 */
interface SearchQueryInterface
{
    /**
     * @param QueryBuilder $qb an existing QueryBuilder for the resource being
     *   searched (e.g. built via the resource's repository); its root alias
     *   is up to the implementation to document/assume
     */
    public function search(string $searchQueryString, QueryBuilder $qb): QueryBuilder;
}
