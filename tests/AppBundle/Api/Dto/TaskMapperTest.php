<?php

namespace Tests\AppBundle\Api\Dto;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Api\Dto\TaskMapper;
use AppBundle\Api\Dto\TaskPackageDto;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Utils\Barcode\BarcodeUtils;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TaskMapperTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $this->iriConverter = $this->prophesize(IriConverterInterface::class);

        $this->taskMapper = new TaskMapper(
            $this->urlGenerator->reveal(),
            $this->iriConverter->reveal(),
        );
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

    private function setId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }

    public function testGetPackagesLabels()
    {
        BarcodeUtils::init('coopcycle', 'salt');

        $this->urlGenerator
            ->generate('task_label_pdf', Argument::type('array'), Argument::any())
            ->will(fn($args) => sprintf('/tasks/label?code=%s', $args[1]['code']));

        $medium = new Package();
        $medium->setName('M');
        $medium->setShortCode('M');
        $medium->setAverageVolumeUnits(4);
        $small = new Package();
        $small->setName('S');
        $small->setShortCode('S');
        $small->setAverageVolumeUnits(2);

        // The package (i.e package type) id must NOT appear in the barcode,
        // it is the task package id that does
        $this->setId($medium, 34);
        $this->setId($small, 33);

        $dropoff = $this->createTask(Task::TYPE_DROPOFF);
        $this->setId($dropoff, 311270);
        $dropoff->addPackageWithQuantity($medium, 1);
        $dropoff->addPackageWithQuantity($small, 1);

        $taskPackages = $dropoff->getPackages();
        $this->setId($taskPackages->get(0), 37730);
        $this->setId($taskPackages->get(1), 37731);

        $packages = $this->taskMapper->getPackages($dropoff, [$dropoff]);

        $this->assertCount(2, $packages);
        $this->assertEquals(
            ['/tasks/label?code=67670011311270P37730U18076'],
            $packages[0]->labels
        );
        // The unit index is continuous across the packages of the task
        $this->assertEquals(
            ['/tasks/label?code=67670011311270P37731U28076'],
            $packages[1]->labels
        );
    }

    public function testGetPackagesLabelsWithQuantities()
    {
        BarcodeUtils::init('coopcycle', 'salt');

        $this->urlGenerator
            ->generate('task_label_pdf', Argument::type('array'), Argument::any())
            ->will(fn($args) => sprintf('/tasks/label?code=%s', $args[1]['code']));

        $medium = new Package();
        $medium->setName('M');
        $medium->setShortCode('M');
        $medium->setAverageVolumeUnits(4);
        $small = new Package();
        $small->setName('S');
        $small->setShortCode('S');
        $small->setAverageVolumeUnits(2);

        $dropoff = $this->createTask(Task::TYPE_DROPOFF);
        $this->setId($dropoff, 1);
        $dropoff->addPackageWithQuantity($medium, 2);
        $dropoff->addPackageWithQuantity($small, 3);

        $taskPackages = $dropoff->getPackages();
        $this->setId($taskPackages->get(0), 10);
        $this->setId($taskPackages->get(1), 11);

        $packages = $this->taskMapper->getPackages($dropoff, [$dropoff]);

        $this->assertEquals([
            '/tasks/label?code=676700111P10U18076',
            '/tasks/label?code=676700111P10U28076',
        ], $packages[0]->labels);

        $this->assertEquals([
            '/tasks/label?code=676700111P11U38076',
            '/tasks/label?code=676700111P11U48076',
            '/tasks/label?code=676700111P11U58076',
        ], $packages[1]->labels);
    }
}
