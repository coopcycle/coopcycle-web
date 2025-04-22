<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\DelayOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class DelayOrderHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(DelayOrder $command)
    {
        $order = $command->getOrder();

        $this->eventRecorder->record(new Event\OrderDelayed($order, $command->getDelay()));
    }
}
