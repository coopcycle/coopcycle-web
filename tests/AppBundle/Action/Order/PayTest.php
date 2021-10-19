<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Pay;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PayTest extends TestCase
{
    use ProphecyTrait;

    public function testMissingStripeTokenThrowsException()
    {
        $this->expectException(BadRequestHttpException::class);

        $doctrine = $this->prophesize(EntityManagerInterface::class);
        $orderManager = $this->prophesize(OrderManager::class);
        $stripeManager = $this->prophesize(StripeManager::class);
        $ona = $this->prophesize(OrderNumberAssignerInterface::class);
        $pmr = $this->prophesize(PaymentMethodRepositoryInterface::class);

        $order = new Order();

        $request = Request::create('/foo');

        $pay = new Pay(
            $orderManager->reveal(),
            $doctrine->reveal(),
            $stripeManager->reveal(),
            $ona->reveal(),
            $pmr->reveal()
        );

        $response = call_user_func_array($pay, [$order, $request]);
    }
}
