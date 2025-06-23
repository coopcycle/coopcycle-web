<?php

namespace Tests\AppBundle\Api\Dto;

use AppBundle\Api\Dto\TaskMapper;
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
        $dropoff1->setWeight(5000);
        $dropoff2 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5000);
        $dropoff3 = $this->createTask(Task::TYPE_DROPOFF);
        $dropoff3->setWeight(5000);

        $this->assertEquals(15000, $this->taskMapper->getWeight($pickup, [$pickup, $dropoff1, $dropoff1, $dropoff3]));
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
}
