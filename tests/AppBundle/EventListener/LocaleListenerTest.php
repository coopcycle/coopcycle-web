<?php

namespace Tests\AppBundle\EventListener;

use AppBundle\EventListener\LocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LocaleListenerTest extends TestCase
{
    private LocaleListener $listener;

    public function setUp(): void
    {
        $this->listener = new LocaleListener('fr');
    }

    private function handle(string $route, string $routeLocale): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_locale', 'fr');

        $request = Request::create(sprintf('/%s/foo', $routeLocale));
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $request->attributes->set('_route', $route);
        $request->attributes->set('_locale', $routeLocale);

        $this->listener->onKernelRequest(
            new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST)
        );

        return $request;
    }

    public function testRouteLocaleIsStoredInSession()
    {
        $request = $this->handle('restaurant', 'eu');

        $this->assertEquals('eu', $request->getSession()->get('_locale'));
        $this->assertEquals('eu', $request->attributes->get('_locale'));
    }

    public function testLiveComponentDoesNotChangeSessionLocale()
    {
        $request = $this->handle('ux_live_component', 'eu');

        $this->assertEquals('fr', $request->getSession()->get('_locale'));
        $this->assertEquals('fr', $request->attributes->get('_locale'));
    }
}
