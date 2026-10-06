<?php

declare(strict_types=1);

namespace AppBundle\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Holds back the messages bound for a transport while a transaction runs, and
 * sends them once it has committed.
 *
 * Doctrine dispatches the domain events from postFlush, so a flush inside a
 * transaction already sends the live updates, webhooks and notifications about
 * rows that are not committed yet - and never will be when it rolls back.
 * Messages handled synchronously are not held: they write in the same transaction.
 *
 * Symfony's DispatchAfterCurrentBusStamp only holds the messages dispatched with
 * the stamp, until the end of the message being handled, not of a transaction
 * inside it.
 *
 * @see TransactionalMessagesMiddleware
 */
class TransactionalMessages
{
    /**
     * @var array<int, array{Envelope, StackInterface}>|null
     */
    private ?array $held = null;

    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback Runs the transaction, and throws when it is rolled back
     *
     * @return T
     */
    public function run(callable $callback): mixed
    {
        // Nested: the messages are sent when the outermost transaction commits
        if ($this->isHolding()) {
            return $callback();
        }

        $this->held = [];

        try {
            $result = $callback();
            $held = $this->held;
        } finally {
            // Rolled back: the messages are dropped along with the rows they are about
            $this->held = null;
        }

        foreach ($held as [$envelope, $stack]) {
            try {
                $stack->next()->handle($envelope, $stack);
            } catch (\Throwable $e) {
                // The transaction is committed, one message that can not be sent
                // must not keep the others from going out.
                $this->logger->error(
                    sprintf('Failed to send message after commit: %s', $e->getMessage()),
                    ['message' => get_class($envelope->getMessage())]
                );
            }
        }

        return $result;
    }

    public function isHolding(): bool
    {
        return !is_null($this->held);
    }

    public function hold(Envelope $envelope, StackInterface $stack): void
    {
        if (!$this->isHolding()) {
            throw new \LogicException('Messages can only be held while a transaction runs');
        }

        $this->held[] = [$envelope, $stack];
    }
}
