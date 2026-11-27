<?php

namespace AppBundle\Geocoder;

use Redis;
use Spatie\GuzzleRateLimiterMiddleware\Store;

class RateLimiterStore implements Store
{
    /**
     * How long an untouched list is kept.
     *
     * The limiter only ever cares about the last time frame, so a provider that
     * stops being called has nothing worth keeping. Belt and braces: it means a
     * key cannot survive indefinitely even if trimming were ever skipped.
     */
    private const TTL = 60;

    private $redis;
    private $service;

    public function __construct(Redis $redis, string $service)
    {
        $this->redis = $redis;
        $this->service = $service;
    }

    public function get(): array
    {
        return $this->redis->lRange($this->key(), 0, -1);
    }

    public function push(int $timestamp, int $limit)
    {
        $key = $this->key();

        $this->redis->rPush($key, $timestamp);

        // The caller only looks at the newest $limit timestamps that fall inside
        // the current time frame, so anything older is dead weight -- and it is
        // not free weight: get() reads the whole list back on every request, so
        // an untrimmed list costs both Redis and this process more on each call
        // than the one before. Trimming here keeps that flat instead of linear.
        $this->redis->lTrim($key, -$limit, -1);
        $this->redis->expire($key, self::TTL);
    }

    private function key(): string
    {
        return sprintf('%s:rate-limiter', $this->service);
    }
}
