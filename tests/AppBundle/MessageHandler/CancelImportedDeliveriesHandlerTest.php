<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Message\CancelImportedDeliveries;
use AppBundle\MessageHandler\CancelImportedDeliveriesHandler;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class CancelImportedDeliveriesHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private array $fixtures;
    private DeliveryImportQueue $queue;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        $this->fixtures = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_taxation.yml',
            __DIR__.'/../../../fixtures/ORM/payment_methods.yml',
            __DIR__.'/../../../fixtures/ORM/stores.yml',
        ]);

        $this->queue = new DeliveryImportQueue();
        $this->queue->setStore($this->fixtures['store_1']);
        $this->queue->setFilename('deliveries.csv');
        $this->queue->setStatus(DeliveryImportQueue::STATUS_COMPLETED);

        $this->entityManager->persist($this->queue);
        $this->entityManager->flush();
    }

    /**
     * As saved by ImportDeliveriesHandler, with its order
     */
    private function importDelivery(Store $store, string $date): Delivery
    {
        $delivery = Delivery::createWithAddress($this->fixtures['address_1'], $this->fixtures['address_2']);
        $delivery->setPickupRange(new \DateTime($date.' 10:00:00'), new \DateTime($date.' 10:30:00'));
        $delivery->setDropoffRange(new \DateTime($date.' 11:00:00'), new \DateTime($date.' 11:30:00'));
        $delivery->setMetadata('delivery_import_id', $this->queue->getId());

        $store->addDelivery($delivery);
        $this->entityManager->persist($delivery);

        self::getContainer()->get(DeliveryOrderManager::class)->createOrder($delivery);
        $this->entityManager->flush();

        return $delivery;
    }

    private function cancelDeliveries(): void
    {
        $statusManager = self::getContainer()->get(DeliveryImportStatusManager::class);
        $this->assertTrue($statusManager->cancellingDeliveries($this->queue));

        // As received by a worker: the events it causes, cancelling the orders,
        // are only handled once the message is
        self::getContainer()->get(MessageBusInterface::class)->dispatch(
            new CancelImportedDeliveries($this->queue->getId()),
            [new ReceivedStamp('async')]
        );

        $this->entityManager->clear();
    }

    private function reload(Delivery $delivery): Delivery
    {
        return $this->entityManager->find(Delivery::class, $delivery->getId());
    }

    public function testDeliveriesAreCancelledButThoseUnderWay(): void
    {
        $toCancel = $this->importDelivery($this->fixtures['store_1'], '2026-12-01');

        $underWay = $this->importDelivery($this->fixtures['store_1'], '2026-12-02');
        $underWay->getPickup()->setStatus(Task::STATUS_DONE);

        $alreadyCancelled = $this->importDelivery($this->fixtures['store_1'], '2026-12-03');
        foreach ($alreadyCancelled->getTasks() as $task) {
            $task->setStatus(Task::STATUS_CANCELLED);
        }

        // Not from this import: the metadata of a store is its own
        $otherStore = $this->importDelivery($this->fixtures['store_2'], '2026-12-04');

        $this->entityManager->flush();

        $this->cancelDeliveries();

        foreach ($this->reload($toCancel)->getTasks() as $task) {
            $this->assertEquals(Task::STATUS_CANCELLED, $task->getStatus());
        }
        $this->assertEquals(OrderInterface::STATE_CANCELLED, $this->reload($toCancel)->getOrder()->getState());

        $this->assertEquals(Task::STATUS_DONE, $this->reload($underWay)->getPickup()->getStatus());
        $this->assertEquals(Task::STATUS_TODO, $this->reload($underWay)->getDropoff()->getStatus());
        $this->assertNotEquals(OrderInterface::STATE_CANCELLED, $this->reload($underWay)->getOrder()->getState());

        foreach ($this->reload($otherStore)->getTasks() as $task) {
            $this->assertEquals(Task::STATUS_TODO, $task->getStatus());
        }

        $queue = $this->entityManager->find(DeliveryImportQueue::class, $this->queue->getId());
        $this->assertEquals(DeliveryImportQueue::DELIVERIES_CANCELLED, $queue->getDeliveriesCancellation());
        $this->assertEquals(3, $queue->getDeliveriesToCancel());
        // The one cancelled since by a dispatcher counts as cancelled
        $this->assertEquals(2, $queue->getCancelledDeliveries());
        $this->assertEquals(1, $queue->getSkippedDeliveries());
    }

    /**
     * Its order is cancelled once the message is handled: when that fails, the retry finds its tasks cancelled
     */
    public function testOrderLeftByAFailedAttemptIsCancelled(): void
    {
        $delivery = $this->importDelivery($this->fixtures['store_1'], '2026-12-01');
        foreach ($delivery->getTasks() as $task) {
            $task->setStatus(Task::STATUS_CANCELLED);
        }
        $this->entityManager->flush();

        $this->assertNotEquals(OrderInterface::STATE_CANCELLED, $delivery->getOrder()->getState());

        $this->cancelDeliveries();

        $this->assertEquals(OrderInterface::STATE_CANCELLED, $this->reload($delivery)->getOrder()->getState());
    }

    public function testMessageHandedOutAgainOnceDoneIsSkipped(): void
    {
        $delivery = $this->importDelivery($this->fixtures['store_1'], '2026-12-01');

        $this->cancelDeliveries();

        // The dispatcher restored it since
        $delivery = $this->reload($delivery);
        foreach ($delivery->getTasks() as $task) {
            $task->setStatus(Task::STATUS_TODO);
        }
        $this->entityManager->flush();

        $handler = self::getContainer()->get(CancelImportedDeliveriesHandler::class);
        $handler(new CancelImportedDeliveries($this->queue->getId()));

        $this->entityManager->clear();

        foreach ($this->reload($delivery)->getTasks() as $task) {
            $this->assertEquals(Task::STATUS_TODO, $task->getStatus());
        }
    }
}
