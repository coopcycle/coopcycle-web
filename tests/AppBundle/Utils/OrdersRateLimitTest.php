<?php

namespace AppBundle\Utils;

use PHPUnit\Framework\TestCase;
use AppBundle\Utils\OrdersRateLimit;
use Prophecy\PhpUnit\ProphecyTrait;
use Redis;
use Prophecy\Argument;
use Psr\Log\NullLogger;

class OrdersRateLimitTest extends TestCase
{
	use ProphecyTrait;

    private $redis;

    public function setUp(): void
    {
        $this->redis = $this->prophesize(Redis::class);

        $this->rateLimiter = new OrdersRateLimit(
            $this->redis->reveal(),
            new NullLogger()
        );
    }

    public function testIsRangeFullReturnsTrue()
    {

    }
}

