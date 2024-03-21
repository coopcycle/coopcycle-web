<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery\ImportQueue;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use League\Csv\Writer;
use League\Flysystem\Filesystem;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImportQueueRedownload
{
    public function __construct(
        private Filesystem $deliveryImportsFilesystem,
        private DeliverySpreadsheetParser $spreadsheetParser)
    {}

    public function __invoke(ImportQueue $data)
    {
        $file = new FlysystemFile($data->getFilename(), $this->deliveryImportsFilesystem);

        $errors = $data->getErrors();

        $rawData = $this->spreadsheetParser->toRawData($file);

        $rowsWithErrors = array_map(fn ($error) => $error['row'], $errors);

        $records = array_filter($rawData, function($value, $key) use ($rowsWithErrors) {
            return in_array($key, $rowsWithErrors);
        }, ARRAY_FILTER_USE_BOTH);

        $csv = Writer::createFromString();

        $csv->insertOne($rawData[0]);
        $csv->insertAll($records);

        $response = new Response($csv->toString());
        $response->headers->add(['Content-Type' => 'text/csv']);

        return $response;
    }
}


