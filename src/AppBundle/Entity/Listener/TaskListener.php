<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Task;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;

class TaskListener
{
    private $tile38;
    private $doorstepChanNamespace;
    private $fleetKey;
    private $logger;

    public function __construct(Redis $tile38, string $doorstepChanNamespace, string $fleetKey, LoggerInterface $logger)
    {
        $this->tile38 = $tile38;
        $this->doorstepChanNamespace = $doorstepChanNamespace;
        $this->fleetKey = $fleetKey;
        $this->logger = $logger;
    }

    public function prePersist(Task $task, LifecycleEventArgs $args)
    {
        if (null === $task->getDoneAfter()) {
            $doneAfter = clone $task->getDoneBefore();
            $doneAfter->modify('-15 minutes');
            $task->setDoneAfter($doneAfter);
        }
    }

    public function postPersist(Task $task, LifecycleEventArgs $args)
    {
        // https://tile38.com/topics/geofencing/
        // SETCHAN warehouse NEARBY fleet FENCE POINT 33.462 -112.268 6000
        // SETCHAN warehouse NEARBY fleet FENCE DETECT enter COMMANDS set POINT 33.462 -112.268 6000
        if ($task->isDoorstep()) {
            $this->tile38->executeRaw([
                'SETCHAN',
                sprintf('%s:dropoff:%d', $this->doorstepChanNamespace, $task->getId()),
                'NEARBY',
                $this->fleetKey,
                'FENCE',
                'DETECT',
                'enter',
                'COMMANDS',
                'set',
                'POINT',
                $task->getAddress()->getGeo()->getLatitude(),
                $task->getAddress()->getGeo()->getLongitude(),
                '1500',
            ]);
        }
    }
}
