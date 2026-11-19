<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\TourRepository;
use AppBundle\Message\ImportDeliveries;
use AppBundle\MessageHandler\ImportDeliveriesHandler;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\LiveUpdatesSuspension;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An imported delivery that matched none of the pricing rules was saved without an order,
 * so it could never be priced, and the import was marked as failed.
 */
class ImportDeliveriesHandlerTest extends TestCase
{
    use ProphecyTrait;

    private LiveUpdatesSuspension $liveUpdatesSuspension;

    protected function setUp(): void
    {
        $this->liveUpdatesSuspension = new LiveUpdatesSuspension();
    }

    protected function tearDown(): void
    {
        // The handler disables push notifications for the whole process,
        // the tests running after this one expect them to be enabled
        RemotePushNotificationManager::enable();

        parent::tearDown();
    }

    public function testDeliveryWithoutPricingRuleGetsAnOrderToReview(): void
    {
        // SET UP THE QUEUE & THE FILE
        $store = new Store();

        $queue = $this->createQueue($store, 42);

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('deliveries.csv', 'pickup.address,dropoff.address');

        $repository = new class($queue) extends EntityRepository {
            public function __construct(private readonly DeliveryImportQueue $queue)
            {
            }

            public function findOneByFilename(string $filename): ?DeliveryImportQueue
            {
                return $filename === $this->queue->getFilename() ? $this->queue : null;
            }
        };

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(DeliveryImportQueue::class)->willReturn($repository);
        $entityManager->flush()->shouldBeCalled();
        $entityManager->beginTransaction()->will(function () {});
        $entityManager->commit()->will(function () {});

        // ONE ROW, WHATEVER ITS PRICE
        $delivery = Delivery::create();
        $delivery->setPickupRange(new \DateTime('2026-10-01 10:00'), new \DateTime('2026-10-01 10:30'));
        $delivery->setDropoffRange(new \DateTime('2026-10-01 11:00'), new \DateTime('2026-10-02 09:00'));

        $result = new SpreadsheetParseResult();
        $result->addData(2, ['delivery' => $delivery, 'tourName' => null]);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->willReturn($result);

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate($delivery)->willReturn(new ConstraintViolationList());

        $entityManager->persist($delivery)->shouldBeCalled();

        $options = null;
        $suspendedWhileSaving = null;
        $suspension = $this->liveUpdatesSuspension;
        $deliveryOrderManager = $this->prophesize(DeliveryOrderManager::class);
        $deliveryOrderManager
            ->createOrder($delivery, Argument::cetera())
            ->shouldBeCalledOnce()
            ->will(function (array $args) use (&$options, &$suspendedWhileSaving, $suspension) {
                $options = $args[1] ?? [];
                $suspendedWhileSaving = $suspension->isSuspended();

                return new Order();
            });

        $statusManager = $this->createStatusManager($queue, true, false);

        $handler = new ImportDeliveriesHandler(
            $entityManager->reveal(),
            $filesystem,
            $parser->reveal(),
            $validator->reveal(),
            $deliveryOrderManager->reveal(),
            $statusManager->reveal(),
            $this->prophesize(DeliveryManager::class)->reveal(),
            new NullLogger(),
            $this->prophesize(TourRepository::class)->reveal(),
            $this->prophesize(DeliveryCreatedNotifier::class)->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $this->liveUpdatesSuspension,
            $this->prophesize(ManagerRegistry::class)->reveal()
        );

        $handler(new ImportDeliveries('deliveries.csv'));

        // A placeholder price & an incident rather than an exception
        $this->assertFalse($options['throwException'] ?? false);

        $this->assertContains($delivery, $store->getDeliveries());

        // To find the deliveries that came from this file
        $this->assertSame(42, $delivery->getMetadata()['delivery_import_id']);

        // No live update per task while saving, a single event once saved
        $this->assertTrue($suspendedWhileSaving);
        $this->assertFalse($this->liveUpdatesSuspension->isSuspended());
        $statusManager->saved($queue, ['2026-10-01', '2026-10-02'])->shouldHaveBeenCalledOnce();

        $statusManager->completed($queue)->shouldHaveBeenCalled();
        $statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    private function createQueue(Store $store, int $id, string $status = DeliveryImportQueue::STATUS_PENDING): DeliveryImportQueue
    {
        $queue = new DeliveryImportQueue();
        $queue->setStore($store);
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        // Set by the database
        (new \ReflectionProperty(DeliveryImportQueue::class, 'id'))->setValue($queue, $id);

        return $queue;
    }

    /**
     * The repository is an anonymous class, as findOneByFilename() is a magic method Prophecy can't double
     */
    private function createEntityManager(DeliveryImportQueue $queue)
    {
        $repository = new class($queue) extends EntityRepository {
            public function __construct(private readonly DeliveryImportQueue $queue)
            {
            }

            public function findOneByFilename(string $filename): ?DeliveryImportQueue
            {
                return $filename === $this->queue->getFilename() ? $this->queue : null;
            }
        };

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(DeliveryImportQueue::class)->willReturn($repository);
        $entityManager->persist(Argument::any())->willReturn(null);
        $entityManager->flush()->willReturn(null);
        $entityManager->beginTransaction()->will(function () {});
        $entityManager->commit()->will(function () {});

        return $entityManager;
    }

    /**
     * @param bool $saving whether the import can still be moved to "saving" once the file is read
     * @param bool ...$isCancelled what each cancellation check returns, in turn
     */
    private function createStatusManager(DeliveryImportQueue $queue, bool $saving, bool ...$isCancelled)
    {
        $statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $statusManager->started($queue)->will(function () {});
        $statusManager->progress($queue, Argument::cetera())->will(function () {});
        $statusManager->completed($queue)->will(function () {});
        $statusManager->failed($queue, Argument::cetera())->will(function () {});
        $statusManager->cancelled($queue)->will(function () {});
        $statusManager->saved($queue, Argument::any())->will(function () {});
        $statusManager->isCancelled($queue)->willReturn(...$isCancelled);
        $statusManager->saving($queue)->willReturn($saving);

        return $statusManager;
    }

    private function createHandler($entityManager, $parser, $statusManager): ImportDeliveriesHandler
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('deliveries.csv', 'pickup.address,dropoff.address');

        return new ImportDeliveriesHandler(
            $entityManager->reveal(),
            $filesystem,
            $parser->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            $this->prophesize(DeliveryOrderManager::class)->reveal(),
            $statusManager->reveal(),
            $this->prophesize(DeliveryManager::class)->reveal(),
            new NullLogger(),
            $this->prophesize(TourRepository::class)->reveal(),
            $this->prophesize(DeliveryCreatedNotifier::class)->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $this->liveUpdatesSuspension,
            $this->prophesize(ManagerRegistry::class)->reveal()
        );
    }

