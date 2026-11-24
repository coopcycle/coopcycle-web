<?php

namespace AppBundle\Spreadsheet\Column;

/**
 * Which field each column of a file holds, as picked on the mapping screen.
 *
 * Columns are keyed by their position in the file, not by their header text: a file can
 * have two columns with the same header, or none at all, and the mapping still has to say
 * which is which.
 */
final class ColumnMapping
{
    /**
     * @param array<int,string> $columns column index => canonical column name.
     *   The columns left out are the ones to ignore.
     */
    private function __construct(private readonly array $columns)
    {}

    /**
     * @param array<int|string,?string> $columns as stored on the import, or as submitted by the
     *   mapping form, where an ignored column is an empty value
     */
    public static function fromArray(array $columns): self
    {
        $mapping = [];

        foreach ($columns as $index => $key) {
            if (null === $key || '' === $key) {
                continue;
            }

            $mapping[(int) $index] = $key;
        }

        ksort($mapping);

        return new self($mapping);
    }

    /**
     * @return array<int,string>
     */
    public function toArray(): array
    {
        return $this->columns;
    }

    public function isEmpty(): bool
    {
        return empty($this->columns);
    }

    /**
     * @return string[] the fields the file is mapped to, in column order
     */
    public function mappedKeys(): array
    {
        return array_values($this->columns);
    }

    public function keyAt(int $index): ?string
    {
        return $this->columns[$index] ?? null;
    }

    /**
     * The fields mapped more than once, which no import can make sense of.
     *
     * @return string[]
     */
    public function duplicates(): array
    {
        $counts = array_count_values($this->columns);

        return array_values(array_keys(array_filter($counts, fn (int $count) => $count > 1)));
    }

    /**
     * Turns a row of the file into a record keyed by field name.
     *
     * Every field of $canonicalKeys is in the record, those the file doesn't carry being
     * null: the parsers read some of their columns without checking they are there first.
     *
     * @param array<int,mixed> $row
     * @param string[] $canonicalKeys every field the parser may read
     * @return array<string,mixed>
     */
    public function apply(array $row, array $canonicalKeys): array
    {
        $record = array_fill_keys($canonicalKeys, null);

        $row = array_values($row);

        foreach ($this->columns as $index => $key) {
            // A row shorter than the header, or a mapping made from a different file
            $record[$key] = $row[$index] ?? null;
        }

        return $record;
    }
}
