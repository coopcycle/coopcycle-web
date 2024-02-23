<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollection;
use AppBundle\Service\RouteOptimizer;
use AppBundle\Service\SettingsManager;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RouteOptimizerTest extends KernelTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->settingsManager->get('latlng')->willReturn('48.856613,2.352222');

        $this->client = self::$container->get('vroom.client');
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
