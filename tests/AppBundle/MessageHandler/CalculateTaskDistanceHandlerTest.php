<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Vehicle;
use AppBundle\Message\CalculateTaskDistance;
use AppBundle\MessageHandler\CalculateTaskDistanceHandler;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\TaskManager;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


class CalculateTaskDistanceHandlerTest extends KernelTestCase
{

    protected $subscriber;
    protected $entityManager;
    protected $taskListManager;
    protected $iriConverter;
    protected $fixturesLoader;
    protected $taskManager;
    protected $handler;

    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $taskListRepository = $this->entityManager->getRepository(TaskList::class);

        $this->taskManager = self::getContainer()->get(TaskManager::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $this->handler = new CalculateTaskDistanceHandler(
            $this->entityManager,
            self::getContainer()->get(RoutingInterface::class),
            $taskListRepository,
            self::getContainer()->get(LoggerInterface::class)
        );

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    public function tearDown(): void {
        parent::tearDown();
        /** @see https://joeymasip.medium.com/symfony-phpunit-testing-database-data-322383ed0603 */
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testCO2CalculationOnSetVehicle() {

        $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/task_list.yml'
        ]);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $task = $taskList->getTasks()[0];

        $this->assertEquals(0, $task->getEmittedCo2());
        $this->assertEquals(0, $task->getTraveledDistanceMeter());

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->findAll()[0];        

        $taskList->setVehicle($vehicle);

        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        // task in a tour
        $task = $taskList->getTasks()[0];
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $task->getId()]);

        $msg = new CalculateTaskDistance($task->getId());
        call_user_func_array($this->handler, [ $msg ]);

        $this->assertEquals(41, $task->getEmittedCo2());
        $this->assertEquals(4175, $task->getTraveledDistanceMeter());

        // task not in a tour
        $task = $taskList->getTasks()[4];
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $task->getId()]);

        $msg = new CalculateTaskDistance($task->getId());
        call_user_func_array($this->handler, [ $msg ]);

        $this->assertEquals(40, $task->getEmittedCo2());
        $this->assertEquals(4045, $task->getTraveledDistanceMeter());

    }

    public function testCO2CalculationWithoutVehicle() {

        $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/task_list.yml'
        ]);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $task = $taskList->getTasks()[0];

        $this->assertEquals(0, $task->getEmittedCo2());
        $this->assertEquals(0, $task->getTraveledDistanceMeter());

        // task in a tour
        $task = $taskList->getTasks()[0];
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $task->getId()]);

        $msg = new CalculateTaskDistance($task->getId());
        call_user_func_array($this->handler, [ $msg ]);

        $this->assertEquals(0, $task->getEmittedCo2());
        $this->assertEquals(0, $task->getTraveledDistanceMeter());

        // task not in a tour
        $task = $taskList->getTasks()[4];
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $task->getId()]);

        $msg = new CalculateTaskDistance($task->getId());
        call_user_func_array($this->handler, [ $msg ]);

        $this->assertEquals(0, $task->getEmittedCo2());
        $this->assertEquals(4045, $task->getTraveledDistanceMeter());

    }
}