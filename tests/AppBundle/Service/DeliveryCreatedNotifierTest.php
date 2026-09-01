<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveriesCreated;
use AppBundle\Message\DeliveryCreated;
use AppBundle\Service\DeliveryCreatedNotifier;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryCreatedNotifierTest extends TestCase
{
    use ProphecyTrait;

    private $messageBus;
    private $notifier;

    public function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus->dispatch(Argument::any())
            ->will(fn ($args) => new Envelope($args[0]));

        $this->notifier = new DeliveryCreatedNotifier($this->messageBus->reveal());
    }

    private function delivery(int $id): Delivery
    {
        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn($id);

        return $delivery->reveal();
    }

    public function testNotifyDispatchesOneMessagePerDelivery()
    {
        $this->notifier->notify($this->delivery(1));
        $this->notifier->notify($this->delivery(2));

        $this->messageBus
            ->dispatch(Argument::type(DeliveryCreated::class))
            ->shouldHaveBeenCalledTimes(2);
    }

    public function testBatchDispatchesASingleRecapMessage()
    {
        $this->notifier->startBatch();

        $this->notifier->notify($this->delivery(1));
        $this->notifier->notify($this->delivery(2));
        $this->notifier->notify($this->delivery(3));

        $this->messageBus->dispatch(Argument::any())->shouldNotHaveBeenCalled();

        $this->notifier->endBatch();

        $this->messageBus
            ->dispatch(Argument::that(fn ($message) =>
                $message instanceof DeliveriesCreated && [1, 2, 3] === $message->getDeliveryIds()))
            ->shouldHaveBeenCalledTimes(1);
    }

    public function testBatchWithOnlyOneDeliveryDispatchesTheRegularMessage()
    {
        $this->notifier->startBatch();
        $this->notifier->notify($this->delivery(1));
        $this->notifier->endBatch();

        $this->messageBus
            ->dispatch(Argument::type(DeliveryCreated::class))
            ->shouldHaveBeenCalledTimes(1);
    }

    public function testEmptyBatchDispatchesNothing()
    {
        $this->notifier->startBatch();
        $this->notifier->endBatch();

        $this->messageBus->dispatch(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testNotifyDispatchesAgainAfterBatchHasEnded()
    {
        $this->notifier->startBatch();
        $this->notifier->notify($this->delivery(1));
        $this->notifier->notify($this->delivery(2));
        $this->notifier->endBatch();

        $this->notifier->notify($this->delivery(3));

        $this->messageBus
            ->dispatch(Argument::type(DeliveryCreated::class))
            ->shouldHaveBeenCalledTimes(1);
        $this->messageBus
            ->dispatch(Argument::type(DeliveriesCreated::class))
            ->shouldHaveBeenCalledTimes(1);
    }
}
