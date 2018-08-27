<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Action\Order\Refuse;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\AppBundle\Action\Order\TestCase;

class RefuseTest extends TestCase
{
    public function setUp()
    {
        $this->actionClass = Refuse::class;

        parent::setUp();
    }

    public function unauthorizedRoleProvider() {
        return [
            ['ROLE_USER'],
            ['ROLE_COURIER']
        ];
    }

    /**
     * @dataProvider unauthorizedRoleProvider
     */
    public function testUnauthorizedRoleThrowsException($role)
    {
        $this->expectException(AccessDeniedHttpException::class);

        $orderManager = $this->prophesize(OrderManager::class);
        $this->action = $this->createAction($orderManager->reveal());

        $order = new Order();

        $request = Request::create('/foo');

        $this->user->setRoles([$role]);

        call_user_func_array($this->action, [$order, $request]);
    }

    public function testOrderCanBeRefusedWithReason()
    {
        $orderManager = $this->prophesize(OrderManager::class);
        $this->action = $this->createAction($orderManager->reveal());

        $order = new Order();

        $content = json_encode(['reason' => 'Rush hour']);
        $request = Request::create('/foo', 'PUT', [], [], [], [], $content);

        $this->user->setRoles(['ROLE_RESTAURANT']);
        $response = call_user_func_array($this->action, [$order, $request]);

        $this->assertSame($order, $response);

        $orderManager->refuse($order, 'Rush hour')->shouldHaveBeenCalled();
    }
}
