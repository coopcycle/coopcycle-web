<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\FinishPreparingOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
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
