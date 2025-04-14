<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\RefuseOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class RefuseOrderHandler
{
    public function __construct(private RecordsMessages $eventRecorder)
    {}

    public function __invoke(RefuseOrder $command)
    {
        $order = $command->getOrder();

        $this->eventRecorder->record(new Event\OrderRefused($order, $command->getReason()));
    }
}
