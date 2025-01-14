<?php

namespace Tests\AppBundle\Domain\Task\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Doctrine\EventSubscriber\TaskListSubscriber;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Vehicle;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\TaskListManager;
use AppBundle\Service\TaskManager;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskDoneHandlerTest extends KernelTestCase
{

    protected $subscriber;
    protected $entityManager;
    protected $taskListManager;
    protected $iriConverter;
    protected $fixturesLoader;
    protected $taskManager;

    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $taskListRepository = $this->entityManager->getRepository(TaskList::class);

        $this->taskManager = self::getContainer()->get(TaskManager::class);
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
            __DIR__.'/../../../../../features/fixtures/ORM/task_list.yml'
        ]);

        $taskList = $this->entityManager->getRepository(TaskList::class)->findAll()[0];

        $task = $taskList->getTasks()[0];

        $this->assertEquals(0, $task->getCo2emissions());
        $this->assertEquals(0, $task->getDistanceFromPrevious());

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->findAll()[0];        

        $taskList->setVehicle($vehicle);

        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        // task in a tour
        $task = $taskList->getTasks()[0];
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $task->getId()]);

        $this->taskManager->markAsDone($task);

        $this->assertEquals(41, $task->getCo2emissions());
        $this->assertEquals(4175, $task->getDistanceFromPrevious());

        // task not in a tour
        $task = $taskList->getTasks()[4];
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $task->getId()]);

        $this->taskManager->markAsDone($task);

        $this->assertEquals(40, $task->getCo2emissions());
        $this->assertEquals(405, $task->getDistanceFromPrevious());

    }
}