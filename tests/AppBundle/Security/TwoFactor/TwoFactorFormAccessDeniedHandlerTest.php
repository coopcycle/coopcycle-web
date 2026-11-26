<?php

declare(strict_types=1);

namespace Tests\AppBundle\Security\TwoFactor;

use AppBundle\Entity\User;
use AppBundle\Security\TwoFactor\TwoFactorFormAccessDeniedHandler;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;

class TwoFactorFormAccessDeniedHandlerTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private TwoFactorFormAccessDeniedHandler $handler;

    public function setUp(): void
    {
        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->method('checkRequestPath')->willReturnCallback(fn(Request $request, string $path) => match ($path) {
            '2fa_login' => $request->getPathInfo() === '/2fa',
            '2fa_login_check' => $request->getPathInfo() === '/2fa_check',
        });

        $successHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $successHandler->method('onAuthenticationSuccess')->willReturnCallback(
            fn(Request $request, TokenInterface $token) => new RedirectResponse('/admin')
        );

        $this->tokenStorage = new TokenStorage();

        $this->handler = new TwoFactorFormAccessDeniedHandler($httpUtils, $this->tokenStorage, $successHandler);
    }

    private function createAuthenticatedToken(): UsernamePasswordToken
    {
        $user = new User();
        $user->setUsername('bob');
        $user->setRoles(['ROLE_ADMIN']);

        return new UsernamePasswordToken($user, 'web', $user->getRoles());
    }

    /**
     * What happened on 2026-11-26: the 2FA form was reloaded right after the
     * code was accepted, and ended on Symfony's 403 page.
     */
    public function testRedirectsAnAuthenticatedUserAwayFromTheForm(): void
    {
        $this->tokenStorage->setToken($this->createAuthenticatedToken());

        $response = $this->handler->handle(Request::create('/2fa'), new AccessDeniedException());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin', $response->getTargetUrl());
    }

    /**
     * A tab restored on the check path, as seen on 2026-11-23.
     */
    public function testRedirectsAnAuthenticatedUserAwayFromTheCheckPath(): void
    {
        $this->tokenStorage->setToken($this->createAuthenticatedToken());

        $response = $this->handler->handle(Request::create('/2fa_check'), new AccessDeniedException());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin', $response->getTargetUrl());
    }

    public function testLeavesOtherPagesToTheDefaultBehaviour(): void
    {
        $this->tokenStorage->setToken($this->createAuthenticatedToken());

        $response = $this->handler->handle(Request::create('/admin/settings'), new AccessDeniedException());

        $this->assertNull($response);
    }

    /**
     * Symfony does not call the handler while the second factor is pending, but
     * that is the one case where redirecting would skip the code.
     */
    public function testDoesNotRedirectWhileTwoFactorIsPending(): void
    {
        $this->tokenStorage->setToken(new TwoFactorToken($this->createAuthenticatedToken(), null, 'web', ['email']));

        $response = $this->handler->handle(Request::create('/2fa'), new AccessDeniedException());

        $this->assertNull($response);
    }
}
