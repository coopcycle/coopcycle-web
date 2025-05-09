<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\TourInput;
use AppBundle\Entity\Task;
use AppBundle\Entity\Tour;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;

class TourProcessor implements ProcessorInterface
{
    public function __construct(
        private RoutingInterface $routing,
        private ProcessorInterface $persistProcessor,
        private ItemProvider $provider)
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Put) {

            /** @var Tour */
            $tour = $this->provider->provide($operation, $uriVariables, $context);

            if (!empty($data->name)) {
                $tour->setName($data->name);
            }

            $tour->setTasks($data->tasks);

        } else {

            $tour = new Tour();

            $tour->setName($data->name);
            $tour->setDate(new \DateTime($data->date));

            foreach ($data->tasks as $task) {
                $tour->addTask($task);
            }
        }

        $tasks = $tour->getTasks();
        $distance = 0;

        // Distance can't be calculated without at least 2 tasks
        if (count($tasks) >= 2) {
            $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $tasks);
            $distance = $this->routing->getDistance(...$coords);
        }

        $tour->setDistance(ceil($distance));

        return $this->persistProcessor->process($tour, $operation, $uriVariables, $context);
    }
}
