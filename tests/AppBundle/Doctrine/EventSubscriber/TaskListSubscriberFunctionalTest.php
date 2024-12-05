<?php

namespace Tests\AppBundle\Doctrine\EventSubscriber;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Doctrine\EventSubscriber\TaskListSubscriber;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Vehicle;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\TaskListManager;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskListSubscriberFunctionalTest extends KernelTestCase
{

    protected $subscriber;
    protected $entityManager;
    protected $taskListManager;
    protected $iriConverter;
    protected $fixturesLoader;

    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $taskListRepository = $this->entityManager->getRepository(TaskList::class);

        $messageBus = $this->prophesize(MessageBus::class);
        $eventBus = $this->prophesize(MessageBusInterface::class);
        $translator = $this->prophesize(TranslatorInterface::class);
        $routing = $this->prophesize(RoutingInterface::class);

        $this->subscriber = new TaskListSubscriber(
            $messageBus->reveal(),
            $eventBus->reveal(),
            $translator->reveal(),
            $routing->reveal(),
            $taskListRepository,
            $this->entityManager,
            new NullLogger()
        );

        $this->taskListManager = self::getContainer()->get(TaskListManager::class);
        $this->iriConverter = self::getContainer()->get(IriConverterInterface::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');


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
            __DIR__.'/../../../../features/fixtures/ORM/task_list.yml'
        ]);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $task = $taskList->getTasks()[0];

        $this->assertEquals($task->getCo2emissions(), 0);
        $this->assertEquals($task->getDistanceFromPrevious(), 0);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->findAll()[0];        

        $taskList->setVehicle($vehicle);

        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        /** @var Task */
        $task = $taskList->getTasks()[0];

        $this->assertEquals($task->getCo2emissions(), 41);
        $this->assertEquals($task->getDistanceFromPrevious(), 4175);
    }

    public function testDistanceCalculationWithoutVehicle() {
        $this->fixturesLoader->load([
            __DIR__.'/../../../../features/fixtures/ORM/task_list.yml'
        ]);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $tasks = $taskList->getTasks();

        $this->assertEquals($tasks[0]->getCo2emissions(), 0);
        $this->assertEquals($tasks[0]->getDistanceFromPrevious(), 0);

        $this->assertEquals($tasks[1]->getCo2emissions(), 0);
        $this->assertEquals($tasks[1]->getDistanceFromPrevious(), 895);

    }
}