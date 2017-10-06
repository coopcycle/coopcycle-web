<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\Order\Accept;
use AppBundle\Entity;
use AppBundle\Tests\Action\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AcceptTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Accept::class;

        parent::setUp();
    }

    public function testOnlyRestaurantsCanAcceptOrders()
    {
        $restaurant = new Entity\Restaurant();

        $order = new Entity\Order();
        $order->setRestaurant($restaurant);
        $order->setStatus(Entity\Order::STATUS_WAITING);

        foreach (['ROLE_USER', 'ROLE_COURIER'] as $role) {
            $this->assertRoleThrowsException([$order], $role, AccessDeniedHttpException::class);
        }

        $this->user->setRoles(['ROLE_RESTAURANT']);
        $response = call_user_func($this->action, $order);

        $this->assertSame($order, $response);
    }

    public function testOnlyOrdersWithStatusWaitingCanBeAccepted()
    {
        $this->user->setRoles(['ROLE_RESTAURANT']);

        $restaurant = new Entity\Restaurant();

        $order = new Entity\Order();
        $order->setRestaurant($restaurant);

        $statusList = [
            Entity\Order::STATUS_CREATED,
            Entity\Order::STATUS_ACCEPTED,
            Entity\Order::STATUS_REFUSED,
            Entity\Order::STATUS_READY,
            Entity\Order::STATUS_CANCELED,
        ];

        foreach ($statusList as $status) {
            try {
                $order->setStatus($status);
                call_user_func($this->action, $order);
            } catch (BadRequestHttpException $e) {
                // Exception is expected
            }
        }

        $order->setStatus(Entity\Order::STATUS_WAITING);
        $response = call_user_func($this->action, $order);

        $this->assertSame($order, $response);
        $this->assertEquals(Entity\Order::STATUS_ACCEPTED, $order->getStatus());
    }
}
