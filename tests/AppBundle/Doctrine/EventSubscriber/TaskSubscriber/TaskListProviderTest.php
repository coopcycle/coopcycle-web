<?php

namespace Tests\AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class TaskListProviderTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $taskListRepository;
    private $provider;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->taskListRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(TaskList::class)
            ->willReturn($this->taskListRepository->reveal());

        // No task list exists yet for the resolved (date, courier), so a fresh
        // one gets created with the date getTaskList() decided on.
        $this->taskListRepository
            ->findOneBy(Argument::any())
            ->willReturn(null);
        $this->entityManager->persist(Argument::type(TaskList::class))->willReturn(null);

        $this->provider = new TaskListProvider($this->entityManager->reveal());
    }

    /**
     * Reproduces issue #874.
     *
     * A task whose time range spans several days is assigned through
     * PUT /api/tasks/{id}/assign, i.e. AssignTrait::assign() -> Task::assignTo($user)
     * with NO date, so assignedOn stays null.
     *
     * getTaskList() then falls back to doneBefore, filing the task on the LAST
     * day of its range instead of the day it is actually being dispatched.
     */
    public function testMultiDayTaskAssignedViaEndpointIsFiledOnDoneBefore()
    {
        $courier = new User();
        $courier->setUsername('bob');

        // Month-spanning task, like the affected user's deliveries.
        $task = new Task();
        $task->setDoneAfter(new \DateTime('2026-07-17 10:00:00'));
        $task->setDoneBefore(new \DateTime('2026-08-17 18:00:00'));

        // Assigned via the endpoint => no date passed => assignedOn is null.
        $task->assignTo($courier);
        $this->assertNull($task->getAssignedOn());

        $taskList = $this->provider->getTaskList($task, $courier);

        // The task must not be silently filed onto the last day of its range.
        $this->assertNotEquals(
            $task->getDoneBefore()->format('Y-m-d'),
            $taskList->getDate()->format('Y-m-d'),
            'A multi-day task must not be filed onto its doneBefore (last) day (issue #874)'
        );
    }
}
