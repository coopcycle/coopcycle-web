<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Accept;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\AppBundle\Action\Order\TestCase;

class AcceptTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Accept::class;

        parent::setUp();
    }

    public function testOnlyRestaurantsCanAcceptOrders()
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

        $orderManager->accept($order)->shouldHaveBeenCalled();
    }
}
