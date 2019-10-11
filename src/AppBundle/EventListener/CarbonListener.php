<?php

namespace AppBundle\EventListener;

use Carbon\Carbon;
use Predis\Client as Redis;
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
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$this->redis->exists('datetime:now')) {
            return;
        }

        $now = $this->redis->get('datetime:now');
        Carbon::setTestNow(Carbon::parse($now));
    }
}
