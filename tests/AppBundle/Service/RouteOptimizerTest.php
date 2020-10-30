<?php

namespace Tests\AppBundle\Normalizer;

use AppBundle\Serializer\RoutingProblemNormalizer;
use AppBundle\Entity\Task;
use Prophecy\PhpUnit\ProphecyTrait;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Service\RouteOptimizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use AppBundle\Entity\Vehicle;
use AppBundle\Entity\RoutingProblem;

class RouteOptimizerTest extends KernelTestCase
{
    use ProphecyTrait;


    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->client = self::$container->get('csa_guzzle.client.vroom');

    }

    public function testOptimize()
    {
        $coords = [
            new GeoCoordinates(48.87261892829001, 2.3363113403320312),
            new GeoCoordinates(48.86923158125418, 2.3548507690429683),
            new GeoCoordinates( 48.876006045998984,  2.3466110229492188)
            ];
        $taskList = [];
        $task_id = 0;
        $address1 = new Address();
        $address1->setGeo($coords[0]);

        // create list of task prophecies
        foreach($coords as $coord)
        {
            $task_proph = $this->prophesize(Task::class);
            $task_proph->getId()->willReturn($task_id);
            $address = new Address();
            $address->setGeo($coord);
            $task_proph->getAddress()->willReturn($address);
            $taskList[] = $task_proph->reveal();
            $task_id += 1;
        }
        $vehicle1 = new Vehicle(1, $address1, $address1);

        $routingProblem = new RoutingProblem();

        foreach($taskList as $task)
        {
            $routingProblem->addTask($task);
        }

        $routingProblem->addVehicle($vehicle1);

        $normalizer = new RoutingProblemNormalizer();
        $optimizer = new RouteOptimizer($normalizer, $this->client);
        $result = $optimizer->optimize($routingProblem);

        $this->assertSame($result[0], $taskList[0]);
        $this->assertSame($result[1], $taskList[2]);
        $this->assertSame($result[2], $taskList[1]);

    }
}
