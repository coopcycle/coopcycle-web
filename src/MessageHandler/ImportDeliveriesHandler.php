<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Exception\DeliveryImportRolledBackException;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryImporter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class ImportDeliveriesHandler
{
    public function __construct(
        private readonly DeliveryImporter $deliveryImporter,
    )
    {
    }

    public function __invoke(ImportDeliveries $message): void
    {
        try {
            $this->deliveryImporter->import($message->getFilename(), $message->getOptions());
        } catch (DeliveryImportRolledBackException $e) {
            // Nothing was imported, and a retry would most likely fail the same way:
            // the file can be imported again as a whole
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }
}
