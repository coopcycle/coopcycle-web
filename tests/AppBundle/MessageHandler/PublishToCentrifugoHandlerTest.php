<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Message\PublishToCentrifugo;
use AppBundle\MessageHandler\PublishToCentrifugoHandler;
use phpcent\Client as CentrifugoClient;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

class PublishToCentrifugoHandlerTest extends TestCase
{
    use ProphecyTrait;

    private PublishToCentrifugoHandler $handler;
    private $centrifugoClient;
    private $logger;

    public function setUp(): void
    {
        $this->centrifugoClient = $this->prophesize(CentrifugoClient::class);
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->handler = new PublishToCentrifugoHandler(
            $this->centrifugoClient->reveal(),
            $this->logger->reveal(),
        );
    }

    private function message(array $channels): PublishToCentrifugo
    {
        return new PublishToCentrifugo($channels, [
            'name' => 'task:done',
            'data' => ['task' => ['id' => 1, 'status' => 'DONE']],
            'version' => '2026-10-05T12:45:12+02:00',
        ]);
    }

    /**
     * One channel is a publish; several are a single broadcast, which is why the
     * fan-out costs one HTTP call rather than one per recipient.
     */
    public function testASingleChannelIsPublished(): void
    {
        $message = $this->message(['ns_events#bob']);

        $this->centrifugoClient
            ->publish('ns_events#bob', ['event' => $message->event])
            ->shouldBeCalledOnce();
        $this->centrifugoClient->broadcast(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testSeveralChannelsAreBroadcast(): void
    {
        $message = $this->message(['ns_events#bob', 'ns_events#alice']);

        $this->centrifugoClient
            ->broadcast(['ns_events#bob', 'ns_events#alice'], ['event' => $message->event])
            ->shouldBeCalledOnce();
        $this->centrifugoClient->publish(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($message);
    }

    /**
     * Centrifugo rejects a broadcast with an empty channel list, and there is
     * nothing to say anyway when an event has no recipients.
     */
    public function testNoChannelsPublishesNothing(): void
    {
        $this->centrifugoClient->publish(Argument::cetera())->shouldNotBeCalled();
        $this->centrifugoClient->broadcast(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($this->message([]));
    }

    /**
     * Centrifugo answers 200 with an `error` object in the body when it refuses a
     * publication, and phpcent only throws on a non-200. Without inspecting the
     * body a refused event is indistinguishable from a delivered one.
     */
    public function testARefusedPublicationIsLoggedAsAnError(): void
    {
        $this->centrifugoClient->broadcast(Argument::cetera())
            ->willReturn(['error' => ['code' => 102, 'message' => 'unknown channel']]);

        $this->logger
            ->error(Argument::containingString('Centrifugo refused event'))
            ->shouldBeCalledOnce();

        ($this->handler)($this->message(['a', 'b']));
    }

    public function testADeliveredPublicationIsNotLoggedAsAnError(): void
    {
        $this->centrifugoClient->broadcast(Argument::cetera())
            ->willReturn(['result' => []]);

        $this->logger->error(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($this->message(['a', 'b']));
    }
}
