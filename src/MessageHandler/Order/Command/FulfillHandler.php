<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Fulfill;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
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
