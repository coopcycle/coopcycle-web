<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Message\CalculateTaskListsDistance;
use AppBundle\Service\RoutingInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CalculateTaskListsDistanceHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoutingInterface $routing,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(CalculateTaskListsDistance $message): void
    {
        $date = new \DateTime($message->date ?? 'yesterday');

        $taskLists = $this->entityManager->getRepository(TaskList::class)
            ->findBy(['date' => $date]);

        $this->logger->info(sprintf(
            'Calculating distances for %d task list(s) on %s',
            count($taskLists),
            $date->format('Y-m-d')
        ));

        foreach ($taskLists as $taskList) {
            try {
                $this->calculate($taskList);
            } catch (\Exception $e) {
                // One unroutable list must not cost us the rest of the day.
                $this->logger->error(sprintf(
                    'Could not calculate distances for TaskList#%d: %s',
                    $taskList->getId(),
                    $e->getMessage()
                ));
            }
        }
    }

    private function calculate(TaskList $taskList): void
    {
        $tasks = $taskList->getTasks();
        $vehicle = $taskList->getVehicle();
        $warehouseGeo = null;

        if (null !== $vehicle) {
            $warehouseGeo = $vehicle->getWarehouse()->getAddress()->getGeo();
        }

        $coordinates = [];

        if (null !== $warehouseGeo) {
            $coordinates[] = $warehouseGeo;
        }

        foreach ($tasks as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        if (null !== $warehouseGeo) {
            // going back to the warehouse
            $coordinates[] = $warehouseGeo;
        }

        if (count($coordinates) <= 1) {
            return;
        }

        // One routing call for the whole list. Doing this per completed task
        // meant rebuilding the same route once for every task on it.
        $route = $this->routing->route(...$coordinates)['routes'][0];

        $distances = $this->distancePerTask($tasks, $route['legs'], null !== $warehouseGeo);

        $co2PerKm = null !== $vehicle ? $vehicle->getCo2emissions() : 0;

        foreach ($distances as $taskId => $distance) {
            $this->store($taskId, $distance, intval($co2PerKm * $distance / 1000));
        }
    }

    /**
     * Maps each task to the distance of the leg that arrives at it.
     *
     * With a warehouse the route starts there, so leg N arrives at task N. Without
     * one the route starts at the first task, which is therefore never arrived at
     * and gets no distance -- leg N then arrives at task N+1.
     *
     * @param Task[] $tasks
     * @return array<int, int> task id => distance in meters
     */
    private function distancePerTask(array $tasks, array $legs, bool $hasWarehouse): array
    {
        if ($hasWarehouse) {
            // The last leg is the return to the warehouse: no task arrives there.
            $legs = array_slice($legs, 0, -1);
        }

        $offset = $hasWarehouse ? 0 : 1;

        $distances = [];

        foreach ($legs as $index => $leg) {
            $task = $tasks[$index + $offset] ?? null;

            if (null === $task) {
                continue;
            }

            $distances[$task->getId()] = intval($leg['distance']);
        }

        return $distances;
    }

    /**
     * Writes straight to the table rather than through the entity.
     *
     * These two columns are a measurement *about* a task, not a change *to* it.
     * Going through the ORM would make Doctrine see a modified entity, which
     * publishes a `task:updated` live update and bumps `updatedAt` -- and a
     * bumped `updatedAt` makes the payload look like the freshest known state to
     * every client ordering updates by it. That is exactly how a batch job ended
     * up reverting completed tasks on the dispatch board.
     */
    private function store(int $taskId, int $distance, int $emittedCo2): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE task SET traveled_distance_meter = :distance, emitted_co2 = :co2 WHERE id = :id',
            ['distance' => $distance, 'co2' => $emittedCo2, 'id' => $taskId],
            ['distance' => ParameterType::INTEGER, 'co2' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
        );
    }
}
