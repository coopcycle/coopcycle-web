<?php

declare(strict_types=1);

namespace AppBundle\Utils;

/**
 * Postal code prefixes an instance keeps: "13" keeps every 13xxx postal code,
 * "75011" only that one. Used by the imports (EDI transporters, RDC) when
 * several instances receive the same shipments.
 */
class PostalCodeFilter
{
    /** @var array<int,string> */
    private array $prefixes;

    public function __construct(array $prefixes = [])
    {
        // An empty prefix would match everything
        $this->prefixes = array_values(array_filter(
            array_map(fn(string $prefix) => trim($prefix), $prefixes),
            fn(string $prefix) => !empty($prefix)
        ));
    }

    public function isEnabled(): bool
    {
        return !empty($this->prefixes);
    }

    /**
     * @return array<int,string>
     */
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }

    /**
     * Whether one of the postal codes starts with one of the prefixes.
     * Empty postal codes never match.
     */
    public function matches(?string ...$postalCodes): bool
    {
        $matching = array_filter(
            array_map(fn(?string $postalCode) => trim($postalCode ?? ''), $postalCodes),
            fn(string $postalCode) => !empty($postalCode) && $this->startsWithAPrefix($postalCode)
        );

        return !empty($matching);
    }

    private function startsWithAPrefix(string $postalCode): bool
    {
        $prefixes = array_filter($this->prefixes, fn(string $prefix) => str_starts_with($postalCode, $prefix));

        return !empty($prefixes);
    }
}
