<?php

namespace AppBundle\Utils\SearchQuery;

/**
 * A single "key:value" (or "-key:value") token parsed from a search query string.
 *
 * @see SearchQueryParser
 */
final class SearchQueryFilter
{
    /**
     * The separator inside a "[from TO to]" range - uppercase, like the "OR"
     * of a value list, and matching the Lucene/Datadog convention.
     */
    private const RANGE_SEPARATOR = ' TO ';

    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly bool $exclude = false,
    ) {
    }

    /**
     * Whether this filter's value is a well-formed "[from TO to]" range, as
     * opposed to a single value. A malformed range (e.g. "[2026-09-25]") is
     * not one, and is left to be handled as an ordinary - and, for a date,
     * unparseable, hence ignored - value.
     */
    public function isRange(): bool
    {
        return null !== $this->getRange();
    }

    /**
     * This filter's range bounds, unquoted, or null if it isn't a range.
     *
     * @return array{from: string, to: string}|null
     */
    public function getRange(): ?array
    {
        if (!preg_match('/^\[(.*)\]$/', $this->value, $matches)) {
            return null;
        }

        $parts = explode(self::RANGE_SEPARATOR, $matches[1], 2);

        if (2 !== count($parts)) {
            return null;
        }

        $from = trim($parts[0], " \t\n\r\0\x0B\"'");
        $to = trim($parts[1], " \t\n\r\0\x0B\"'");

        if ('' === $from || '' === $to) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }
}
