<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\TourRepository;
use AppBundle\Message\ImportDeliveries;
use AppBundle\MessageHandler\ImportDeliveriesHandler;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\LiveUpdatesSuspension;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\ImportStatusManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportDeliveriesHandlerFunctionalTest extends KernelTestCase
{
    use ProphecyTrait;

    const FILENAME = 'deliveries.csv';

    private EntityManagerInterface $entityManager;
    private array $fixtures;

    public function setUp(): void
    {
        // SET UP SYMFONY
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // PURGE DATABASE
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        // LOAD AND PERSIST FIXTURES
        $this->fixtures = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/stores.yml'
        ]);

        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setStore($this->fixtures['store_1']);
        $queue->setFilename(self::FILENAME);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();
    }

    private function createHandler(DeliverySpreadsheetParser $parser, DeliveryOrderManager $deliveryOrderManager): ImportDeliveriesHandler
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write(self::FILENAME, 'not parsed, the parser is mocked');

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate(Argument::any())->willReturn(new ConstraintViolationList());

        return new ImportDeliveriesHandler(
            $this->entityManager,
            $filesystem,
            $parser,
            $validator->reveal(),
            $deliveryOrderManager,
            self::getContainer()->get(ImportStatusManager::class),
            $this->prophesize(DeliveryManager::class)->reveal(),
            new NullLogger(),
            self::getContainer()->get(TourRepository::class),
            self::getContainer()->get(DeliveryCreatedNotifier::class),
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(LiveUpdatesSuspension::class),
            self::getContainer()->get('doctrine'),
        );
    }

    private function createParser(Delivery $first, Delivery $second): DeliverySpreadsheetParser
    {
        $result = new SpreadsheetParseResult();
        $result->setData([
            2 => ['delivery' => $first, 'tourName' => null],
            3 => ['delivery' => $second, 'tourName' => null],
        ]);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->willReturn($result);

        return $parser->reveal();
    }

    // Each delivery needs a different date, as Prophecy merges the expectations with equal arguments
    private function createDelivery(string $date): Delivery
    {
        $delivery = Delivery::createWithAddress($this->fixtures['address_1'], $this->fixtures['address_2']);
        $delivery->setPickupRange(new \DateTime($date.' 10:00:00'), new \DateTime($date.' 10:30:00'));
        $delivery->setDropoffRange(new \DateTime($date.' 11:00:00'), new \DateTime($date.' 11:30:00'));

        return $delivery;
    }

    private function assertNothingWasImported(int $failedRow = 3): void
    {
        $this->entityManager->clear();

        $this->assertCount(0, $this->entityManager->getRepository(Delivery::class)->findAll());

        /** @var ImportQueue $queue */
        $queue = $this->entityManager->getRepository(ImportQueue::class)->findOneByFilename(self::FILENAME);

        $this->assertEquals(ImportQueue::STATUS_FAILED, $queue->getStatus());
        $this->assertEquals([
            [
                'row' => $failedRow,
                'errors' => [self::getContainer()->get(TranslatorInterface::class)->trans('deliveries.imports.error.rolled_back')],
            ]
        ], $queue->getErrors());
    }

    public function testImportIsRolledBackWhenARowFails(): void
    {
        $first = $this->createDelivery('2026-10-01');
        $second = $this->createDelivery('2026-10-02');

        $order = $this->prophesize(OrderInterface::class)->reveal();
        $entityManager = $this->entityManager;

        $deliveryOrderManager = $this->prophesize(DeliveryOrderManager::class);
        // Like the actual implementation, the first row is flushed
        $deliveryOrderManager->createOrder(Argument::is($first), Argument::any())->will(function () use ($entityManager, $order) {
            $entityManager->flush();

            return $order;
        });
        $deliveryOrderManager->createOrder(Argument::is($second), Argument::any())->willThrow(new \RuntimeException('Deadlock detected'));

        $handler = $this->createHandler($this->createParser($first, $second), $deliveryOrderManager->reveal());

        try {
            $handler(new ImportDeliveries(self::FILENAME));
            $this->fail('An exception should have been thrown');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertEquals(sprintf('Importing deliveries from %s failed: Deadlock detected', self::FILENAME), $e->getMessage());
        }

        $this->assertNothingWasImported();
        $this->assertFalse(self::getContainer()->get(LiveUpdatesSuspension::class)->isSuspended());
    }

    public function testImportIsRolledBackWhenAFlushFails(): void
    {
        $first = $this->createDelivery('2026-10-01');
        $second = $this->createDelivery('2026-10-02');

        $order = $this->prophesize(OrderInterface::class)->reveal();
        $entityManager = $this->entityManager;

        $deliveryOrderManager = $this->prophesize(DeliveryOrderManager::class);
        $deliveryOrderManager->createOrder(Argument::is($first), Argument::any())->will(function () use ($entityManager, $order) {
            $entityManager->flush();

            return $order;
        });
        // A failing flush closes the entity manager
        $deliveryOrderManager->createOrder(Argument::is($second), Argument::any())->will(function () use ($entityManager) {
            $invalid = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
            $invalid->setStatus('longer than the column allows');
            $entityManager->persist($invalid);
            $entityManager->flush();
        });

        $handler = $this->createHandler($this->createParser($first, $second), $deliveryOrderManager->reveal());

        try {
            $handler(new ImportDeliveries(self::FILENAME));
            $this->fail('An exception should have been thrown');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertStringStartsWith(sprintf('Importing deliveries from %s failed: ', self::FILENAME), $e->getMessage());
        }

        $this->assertTrue($this->entityManager->isOpen());
        $this->assertNothingWasImported();
    }

    /**
     * What comes after the order, the tour above all, is flushed with the row:
     * flushed with the next one, it was that row the error pointed at
     */
    public function testFailureIsReportedOnTheRowItHappenedOn(): void
    {
        $first = $this->createDelivery('2026-10-01');
        $second = $this->createDelivery('2026-10-02');

        $order = $this->prophesize(OrderInterface::class)->reveal();
        $entityManager = $this->entityManager;

        $deliveryOrderManager = $this->prophesize(DeliveryOrderManager::class);
        // Persisted after the order was flushed
        $deliveryOrderManager->createOrder(Argument::is($first), Argument::any())->will(function () use ($entityManager, $order) {
            $invalid = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
            $invalid->setStatus('longer than the column allows');
            $entityManager->persist($invalid);

            return $order;
        });
        $deliveryOrderManager->createOrder(Argument::is($second), Argument::any())->willReturn($order);

        $handler = $this->createHandler($this->createParser($first, $second), $deliveryOrderManager->reveal());

        try {
            $handler(new ImportDeliveries(self::FILENAME));
            $this->fail('An exception should have been thrown');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertStringStartsWith(sprintf('Importing deliveries from %s failed: ', self::FILENAME), $e->getMessage());
        }

        $deliveryOrderManager->createOrder(Argument::is($second), Argument::any())->shouldNotHaveBeenCalled();
        $this->assertNothingWasImported(2);
    }

    public function testFinishedImportIsSkipped(): void
    {
        /** @var ImportQueue $queue */
        $queue = $this->entityManager->getRepository(ImportQueue::class)->findOneByFilename(self::FILENAME);
        $queue->setStatus(ImportQueue::STATUS_COMPLETED);
        $this->entityManager->flush();

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::cetera())->shouldNotBeCalled();

        $handler = $this->createHandler($parser->reveal(), $this->prophesize(DeliveryOrderManager::class)->reveal());

        $handler(new ImportDeliveries(self::FILENAME));

        $this->entityManager->clear();
        $this->assertCount(0, $this->entityManager->getRepository(Delivery::class)->findAll());
    }
}
