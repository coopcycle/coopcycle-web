<?php

namespace AppBundle\LoopEat;

use AppBundle\Entity\Task;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskListener
{
    public function __construct(private TranslatorInterface $translator)
    {}

    public function prePersist(Task $task, LifecycleEventArgs $args)
    {
    	if (!$task->isDropoff()) {
    		return;
    	}

        $delivery = $task->getDelivery();
        if (null !== $delivery) {

        	$order = $delivery->getOrder();
        	if (null !== $order) {
        		if ($order->hasLoopeatReturns()) {
        			$comments = $this->translator->trans('loopeat.task_comments.returns')
        				. "\n\n" . $order->getLoopeatReturnsAsText();
        			$task->appendToComments($comments);
        		}
        	}
        }
    }
}

