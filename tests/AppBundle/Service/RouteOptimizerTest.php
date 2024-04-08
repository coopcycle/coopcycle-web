<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollection;
use AppBundle\Entity\TaskList;
use AppBundle\Service\RouteOptimizer;
use AppBundle\Service\SettingsManager;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;


class RouteOptimizerTest extends KernelTestCase
{
    use ProphecyTrait;


    protected ?EntityManagerInterface $entityManager;
    protected $settingsManager;
    protected $client;
    protected $fixturesLoader;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->settingsManager->get('latlng')->willReturn('48.856613,2.352222');

        $this->client = self::$container->get('vroom.client');

        $this->entityManager = self::$container->get(EntityManagerInterface::class);

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
            } elseif (1 === $task_id) {
                $delivery->getDropoff()->willReturn($task_proph->reveal());
                $task_proph->getDelivery()->willReturn($delivery->reveal());
            } else {
                $task_proph->getDelivery()->willReturn(null);
            }

            $task_proph->getTour()->willReturn(null);

            $taskList[] = $task_proph->reveal();
            $task_id += 1;
        }

        $taskCollection = $this->prophesize(TaskCollection::class);
        $taskCollection->getTasks()->willReturn($taskList);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal());

        $problem = $optimizer->createRoutingProblem($taskCollection->reveal());

        $this->assertCount(1, $problem->getShipments());
        $this->assertCount(1, $problem->getJobs());
    }

    public function testCreateRoutingProblemWithTour()
    {

        $this->fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/optimizer/set1.yml'
        ]);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal());

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        foreach($this->entityManager->getRepository(Task::class)->findAll() as $task) {
            $taskList->addTask($task);
        }

        $problem = $optimizer->createRoutingProblem($taskList);

        $this->assertCount(1, $problem->getShipments());

        $tourShipment = $problem->getShipments()[0];

        $this->assertEquals($tourShipment->pickup->location,[2.3681042, 48.8532461]);
        $this->assertEquals($tourShipment->delivery->location, [2.362811, 48.867598]);
        $tourShipment->delivery->location = [];

        $this->assertCount(4, $problem->getJobs());
    }

    public function testOptimizeWithTour()
    {

        // this fixture load a tour in the centre of paris with 3 tasks and 3 tasks in the outskirts of Paris
        // we want to make sure the tour tasks are kept together
        $this->fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/optimizer/set2.yml'
        ]);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal());

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        foreach($this->entityManager->getRepository(Task::class)->findAll() as $task) {
            $taskList->addTask($task);
        }

        $solution = $optimizer->optimize($taskList);

        // tour first task was at the beginning in the fixture, and still at the beginning here because it is used as the starting point in the vroom problem
        $this->assertEquals(48.8704288, $solution[0]->getAddress()->getGeo()->getLatitude());

        // other steps of the tour, in the same order as it was given
        $this->assertEquals(48.8669865, $solution[1]->getAddress()->getGeo()->getLatitude());
        $this->assertEquals(48.8655514, $solution[2]->getAddress()->getGeo()->getLatitude());

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    public function testOptimize()
    {
        $coords = [
            new GeoCoordinates(48.87261892829001,  2.3363113403320312),
            new GeoCoordinates(48.86923158125418,  2.3548507690429683),
            new GeoCoordinates(48.876006045998984, 2.3466110229492188)
        ];

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
            $task_proph->getTour()->willReturn(null);

            $taskList[] = $task_proph->reveal();
            $task_id += 1;
        }

        $taskCollection = $this->prophesize(TaskCollection::class);
        $taskCollection->getTasks()->willReturn($taskList);

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal());

        $result = $optimizer->optimize($taskCollection->reveal());

        $this->assertSame($result[0], $taskList[0]);
        $this->assertSame($result[1], $taskList[2]);
        $this->assertSame($result[2], $taskList[1]);
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

        $optimizer = new RouteOptimizer($this->client, $this->settingsManager->reveal());

        $addresses = $optimizer->optimizePickupsAndDelivery($pickups, $dropoff);

        $this->assertCount(4, $addresses);

        $this->assertSame($addresses[0], $pickups[1]);
        $this->assertSame($addresses[1], $pickups[2]);
        $this->assertSame($addresses[2], $pickups[0]);
        $this->assertSame($addresses[3], $dropoff);
    }
}
