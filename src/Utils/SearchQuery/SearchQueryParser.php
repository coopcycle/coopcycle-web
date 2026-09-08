<?php

namespace AppBundle\Utils\SearchQuery;

/**
 * Parses a Sentry/Datadog-style search query string into structured filters
 * ("key:value") plus free-text terms, for use with fuzzy search.
 *
 * Grammar (whitespace-separated tokens, values may be quoted to include spaces):
 *   key:value                 -> included filter
 *   -key:value                -> excluded filter
 *   key:(value1 OR value2)    -> one included filter per value
 *   -key:(value1 OR value2)   -> one excluded filter per value
 *   "some free text"          -> free-text term
 *   foo                       -> free-text term
 *
 * The "(v1 OR v2)" group form (built by a `multi: true` SearchQueryBar
 * field's checkbox dropdown) expands to several SearchQueryFilter entries
 * sharing the same key/exclude - the same shape repeating a key produces
 * (e.g. "state:new state:accepted"), so getFilters($key) handles both alike.
 *
 * This mirrors the JS implementation in
 * js/app/components/SearchQueryBar/queryString.js, so keep both in sync.
 */
class SearchQueryParser
{
    public function parse(?string $query): SearchQuery
    {
        $filters = [];
        $terms = [];

        foreach ($this->tokenize((string) $query) as $raw) {

            $exclude = false;
            $token = $raw;

            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $exclude = true;
                $token = substr($token, 1);
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):(.+)$/', $token, $matches)) {
                $key = $matches[1];
                $rawValue = $matches[2];

                if (preg_match('/^\((.*)\)$/', $rawValue, $groupMatches)) {
                    foreach ($this->splitGroupValues($groupMatches[1]) as $value) {
                        $filters[] = new SearchQueryFilter($key, $value, $exclude);
                    }
                } else {
                    $filters[] = new SearchQueryFilter($key, $rawValue, $exclude);
                }
            } else {
                $terms[] = $raw;
            }
        }

        return new SearchQuery($filters, $terms);
    }

    /**
     * Splits a query string on whitespace, honoring single/double-quoted
     * substrings (which may appear anywhere within a token, e.g. `key:"a b"`),
     * and treating a "key:(...)" value group as one token regardless of the
     * whitespace inside it (e.g. `owner:("a" OR "b")` stays one token).
     *
     * @return string[]
     */
    private function tokenize(string $query): array
    {
        $tokens = [];
        $current = '';
        $quoteChar = null;
        $groupDepth = 0;

        $length = strlen($query);
        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];

            if ($groupDepth > 0) {
                // Inside a "key:(...)" value group: copy everything verbatim
                // (quotes included) so the " OR "-separated values can be
                // split out later - only whitespace *outside* the group
                // acts as a token boundary.
                if ($quoteChar) {
                    $current .= $char;
                    if ($char === $quoteChar) {
                        $quoteChar = null;
                    }
                    continue;
                }
                if ($char === '"' || $char === "'") {
                    $quoteChar = $char;
                    $current .= $char;
                    continue;
                }
                if ($char === '(') {
                    $groupDepth++;
                } elseif ($char === ')') {
                    $groupDepth--;
                }
                $current .= $char;
                continue;
            }

            if ($quoteChar) {
                if ($char === $quoteChar) {
                    $quoteChar = null;
                } else {
                    $current .= $char;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quoteChar = $char;
                continue;
            }

            if ($char === '(' && preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_]*:$/', $current)) {
                $groupDepth = 1;
                $current .= $char;
                continue;
            }

            if (ctype_space($char)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Splits the inner content of a "(v1 OR v2)" value group into its
     * individual, unquoted values - honoring quotes around each value.
     *
     * @return string[]
     */
    private function splitGroupValues(string $inner): array
    {
        $values = [];
        $current = '';
        $quoteChar = null;

        $length = strlen($inner);
        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

            if ($quoteChar) {
                if ($char === $quoteChar) {
                    $quoteChar = null;
                } else {
                    $current .= $char;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quoteChar = $char;
                continue;
            }

            if (substr($inner, $i, 4) === ' OR ') {
                $values[] = $current;
                $current = '';
                $i += 3;
                continue;
            }

            $current .= $char;
        }
        $values[] = $current;

        return array_values(array_filter(
            array_map('trim', $values),
            fn ($value) => '' !== $value
        ));
    }
}
