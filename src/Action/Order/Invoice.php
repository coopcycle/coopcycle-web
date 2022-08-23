<?php

namespace AppBundle\Action\Order;


use AppBundle\Entity\Sylius\Order;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Invoice
{

    private Filesystem $receiptsFilesystem;

    public function __construct(Filesystem $receiptsFilesystem)
    {
        $this->receiptsFilesystem = $receiptsFilesystem;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Order $data, Request $request)
    {
        if (!$data->hasReceipt()) {
            throw new \Exception(sprintf('Receipt for order "%s" does not exist', $data->getNumber()));
        }

        $filename = sprintf('%s.pdf', $data->getNumber());

        if (!$this->receiptsFilesystem->has($filename)) {
            throw new \Exception(sprintf('File %s.pdf does not exist', $data->getNumber()));
        }

        return match ($request->get('format')) {
            'pdf' => new Response((string)$this->receiptsFilesystem->read($filename), 200, [
                'Content-Disposition' => 'inline',
                'Content-Type' => 'application/pdf',
            ]),
            default => new JsonResponse([
                'invoice' => base64_encode($this->receiptsFilesystem->read($filename))
            ]),
        };

    }
}
