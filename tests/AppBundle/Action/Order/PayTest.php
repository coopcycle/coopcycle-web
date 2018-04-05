<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Pay;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Sylius\Order;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\AppBundle\Action\Order\TestCase;

class PayTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Pay::class;

        parent::setUp();
    }

    public function testWrongCustomerThrowsException()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $order = new Order();
        $order->setCustomer(new ApiUser);

        $request = Request::create('/foo');

        $response = call_user_func_array($this->action, [$order, $request]);
    }

    public function testMissingStripeTokenThrowsException()
    {
        $this->expectException(BadRequestHttpException::class);

        $this->user->setRoles(['ROLE_RESTAURANT']);

        $order = new Order();
        $order->setCustomer($this->user);

        $request = Request::create('/foo');

        $response = call_user_func_array($this->action, [$order, $request]);
    }
}
