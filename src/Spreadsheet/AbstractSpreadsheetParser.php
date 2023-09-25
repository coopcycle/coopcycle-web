<?php

namespace AppBundle\Spreadsheet;

use League\Flysystem\File;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

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

    abstract public function validateHeader(array $header);

    abstract public function getExampleData(): array;

    /**
     * @param array $data
     * @param array $options
     * @return array|SpreadsheetParseResult
     */
    abstract public function parseData(array $data, array $options = []): array | SpreadsheetParseResult;

    /**
     * @param File|string $file
     * @param array $options
     * @throws \Exception
     */
    public function parse($file, array $options = [])
    {
        $isTempFile = false;

        if (is_string($file)) {
            $filename = $file;
        } else {
            if ($file instanceof File) {
                $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_spreadsheet_parser_');
                if (false === file_put_contents($tempnam, $file->read())) {
                    throw new \Exception(sprintf('Could not write temp file %s', $tempnam));
                }

                $isTempFile = true;
                $filename = $tempnam;
            }
        }

        $reader = $this->createReader($filename);

        $spreadsheet = $reader->load($filename);

        $data = [];
        $header = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach ($sheet->toArray() as $rowIndex => $row) {
                if ($rowIndex === 0) {
                    $header = $row;
                    continue;
                }

                // Verify that the row is not completely empty
                if (0 === count(array_filter($row))) {
                    continue;
                }

                $data[] = $row;
            }
        }

        try {
            $this->validateHeader($header);
        } catch (\Exception $e) {
            if ($isTempFile) {
                unlink($filename);
            }
            throw $e;
        }

        $data = array_map(function ($row) use ($header) {

            // Fix the file structure if some columns are "merged"
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            return array_combine($header, $row);
        }, $data);

        if ($isTempFile) {
            unlink($filename);
        }

        return $this->parseData($data, $options);
    }
}
