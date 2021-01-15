<?php

namespace AppBundle\Geocoder;

use Redis;
use Spatie\GuzzleRateLimiterMiddleware\Store;

class RateLimiterStore implements Store
{
    private $redis;
    private $service;

    public function __construct(Redis $redis, string $service)
    {
        $this->redis = $redis;
        $this->service = $service;
    }

    public function get(): array
    {
        return $this->redis->lRange(sprintf('%s:rate-limiter', $this->service), 0, -1);
    }

    public function push(int $timestamp, int $limit)
    {
        $this->redis->rPush(sprintf('%s:rate-limiter', $this->service), $timestamp);
    }
}
