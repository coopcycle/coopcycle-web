<?php

namespace Tests\AppBundle\LoopEat;

use AppBundle\LoopEat\Context;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ContextTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {}

    public function testSuggestToReturnContainers()
    {
    	$context = new Context();

    	$context->formats = [
    		['id' => 1, 'cost_cents' => 100],
    		['id' => 2, 'cost_cents' => 200]
    	];

    	$context->containers = [
    		['format_id' => 1, 'quantity' => 1],
    		['format_id' => 2, 'quantity' => 4]
    	];
    	$context->containersCount = 5;

    	$context->requiredAmount = 700;

    	$order = $this->prophesize(OrderInterface::class);
    	$order
    		->getReturnsAmountForLoopeat()
    		->willReturn(0);
    	$order
    		->hasLoopeatReturns()
    		->willReturn(false);

    	$suggestion = $context->suggest($order->reveal());

    	$this->assertEquals(Context::SUGGESTION_RETURNS, $suggestion);
    }

    public function testSuggestToAddCredits()
    {
    	$context = new Context();

    	$context->formats = [
    		['id' => 1, 'cost_cents' => 100],
    		['id' => 2, 'cost_cents' => 200]
    	];

    	$context->containers = [];
    	$context->containersCount = 0;

    	$context->requiredAmount = 700;

    	$order = $this->prophesize(OrderInterface::class);
    	$order
    		->getReturnsAmountForLoopeat()
    		->willReturn(0);
    	$order
    		->hasLoopeatReturns()
    		->willReturn(false);

    	$suggestion = $context->suggest($order->reveal());

    	$this->assertEquals(Context::SUGGESTION_ADD_CREDITS, $suggestion);
    }

    public function testSuggestNone()
    {
    	$context = new Context();

    	$context->formats = [
    		['id' => 1, 'cost_cents' => 100],
    		['id' => 2, 'cost_cents' => 200]
    	];

    	$context->containers = [];
    	$context->containersCount = 0;

    	$context->creditsCountCents = 1200;
    	$context->requiredAmount = 700;

    	$order = $this->prophesize(OrderInterface::class);
    	$order
    		->getReturnsAmountForLoopeat()
    		->willReturn(0);
    	$order
    		->hasLoopeatReturns()
    		->willReturn(false);

    	$suggestion = $context->suggest($order->reveal());

    	$this->assertEquals(Context::SUGGESTION_NONE, $suggestion);
    }
}
