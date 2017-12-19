<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\Delivery\Accept;
use AppBundle\Entity;
use AppBundle\Tests\Action\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AcceptTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Accept::class;

        parent::setUp();
    }

    public function testOnlyCouriersCanAcceptDeliveries()
    {
        $delivery = new Entity\Delivery();

        $delivery->setStatus(Entity\Delivery::STATUS_WAITING);

        foreach (['ROLE_USER', 'ROLE_RESTAURANT'] as $role) {
            $this->assertRoleThrowsException([$delivery], $role, AccessDeniedException::class);
        }
    }

    public function testOnlyDeliveryWithStatusWaitingCanBeAccepted()
    {
        $this->user->setRoles(['ROLE_COURIER']);

        $order = new Entity\Order();

        $delivery = new Entity\Delivery();
        $delivery->setOrder($order);

        $statusList = [
            Entity\Delivery::STATUS_DISPATCHED,
            Entity\Delivery::STATUS_PICKED,
            Entity\Delivery::STATUS_DELIVERED,
            Entity\Delivery::STATUS_ACCIDENT,
            Entity\Delivery::STATUS_CANCELED,
        ];

        foreach ($statusList as $status) {
            try {
                $delivery->setStatus($status);
                call_user_func($this->action, $delivery);
            } catch (\Exception $e) {
                $this->assertInstanceOf(BadRequestHttpException::class, $e);
            }
        }
    }

    public function testDeliveryIsAccepted()
    {
        $order = new Entity\Order();
        $order->setStatus(Entity\Order::STATUS_ACCEPTED);

        $delivery = new Entity\Delivery();
        $delivery->setStatus(Entity\Delivery::STATUS_WAITING);
        $delivery->setOrder($order);

        self::setEntityId($delivery, 123);
        self::setEntityId($this->user, 456);

        $this->user->setRoles(['ROLE_COURIER']);
        $response = call_user_func($this->action, $delivery);

        $this->assertSame($delivery, $response);
        $this->assertEquals(Entity\Delivery::STATUS_DISPATCHED, $response->getStatus());
        $this->assertSame($this->user, $response->getCourier());

        $this->redisProphecy
            ->lrem('deliveries:dispatching', 0, '123')
            ->shouldHaveBeenCalled();

        $this->redisProphecy
            ->hset('deliveries:delivering', 'delivery:123', 'courier:456')
            ->shouldHaveBeenCalled();

        $this->redisProphecy
            ->publish('couriers', '456')
            ->shouldHaveBeenCalled();
    }
}
