<?php

namespace AppBundle\Messenger;

use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Redis;

class MockDateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $environment
    )
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ('test' === $this->environment) {
            if ($this->redis->exists('datetime:now')) {
                $now = $this->redis->get('datetime:now');
                Carbon::setTestNow(Carbon::parse($now));
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }

}
