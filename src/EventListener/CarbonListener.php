<?php

namespace AppBundle\EventListener;

use Carbon\Carbon;
use Redis;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class CarbonListener
{
    public function __construct(
        private readonly Redis $redis,
    )
    {
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$this->redis->exists('datetime:now')) {
            return;
        }

        $now = $this->redis->get('datetime:now');
        Carbon::setTestNow(Carbon::parse($now));
    }
}
