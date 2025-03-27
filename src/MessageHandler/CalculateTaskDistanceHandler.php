<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskListRepository;
use AppBundle\Message\CalculateTaskDistance;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CalculateTaskDistanceHandler implements MessageHandlerInterface
{

    public function __construct(
        private EntityManagerInterface $objectManager,
        private RoutingInterface $routing,
        private TaskListRepository $taskListRepository,
        private LoggerInterface $logger  
    )
    {}

    public function __invoke(CalculateTaskDistance $message)
    {
        $task = $this->objectManager->getRepository(Task::class)->findOneById($message->getTaskId());

        $taskList = $this->taskListRepository->findLastTaskListByTask($task);

        if (!$taskList) {
            $this->logger->error('Task was marked as finished but no corresponding tasklist was found'); // should not happen
            return;
        }

        $coordinates = [];
        $vehicle = $taskList->getVehicle();

        if (!is_null($vehicle)) {
            $coordinates[] = $taskList->getVehicle()->getWarehouse()->getAddress()->getGeo();
        }

        foreach ($taskList->getTasks() as $item) {
            $coordinates[] = $item->getAddress()->getGeo();
        }

        // going back to the warehouse
        if (!is_null($vehicle)) {
            $coordinates[] = $taskList->getVehicle()->getWarehouse()->getAddress()->getGeo();
        }


        if (count($coordinates) <= 1) {
            return;
        }

        // TODO : if we saved the whole route on the TaskList (not just the distance) we would not have to recalculate the legs here
        $route = $this->routing->route(...$coordinates)['routes'][0];

        if (!is_null($vehicle)) {
            $legs = array_slice($route["legs"], 0, -1); // return to the warehouse is not materialized by a task
            foreach ($legs as $index => $leg) {
                $current = $taskList->getTasks()[$index];
                if ($current->getId() === $task->getId()) {
                    $emissions = intval($vehicle->getCo2emissions() * $leg['distance'] / 1000);
                    $task->setTraveledDistanceMeter(intval($leg['distance'])); // in meter
                    $task->setEmittedCo2($emissions);
                    break;
                }
            }
        } else {
            $legs = $route["legs"];
            foreach ($legs as $index => $leg) {
                $current = $taskList->getTasks()[$index + 1]; // we assume we start at the first task, as there is no warehouse
                if ($current->getId() === $task->getId()) {
                    $task->setTraveledDistanceMeter(intval($leg['distance'])); // in meter
                    $task->setEmittedCo2(0);
                    break;
                } // reset
            }
        }

        $this->objectManager->persist($task);
        $this->objectManager->flush();
    }
}
