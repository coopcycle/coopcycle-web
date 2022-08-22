<?php

namespace AppBundle\Action\Order;


use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\ReceiptGenerator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

class GenerateInvoice
{

    private ReceiptGenerator $receiptGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(ReceiptGenerator $receiptGenerator, EntityManagerInterface $entityManager)
    {
        $this->receiptGenerator = $receiptGenerator;
        $this->entityManager = $entityManager;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Order $data, Request $request): Order
    {

        if (!$data->hasReceipt()) {

            $body = [];
            $content = $request->getContent();
            if (!empty($content)) {
                $body = json_decode($content, true);
            }

            $receipt = $this->receiptGenerator->create($data);


            if (isset($body['billingAddress']) && !empty($body['billingAddress'])) {
                $receipt->setBillingAddress($body['billingAddress']);
            }


            $data->setReceipt($receipt);

            $this->entityManager->flush();

            $this->receiptGenerator->generate($data, sprintf('%s.pdf', $data->getNumber()));

        }
        return $data;
    }
}
