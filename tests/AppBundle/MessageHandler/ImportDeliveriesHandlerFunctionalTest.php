<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\TourRepository;
use AppBundle\Message\ImportDeliveries;
use AppBundle\MessageHandler\ImportDeliveriesHandler;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\LiveUpdates;
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

        $queue = new DeliveryImportQueue();
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
            self::getContainer()->get(TranslatorInterface::class),
            $deliveryOrderManager,
            $this->prophesize(LiveUpdates::class)->reveal(),
            $this->prophesize(DeliveryManager::class)->reveal(),
            new NullLogger(),
            self::getContainer()->get(TourRepository::class),
            self::getContainer()->get(DeliveryCreatedNotifier::class),
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

    private function assertNothingWasImported(): void
    {
        $this->entityManager->clear();

        $this->assertCount(0, $this->entityManager->getRepository(Delivery::class)->findAll());

        /** @var DeliveryImportQueue $queue */
        $queue = $this->entityManager->getRepository(DeliveryImportQueue::class)->findOneByFilename(self::FILENAME);

        $this->assertEquals(DeliveryImportQueue::STATUS_FAILED, $queue->getStatus());
        $this->assertEquals([
            [
                'row' => 3,
                'errors' => [self::getContainer()->get(TranslatorInterface::class)->trans('delivery.import.error.interrupted', [], 'validators')],
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
            $this->assertEquals(sprintf('Import of file %s failed: Deadlock detected', self::FILENAME), $e->getMessage());
        }

        $this->assertNothingWasImported();
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
            $invalid = new DeliveryImportQueue();
            $invalid->setStatus('longer than the column allows');
            $entityManager->persist($invalid);
            $entityManager->flush();
        });

        $handler = $this->createHandler($this->createParser($first, $second), $deliveryOrderManager->reveal());

        try {
            $handler(new ImportDeliveries(self::FILENAME));
            $this->fail('An exception should have been thrown');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertStringStartsWith(sprintf('Import of file %s failed: ', self::FILENAME), $e->getMessage());
        }

        $this->assertTrue($this->entityManager->isOpen());
        $this->assertNothingWasImported();
    }

    public function testFinishedImportIsSkipped(): void
    {
        /** @var DeliveryImportQueue $queue */
        $queue = $this->entityManager->getRepository(DeliveryImportQueue::class)->findOneByFilename(self::FILENAME);
        $queue->setStatus(DeliveryImportQueue::STATUS_COMPLETED);
        $this->entityManager->flush();

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::cetera())->shouldNotBeCalled();

        $handler = $this->createHandler($parser->reveal(), $this->prophesize(DeliveryOrderManager::class)->reveal());

        $handler(new ImportDeliveries(self::FILENAME));

        $this->entityManager->clear();
        $this->assertCount(0, $this->entityManager->getRepository(Delivery::class)->findAll());
    }
}
