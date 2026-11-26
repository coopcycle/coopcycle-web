<?php

namespace AppBundle\Action\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use League\Flysystem\Filesystem;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Csv
{
    public function __construct(
        private Filesystem $deliveryImportsFilesystem,
        private DeliverySpreadsheetParser $spreadsheetParser)
    {}

    public function __invoke(ImportQueue $data)
    {
        $file = new FlysystemFile($data->getFilename(), $this->deliveryImportsFilesystem);

        $response = new Response($this->spreadsheetParser->toCsv($file));
        $response->headers->add(['Content-Type' => 'text/csv']);

        return $response;
    }
}

