<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CancelOrder;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Handler\CancelOrderHandler;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use SimpleBus\Message\Recorder\RecordsMessages;

class CancelOrderHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);

        $this->handler = new CancelOrderHandler(
            $this->eventRecorder->reveal()
        );
    }

    public function testCancelOrderWithNoShowReasonThrowsException()
    {
        $this->expectException(OrderNotCancellableException::class);

        $order = new Order();
        $order->setFulfillmentMethod('delivery');

        $this->eventRecorder
            ->record(Argument::type(OrderCancelled::class))
            ->shouldNotBeCalled();

        $command = new CancelOrder($order, OrderInterface::CANCEL_REASON_NO_SHOW);

        call_user_func_array($this->handler, [ $command ]);
    }
}
