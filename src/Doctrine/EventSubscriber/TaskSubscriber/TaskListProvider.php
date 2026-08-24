<?php

namespace AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskListProvider
{
    private $objectManager;
    private $clock;
    private $taskListCache = [];

    public function __construct(EntityManagerInterface $objectManager, ClockInterface $clock)
    {
        $this->objectManager = $objectManager;
        $this->clock = $clock;
    }

    /**
     * Resolve the task list a task belongs to (or should belong to) for a courier.
     *
     * The persisted TaskList\Item structure is the source of truth: we look up
     * the list that already contains the task rather than guessing a date from
     * the task itself. Only when the task is not assigned to this courier yet do
     * we fall back to a date, and that date is the dispatch day (today, clamped
     * to the task's time window) — never blindly `doneBefore` (see issue #874).
     */
    public function getTaskList(Task $task, UserInterface $courier) : TaskList
    {
        // 1. In-memory: a list resolved earlier in this request may already
        //    contain the task (e.g. set_items just appended the item, not yet
        //    flushed, so a DB query would not see it). Return it to avoid
        //    creating a duplicate task list / item.
        foreach ($this->taskListCache as $taskList) {
            if ($taskList->getCourier() === $courier && $taskList->containsTask($task)) {
                return $taskList;
            }
        }

        // 2. Persisted: the list that already contains this task for this courier.
        //    Skip for a task that is not persisted yet (no id): it cannot be in
        //    any list, and binding a task without identifier to the query throws.
        if (null !== $task->getId()) {
            /** @var TaskListRepository $taskListRepository */
            $taskListRepository = $this->objectManager->getRepository(TaskList::class);
            $existing = $taskListRepository->findTaskListContainingTask($task, $courier);

            if (null !== $existing) {
                $this->cache($existing);

                return $existing;
            }
        }

        // 3. Not assigned to this courier yet: use (or create) the list for the
        //    day the task should be dispatched on.
        return $this->getTaskListForUserAndDate($this->resolveAssignmentDate($task), $courier);
    }

    public function getTaskListForUserAndDate(\DateTime $date, UserInterface $courier)
    {
        $taskListRepository = $this->objectManager->getRepository(TaskList::class);

        $taskListCacheKey = sprintf('%s-%s', $date->format('Y-m-d'), $courier->getUsername());

        if (!isset($this->taskListCache[$taskListCacheKey])) {

            $taskList = $taskListRepository->findOneBy([
                'date' => $date,
                'courier' => $courier,
            ]);

            if (!$taskList) {
                $taskList = new TaskList();
                $taskList->setDate($date);
                $taskList->setCourier($courier);

                $this->objectManager->persist($taskList);
            }

            $this->taskListCache[$taskListCacheKey] = $taskList;
        }

        return $this->taskListCache[$taskListCacheKey];
    }

    /**
     * The day a not-yet-listed task should be dispatched on: today, clamped to
     * the task's [doneAfter, doneBefore] window so we never file it outside its
     * own time range.
     */
    private function resolveAssignmentDate(Task $task): \DateTime
    {
        $today = $this->atMidnight($this->clock->now());

        $after = $task->getAfter();
        if (null !== $after) {
            $firstDay = $this->atMidnight($after);
            if ($today < $firstDay) {
                return $firstDay;
            }
        }

        $before = $task->getBefore();
        if (null !== $before) {
            $lastDay = $this->atMidnight($before);
            if ($today > $lastDay) {
                return $lastDay;
            }
        }

        return $today;
    }

    private function atMidnight(\DateTimeInterface $date): \DateTime
    {
        return new \DateTime($date->format('Y-m-d'));
    }

    private function cache(TaskList $taskList): void
    {
        $key = sprintf('%s-%s', $taskList->getDate()->format('Y-m-d'), $taskList->getCourier()->getUsername());
        $this->taskListCache[$key] = $taskList;
    }
}
