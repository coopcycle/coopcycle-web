<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\StartPreparingOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class StartPreparingOrderHandler
{
    public function __construct(private RecordsMessages $eventRecorder)
    {
    }

    public function __invoke(StartPreparingOrder $command)
    {
        $order = $command->getOrder();

        $this->eventRecorder->record(new Event\OrderPreparationStarted($order));
    }
}
