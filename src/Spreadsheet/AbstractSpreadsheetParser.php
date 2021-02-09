<?php

namespace AppBundle\Spreadsheet;

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Type;
use League\Flysystem\File;

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

    protected function createReader($filename)
    {
        $mimeType = mime_content_type($filename);

        if (in_array($mimeType, self::MIME_TYPE_CSV)) {
            return $this->createCsvReader($filename);
        }

        if (in_array($mimeType, self::MIME_TYPE_ODS)) {
            return ReaderEntityFactory::createODSReader();
        }

        if (in_array($mimeType, self::MIME_TYPE_XLSX)) {
            return ReaderEntityFactory::createXLSXReader();
        }

        throw new \Exception('Unsupported file type');
    }

    protected function createCsvReader($filename)
    {
        $csvReader = ReaderEntityFactory::createCSVReader();
        $csvReader->setFieldDelimiter($this->getCsvDelimiter($filename));

        return $csvReader;
    }

    protected function getCsvDelimiter($filename)
    {
        $delimiters = array(
            ';' => 0,
            ',' => 0,
            "\t" => 0,
            '|' => 0,
        );

        $handle = fopen($filename, "r");
        $firstLine = fgets($handle);
        fclose($handle);

        foreach ($delimiters as $delimiter => &$count) {
            $count = count(str_getcsv($firstLine, $delimiter));
        }

        return array_search(max($delimiters), $delimiters);
    }

    abstract public function validateHeader(array $header);

    abstract public function getExampleData(): array;

    /**
     * @param array $data
     * @param array $options
     * @return array
     */
    abstract public function parseData(array $data, array $options = []): array;

    /**
     * @param File|string $file
     * @param array $options
     * @throws IOException
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
                    throw new IOException(sprintf('Could not write temp file %s', $tempnam));
                }

                $isTempFile = true;
                $filename = $tempnam;
            }
        }

        $reader = $this->createReader($filename);

        $reader->open($filename);

        $data = [];
        $header = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    $header = $row->toArray();
                    continue;
                }

                // Verify that the row is not completely empty
                if (0 === count(array_filter($row->toArray()))) {
                    continue;
                }

                $data[] = $row->toArray();
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
