<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\Order\Pay;
use AppBundle\Entity;
use AppBundle\Tests\Action\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PayTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Pay::class;

        parent::setUp();
    }

    public function testOnlyOrdersWithStatusCreatedCanBePaid()
    {
        $this->user->setRoles(['ROLE_RESTAURANT']);

        $order = new Entity\Order();
        $order->setCustomer($this->user);

        $request = Request::create('/foo');

        $statusList = [
            Entity\Order::STATUS_WAITING,
            Entity\Order::STATUS_ACCEPTED,
            Entity\Order::STATUS_REFUSED,
            Entity\Order::STATUS_READY,
            Entity\Order::STATUS_CANCELED,
        ];

        foreach ($statusList as $status) {
            try {
                $order->setStatus($status);
                call_user_func_array($this->action, [$order, $request]);
            } catch (\Exception $e) {
                $this->assertInstanceOf(BadRequestHttpException::class, $e);
            }
        }
    }

    public function testWrongCustomerThrowsException()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->user->setRoles(['ROLE_RESTAURANT']);

        $order = new Entity\Order();
        $order->setCustomer(new Entity\ApiUser);
        $order->setStatus(Entity\Order::STATUS_CREATED);

        $request = Request::create('/foo');

        $response = call_user_func_array($this->action, [$order, $request]);
    }

    public function testMissingStripeTokenThrowsException()
    {
        $this->expectException(BadRequestHttpException::class);

        $this->user->setRoles(['ROLE_RESTAURANT']);

        $order = new Entity\Order();
        $order->setCustomer($this->user);
        $order->setStatus(Entity\Order::STATUS_CREATED);

        $request = Request::create('/foo');

        $response = call_user_func_array($this->action, [$order, $request]);
    }

    public function testOrderHasStatusWaiting()
    {
        $this->user->setRoles(['ROLE_RESTAURANT']);

        $order = new Entity\Order();
        $order->setCustomer($this->user);
        $order->setStatus(Entity\Order::STATUS_CREATED);

        $data = [
            'stripeToken' => 'abcdef123456'
        ];

        $request = Request::create('/foo', 'POST', [], [], [], [], json_encode($data));

        $response = call_user_func_array($this->action, [$order, $request]);

        $this->assertSame($order, $response);
        $this->assertEquals(Entity\Order::STATUS_WAITING, $order->getStatus());

        $this->eventDispatcher
            ->dispatch('order.payment_success', new GenericEvent($order))
            ->shouldHaveBeenCalled();
    }
}
