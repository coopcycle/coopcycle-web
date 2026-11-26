<?php

namespace Tests\AppBundle\Api\EventSubscriber;

use AppBundle\Api\EventSubscriber\MaintenanceSubscriber;
use AppBundle\Service\MaintenanceManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Redis;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MaintenanceSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $subscriber;

    public function setUp(): void
    {
        $redis = $this->prophesize(Redis::class);
        $redis->get('maintenance')->willReturn('1');
        $redis->get('maintenance_message')->willReturn('Nope');

        // Nobody is authenticated
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted(\Prophecy\Argument::any())->willReturn(false);

        $this->subscriber = new MaintenanceSubscriber(
            $redis->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            new MaintenanceManager($authorizationChecker->reveal())
        );
    }

    private function dispatch(string $path): RequestEvent
    {
        $request = Request::create($path, 'POST');
        $request->attributes->set('_api_resource_class', 'Foo');

        $event = new RequestEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->subscriber->checkMaintenance($event);

        return $event;
    }

    public function testApiRequestsAreBlocked()
    {
        $event = $this->dispatch('/api/orders');

        $this->assertNotNull($event->getResponse());
        $this->assertEquals(503, $event->getResponse()->getStatusCode());
    }

    public function testZeltyWebhooksAreAllowed()
    {
        $this->assertNull($this->dispatch('/api/zelty/webhook/order.status.update')->getResponse());
        $this->assertNull($this->dispatch('/api/zelty/webhook/dish.update')->getResponse());
        $this->assertNull($this->dispatch('/api/zelty/webhook/catalog/178')->getResponse());
    }
}
