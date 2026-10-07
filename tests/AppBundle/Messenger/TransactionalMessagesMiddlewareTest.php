<?php

declare(strict_types=1);

namespace Tests\AppBundle\Messenger;

use AppBundle\Message\PublishToCentrifugo;
use AppBundle\Messenger\TransactionalMessages;
use AppBundle\Messenger\TransactionalMessagesMiddleware;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

class TransactionalMessagesMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    private TransactionalMessages $transactionalMessages;
    private MessageBus $bus;

    /**
     * @var object[] The messages that made it past the middleware
     */
    private array $dispatched = [];

    public function setUp(): void
    {
        $sendersLocator = $this->prophesize(SendersLocatorInterface::class);

        // PublishToCentrifugo is routed to a transport, anything else is handled synchronously
        $sendersLocator
            ->getSenders(Argument::that(fn(Envelope $envelope) => $envelope->getMessage() instanceof PublishToCentrifugo))
            ->willReturn(['async' => $this->prophesize(SenderInterface::class)->reveal()]);
        $sendersLocator
            ->getSenders(Argument::that(fn(Envelope $envelope) => !$envelope->getMessage() instanceof PublishToCentrifugo))
            ->willReturn([]);

        $this->transactionalMessages = new TransactionalMessages(new NullLogger());

        $recorder = new class($this->dispatched) implements MiddlewareInterface {
            public function __construct(private array &$dispatched)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->dispatched[] = $envelope->getMessage();

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $this->bus = new MessageBus([
            new TransactionalMessagesMiddleware($this->transactionalMessages, $sendersLocator->reveal()),
            $recorder,
        ]);
    }

    private function createLiveUpdate(): PublishToCentrifugo
    {
        return new PublishToCentrifugo(['test_events#bob'], ['name' => 'task:created', 'data' => [], 'version' => null]);
    }

    public function testSendsRightAwayOutsideOfATransaction()
    {
        $liveUpdate = $this->createLiveUpdate();

        $this->bus->dispatch($liveUpdate);

        $this->assertSame([$liveUpdate], $this->dispatched);
    }

    public function testSendsOnceTheTransactionCommits()
    {
        $liveUpdate = $this->createLiveUpdate();

        $result = $this->transactionalMessages->run(function () use ($liveUpdate) {
            $this->bus->dispatch($liveUpdate);

            $this->assertSame([], $this->dispatched);

            return 'committed';
        });

        $this->assertEquals('committed', $result);
        $this->assertSame([$liveUpdate], $this->dispatched);
        $this->assertFalse($this->transactionalMessages->isHolding());
    }

    public function testDropsTheMessagesWhenTheTransactionRollsBack()
    {
        try {
            $this->transactionalMessages->run(function () {
                $this->bus->dispatch($this->createLiveUpdate());

                throw new \RuntimeException('Allowed memory size exhausted');
            });
            $this->fail('The exception should not be swallowed');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Allowed memory size exhausted', $e->getMessage());
        }

        $this->assertSame([], $this->dispatched);
        $this->assertFalse($this->transactionalMessages->isHolding());

        // The next transaction starts from scratch
        $liveUpdate = $this->createLiveUpdate();
        $this->transactionalMessages->run(fn() => $this->bus->dispatch($liveUpdate));

        $this->assertSame([$liveUpdate], $this->dispatched);
    }

    public function testDoesNotHoldMessagesHandledSynchronously()
    {
        // A domain event: its handlers write in the same transaction
        $event = new \stdClass();

        $this->transactionalMessages->run(function () use ($event) {
            $this->bus->dispatch($event);

            $this->assertSame([$event], $this->dispatched);
        });
    }

    public function testDoesNotHoldMessagesReceivedFromATransport()
    {
        $liveUpdate = $this->createLiveUpdate();

        $this->transactionalMessages->run(function () use ($liveUpdate) {
            $this->bus->dispatch(new Envelope($liveUpdate, [new ReceivedStamp('async')]));

            $this->assertSame([$liveUpdate], $this->dispatched);
        });
    }

    public function testNestedTransactionsSendWhenTheOutermostCommits()
    {
        $liveUpdate = $this->createLiveUpdate();

        $this->transactionalMessages->run(function () use ($liveUpdate) {
            $this->transactionalMessages->run(fn() => $this->bus->dispatch($liveUpdate));

            $this->assertSame([], $this->dispatched);
        });

        $this->assertSame([$liveUpdate], $this->dispatched);
    }
}
