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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The routing service is stubbed on purpose.
 *
 * What this handler decides is *which leg belongs to which task*. Routing
 * against the real OSRM would make the expected numbers depend on the map
 * extract and OSRM version shipped in each environment -- which is how the
 * previous version of this test came to assert distances that do not appear
 * anywhere in the route it actually computes.
 *
 * Legs are therefore 1000, 2000, 3000... in order, so the leg a task was given
 * can be read straight off the number.
 */
class CalculateTaskListsDistanceHandlerTest extends KernelTestCase
{
    use ProphecyTrait;

    private const CO2_PER_KM = 10; // the fixture vehicle

    private $entityManager;
    private $fixturesLoader;
    private $routing;
    private $handler;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->routing->route(Argument::cetera())->will(function ($coordinates) {
            $legs = [];

            // A route over N points has N-1 legs.
            for ($i = 1; $i < count($coordinates); $i++) {
                $legs[] = ['distance' => $i * 1000];
            }

            return ['routes' => [['legs' => $legs]]];
        });

        $this->handler = new CalculateTaskListsDistanceHandler(
            $this->entityManager,
            $this->routing->reveal(),
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

    private function withVehicle(TaskList $taskList): TaskList
    {
        $taskList->setVehicle($this->entityManager->getRepository(Vehicle::class)->findAll()[0]);

        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        return $taskList;
    }

    private function sweep(TaskList $taskList): void
    {
        ($this->handler)(new CalculateTaskListsDistance($taskList->getDate()->format('Y-m-d')));
    }

    /**
     * The handler writes straight to the table, so entities held here are unaware
     * of it until reloaded.
     */
    private function reload(int $taskId): Task
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Task::class)->find($taskId);
    }

    /**
     * @return int[] task ids, in list order
     */
    private function taskIds(TaskList $taskList): array
    {
        return array_map(fn (Task $t) => $t->getId(), $taskList->getTasks());
    }

    /**
     * With a warehouse the route is warehouse -> each task -> warehouse, so leg N
     * arrives at task N. The final leg is the return to the warehouse and belongs
     * to no task.
     *
     * The fixture list holds 7 tasks, some inside tours and some not, and all of
     * them are covered in a single pass -- where the per-completion job only ever
     * wrote the one task it was dispatched for.
     */
    public function testEveryTaskGetsTheLegThatArrivesAtIt()
    {
        $taskList = $this->withVehicle($this->loadTaskList());
        $ids = $this->taskIds($taskList);

        $this->assertCount(7, $ids, 'fixture shape changed');

        $this->sweep($taskList);

        foreach ([1000, 2000, 3000, 4000, 5000, 6000, 7000] as $index => $expected) {
            $this->assertEquals(
                $expected,
                $this->reload($ids[$index])->getTraveledDistanceMeter(),
                sprintf('task at position %d', $index)
            );
        }
    }

    public function testEmissionsAreTheVehicleRateOverTheDistance()
    {
        $taskList = $this->withVehicle($this->loadTaskList());
        $ids = $this->taskIds($taskList);

        $this->sweep($taskList);

        // first task: 1000m
        $this->assertEquals(self::CO2_PER_KM * 1, $this->reload($ids[0])->getEmittedCo2());
        // fifth task: 5000m
        $this->assertEquals(self::CO2_PER_KM * 5, $this->reload($ids[4])->getEmittedCo2());
    }

    /**
     * Without a vehicle there is no warehouse, so the route starts at the first
     * task: nothing travels to it, and there is no vehicle to attribute emissions
     * to. Leg N then arrives at task N+1.
     */
    public function testWithoutAVehicleTheFirstTaskHasNoDistanceAndNothingEmits()
    {
        $taskList = $this->loadTaskList();
        $ids = $this->taskIds($taskList);

        $this->sweep($taskList);

        $first = $this->reload($ids[0]);
        $this->assertEquals(0, $first->getTraveledDistanceMeter());
        $this->assertEquals(0, $first->getEmittedCo2());

        foreach ([1000, 2000, 3000, 4000, 5000, 6000] as $index => $expected) {
            $task = $this->reload($ids[$index + 1]);

            $this->assertEquals($expected, $task->getTraveledDistanceMeter(),
                sprintf('task at position %d', $index + 1));
            $this->assertEquals(0, $task->getEmittedCo2(), 'no vehicle, no emissions');
        }
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
        $taskList = $this->withVehicle($this->loadTaskList());
        $taskId = $this->taskIds($taskList)[0];

        $before = $this->reload($taskId)->getUpdatedAt();

        $this->sweep($taskList);

        $task = $this->reload($taskId);

        $this->assertEquals(1000, $task->getTraveledDistanceMeter(),
            'the distance must still have been written');

        $this->assertEquals(
            $before->format(\DateTime::ATOM),
            $task->getUpdatedAt()->format(\DateTime::ATOM),
            'a measurement about a task is not a change to it, and must not look like one'
        );
    }
}
