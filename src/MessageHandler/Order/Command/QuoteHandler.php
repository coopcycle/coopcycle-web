<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Quote;
use AppBundle\Domain\Order\Event;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class QuoteHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private OrderNumberAssignerInterface $orderNumberAssigner)
    {}

    public function __invoke(Quote $command)
    {
        $order = $command->getOrder();

        $this->orderNumberAssigner->assignNumber($order);

        $event = new Event\CheckoutSucceeded($order);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
