<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\OnDemand;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Handler\OnDemandHandler;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Prophecy\Argument;

class OnDemandHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $orderNumberAssigner;

    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);

        $this->handler = new OnDemandHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal()
        );
    }

    public function testNumberIsAssigned()
    {
        $order = $this->prophesize(OrderInterface::class);

        $this->orderNumberAssigner
            ->assignNumber($order)
            ->shouldBeCalled();

        $this->eventRecorder
            ->record(Argument::type(OrderCreated::class))
            ->shouldBeCalled();

        $command = new OnDemand($order->reveal());

        call_user_func_array($this->handler, [$command]);
    }
}
