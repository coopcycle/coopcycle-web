<?php

namespace AppBundle\Pricing\Matrix;

/**
 * One axis of a pricing matrix: a single variable, plus the entries (rows or columns)
 * that split it into ranges or values.
 *
 * The variable comes from a fixed list, so that an axis can always be turned into a
 * condition and checked for gaps and overlaps. Arbitrary rule expressions are not
 * accepted here on purpose.
 */
final class MatrixAxis
{
    public const VARIABLE_VOLUME_UNITS = 'packages.totalVolumeUnits()';
    public const VARIABLE_DELIVERY_VOLUME_UNITS = 'delivery.packages.totalVolumeUnits()';
    public const VARIABLE_WEIGHT = 'weight';
    public const VARIABLE_DISTANCE = 'distance';
    public const VARIABLE_ORDER_ITEMS_TOTAL = 'order.itemsTotal';
    public const VARIABLE_ZONE = 'zone';
    public const VARIABLE_TIME_SLOT = 'time_slot';

    public const NUMERIC_VARIABLES = [
        self::VARIABLE_VOLUME_UNITS,
        self::VARIABLE_DELIVERY_VOLUME_UNITS,
        self::VARIABLE_WEIGHT,
        self::VARIABLE_DISTANCE,
        self::VARIABLE_ORDER_ITEMS_TOTAL,
    ];

    public const ENUMERATED_VARIABLES = [
        self::VARIABLE_ZONE,
        self::VARIABLE_TIME_SLOT,
    ];

    public const ADDRESS_SOURCE_PICKUP = 'pickup';
    public const ADDRESS_SOURCE_DROPOFF = 'dropoff';
    public const ADDRESS_SOURCE_TASK = 'task';

    /**
     * @param MatrixAxisEntry[] $entries
     */
    public function __construct(
        public readonly string $variable,
        public readonly array $entries,
        /** Which address a zone axis reads; null for every other variable */
        public readonly ?string $addressSource = null,
    ) {
        if (!in_array($variable, self::variables(), true)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid matrix axis variable', $variable));
        }

        if (self::VARIABLE_ZONE === $variable && null === $addressSource) {
            throw new \InvalidArgumentException('A zone axis needs an address source');
        }
    }

    public static function variables(): array
    {
        return array_merge(self::NUMERIC_VARIABLES, self::ENUMERATED_VARIABLES);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            variable: (string) ($data['variable'] ?? ''),
            entries: array_map(
                fn(array $entry) => MatrixAxisEntry::fromArray($entry),
                $data['entries'] ?? []
            ),
            addressSource: $data['addressSource'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'variable' => $this->variable,
            'entries' => array_map(fn(MatrixAxisEntry $entry) => $entry->toArray(), $this->entries),
        ];

        if (null !== $this->addressSource) {
            $data['addressSource'] = $this->addressSource;
        }

        return $data;
    }

    public function isNumeric(): bool
    {
        return in_array($this->variable, self::NUMERIC_VARIABLES, true);
    }

    public function getEntry(string $key): ?MatrixAxisEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The expression fragment matching one entry of this axis.
     *
     * Only the operators the rest of the pricing UI knows how to render and parse back
     * ('<', '>' and 'in') are used, hence the -1/+1 on open-ended bounds: every numeric
     * variable here is an integer (volume units, grams, meters, cents).
     */
    public function conditionFor(MatrixAxisEntry $entry): string
    {
        if (self::VARIABLE_ZONE === $this->variable) {
            return sprintf('in_zone(%s.address, "%s")', $this->addressSource, $entry->value);
        }

        if (self::VARIABLE_TIME_SLOT === $this->variable) {
            return sprintf('%s == "%s"', $this->variable, $entry->value);
        }

        if (null !== $entry->min && null !== $entry->max) {
            return sprintf('%s in %d..%d', $this->variable, $entry->min, $entry->max);
        }

        if (null !== $entry->min) {
            return sprintf('%s > %d', $this->variable, $entry->min - 1);
        }

        if (null !== $entry->max) {
            return sprintf('%s < %d', $this->variable, $entry->max + 1);
        }

        throw new \InvalidArgumentException(
            sprintf('Entry "%s" of a %s axis has no bound', $entry->key, $this->variable)
        );
    }
}
