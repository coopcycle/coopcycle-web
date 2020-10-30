<?php

namespace Tests\AppBundle\Normalizer;

use PHPUnit\Framework\TestCase;
use AppBundle\Serializer\RoutingProblemNormalizer;
use AppBundle\Entity\Task;
use Prophecy\PhpUnit\ProphecyTrait;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Vehicle;
use AppBundle\Entity\RoutingProblem;

class RoutingProblemNormalizerTest extends TestCase
{
    use ProphecyTrait;


    public function setUp(): void
    {

    }


    public function testNormalization()
    {
        $coords = [
            new GeoCoordinates(48.87261892829001, 2.3363113403320312),
            new GeoCoordinates(48.86923158125418, 2.3548507690429683),
            new GeoCoordinates(48.876006045998984,  2.3466110229492188)
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

        foreach($taskList as $task){
            $routingProblem->addTask($task);
        }
        $routingProblem->addVehicle($vehicle1);

        $normalizer = new RoutingProblemNormalizer();

        $result = $normalizer->normalize($routingProblem);

        $this->assertEquals([
            "jobs"=>[
                ["id"=>0, "location"=>[2.3363113403320312, 48.87261892829001]],
                ["id"=>1, "location"=>[2.3548507690429683, 48.86923158125418]],
                ["id"=>2, "location"=>[2.3466110229492188, 48.876006045998984]],
            ],
            "vehicles"=>[
                ["id"=>1, "start"=>[2.3363113403320312, 48.87261892829001], "end"=>[2.3363113403320312, 48.87261892829001]]
            ]
        ], $result);
    }
}
