<?php

namespace AppBundle\MessageHandler;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskCollection;
use AppBundle\Message\CalculateRoute;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CalculateRouteHandler implements MessageHandlerInterface
{
    private $objectManager;
    private $routing;
    private $eventBus;

    public function __construct(
        EntityManagerInterface $objectManager,
        RoutingInterface $routing,
        MessageBus $eventBus)
    {
        $this->objectManager = $objectManager;
        $this->routing = $routing;
        $this->eventBus = $eventBus;
    }

    public function __invoke(CalculateRoute $message)
    {
        $address = $this->objectManager->getRepository(Address::class)
            ->find($message->getAddressId());

        if (!$address) {
            return;
        }

        $tasks = $this->objectManager->getRepository(Task::class)
            ->findByAddress($address);

        $toUpdate = [];

        foreach ($tasks as $task) {

            $collections = $this->objectManager->getRepository(TaskCollection::class)
                ->findByTask($task);

            foreach ($collections as $collection) {
                $toUpdate[] = $collection;
            }
        }

        foreach ($toUpdate as $collection) {
            $this->calculate($collection);
        }

        $this->objectManager->flush();

        foreach ($toUpdate as $collection) {
            $this->eventBus->handle(new Event\TaskCollectionUpdated($collection));
        }
    }

    private function calculate(TaskCollectionInterface $taskCollection)
    {
        $coordinates = [];
        foreach ($taskCollection->getTasks() as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        if (count($coordinates) <= 1) {
            $taskCollection->setDistance(0);
            $taskCollection->setDuration(0);
            $taskCollection->setPolyline('');
        } else {
            $taskCollection->setDistance($this->routing->getDistance(...$coordinates));
            $taskCollection->setDuration($this->routing->getDuration(...$coordinates));
            $taskCollection->setPolyline($this->routing->getPolyline(...$coordinates));
        }
    }
}
