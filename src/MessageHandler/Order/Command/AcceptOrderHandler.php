<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\AcceptOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Exception\LoopeatInsufficientStockException;
use AppBundle\Validator\Constraints\LoopeatStock as AssertLoopeatStock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Validator\ValidatorInterface;
#[AsMessageHandler(bus: 'command.bus')]
class AcceptOrderHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ValidatorInterface $validator)
    {}

    public function __invoke(AcceptOrder $command)
    {
        $order = $command->getOrder();

        $violations = $this->validator->validate($order->getItems(), new All([ new AssertLoopeatStock(true) ]));
        if (count($violations) > 0) {
            throw new LoopeatInsufficientStockException($violations);
        }

        $event = new Event\OrderAccepted($order);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
