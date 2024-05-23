<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\FinishPreparingOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class FinishPreparingOrderHandler
{
    public function __construct(private RecordsMessages $eventRecorder)
    {
    }

    public function __invoke(FinishPreparingOrder $command)
    {
        $order = $command->getOrder();

        $this->eventRecorder->record(new Event\OrderPreparationFinished($order));
    }
}
