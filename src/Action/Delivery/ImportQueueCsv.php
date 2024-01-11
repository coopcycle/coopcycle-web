<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery\ImportQueue;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImportQueueCsv
{
    public function __construct(private Filesystem $deliveryImportsFilesystem)
    {}

    public function __invoke(ImportQueue $data)
    {
        // TODO Also manage XLSX & ODT files

        $contents = $this->deliveryImportsFilesystem->read($data->getFilename());

        $response = new Response($contents);
        $response->headers->add(['Content-Type' => 'text/csv']);

        return $response;
    }
}

