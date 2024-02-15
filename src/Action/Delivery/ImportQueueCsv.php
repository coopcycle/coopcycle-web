<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery\ImportQueue;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImportQueueCsv
{
    public function __construct(
        private Filesystem $deliveryImportsFilesystem,
        private DeliverySpreadsheetParser $spreadsheetParser)
    {}

    public function __invoke(ImportQueue $data)
    {
        $file = $this->deliveryImportsFilesystem->get($data->getFilename());

        $response = new Response($this->spreadsheetParser->toCsv($file));
        $response->headers->add(['Content-Type' => 'text/csv']);

        return $response;
    }
}

