<?php

namespace Tests\AppBundle\Normalizer;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
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
        $task1 = $this->prophesize(Task::class);
        $task2 = $this->prophesize(Task::class);
        $task3 = $this->prophesize(Task::class);
        $task1->getId()->willReturn(1);
        $task2->getId()->willReturn(2);
        $task3->getId()->willReturn(3);
        $address1 = new Address();
        $address1->setGeo(new GeoCoordinates(48.87261892829001, 2.3363113403320312));
        $task1->getAddress()->willReturn($address1);
        $address2 = new Address();
        $address2->setGeo(new GeoCoordinates(48.86923158125418, 2.3548507690429683));
        $task2->getAddress()->willReturn($address2);
        $address3 = new Address();
        $address3->setGeo(new GeoCoordinates( 48.876006045998984,  2.3466110229492188));
        $task3->getAddress()->willReturn($address3);
        $vehicle1 = new Vehicle(1, $address1, $address1);

        $taskList = [$task1->reveal(), $task2->reveal(), $task3->reveal()];

        $routingProblem = new RoutingProblem();

        foreach($taskList as $task)
        {
            $routingProblem->addTask($task);
        }

        $routingProblem->addVehicle($vehicle1);

        $normalizer = new RoutingProblemNormalizer();
        $optimizer = new RouteOptimizer($normalizer, $this->client);
        $result = $optimizer->optimize($routingProblem);

        $this->assertSame($result[0], $task1->reveal());
        $this->assertSame($result[1], $task3->reveal());
        $this->assertSame($result[2], $task2->reveal());

    }
}
