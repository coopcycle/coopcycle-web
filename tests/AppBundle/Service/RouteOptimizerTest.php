<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollection;
use AppBundle\Service\RouteOptimizer;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RouteOptimizerTest extends KernelTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

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

        $optimizer = new RouteOptimizer($this->client);

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

        $optimizer = new RouteOptimizer($this->client);

        $result = $optimizer->optimize($taskCollection->reveal());

        $this->assertSame($result[0], $taskList[0]);
        $this->assertSame($result[1], $taskList[2]);
        $this->assertSame($result[2], $taskList[1]);
    }
}
