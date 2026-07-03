<?php

namespace AppBundle\EventListener;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthenticationWebSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    private $providerKey;
    private $options;

    public function __construct(
        private HttpUtils $httpUtils,
        private UrlGeneratorInterface $router
    ) { }

    /**
     * Set the provider key.
     * This is injected by CustomAuthenticationSuccessHandler.
     *
     * @param string $providerKey
     */
    public function setFirewallName($providerKey)
    {
        $this->providerKey = $providerKey;
    }

    /**
     * Set the options.
     * This is injected by CustomAuthenticationSuccessHandler.
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    //NOTE: This is kinda hacky, should fix the redirection issue by not
    //saving thoses URL in `_target_path` in the first place.
    private function isBlacklistedPath(string $path): bool
    {
        $path = rtrim(parse_url($path)['path'], '/');
        return in_array($path, ['/profile/jwt', '/profile/notifications']);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        // This is the URL (if any) the user visited that forced them to login
        $targetPath = $this->getTargetPath($request->getSession(), $this->providerKey);

        // Check if there is a target path in request params
        if (!$targetPath) {
            $targetPath = $request->get('_target_path');
        }

        if ($targetPath && $this->isBlacklistedPath($targetPath)) {
            $targetPath = null;
        }

        // If there is no target path, redirect depending on role
        if (!$targetPath) {

            $user = $token->getUser();

            if (is_object($user) && is_callable([ $user, 'hasRole' ])) {

                if ($user->hasRole('ROLE_DISPATCHER')) {
                    return new RedirectResponse($this->router->generate('admin_index'));
                }

                if ($user->hasRole('ROLE_STORE') || $user->hasRole('ROLE_RESTAURANT')) {
                    return new RedirectResponse($this->router->generate('dashboard'));
                }

                if ($user->hasRole('ROLE_COURIER')) {
                    return new RedirectResponse($this->router->generate('profile_edit'));
                }

                return $this->httpUtils->createRedirectResponse($request, $this->options['default_target_path']);
            }
        }

        return new RedirectResponse($targetPath);
    }
}

