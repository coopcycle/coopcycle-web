<?php

declare(strict_types=1);

namespace AppBundle\Security\TwoFactor;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * The 2FA form is only reachable while the second factor is pending
 * (IS_AUTHENTICATED_2FA_IN_PROGRESS). Once the code is accepted, loading it
 * again (a reload while the next page is still loading, the back button, a
 * restored tab) used to end on Symfony's bare 403 page.
 *
 * Symfony only calls this handler for fully authenticated users, so there is
 * nothing left to verify: send them where a successful 2FA would have.
 */
class TwoFactorFormAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private HttpUtils $httpUtils,
        private TokenStorageInterface $tokenStorage,
        private AuthenticationSuccessHandlerInterface $successHandler,
    ) { }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if (!$this->isTwoFactorRequest($request)) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (is_null($token) || $token instanceof TwoFactorTokenInterface) {
            return null;
        }

        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    private function isTwoFactorRequest(Request $request): bool
    {
        return $this->httpUtils->checkRequestPath($request, '2fa_login')
            || $this->httpUtils->checkRequestPath($request, '2fa_login_check');
    }
}