    /**
     * The transport hands the message out again after an hour, while the first
     * worker is still on a large file: both used to import it.
     */
    public function testImportThatIsNotPendingIsLeftAlone(): void
    {
        foreach ([
            DeliveryImportQueue::STATUS_STARTED,
            DeliveryImportQueue::STATUS_CANCELLED,
            DeliveryImportQueue::STATUS_COMPLETED,
        ] as $status) {
            $queue = $this->createQueue(new Store(), 42, $status);

            $parser = $this->prophesize(DeliverySpreadsheetParser::class);
            $statusManager = $this->prophesize(DeliveryImportStatusManager::class);

            $handler = $this->createHandler($this->createEntityManager($queue), $parser, $statusManager);
            $handler(new ImportDeliveries('deliveries.csv'));

            $parser->parse(Argument::cetera())->shouldNotHaveBeenCalled();
            $statusManager->started(Argument::cetera())->shouldNotHaveBeenCalled();
            $this->assertSame($status, $queue->getStatus());
        }
    }

    public function testCancelledImportStopsBeforeAnyDeliveryIsSaved(): void
    {
        $store = new Store();
        $queue = $this->createQueue($store, 42);

        $entityManager = $this->createEntityManager($queue);

        // The dispatcher cancels it while the 10th row is read
        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->will(function (array $args) {
            for ($row = 1; $row <= 20; $row++) {
                $args[1]['on_row']($row, 20, 0);
            }

            return new SpreadsheetParseResult();
        });

        $statusManager = $this->createStatusManager($queue, true, false, true);

        $handler = $this->createHandler($entityManager, $parser, $statusManager);
        $handler(new ImportDeliveries('deliveries.csv'));

        // Reported the 1st row, then stopped at the 10th without reporting it:
        // the report carries the status the worker has in memory, still "started"
        $statusManager->progress($queue, 1, 20, 0)->shouldHaveBeenCalledOnce();
        $statusManager->progress(Argument::cetera())->shouldHaveBeenCalledOnce();

        // The last word in the admin
        $statusManager->cancelled($queue)->shouldHaveBeenCalledOnce();

        $entityManager->persist(Argument::any())->shouldNotHaveBeenCalled();
        $entityManager->flush()->shouldNotHaveBeenCalled();
        $statusManager->completed(Argument::cetera())->shouldNotHaveBeenCalled();
        $statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * The dispatcher cancelled it after the last check made while reading the file,
     * and the worker went on saving the deliveries all the same
     */
    public function testImportCancelledOnceReadIsNotSaved(): void
    {
        $store = new Store();
        $queue = $this->createQueue($store, 42);

        $entityManager = $this->createEntityManager($queue);

        $delivery = Delivery::create();

        $result = new SpreadsheetParseResult();
        $result->addData(1, ['delivery' => $delivery, 'tourName' => null]);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->will(function (array $args) use ($result) {
            $args[1]['on_row'](1, 1, 0);

            return $result;
        });

        // Not cancelled yet while reading, then too late to move it to "saving"
        $statusManager = $this->createStatusManager($queue, false, false);

        $handler = $this->createHandler($entityManager, $parser, $statusManager);
        $handler(new ImportDeliveries('deliveries.csv'));

        $entityManager->persist(Argument::any())->shouldNotHaveBeenCalled();
        $entityManager->flush()->shouldNotHaveBeenCalled();
        $this->assertNotContains($delivery, $store->getDeliveries());

        $statusManager->cancelled($queue)->shouldHaveBeenCalledOnce();
        $statusManager->completed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testProgressIsReportedEveryFewRows(): void
    {
        $store = new Store();
        $queue = $this->createQueue($store, 42);

        $entityManager = $this->createEntityManager($queue);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->will(function (array $args) {
            for ($row = 1; $row <= 25; $row++) {
                $args[1]['on_row']($row, 25, 0);
            }

            return new SpreadsheetParseResult();
        });

        $statusManager = $this->createStatusManager($queue, true, false);

        $handler = $this->createHandler($entityManager, $parser, $statusManager);
        $handler(new ImportDeliveries('deliveries.csv'));

        // The first row, every 10th row, and the last one
        foreach ([1, 10, 20, 25] as $row) {
            $statusManager->progress($queue, $row, 25, 0)->shouldHaveBeenCalledOnce();
        }
        $statusManager->progress(Argument::cetera())->shouldHaveBeenCalledTimes(4);

        $statusManager->completed($queue)->shouldHaveBeenCalled();
    }
}
