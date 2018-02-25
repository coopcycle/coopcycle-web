<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskCollectionSubscriber implements EventSubscriber
{
    private $dispatcher;
    private $routing;

    public function __construct(EventDispatcherInterface $dispatcher, RoutingInterface $routing)
    {
        $this->dispatcher = $dispatcher;
        $this->routing = $routing;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
        );
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaskCollectionInterface) {

            $coordinates = [];
            foreach ($entity->getTasks() as $task) {
                $coordinates[] = $task->getAddress()->getGeo();
            }

            $data = $this->routing->getServiceResponse('route', $coordinates, [
                'steps' => 'true',
                'overview' => 'full'
            ]);

            $entity->setDistance((int) $data['routes'][0]['distance']);
            $entity->setDuration((int) $data['routes'][0]['duration']);
        }
    }
}
