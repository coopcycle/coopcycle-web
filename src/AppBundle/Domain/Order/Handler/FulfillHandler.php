<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Fulfill;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class FulfillHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(Fulfill $command)
    {
        $order = $command->getOrder();

        $this->eventRecorder->record(new Event\OrderFulfilled($order));
    }
}
