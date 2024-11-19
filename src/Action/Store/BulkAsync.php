<?php

namespace AppBundle\Action\Store;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Message\ImportDeliveries;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;


class BulkAsync
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected MessageBusInterface $messageBus,
        protected RequestStack $requestStack,
        protected Hashids $hashids8,
        protected Filesystem $deliveryImportsFilesystem
        )
    {}

    public function __invoke($data)
    {
        $queue = new DeliveryImportQueue();
        $queue->setStore($data);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        $csv = $this->requestStack->getCurrentRequest()->getContent();

        $now = new DateTime();
        $nowFormatted = $now->format('Y-m-d_G:i:s');

        $filename = sprintf('%s_%s_%s.%s', 'API_import', $nowFormatted, $this->hashids8->encode($queue->getId()), 'csv');

        $this->deliveryImportsFilesystem->write($filename, $csv);

        $queue->setFilename($filename);
        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new ImportDeliveries($filename, ['create_task_if_address_not_geocoded' => true]),
            [ new DelayStamp(5000) ]
        );

        return $queue;
    }
}
