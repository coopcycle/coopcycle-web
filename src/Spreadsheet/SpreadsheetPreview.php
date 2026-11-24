<?php

namespace AppBundle\Spreadsheet;

/**
 * The first few rows of a file, to show on the mapping screen and to suggest a mapping from.
 */
final class SpreadsheetPreview
{
    /**
     * @param array<int,string> $header the header row, by column index
     * @param array<int,array<int,mixed>> $rows the first data rows, by column index
     */
    public function __construct(
        private readonly array $header,
        private readonly array $rows,
    ) {}

    /**
     * @return array<int,string>
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return int[] the column indexes of the file, header or not
     */
    public function getColumnIndexes(): array
    {
        return array_keys($this->header);
    }

    /**
     * What the file holds in one column, to show next to its field and to sniff its type from.
     *
     * @return string[] without the empty values, which say nothing about the column
     */
    public function getSampleValues(int $index): array
    {
        $values = [];

        foreach ($this->rows as $row) {
            $value = $row[$index] ?? null;

            if (null === $value || '' === trim((string) $value)) {
                continue;
            }

            $values[] = (string) $value;
        }

        return $values;
    }

    public function isEmpty(): bool
    {
        return empty(array_filter($this->header, fn ($value) => '' !== trim((string) $value)));
    }

    public function hasRows(): bool
    {
        return !empty($this->rows);
    }
}
