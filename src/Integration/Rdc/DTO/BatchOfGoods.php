<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

/**
 * Goods handled together at a location, e.g. 6 cardboard parcels: an entry
 * of `containedBatchesOfGoods`. The container type and subtype are free text.
 */
final class BatchOfGoods
{
    public function __construct(
        public readonly ?string $containerType = null,
        public readonly ?string $containerSubtype = null,
        /** The number of containers, null when not given as a whole number of them */
        public readonly ?int $quantity = null,
    ) {}

    public static function parse(array $batch): self
    {
        return new self(
            containerType: self::text($batch['containerType'] ?? null),
            containerSubtype: self::text($batch['containerSubtype'] ?? null),
            quantity: self::containerCount($batch['quantity'] ?? null),
        );
    }

    /**
     * The keys a package is looked up with in the package mapping, the most
     * precise first: "PARCEL/CARDBOARD", then "PARCEL".
     *
     * @return string[]
     */
    public function getMappingKeys(): array
    {
        if (is_null($this->containerType)) {
            return [];
        }

        if (is_null($this->containerSubtype)) {
            return [$this->containerType];
        }

        return [sprintf('%s/%s', $this->containerType, $this->containerSubtype), $this->containerType];
    }

    public function toArray(): array
    {
        return [
            'containerType' => $this->containerType,
            'containerSubtype' => $this->containerSubtype,
            'quantity' => $this->quantity,
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function containerCount(mixed $quantity): ?int
    {
        if (!is_array($quantity)) {
            return null;
        }

        // A quantity in another unit (a weight, a volume) is not a count
        $unit = $quantity['unit'] ?? 'CONTAINER';
        if ($unit !== 'CONTAINER') {
            return null;
        }

        $value = $quantity['value'] ?? null;
        if (!is_numeric($value) || (float) $value <= 0 || floor((float) $value) !== (float) $value) {
            return null;
        }

        return (int) $value;
    }
}
