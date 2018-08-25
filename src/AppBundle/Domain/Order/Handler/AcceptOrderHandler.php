<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\AcceptOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class AcceptOrderHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(AcceptOrder $command)
    {
        $order = $command->getOrder();

        $this->eventRecorder->record(new Event\OrderAccepted($order));
    }
}
