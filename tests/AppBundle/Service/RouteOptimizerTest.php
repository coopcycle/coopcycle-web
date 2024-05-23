<?php

namespace Tests\AppBundle\Service;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollection;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Service\RouteOptimizer;
use AppBundle\Service\SettingsManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RouteOptimizerTest extends KernelTestCase
{
    use ProphecyTrait;


    protected ?EntityManagerInterface $entityManager;
    protected $settingsManager;
    protected $client;
    protected $fixturesLoader;
    protected $logger;
    protected $iriConverter;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->settingsManager->get('latlng')->willReturn('48.856613,2.352222');

        $this->client = self::$container->get('vroom.client');

        $this->entityManager = self::$container->get(EntityManagerInterface::class);

        $this->logger = self::$container->get(LoggerInterface::class);

        $this->iriConverter = self::$container->get(IriConverterInterface::class);

        $this->fixturesLoader = self::$container->get('fidry_alice_data_fixtures.loader.doctrine');
    }


    protected function tearDown(): void
    {
        parent::tearDown();

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        /** @see https://joeymasip.medium.com/symfony-phpunit-testing-database-data-322383ed0603 */
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testCreateRoutingProblemWithShipments()
    {
        $coords = [
            new GeoCoordinates(48.87261892829001,  2.3363113403320312),
            new GeoCoordinates(48.86923158125418,  2.3548507690429683),
            new GeoCoordinates(48.876006045998984, 2.3466110229492188)
        ];

        $taskList = [];
        $itemsList = [];
        $task_id = 0;
        $address1 = new Address();
        $address1->setGeo($coords[0]);

        $now = new \DateTime();

        $after = clone $now;
        $after->setTime(0, 0, 0);

        $before = clone $now;
        $before->setTime(23, 59, 59);

        $delivery = $this->prophesize(Delivery::class);

        // create list of task prophecies
        foreach($coords as $coord)
        {
            $address = new Address();
            $address->setGeo($coord);

            $task_proph = $this->prophesize(Task::class);
            $task_proph->getId()->willReturn($task_id);
            $task_proph->getAddress()->willReturn($address);
            $task_proph->getAfter()->willReturn($after);
            $task_proph->getBefore()->willReturn($before);

            if (0 === $task_id) {
                $delivery->getPickup()->willReturn($task_proph->reveal());
                $task_proph->getDelivery()->willReturn($delivery->reveal());
                $task_proph->isDropoff()->willReturn(false);
            } elseif (1 === $task_id) {
                $delivery->getDropoff()->willReturn($task_proph->reveal());
                $task_proph->getDelivery()->willReturn($delivery->reveal());
                $task_proph->isDropoff()->willReturn(true);
            } else {
                $task_proph->getDelivery()->willReturn(null);
            }

            $task = $task_proph->reveal();
            $item = new Item();
            $item->setTask($task);
            $taskList[] = $task;
            $itemsList[] = $item;
            $task_id += 1;
        }

        $taskCollection = $this->prophesize(TaskList::class);
        $taskCollection->getItems()->willReturn($itemsList);
        $taskCollection->getTasks()->willReturn($taskList);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $problem = $optimizer->createRoutingProblem($taskCollection->reveal());

        $this->assertCount(1, $problem->getShipments());
        $this->assertCount(1, $problem->getJobs());
    }

    public function testCreateRoutingProblemWithMultiDropoffDeliveries()
    {
        $coords = [
            new GeoCoordinates(48.87261892829001,  2.3363113403320312),
            new GeoCoordinates(48.86923158125418,  2.3548507690429683),
            new GeoCoordinates(48.876006045998984, 2.3466110229492188)
        ];

        $taskList = [];
        $itemsList = [];
        $task_id = 0;
        $address1 = new Address();
        $address1->setGeo($coords[0]);

        $now = new \DateTime();

        $after = clone $now;
        $after->setTime(0, 0, 0);

        $before = clone $now;
        $before->setTime(23, 59, 59);

        $delivery = $this->prophesize(Delivery::class);

        // create list of task prophecies
        foreach($coords as $coord)
        {
            $address = new Address();
            $address->setGeo($coord);

            $task_proph = $this->prophesize(Task::class);
            $task_proph->getId()->willReturn($task_id);
            $task_proph->getAddress()->willReturn($address);
            $task_proph->getAfter()->willReturn($after);
            $task_proph->getBefore()->willReturn($before);

            if (0 === $task_id) {
                $delivery->getPickup()->willReturn($task_proph->reveal());
                $task_proph->getDelivery()->willReturn($delivery->reveal());
                $task_proph->isDropoff()->willReturn(false);
            } elseif (1 === $task_id) {
                $delivery->getDropoff()->willReturn($task_proph->reveal());
                $task_proph->getDelivery()->willReturn($delivery->reveal());
                $task_proph->isDropoff()->willReturn(true);
            } else {
                $delivery->getDropoff()->willReturn($task_proph->reveal());
                $task_proph->getDelivery()->willReturn($delivery->reveal());
                $task_proph->isDropoff()->willReturn(true);
            }

            $task = $task_proph->reveal();
            $item = new Item();
            $item->setTask($task);
            $taskList[] = $task;
            $itemsList[] = $item;
            $task_id += 1;
        }

        $taskCollection = $this->prophesize(TaskList::class);
        $taskCollection->getItems()->willReturn($itemsList);
        $taskCollection->getTasks()->willReturn($taskList);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $problem = $optimizer->createRoutingProblem($taskCollection->reveal());

        $this->assertCount(2, $problem->getShipments());
        $this->assertCount(0, $problem->getJobs());
    }

    public function testCreateRoutingProblemWithTour()
    {

        $this->fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/optimizer/set1.yml'
        ]);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $problem = $optimizer->createRoutingProblem($taskList);

        $this->assertCount(0, $problem->getShipments());
        $this->assertCount(5, $problem->getJobs());
    }

    public function testOptimizeWithMultiDropoffDelivery()
    {

        // this fixture load a tour in the centre of paris with 3 tasks and 3 tasks in the outskirts of Paris
        // we want to make sure the tour tasks are kept together
        $this->fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/optimizer/set3.yml'
        ]);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $solution = $optimizer->optimize($taskList);

        $this->assertEquals(Task::TYPE_PICKUP, $solution[0]->getTask()->getType());
    }

    public function testOptimizeWithTour()
    {

        // this fixture load a tour in the centre of paris with 3 tasks and 3 tasks in the outskirts of Paris
        // we want to make sure the tour tasks are kept together
        $this->fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/optimizer/set2.yml'
        ]);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $solution = $optimizer->optimize($taskList);

        $this->assertEquals("tour_1", $solution[0]->getTour()->getName());

        $this->assertEquals(48.8501504, $solution[1]->getTask()->getAddress()->getGeo()->getLatitude());
        $this->assertEquals(48.8696315, $solution[2]->getTask()->getAddress()->getGeo()->getLatitude());
    }

    public function testOptimize()
    {
        $coords = [
            new GeoCoordinates(48.87261892829001,  2.3363113403320312),
            new GeoCoordinates(48.86923158125418,  2.3548507690429683),
            new GeoCoordinates(48.876006045998984, 2.3466110229492188)
        ];

        $itemList = new ArrayCollection();
        $taskList = [];
        $task_id = 0;
        $address1 = new Address();
        $address1->setGeo($coords[0]);

        $now = new \DateTime();

        $after = clone $now;
        $after->setTime(0, 0, 0);

        $before = clone $now;
        $before->setTime(23, 59, 59);

        // create list of task prophecies
        foreach($coords as $coord)
        {
            $address = new Address();
            $address->setGeo($coord);

            $task_proph = $this->prophesize(Task::class);
            $task_proph->getId()->willReturn($task_id);
            $task_proph->getAddress()->willReturn($address);
            $task_proph->getAfter()->willReturn($after);
            $task_proph->getBefore()->willReturn($before);
            $task_proph->getDelivery()->willReturn(null);

            $task = $task_proph->reveal();
            $item = new Item();
            $item->setTask($task);
            $itemList->add($item);
            $taskList[] = $task;
            $task_id += 1;
        }

        $taskCollection = $this->prophesize(TaskList::class);
        $taskCollection->getTasks()->willReturn($taskList);
        $taskCollection->getItems()->willReturn($itemList);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $result = $optimizer->optimize($taskCollection->reveal());

        $this->assertSame($result[0]->getTask(), $taskList[0]);
        $this->assertSame($result[1]->getTask(), $taskList[2]);
        $this->assertSame($result[2]->getTask(), $taskList[1]);
    }

    public function testOptimizePickupsAndDelivery()
    {
        // 24, Rue de Rivoli, Paris
        $dropoffCoords = new GeoCoordinates(48.85611377884633, 2.358438422685973);

        $pickupsCoords = [
            // 93 Bd Beaumarchais, Paris
            new GeoCoordinates(48.859689349806516, 2.3672223527688017),
            // 41 R. de Turbigo, Paris
            new GeoCoordinates(48.864977465145486, 2.352779054615334),
            // 18 Bd Voltaire, Paris
            new GeoCoordinates(48.86537371109955, 2.367317869956039),
        ];

        $pickups = array_map(function ($coords) {
            $address = new Address();
            $address->setGeo($coords);

            return $address;
        }, $pickupsCoords);

        $dropoff = new Address();
        $dropoff->setGeo($dropoffCoords);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal(), $this->logger, $this->iriConverter);

        $addresses = $optimizer->optimizePickupsAndDelivery($pickups, $dropoff);

        $this->assertCount(4, $addresses);

        $this->assertSame($addresses[0], $pickups[1]);
        $this->assertSame($addresses[1], $pickups[2]);
        $this->assertSame($addresses[2], $pickups[0]);
        $this->assertSame($addresses[3], $dropoff);
    }
}
