<?php

namespace AppBundle\Pricing\Matrix;

/**
 * One row or one column of a pricing matrix.
 *
 * The key is stable for the lifetime of the entry: rules generated from a cell are
 * matched back to that cell by (row key, column key), so that reordering or inserting
 * rows does not make the generator drop and recreate unrelated rules.
 */
final class MatrixAxisEntry
{
    public function __construct(
        public readonly string $key,
        public readonly ?string $label = null,
        // Numeric axes: inclusive bounds, either of which may be null for an open-ended entry
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        // Enumerated axes: the zone name, the time slot IRI, ...
        public readonly ?string $value = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['key']) || '' === trim((string) $data['key'])) {
            throw new \InvalidArgumentException('A matrix axis entry needs a key');
        }

        return new self(
            key: (string) $data['key'],
            label: isset($data['label']) ? (string) $data['label'] : null,
            min: isset($data['min']) ? (int) $data['min'] : null,
            max: isset($data['max']) ? (int) $data['max'] : null,
            value: isset($data['value']) ? (string) $data['value'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'min' => $this->min,
            'max' => $this->max,
            'value' => $this->value,
        ], fn($value) => null !== $value);
    }
}
