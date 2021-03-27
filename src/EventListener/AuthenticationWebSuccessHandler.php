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

    private $httpUtils;
    private $router;
    private $providerKey;
    private $options;

    public function __construct(HttpUtils $httpUtils, UrlGeneratorInterface $router)
    {
        $this->httpUtils = $httpUtils;
        $this->router = $router;
    }

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
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        // This is the URL (if any) the user visited that forced them to login
        $targetPath = $this->getTargetPath($request->getSession(), $this->providerKey);

        // If there is no target path, redirect depending on role
        if (!$targetPath) {

            $user = $token->getUser();

            if (is_object($user) && is_callable([ $user, 'hasRole' ])) {

                if ($user->hasRole('ROLE_ADMIN')) {
                    return new RedirectResponse($this->router->generate('admin_index'));
                }

                if ($user->hasRole('ROLE_STORE') || $user->hasRole('ROLE_RESTAURANT')) {
                    return new RedirectResponse($this->router->generate('dashboard'));
                }

                if ($user->hasRole('ROLE_COURIER')) {
                    return new RedirectResponse($this->router->generate('nucleos_profile_profile_show'));
                }

                return $this->httpUtils->createRedirectResponse($request, $this->options['default_target_path']);
            }
        }

        return new RedirectResponse($targetPath);
    }
}

