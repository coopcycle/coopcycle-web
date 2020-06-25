<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\OnDemand;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;

class OnDemandHandler
{
    private $eventRecorder;
    private $orderNumberAssigner;

    public function __construct(
        RecordsMessages $eventRecorder,
        OrderNumberAssignerInterface $orderNumberAssigner)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
    }

    public function __invoke(OnDemand $command)
    {
        $order = $command->getOrder();

        // TODO Check if the order is actually for OnDemand

        $this->orderNumberAssigner->assignNumber($order);

        $this->eventRecorder->record(new Event\OrderCreated($order));
    }
}
