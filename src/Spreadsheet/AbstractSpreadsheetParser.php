<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Spreadsheet\Column\ColumnDefinition;
use AppBundle\Spreadsheet\Column\ColumnMapping;
use League\Csv\Writer;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use Symfony\Component\HttpFoundation;

abstract class AbstractSpreadsheetParser
{
    /**
     * How many rows of the file the mapping screen shows, and sniffs the columns from
     */
    const PREVIEW_ROWS = 5;

    /**
     * What the text columns hold, as a longer value doesn't fit and fails the whole
     * import when it's saved
     */
    const MAX_TEXT_LENGTH = 255;

    const MIME_TYPE_ODS = [
        'application/vnd.oasis.opendocument.spreadsheet'
    ];
    const MIME_TYPE_CSV = [
        'text/csv',
        'text/plain'
    ];
    const MIME_TYPE_XLSX = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream'
    ];

    public static function getMimeTypes()
    {
        return array_merge(
            self::MIME_TYPE_CSV,
            self::MIME_TYPE_ODS,
            self::MIME_TYPE_XLSX
        );
    }

    public static function getFileExtension($mimeType)
    {
        if (in_array($mimeType, self::MIME_TYPE_CSV)) {
            return 'csv';
        }

        if (in_array($mimeType, self::MIME_TYPE_ODS)) {
            return 'ods';
        }

        if (in_array($mimeType, self::MIME_TYPE_XLSX)) {
            return 'xlsx';
        }

        throw new \Exception('Unsupported file type');
    }

    /**
     * @param ?int $maxRows only read that many rows, the header row included
     */
    protected function createReader($filename, ?int $maxRows = null): IReader
    {
        $mimeType = mime_content_type($filename);

        $reader = match (true) {
            in_array($mimeType, self::MIME_TYPE_CSV) => new Csv(),
            in_array($mimeType, self::MIME_TYPE_ODS) => new Ods(),
            in_array($mimeType, self::MIME_TYPE_XLSX) => new Xlsx(),
            default => throw new \Exception('Unsupported file type'),
        };

        if (!is_null($maxRows)) {
            $reader->setReadFilter(new RowLimitReadFilter($maxRows));
        }

        return $reader;
    }

    abstract public function getExampleData(): array;

    /**
     * The fields this parser reads, for the mapping screen to offer and for
     * ColumnDetector to match a file's columns against.
     *
     * A parser that doesn't describe its columns can't be imported with a mapping, and
     * keeps reading the file by its header names.
     *
     * @return ColumnDefinition[]
     */
    public function getColumns(): array
    {
        return [];
    }

    /**
     * @return string[] every field name parseData() may read
     */
    public function getColumnKeys(): array
    {
        return array_map(fn (ColumnDefinition $column) => $column->key, $this->getColumns());
    }

    /**
     * The fields a file has to carry to be imported at all.
     *
     * About the column, not the value: a row with an empty address is kept or rejected
     * depending on the options, but a file with no address column at all is of no use.
     *
     * @return string[]
     */
    public function getRequiredColumnKeys(): array
    {
        return array_values(array_map(
            fn (ColumnDefinition $column) => $column->key,
            array_filter($this->getColumns(), fn (ColumnDefinition $column) => $column->isRequired())
        ));
    }

    /**
     * The fields a file has to carry one of, when either will do.
     *
     * An address can be given as an address or as coordinates, which getRequiredColumnKeys()
     * can't say: it would ask for both.
     *
     * @return array<int,string[]> each entry a list of keys, one of which must be mapped
     */
    public function getRequiredColumnGroups(): array
    {
        return [];
    }

    abstract public function parseData(array $data, array $options = []): array | SpreadsheetParseResult;

    /**
     * Options:
     * - column_mapping: which field each column of the file holds, as a ColumnMapping or as
     *   the array it is stored as. Without it the columns are read by their header name, as
     *   they always were: that is what the API and the imports that predate the mapping
     *   screen rely on.
     *
     * @param FlysystemFile|HttpFoundation\File\File|string $file
     * @throws \Exception
     */
    public function parse($file, array $options = [])
    {
        $spreadsheet = $this->loadSpreadsheet($file);

        // The first sheet only, as toRawData(), toCsv() and getHeaderRow() do, and as the
        // mapping screen shows: reading every sheet would take the header from the last one
        // and pile up the rows of all of them
        $sheet = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());

        $data = [];
        $header = [];

        $definitionOfEmptyFlags =
            CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL;

        foreach ($sheet->toArray() as $rowIndex => $row) {
            if ($rowIndex === 0) {
                $header = $row;
                continue;
            }

            // Verify that the row is not completely empty
            if ($sheet->isEmptyRow($rowIndex, $definitionOfEmptyFlags)) {
                continue;
            }
            if (0 === count(array_filter($row, fn ($value) => !empty(trim($value ?? ''))))) {
                continue;
            }

            $data[] = $row;
        }

        $mapping = $this->columnMappingFromOptions($options);

        if (!is_null($mapping)) {
            $canonicalKeys = $this->getColumnKeys();

            $data = array_map(fn ($row) => $mapping->apply($row, $canonicalKeys), $data);
        } else {
            $data = array_map(function ($row) use ($header) {

                // Fix the file structure if some columns are "merged"
                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), '');
                }

                // More cells than the header, which array_combine() refuses outright
                if (count($row) > count($header)) {
                    $row = array_slice($row, 0, count($header));
                }

                return array_combine($header, $row);
            }, $data);
        }

        return $this->parseData($data, $options);
    }

    /**
     * The mapping to read the file with, if this import has one.
     *
     * It is stored as json and travels as an array: taken as is, a mapping to no column at
     * all would read every column as the field null.
     */
    private function columnMappingFromOptions(array $options): ?ColumnMapping
    {
        $mapping = $options['column_mapping'] ?? null;

        if (is_array($mapping)) {
            $mapping = ColumnMapping::fromArray($mapping);
        }

        if (!$mapping instanceof ColumnMapping || $mapping->isEmpty()) {
            return null;
        }

        return $mapping;
    }

    /**
     * The first rows of a file, to show on the mapping screen and to suggest a mapping from.
     *
     * @param FlysystemFile|HttpFoundation\File\File|string $file
     * @throws \Exception
     */
    public function preview($file, int $rows = self::PREVIEW_ROWS): SpreadsheetPreview
    {
        // The header row on top of the rows shown
        $spreadsheet = $this->loadSpreadsheet($file, $rows + 1);

        $sheet = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());

        $allRows = $sheet->toArray();

        $header = array_map(
            fn ($value) => trim((string) ($value ?? '')),
            array_values(array_shift($allRows) ?? [])
        );

        $dataRows = [];

        foreach ($allRows as $row) {
            $row = array_values($row);

            if (0 === count(array_filter($row, fn ($value) => '' !== trim((string) ($value ?? ''))))) {
                continue;
            }

            $dataRows[] = $row;

            if (count($dataRows) >= $rows) {
                break;
            }
        }

        return new SpreadsheetPreview($header, $dataRows);
    }

    /**
     * @param FlysystemFile|HttpFoundation\File\File|string $file
     * @param ?int $maxRows only read that many rows, the header row included
     * @throws \Exception
     */
    public function loadSpreadsheet($file, ?int $maxRows = null): Spreadsheet
    {
        $isTempFile = false;

        if (is_object($file)) {

            if ($file instanceof FlysystemFile) {

                $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_spreadsheet_parser_');
                if (false === file_put_contents($tempnam, $file->getFilesystem()->read($file->getPathName()))) {
                    throw new \Exception(sprintf('Could not write temp file %s', $tempnam));
                }

                $isTempFile = true;
                $filename = $tempnam;

            } elseif ($file instanceof HttpFoundation\File\File) {
                $filename = $file->getPathname();
            }

        } elseif (is_string($file)) {
            $filename = $file;
        }

        $reader = $this->createReader($filename, $maxRows);

        $spreadsheet = $reader->load($filename);

        if ($isTempFile) {
            unlink($filename);
        }

        return $spreadsheet;
    }

    /**
     * @param FlysystemFile|HttpFoundation\File\File|string $file
     */
    public function toCsv($file): string
    {
        $spreadsheet = $this->loadSpreadsheet($file);

        $records = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex())->toArray();

        $csv = Writer::createFromString();

        $csv->insertAll($records);

        return $csv->toString();
    }

    /**
     * @param FlysystemFile|HttpFoundation\File\File|string $file
     */
    public function toRawData($file): array
    {
        $spreadsheet = $this->loadSpreadsheet($file);

        return $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex())->toArray();
    }

    public function getHeaderRow(Spreadsheet $spreadsheet): array
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach ($sheet->toArray() as $rowIndex => $row) {
                if ($rowIndex === 0) {
                    return $row;
                }
            }
        }

        return [];
    }
}
