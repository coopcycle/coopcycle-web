<?php

namespace AppBundle\EventListener;

use Carbon\Carbon;
use Redis;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class CarbonListener
{
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->redis->exists('datetime:now')) {
            return;
        }

        $now = $this->redis->get('datetime:now');
        Carbon::setTestNow(Carbon::parse($now));
    }
}
