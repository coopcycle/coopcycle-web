<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Ready;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\AppBundle\Action\Order\TestCase;

class ReadyTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Ready::class;

        parent::setUp();
    }

    public function testOnlyRestaurantsCanSetOrdersAsReady()
    {
        $orderManager = $this->prophesize(OrderManager::class);
        $this->action = $this->createAction($orderManager->reveal());

        $order = new Order();

        foreach (['ROLE_USER', 'ROLE_COURIER'] as $role) {
            $this->assertRoleThrowsException([$order], $role, AccessDeniedHttpException::class);
        }

        $this->user->setRoles(['ROLE_RESTAURANT']);
        $response = call_user_func($this->action, $order);

        $this->assertSame($order, $response);

        $orderManager->ready($order)->shouldHaveBeenCalled();
    }
}
