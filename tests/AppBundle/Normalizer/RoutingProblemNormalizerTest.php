<?php

namespace Tests\AppBundle\Normalizer;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Vroom\RoutingProblem;
use AppBundle\Vroom\RoutingProblemNormalizer;
use AppBundle\Vroom\Job;
use AppBundle\Vroom\Vehicle;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RoutingProblemNormalizerTest extends KernelTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
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

        $now = new \DateTime();

        $after = clone $now;
        $after->modify('+5 minutes');

        $before = clone $now;
        $before->modify('+10 minutes');

        // create list of task prophecies
        foreach ($coords as $coord) {
            $address = new Address();
            $address->setGeo($coord);

            $task_proph = $this->prophesize(Task::class);
            $task_proph->getId()->willReturn($task_id);
            $task_proph->getAddress()->willReturn($address);
            $task_proph->getAfter()->willReturn($after);
            $task_proph->getBefore()->willReturn($before);
            $taskList[] = $task_proph->reveal();

            $task_id += 1;
        }

        $iriConverter = self::$container->get(IriConverterInterface::class);

        $vehicle1 = new Vehicle(1, 'bike', $address1->getGeo()->toGeocoderCoordinates(), $address1->getGeo()->toGeocoderCoordinates());
        $routingProblem = new RoutingProblem();

        foreach ($taskList as $task){
            $routingProblem->addJob(
                Task::toVroomJob(
                    $task,
                    $iriConverter->getItemIriFromResourceClass(Task::class, ['id' => $task->getId()])
                ));
        }
        $routingProblem->addVehicle($vehicle1);

        $normalizer = new RoutingProblemNormalizer();

        $result = $normalizer->normalize($routingProblem);

        $this->assertEquals([
            "jobs"=>[
                [
                    "id"=>0,
                    "location"=>[2.336311340332, 48.87261892829],
                    "time_windows"=>[
                        [ (int) $after->format('U'), (int) $before->format('U') ]
                    ],
                    "description"=> "/api/tasks/0"
                ],
                [
                    "id"=>1,
                    "location"=>[2.354850769043, 48.869231581254],
                    "time_windows"=>[
                        [ (int) $after->format('U'), (int) $before->format('U') ]
                    ],
                    "description"=> "/api/tasks/1"
                ],
                [
                    "id"=>2,
                    "location"=>[2.3466110229492, 48.876006045999],
                    "time_windows"=>[
                        [ (int) $after->format('U'), (int) $before->format('U') ]
                    ],
                    "description"=> "/api/tasks/2"
                ],
            ],
            "shipments"=>[],
            "vehicles"=>[
                ["id"=>1, "profile"=>"bike", "start"=>[2.336311340332, 48.87261892829], "end"=>[2.336311340332, 48.87261892829],]
            ]
        ], $result);
    }
}
