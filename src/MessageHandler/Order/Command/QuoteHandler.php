<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Quote;
use AppBundle\Domain\Order\Event;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class QuoteHandler
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

    public function __invoke(Quote $command)
    {
        $order = $command->getOrder();

        $this->orderNumberAssigner->assignNumber($order);

        $this->eventRecorder->record(new Event\CheckoutSucceeded($order));
    }
}
