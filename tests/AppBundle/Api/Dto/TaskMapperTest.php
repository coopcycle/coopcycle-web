<?php

namespace Tests\AppBundle\Api\Dto;

use AppBundle\Api\Dto\TaskMapper;
use AppBundle\Api\Dto\TaskPackageDto;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TaskMapperTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);

        $this->taskMapper = new TaskMapper(
            $this->urlGenerator->reveal());
    }

    private function createTask($type)
    {
        $task = new Task();
        $task->setType($type);

        return $task;
    }

    public function testGetWeightForMultiDropoff()
    {
        $pickup = $this->createTask(Task::TYPE_PICKUP);

        $dropoff1 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(3000);
        $dropoff2 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(4000);
        $dropoff3 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff3->setWeight(5000);

        $this->assertEquals(12000, $this->taskMapper->getWeight($pickup, [$pickup, $dropoff1, $dropoff2, $dropoff3]));

        $this->assertEquals(3000, $this->taskMapper->getWeight($dropoff1, [$pickup, $dropoff1, $dropoff2, $dropoff3]));

        $this->assertEquals(4000, $this->taskMapper->getWeight($dropoff2, [$pickup, $dropoff1, $dropoff2, $dropoff3]));
    }

    public function testGetPackagesForMultiDropoff()
    {
        $small = new Package();
        $small->setName('SMALL');
        $small->setShortCode('AB');
        $small->setAverageVolumeUnits(1);

        $pickup = $this->createTask(Task::TYPE_PICKUP);

        $dropoff1 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff1->addPackageWithQuantity($small, 5);
        $dropoff2 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff2->addPackageWithQuantity($small, 5);
        $dropoff3 = $this->createTask(Task::TYPE_DROPOFF);

        $packages = $this->taskMapper->getPackages($pickup, [$pickup, $dropoff1, $dropoff2, $dropoff3]);

        $this->assertCount(1, $packages);
        $this->assertInstanceOf(TaskPackageDto::class, $packages[0]);
        $this->assertEquals(10, $packages[0]->quantity);
    }

    public function testGetWeightForMultiPickup()
    {
        $pickup1 = $this->createTask(Task::TYPE_PICKUP);
        $pickup1->setWeight(5000);
        $pickup2 = $this->createTask(Task::TYPE_PICKUP);
        $pickup2->setWeight(5000);
        $pickup3 = $this->createTask(Task::TYPE_PICKUP);
        $pickup3->setWeight(5000);

        $dropoff = $this->createTask(Task::TYPE_DROPOFF);

        $this->assertEquals(15000, $this->taskMapper->getWeight($dropoff, [$pickup1, $pickup2, $pickup3, $dropoff]));
    }

    public function testGetPackagesForMultiPickup()
    {
        $small = new Package();
        $small->setName('SMALL');
        $small->setShortCode('AB');
        $small->setAverageVolumeUnits(1);

        $pickup1 = $this->createTask(Task::TYPE_PICKUP);
        $pickup1->addPackageWithQuantity($small, 5);
        $pickup2 = $this->createTask(Task::TYPE_PICKUP);
        $pickup2->addPackageWithQuantity($small, 15);
        $pickup3 = $this->createTask(Task::TYPE_PICKUP);

        $dropoff = $this->createTask(Task::TYPE_DROPOFF);

        $packages = $this->taskMapper->getPackages($dropoff, [$pickup1, $pickup2, $pickup3, $dropoff]);

        $this->assertCount(1, $packages);
        $this->assertInstanceOf(TaskPackageDto::class, $packages[0]);
        $this->assertEquals(20, $packages[0]->quantity);
    }
}
