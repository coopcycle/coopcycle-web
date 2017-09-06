<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\Order\Ready;
use AppBundle\Entity;
use AppBundle\Tests\Action\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReadyTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Ready::class;

        parent::setUp();
    }

    public function testOnlyRestaurantsCanSetOrdersAsReady()
    {
        $order = new Entity\Order();
        $order->setStatus(Entity\Order::STATUS_ACCEPTED);

        foreach (['ROLE_USER', 'ROLE_COURIER'] as $role) {
            $this->assertRoleThrowsException([$order], $role, AccessDeniedHttpException::class);
        }
    }

    public function testWrongRestaurantThrowsException()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $restaurant = new Entity\Restaurant();
        $this->user->addRestaurant($restaurant);

        $anotherRestaurant = new Entity\Restaurant();

        $order = new Entity\Order();
        $order->setStatus(Entity\Order::STATUS_ACCEPTED);
        $order->setRestaurant($anotherRestaurant);

        $this->user->setRoles(['ROLE_RESTAURANT']);
        $response = call_user_func($this->action, $order);
    }

    public function testOnlyOrdersWithStatusAcceptedCanBeSetAsReady()
    {
        $this->user->setRoles(['ROLE_RESTAURANT']);

        $restaurant = new Entity\Restaurant();
        $order = new Entity\Order();

        $order->setRestaurant($restaurant);
        $this->user->addRestaurant($restaurant);

        $statusList = [
            Entity\Order::STATUS_CREATED,
            Entity\Order::STATUS_WAITING,
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

        $order->setStatus(Entity\Order::STATUS_ACCEPTED);
        $response = call_user_func($this->action, $order);

        $this->assertSame($order, $response);
        $this->assertEquals(Entity\Order::STATUS_READY, $order->getStatus());
    }
}
