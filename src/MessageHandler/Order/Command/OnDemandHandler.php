<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\OnDemand;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
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
