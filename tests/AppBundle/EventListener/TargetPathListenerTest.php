<?php

namespace Tests\AppBundle\EventListener;

use AppBundle\EventListener\TargetPathListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class TargetPathListenerTest extends TestCase
{
    private const TARGET_PATH_KEY = '_security.web.target_path';

    private TargetPathListener $listener;

    public function setUp(): void
    {
        $this->listener = new TargetPathListener();
    }

    private function handle(string $uri, array $headers = []): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(self::TARGET_PATH_KEY, $uri);

        $request = Request::create($uri);
        $request->setSession($session);
        // hasPreviousSession() only returns true once the session cookie is there
        $request->cookies->set($session->getName(), $session->getId());

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $this->listener->onKernelResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        ));

        return $session;
    }

    /**
     * The reason the listener exists: a background request denied while the user
     * sits on the login form must not become the place they land afterwards.
     */
    public function testDropsTargetPathOfABackgroundRequest()
    {
        $uri = 'http://localhost/cart.json';

        $session = $this->handle($uri, ['Sec-Fetch-Dest' => 'empty']);

        $this->assertFalse($session->has(self::TARGET_PATH_KEY));
    }

    public function testKeepsTargetPathOfATopLevelNavigation()
    {
        $uri = 'http://localhost/order/';

        $session = $this->handle($uri, ['Sec-Fetch-Dest' => 'document']);

        $this->assertSame($uri, $session->get(self::TARGET_PATH_KEY));
    }

    /**
     * A framed navigation is still a navigation. The app is served inside a
     * frame when embedded (and Cypress runs it in an iframe), and dropping the
     * target path there sent a customer logging in from the checkout back to
     * the homepage.
     *
     * @dataProvider framedDestinations
     */
    public function testKeepsTargetPathOfAFramedNavigation(string $dest)
    {
        $uri = 'http://localhost/order/';

        $session = $this->handle($uri, ['Sec-Fetch-Dest' => $dest]);

        $this->assertSame($uri, $session->get(self::TARGET_PATH_KEY));
    }

    public function framedDestinations(): array
    {
        return [
            'iframe' => ['iframe'],
            'frame' => ['frame'],
        ];
    }

    /**
     * Clients that do not send Sec-Fetch-* fall back to the Accept header.
     */
    public function testKeepsTargetPathWhenAcceptLooksLikeANavigation()
    {
        $uri = 'http://localhost/order/';

        $session = $this->handle($uri, ['Accept' => 'text/html,application/xhtml+xml']);

        $this->assertSame($uri, $session->get(self::TARGET_PATH_KEY));
    }

    public function testDropsTargetPathWhenAcceptLooksLikeAnApiCall()
    {
        $uri = 'http://localhost/cart.json';

        $session = $this->handle($uri, ['Accept' => 'application/json']);

        $this->assertFalse($session->has(self::TARGET_PATH_KEY));
    }

    /**
     * Only the entry matching the current request is dropped.
     */
    public function testLeavesUnrelatedTargetPathsAlone()
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(self::TARGET_PATH_KEY, 'http://localhost/order/');

        $request = Request::create('http://localhost/cart.json');
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $request->headers->set('Sec-Fetch-Dest', 'empty');

        $this->listener->onKernelResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        ));

        $this->assertSame('http://localhost/order/', $session->get(self::TARGET_PATH_KEY));
    }
}
