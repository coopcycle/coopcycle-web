<?php

namespace AppBundle\Spreadsheet\Column;

/**
 * One field a spreadsheet column can be mapped to.
 *
 * The parsers describe the columns they read with these, which is what the mapping
 * screen offers and what ColumnDetector matches a file's columns against.
 */
final class ColumnDefinition
{
    /**
     * @param string $key the canonical column name the parser reads, e.g. "pickup.address"
     * @param string[] $aliases other names this field is known by, matched after the key itself.
     *   Written here rather than in the translation catalogs: detection must not depend on the
     *   locale of whoever uploads the file, and a parallel dictionary would be one more thing
     *   for translators to keep in sync.
     */
    public function __construct(
        public readonly string $key,
        public readonly ColumnType $type,
        public readonly ColumnGroup $group,
        public readonly bool $required = false,
        public readonly array $aliases = [],
    ) {}

    /**
     * Whether the column has to be mapped for the file to be imported at all.
     *
     * Not whether the value may be empty: an address is required as a column, while a row
     * without one is kept or rejected depending on create_task_if_address_not_geocoded.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    public function labelKey(): string
    {
        return sprintf('import.column.%s', $this->key);
    }
}
