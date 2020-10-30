<?php

namespace Tests\AppBundle\Normalizer;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use AppBundle\Serializer\VroomNormalizer;
use AppBundle\Entity\Task;
use Prophecy\PhpUnit\ProphecyTrait;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Service\RouteOptimizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
        $address1->setGeo(new GeoCoordinates(5.5, 5.5));
        $task1->getAddress()->willReturn($address1);
        $address2 = new Address();
        $address2->setGeo(new GeoCoordinates(15.5, 25.5));
        $task2->getAddress()->willReturn($address2);
        $address3 = new Address();
        $address3->setGeo(new GeoCoordinates(35.5, 45.5));
        $task3->getAddress()->willReturn($address3);

        $taskList = [$task1->reveal(), $task2->reveal(), $task3->reveal()];
        $normalizer = new VroomNormalizer();
        $optimizer = new RouteOptimizer($normalizer, $this->client);
        $optimizer->optimize($taskList);

    }
}
