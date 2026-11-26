<?php

declare(strict_types=1);

namespace Tests\AppBundle\EventListener;

use AppBundle\Entity\User;
use AppBundle\EventListener\AuthenticationWebSuccessHandler;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\HttpUtils;

class AuthenticationWebSuccessHandlerTest extends TestCase
{
    private AuthenticationWebSuccessHandler $handler;

    public function setUp(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(fn(string $name) => match ($name) {
            'admin_index' => '/admin',
            'dashboard' => '/dashboard',
            'profile_edit' => '/profile/edit',
        });

        $this->handler = new AuthenticationWebSuccessHandler(new HttpUtils(), $router);
        $this->handler->setFirewallName('web');
        $this->handler->setOptions(['default_target_path' => '/']);
    }

    private function createRequest(?string $targetPath = null): Request
    {
        $session = new Session(new MockArraySessionStorage());

        if (!is_null($targetPath)) {
            $session->set('_security.web.target_path', $targetPath);
        }

        $request = Request::create('http://localhost/2fa_check', 'POST');
        $request->setSession($session);

        return $request;
    }

    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setUsername('bob');
        $user->setRoles($roles);

        return $user;
    }

    public function testRedirectsToTheTargetPath(): void
    {
        $user = $this->createUser(['ROLE_ADMIN']);

        $response = $this->handler->onAuthenticationSuccess(
            $this->createRequest('http://localhost/admin/dashboard'),
            new UsernamePasswordToken($user, 'web', $user->getRoles())
        );

        $this->assertSame('http://localhost/admin/dashboard', $response->getTargetUrl());
    }

    /**
     * ROLE_ADMIN reaches ROLE_DISPATCHER through the role hierarchy only, which
     * $user->hasRole() ignores: admins who aren't explicitly dispatchers used
     * to land on the homepage.
     */
    public function testRedirectsAnAdminToTheAdmin(): void
    {
        $user = $this->createUser(['ROLE_ADMIN']);

        $response = $this->handler->onAuthenticationSuccess(
            $this->createRequest(),
            new UsernamePasswordToken($user, 'web', $user->getRoles())
        );

        $this->assertSame('/admin', $response->getTargetUrl());
    }

    /**
     * The handler also runs on the login form, while the second factor is
     * still pending.
     */
    public function testRedirectsAnAdminToTheAdminWhileTwoFactorIsPending(): void
    {
        $user = $this->createUser(['ROLE_ADMIN']);
        $authenticatedToken = new UsernamePasswordToken($user, 'web', $user->getRoles());

        $response = $this->handler->onAuthenticationSuccess(
            $this->createRequest(),
            new TwoFactorToken($authenticatedToken, null, 'web', ['email'])
        );

        $this->assertSame('/admin', $response->getTargetUrl());
    }

    public function testRedirectsAStoreToTheDashboard(): void
    {
        $user = $this->createUser(['ROLE_STORE']);

        $response = $this->handler->onAuthenticationSuccess(
            $this->createRequest(),
            new UsernamePasswordToken($user, 'web', $user->getRoles())
        );

        $this->assertSame('/dashboard', $response->getTargetUrl());
    }

    public function testRedirectsACourierToTheProfile(): void
    {
        $user = $this->createUser(['ROLE_COURIER']);

        $response = $this->handler->onAuthenticationSuccess(
            $this->createRequest(),
            new UsernamePasswordToken($user, 'web', $user->getRoles())
        );

        $this->assertSame('/profile/edit', $response->getTargetUrl());
    }

    public function testRedirectsACustomerToTheHomepage(): void
    {
        $user = $this->createUser(['ROLE_USER']);

        $response = $this->handler->onAuthenticationSuccess(
            $this->createRequest(),
            new UsernamePasswordToken($user, 'web', $user->getRoles())
        );

        $this->assertSame('http://localhost/', $response->getTargetUrl());
    }
}
