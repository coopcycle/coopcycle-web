<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Dto\TourInput;
use AppBundle\Entity\Task;
use AppBundle\Entity\Tour;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;

class TourInputDataTransformer implements DataTransformerInterface
{
    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        if ($context["operation_type"] == "item" && $context["item_operation_name"] == "put") {
            $tour = $context['object_to_populate'];

            if (!empty($data->name)) {
                $tour->setName($data->name);
            }
            
            $tour->setTasks($data->tasks);

            foreach ($data->tasks as $task) {
                $task->setTour($tour);
            }

        } else {
            $tour = new Tour();
            
            $tour->setName($data->name);

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

        return $tour;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Tour) {
            return false;
        }

        return $to === Tour::class && ($context['input']['class'] ?? null) === TourInput::class;
    }
}

