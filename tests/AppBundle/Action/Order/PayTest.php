<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Pay;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Doctrine\Common\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PayTest extends TestCase
{
    public function testMissingStripeTokenThrowsException()
    {
        $this->expectException(BadRequestHttpException::class);

        $doctrine = $this->prophesize(ManagerRegistry::class);
        $orderManager = $this->prophesize(OrderManager::class);

        $order = new Order();

        $request = Request::create('/foo');

        $pay = new Pay($orderManager->reveal(), $doctrine->reveal());

        $response = call_user_func_array($pay, [$order, $request]);
    }
}
