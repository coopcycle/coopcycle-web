<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Refuse;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RefuseTest extends TestCase
{
    use ProphecyTrait;

    public function testOrderCanBeRefusedWithReason()
    {
        $orderManager = $this->prophesize(OrderManager::class);

        $order = new Order();

        $content = json_encode(['reason' => 'Rush hour']);
        $request = Request::create('/foo', 'PUT', [], [], [], [], $content);

        $refuse = new Refuse($orderManager->reveal());

        $response = call_user_func_array($refuse, [$order, $request]);

        $this->assertSame($order, $response);
        $orderManager->refuse($order, 'Rush hour')->shouldHaveBeenCalled();
    }
}
