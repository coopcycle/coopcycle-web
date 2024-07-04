<?php

namespace AppBundle\Service;

use AppBundle\Domain\Order\Event as OrderEvents;
use AppBundle\Domain\Task\Event as TaskEvents;
use AppBundle\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

class NotificationPreferences
{
	private $events = [];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    	$this->events = [
    		OrderEvents\OrderCreated::messageName(),
	        OrderEvents\OrderAccepted::messageName(),
	        OrderEvents\OrderRefused::messageName(),
	        OrderEvents\OrderPicked::messageName(),
	        OrderEvents\OrderDropped::messageName(),
	        OrderEvents\OrderFulfilled::messageName(),
	        TaskEvents\TaskCreated::messageName(),
	        TaskEvents\TaskAssigned::messageName(),
	        TaskEvents\TaskUnassigned::messageName(),
	        TaskEvents\TaskStarted::messageName(),
	        TaskEvents\TaskDone::messageName(),
	        TaskEvents\TaskFailed::messageName(),
	        TaskEvents\TaskCancelled::messageName(),
	        TaskEvents\TaskRescheduled::messageName(),
	    ];
    }

    public function getConfigurableEvents()
    {
    	return $this->events;
    }

    public function isEventEnabled($event): bool
    {
    	$notification = $this->entityManager
            ->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->where('n.name = :messageName')
            ->setParameter('messageName', $event)
            ->getQuery()
            ->getOneOrNullResult();

        // It's enabled by default
        if (null === $notification) {

        	return true;
        }

        return $notification->isEnabled();
    }
}
