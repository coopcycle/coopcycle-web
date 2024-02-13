<?php

namespace AppBundle\Spreadsheet;

use League\Flysystem;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use Symfony\Component\HttpFoundation;

abstract class AbstractSpreadsheetParser
{
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

    protected function createReader($filename): IReader
    {
        $mimeType = mime_content_type($filename);

        if (in_array($mimeType, self::MIME_TYPE_CSV)) {
            return new Csv();
        }

        if (in_array($mimeType, self::MIME_TYPE_ODS)) {
            return new Ods();
        }

        if (in_array($mimeType, self::MIME_TYPE_XLSX)) {
            return new Xlsx();
        }

        throw new \Exception('Unsupported file type');
    }

    abstract public function getExampleData(): array;

    /**
     * @param array $data
     * @param array $options
     * @return array|SpreadsheetParseResult
     */
    abstract public function parseData(array $data, array $options = []): array | SpreadsheetParseResult;

    /**
     * @param Flysystem\File|HttpFoundation\File\File|string $file
     * @param array $options
     * @throws \Exception
     */
    public function parse($file, array $options = [])
    {
        $spreadsheet = $this->loadSpreadsheet($file);

        $data = [];
        $header = [];

        $definitionOfEmptyFlags =
            CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
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
        }

        $data = array_map(function ($row) use ($header) {

            // Fix the file structure if some columns are "merged"
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            return array_combine($header, $row);
        }, $data);

        return $this->parseData($data, $options);
    }

    /**
     * @param Flysystem\File|HttpFoundation\File\File|string $file
     * @return Spreadsheet
     * @throws \Exception
     */
    public function loadSpreadsheet($file): Spreadsheet
    {
        $isTempFile = false;

        if (is_object($file)) {

            if ($file instanceof Flysystem\File) {

                $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_spreadsheet_parser_');
                if (false === file_put_contents($tempnam, $file->read())) {
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

        $reader = $this->createReader($filename);

        $spreadsheet = $reader->load($filename);

        if ($isTempFile) {
            unlink($filename);
        }

        return $spreadsheet;
    }

    /**
     * @param Flysystem\File|HttpFoundation\File\File|string $file
     * @return string
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
     * @param Flysystem\File|HttpFoundation\File\File|string $file
     * @return array
     */
    public function toRawData($file): array
    {
        $spreadsheet = $this->loadSpreadsheet($file);

        return $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex())->toArray();
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @return array
     */
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
