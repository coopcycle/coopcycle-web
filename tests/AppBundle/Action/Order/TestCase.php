<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Service\OrderManager;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

abstract class TestCase extends BaseTestCase
{
    use ProphecyTrait;

    protected $action;
    protected $actionClass;

    public function setUp(): void
    {
        $this->orderManager = $this->prophesize(OrderManager::class);
        $this->action = $this->createAction($this->orderManager->reveal());
    }

    protected function createAction(OrderManager $orderManager)
    {
        return new $this->actionClass($orderManager);
    }
}
