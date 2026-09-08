<?php

namespace AppBundle\Utils\SearchQuery;

/**
 * Parses a Sentry/Datadog-style search query string into structured filters
 * ("key:value") plus free-text terms, for use with fuzzy search.
 *
 * Grammar (whitespace-separated tokens, values may be quoted to include spaces):
 *   key:value          -> included filter
 *   -key:value         -> excluded filter
 *   "some free text"    -> free-text term
 *   foo                -> free-text term
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
                $filters[] = new SearchQueryFilter($matches[1], $matches[2], $exclude);
            } else {
                $terms[] = $raw;
            }
        }

        return new SearchQuery($filters, $terms);
    }

    /**
     * Splits a query string on whitespace, honoring single/double-quoted
     * substrings (which may appear anywhere within a token, e.g. `key:"a b"`).
     *
     * @return string[]
     */
    private function tokenize(string $query): array
    {
        $tokens = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;

        $length = strlen($query);
        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];

            if ($inQuotes) {
                if ($char === $quoteChar) {
                    $inQuotes = false;
                } else {
                    $current .= $char;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inQuotes = true;
                $quoteChar = $char;
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
}
