<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\Order\Refuse;
use AppBundle\Entity;
use AppBundle\Tests\Action\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RefuseTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Refuse::class;

        parent::setUp();
    }

    public function testOnlyRestaurantsCanRefuseOrders()
    {
        $order = new Entity\Order();
        $order->setStatus(Entity\Order::STATUS_WAITING);

        foreach (['ROLE_USER', 'ROLE_COURIER'] as $role) {
            $this->assertRoleThrowsException([$order], $role, AccessDeniedHttpException::class);
        }

        $this->user->setRoles(['ROLE_RESTAURANT']);
        $response = call_user_func($this->action, $order);

        $this->assertSame($order, $response);
    }

    public function testOnlyOrdersWithStatusAcceptedCanBeRefused()
    {
        $this->user->setRoles(['ROLE_RESTAURANT']);

        $order = new Entity\Order();

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
            } catch (\Exception $e) {
                $this->assertInstanceOf(BadRequestHttpException::class, $e);
            }
        }

        $order->setStatus(Entity\Order::STATUS_WAITING);
        $response = call_user_func($this->action, $order);

        $this->assertSame($order, $response);
        $this->assertEquals(Entity\Order::STATUS_REFUSED, $order->getStatus());
    }
}
