<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\Delivery\Deliver;
use AppBundle\Entity;
use AppBundle\Tests\Action\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeliverTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Deliver::class;

        parent::setUp();
    }

    public function testOnlyCouriersCanAcceptDeliveries()
    {
        $delivery = new Entity\Delivery();

        $delivery->setStatus(Entity\Delivery::STATUS_WAITING);

        foreach (['ROLE_USER', 'ROLE_RESTAURANT'] as $role) {
            $this->assertRoleThrowsException([$delivery], $role, AccessDeniedHttpException::class);
        }
    }

    public function testOnlyDeliveryWithStatusPickedCanBeDelivered()
    {
        $this->user->setRoles(['ROLE_COURIER']);

        $order = new Entity\Order();

        $delivery = new Entity\Delivery();
        $delivery->setOrder($order);

        $statusList = [
            Entity\Delivery::STATUS_WAITING,
            Entity\Delivery::STATUS_DISPATCHED,
            Entity\Delivery::STATUS_DELIVERED,
            Entity\Delivery::STATUS_ACCIDENT,
            Entity\Delivery::STATUS_CANCELED,
        ];

        foreach ($statusList as $status) {
            try {
                $delivery->setStatus($status);
                call_user_func($this->action, $delivery);
            } catch (\Exception $e) {
                $this->assertInstanceOf(AccessDeniedHttpException::class, $e);
            }
        }
    }



    public function testDeliveryIsDelivered()
    {
        $order = new Entity\Order();
        $order->setStatus(Entity\Order::STATUS_READY);

        $delivery = new Entity\Delivery();
        $delivery->setStatus(Entity\Delivery::STATUS_PICKED);
        $delivery->setOrder($order);
        $delivery->setCourier($this->user);

        self::setEntityId($order, 123);
        self::setEntityId($this->user, 456);

        $this->user->setRoles(['ROLE_COURIER']);
        $response = call_user_func($this->action, $delivery);

        $this->assertSame($delivery, $response);
        $this->assertEquals(Entity\Delivery::STATUS_DELIVERED, $response->getStatus());
        $this->assertSame($this->user, $response->getCourier());

        $this->redisProphecy
            ->hdel('deliveries:delivering', 'delivery:123')
            ->shouldHaveBeenCalled();

        $this->redisProphecy
            ->publish('couriers:available', '456')
            ->shouldHaveBeenCalled();
    }
}
