<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\DelayOrder;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;

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
