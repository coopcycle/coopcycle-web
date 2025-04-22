<?php

namespace Tests\AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\OnDemand;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\MessageHandler\Order\Command\OnDemandHandler;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Prophecy\Argument;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OnDemandHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $messageBus;
    private $orderNumberAssigner;

    private $handler;

    public function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);

        $this->handler = new OnDemandHandler(
            $this->messageBus->reveal(),
            $this->orderNumberAssigner->reveal()
        );
    }

    public function testNumberIsAssigned()
    {
        $order = $this->prophesize(OrderInterface::class);

        $this->orderNumberAssigner
            ->assignNumber($order)
            ->shouldBeCalled();

        $this->messageBus
            ->dispatch(Argument::that(function (Envelope $envelope) { return $envelope->getMessage() instanceof OrderCreated; }))
            ->willReturn(new Envelope(new OrderCreated($order->reveal())))
            ->shouldBeCalled();

        $command = new OnDemand($order->reveal());

        call_user_func_array($this->handler, [$command]);
    }
}
