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
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * An imported delivery that matched none of the pricing rules was saved without an order,
 * so it could never be priced, and the import was marked as failed.
 */
class ImportDeliveriesHandlerTest extends TestCase
{
    use ProphecyTrait;

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

        $queue = new DeliveryImportQueue();
        $queue->setStore($store);
        $queue->setFilename('deliveries.csv');

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

        // ONE ROW, WHATEVER ITS PRICE
        $delivery = Delivery::create();

        $result = new SpreadsheetParseResult();
        $result->addData(2, ['delivery' => $delivery, 'tourName' => null]);

        $parser = $this->prophesize(DeliverySpreadsheetParser::class);
        $parser->parse(Argument::type('string'), Argument::any())->willReturn($result);

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate($delivery)->willReturn(new ConstraintViolationList());

        $entityManager->persist($delivery)->shouldBeCalled();

        $options = null;
        $deliveryOrderManager = $this->prophesize(DeliveryOrderManager::class);
        $deliveryOrderManager
            ->createOrder($delivery, Argument::cetera())
            ->shouldBeCalledOnce()
            ->will(function (array $args) use (&$options) {
                $options = $args[1] ?? [];

                return new Order();
            });

        $statusManager = $this->prophesize(DeliveryImportStatusManager::class);

        $handler = new ImportDeliveriesHandler(
            $entityManager->reveal(),
            $filesystem,
            $parser->reveal(),
            $validator->reveal(),
            $deliveryOrderManager->reveal(),
            $statusManager->reveal(),
            $this->prophesize(DeliveryManager::class)->reveal(),
            new NullLogger(),
            $this->prophesize(TourRepository::class)->reveal()
        );

        $handler(new ImportDeliveries('deliveries.csv'));

        // A placeholder price & an incident rather than an exception
        $this->assertFalse($options['throwException'] ?? false);

        $this->assertContains($delivery, $store->getDeliveries());

        $statusManager->completed($queue)->shouldHaveBeenCalled();
        $statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
