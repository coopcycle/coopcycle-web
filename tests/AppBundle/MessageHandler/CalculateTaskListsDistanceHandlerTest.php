<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Vehicle;
use AppBundle\Message\CalculateTaskListsDistance;
use AppBundle\MessageHandler\CalculateTaskListsDistanceHandler;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CalculateTaskListsDistanceHandlerTest extends KernelTestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $fixturesLoader;
    private $handler;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $this->handler = new CalculateTaskListsDistanceHandler(
            $this->entityManager,
            self::getContainer()->get(RoutingInterface::class),
            self::getContainer()->get(LoggerInterface::class)
        );

        (new ORMPurger($this->entityManager))->purge();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null;
    }

    private function loadTaskList(): TaskList
    {
        $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/task_list.yml'
        ]);

        return $this->entityManager->getRepository(TaskList::class)->findAll()[0];
    }

    private function sweep(TaskList $taskList): void
    {
        ($this->handler)(new CalculateTaskListsDistance($taskList->getDate()->format('Y-m-d')));
    }

    /**
     * The handler writes straight to the table, so the entities held here are
     * unaware of it until they are reloaded.
     */
    private function reload(int $taskId): Task
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Task::class)->find($taskId);
    }

    /**
     * The fixture's route, as returned by the routing service, has these legs:
     *
     *   warehouse->43  43->44  44->45  45->46  46->47  47->48  48->49  49->warehouse
     *        4190         898    2750    2803    4346    5245       0            0
     *
     * The last one is the return to the warehouse: no task arrives there, so it
     * is dropped. Each remaining leg belongs to the task it arrives at. (48->49
     * really is zero: the fixture puts those two at the same address.)
     *
     * @return int[] expected distance per task, in list order
     */
    private function expectedWithWarehouse(): array
    {
        return [4190, 898, 2750, 2803, 4346, 5245, 0];
    }

    public function testEveryTaskOfTheListGetsTheLegThatArrivesAtIt()
    {
        $taskList = $this->loadTaskList();

        $taskList->setVehicle($this->entityManager->getRepository(Vehicle::class)->findAll()[0]);
        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        // Tasks inside a tour and tasks outside one, all in one pass. The
        // per-completion job only ever wrote the single task it was dispatched
        // for, so a bulk completion left most of the list at zero.
        $ids = array_map(fn ($t) => $t->getId(), $taskList->getTasks());

        $this->sweep($taskList);

        foreach ($this->expectedWithWarehouse() as $index => $expected) {
            $this->assertEquals(
                $expected,
                $this->reload($ids[$index])->getTraveledDistanceMeter(),
                sprintf('task at position %d', $index)
            );
        }
    }

    public function testEmissionsAreDerivedFromTheVehicle()
    {
        $taskList = $this->loadTaskList();

        $taskList->setVehicle($this->entityManager->getRepository(Vehicle::class)->findAll()[0]);
        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        $firstId = $taskList->getTasks()[0]->getId();

        $this->sweep($taskList);

        $this->assertEquals(41, $this->reload($firstId)->getEmittedCo2());
    }

    /**
     * With no vehicle there is no warehouse, so the route starts at the first
     * task: nothing travels to it, and there is no vehicle to attribute
     * emissions to either.
     */
    public function testWithoutAVehicleTheFirstTaskHasNoDistanceAndNothingEmits()
    {
        $taskList = $this->loadTaskList();

        $ids = array_map(fn ($t) => $t->getId(), $taskList->getTasks());

        $this->sweep($taskList);

        $first = $this->reload($ids[0]);
        $this->assertEquals(0, $first->getTraveledDistanceMeter());
        $this->assertEquals(0, $first->getEmittedCo2());

        // Every later task still gets the leg arriving at it, shifted by one
        // because the route no longer starts at the warehouse.
        $later = $this->reload($ids[4]);
        $this->assertEquals(4346, $later->getTraveledDistanceMeter());
        $this->assertEquals(0, $later->getEmittedCo2(), 'no vehicle, no emissions');
    }

    /**
     * The point of writing through the connection rather than the entity.
     *
     * Going through the ORM would mark the task dirty, which publishes a
     * `task:updated` live update built from whatever state this worker happens to
     * hold, and bumps `updatedAt` so that payload looks like the newest known
     * state to any client ordering updates by it. That is how the per-completion
     * version of this job reverted completed tasks on the dispatch board.
     */
    public function testDoesNotTouchUpdatedAt()
    {
        $taskList = $this->loadTaskList();

        $taskList->setVehicle($this->entityManager->getRepository(Vehicle::class)->findAll()[0]);
        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        $taskId = $taskList->getTasks()[0]->getId();
        $before = $this->reload($taskId)->getUpdatedAt();

        $this->sweep($taskList);

        $task = $this->reload($taskId);

        $this->assertEquals(4190, $task->getTraveledDistanceMeter(),
            'the distance must still have been written');

        $this->assertEquals(
            $before->format(\DateTime::ATOM),
            $task->getUpdatedAt()->format(\DateTime::ATOM),
            'a measurement about a task is not a change to it, and must not look like one'
        );
    }
}
