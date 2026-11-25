<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Exception\DeliveryImportRolledBackException;
use AppBundle\Message\ImportDeliveries;
use AppBundle\MessageHandler\ImportDeliveriesHandler;
use AppBundle\Service\DeliveryImporter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class ImportDeliveriesHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testFileIsImportedWithTheOptionsOfTheMessage(): void
    {
        $importer = $this->prophesize(DeliveryImporter::class);
        $importer->import('deliveries.csv', ['create_task_if_address_not_geocoded' => true])->willReturn(2);

        $handler = new ImportDeliveriesHandler($importer->reveal());
        $handler(new ImportDeliveries('deliveries.csv', ['create_task_if_address_not_geocoded' => true]));

        $importer->import('deliveries.csv', ['create_task_if_address_not_geocoded' => true])->shouldHaveBeenCalledOnce();
    }

    /**
     * Nothing was imported, and a retry would most likely fail the same way
     */
    public function testImportThatWasRolledBackIsNotRetried(): void
    {
        $importer = $this->prophesize(DeliveryImporter::class);
        $importer->import('deliveries.csv', [])->willThrow(
            new DeliveryImportRolledBackException('Importing deliveries from deliveries.csv failed: Deadlock detected')
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Importing deliveries from deliveries.csv failed: Deadlock detected');

        $handler = new ImportDeliveriesHandler($importer->reveal());
        $handler(new ImportDeliveries('deliveries.csv'));
    }

    /**
     * Before anything was saved (a timeout, say): the worker retries it,
     * see DeliveryImportFailureSubscriber
     */
    public function testOtherFailuresAreRetried(): void
    {
        $importer = $this->prophesize(DeliveryImporter::class);
        $importer->import('deliveries.csv', [])->willThrow(new \RuntimeException('Could not read the file'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not read the file');

        $handler = new ImportDeliveriesHandler($importer->reveal());
        $handler(new ImportDeliveries('deliveries.csv'));
    }
}
