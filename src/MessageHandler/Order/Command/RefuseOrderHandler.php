<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\RefuseOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
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
