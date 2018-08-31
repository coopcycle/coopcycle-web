<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CancelOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class CancelOrderHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(CancelOrder $command)
    {
        $order = $command->getOrder();
        $reason = $command->getReason();

        $this->eventRecorder->record(new Event\OrderCancelled($order, $reason));
    }
}
