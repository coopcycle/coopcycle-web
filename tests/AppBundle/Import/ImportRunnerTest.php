<?php

declare(strict_types=1);

namespace Tests\AppBundle\Import;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\TourRepository;
use AppBundle\Import\DeliveryImportHandler;
use AppBundle\Import\ImportRegistry;
use AppBundle\Import\ImportRunner;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\ImportStatusManager;
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
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An imported delivery that matched none of the pricing rules was saved without an order,
 * so it could never be priced, and the import was marked as failed.
 */
class ImportRunnerTest extends TestCase
{
    use ProphecyTrait;

    private LiveUpdatesSuspension $liveUpdatesSuspension;

    protected function setUp(): void
    {
        $this->liveUpdatesSuspension = new LiveUpdatesSuspension();
    }

    protected function tearDown(): void
    {
        // The runner disables push notifications for the whole process,
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

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(ImportQueue::class)->willReturn(self::createRepository($queue));
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

        $importer = $this->createRunner(
            $entityManager,
            $statusManager,
            $this->createImportHandler(
                $entityManager,
                $filesystem,
                $parser,
                $validator->reveal(),
                $deliveryOrderManager->reveal()
            )
        );

        $this->assertSame(1, $importer->run('deliveries.csv'));

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

    private function createQueue(Store $store, int $id, string $status = ImportQueue::STATUS_PENDING): ImportQueue
    {
        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setStore($store);
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        // Set by the database
        (new \ReflectionProperty(ImportQueue::class, 'id'))->setValue($queue, $id);

        return $queue;
    }

    /**
     * An anonymous class rather than a double: find() and findOneBy() are final on
     * EntityRepository
     */
    private static function createRepository(ImportQueue $queue): EntityRepository
    {
        return new class($queue) extends EntityRepository {
            public function __construct(private readonly ImportQueue $queue)
            {
            }

            public function find($id, $lockMode = null, $lockVersion = null): ?ImportQueue
            {
                return $id === $this->queue->getId() ? $this->queue : null;
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?ImportQueue
            {
                return ($criteria['filename'] ?? null) === $this->queue->getFilename() ? $this->queue : null;
            }
        };
    }

    private function createEntityManager(ImportQueue $queue)
    {
        $repository = self::createRepository($queue);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(ImportQueue::class)->willReturn($repository);
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
    private function createStatusManager(ImportQueue $queue, bool $saving, bool ...$isCancelled)
    {
        $statusManager = $this->prophesize(ImportStatusManager::class);
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

    private function createImporter($entityManager, $parser, $statusManager): ImportRunner
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('deliveries.csv', 'pickup.address,dropoff.address');

        return $this->createRunner(
            $entityManager,
            $statusManager,
            $this->createImportHandler(
                $entityManager,
                $filesystem,
                $parser,
                $this->prophesize(ValidatorInterface::class)->reveal(),
                $this->prophesize(DeliveryOrderManager::class)->reveal()
            )
        );
    }

    /**
     * The real delivery handler over doubled collaborators: what it does with a row is
     * what the runner around it is being tested with.
     */
    private function createImportHandler(
        $entityManager,
        Filesystem $filesystem,
        $parser,
        ValidatorInterface $validator,
        DeliveryOrderManager $deliveryOrderManager
    ): DeliveryImportHandler {
        return new DeliveryImportHandler(
            $entityManager->reveal(),
            $filesystem,
            $parser->reveal(),
            $validator,
            $deliveryOrderManager,
            $this->prophesize(DeliveryManager::class)->reveal(),
            $this->prophesize(TourRepository::class)->reveal(),
            $this->prophesize(DeliveryCreatedNotifier::class)->reveal()
        );
    }

    private function createRunner($entityManager, $statusManager, DeliveryImportHandler $importHandler): ImportRunner
    {
        $registry = new ImportRegistry(
            new ServiceLocator([ImportQueue::TYPE_DELIVERIES => fn () => $importHandler]),
            new ServiceLocator([])
        );

        return new ImportRunner(
            $entityManager->reveal(),
            $registry,
            $statusManager->reveal(),
            $this->liveUpdatesSuspension,
            $this->prophesize(TranslatorInterface::class)->reveal(),
            new NullLogger(),
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
            ImportQueue::STATUS_STARTED,
            ImportQueue::STATUS_CANCELLED,
            ImportQueue::STATUS_COMPLETED,
        ] as $status) {
            $queue = $this->createQueue(new Store(), 42, $status);

            $parser = $this->prophesize(DeliverySpreadsheetParser::class);
            $statusManager = $this->prophesize(ImportStatusManager::class);

            $importer = $this->createImporter($this->createEntityManager($queue), $parser, $statusManager);
            $importer->run('deliveries.csv');

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

        $importer = $this->createImporter($entityManager, $parser, $statusManager);
        $importer->run('deliveries.csv');

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

        $importer = $this->createImporter($entityManager, $parser, $statusManager);
        $importer->run('deliveries.csv');

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

        $importer = $this->createImporter($entityManager, $parser, $statusManager);
        $importer->run('deliveries.csv');

        // The first row, every 10th row, and the last one
        foreach ([1, 10, 20, 25] as $row) {
            $statusManager->progress($queue, $row, 25, 0)->shouldHaveBeenCalledOnce();
        }
        $statusManager->progress(Argument::cetera())->shouldHaveBeenCalledTimes(4);

        $statusManager->completed($queue)->shouldHaveBeenCalled();
    }

    /**
     * The mapping a dispatcher picked on the mapping screen is what the file is read with
     */
    public function testTheColumnMappingOfTheImportIsUsedToReadTheFile(): void
    {
        $queue = $this->createQueue(new Store(), 42);
        $queue->setColumnMapping([0 => 'pickup.address', 2 => 'dropoff.address']);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->willReturn(new SpreadsheetParseResult());

        $importer = $this->createImporter(
            $this->createEntityManager($queue),
            $parser,
            $this->createStatusManager($queue, true, false)
        );
        $importer->run('deliveries.csv');

        $parser->parse(
            Argument::type('string'),
            Argument::withEntry('column_mapping', [0 => 'pickup.address', 2 => 'dropoff.address'])
        )->shouldHaveBeenCalled();
    }

    /**
     * An import from the API, or one sent before the mapping screen existed and still in
     * the transport across the deploy, is read by the names in its header row
     */
    public function testAnImportWithoutAColumnMappingIsReadByItsHeader(): void
    {
        $queue = $this->createQueue(new Store(), 42);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->willReturn(new SpreadsheetParseResult());

        $importer = $this->createImporter(
            $this->createEntityManager($queue),
            $parser,
            $this->createStatusManager($queue, true, false)
        );
        $importer->run('deliveries.csv');

        $parser->parse(Argument::type('string'), Argument::not(Argument::withKey('column_mapping')))
            ->shouldHaveBeenCalled();
    }

    /**
     * The options the message carries are the API's own, and the queue must not undo them
     */
    public function testTheOptionsOfTheMessageSurviveTheColumnMapping(): void
    {
        $queue = $this->createQueue(new Store(), 42);
        $queue->setColumnMapping([0 => 'pickup.address']);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->willReturn(new SpreadsheetParseResult());

        $importer = $this->createImporter(
            $this->createEntityManager($queue),
            $parser,
            $this->createStatusManager($queue, true, false)
        );
        $importer->run('deliveries.csv', ['create_task_if_address_not_geocoded' => true]);

        $parser->parse(
            Argument::type('string'),
            Argument::withEntry('create_task_if_address_not_geocoded', true)
        )->shouldHaveBeenCalled();
    }

    /**
     * A file still waiting for its columns to be mapped is not one to import
     */
    public function testImportWaitingForItsColumnsToBeMappedIsLeftAlone(): void
    {
        $queue = $this->createQueue(new Store(), 42, ImportQueue::STATUS_MAPPING);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $statusManager = $this->prophesize(ImportStatusManager::class);

        $importer = $this->createImporter($this->createEntityManager($queue), $parser, $statusManager);
        $importer->run('deliveries.csv');

        $parser->parse(Argument::cetera())->shouldNotHaveBeenCalled();
        $statusManager->started(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->assertSame(ImportQueue::STATUS_MAPPING, $queue->getStatus());
    }
}
