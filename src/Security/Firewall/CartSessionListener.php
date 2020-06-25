<?php

namespace AppBundle\Security\Firewall;

use AppBundle\Security\Authentication\Token\CartSessionToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CartSessionListener
{
    protected $tokenStorage;
    protected $authenticationManager;
    protected $jwtTokenAuthenticator;
    protected $httpMessageFactory;
    protected $oAuth2TokenFactory;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        JWTTokenAuthenticator $jwtTokenAuthenticator)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->jwtTokenAuthenticator = $jwtTokenAuthenticator;
    }

    public function __invoke(RequestEvent $event)
    {
        $request = $event->getRequest();

        $tokenExtractor = new AuthorizationHeaderTokenExtractor('Bearer', 'X-CoopCycle-Session');

        $sessionToken = $tokenExtractor->extract($request);

        $supports = $this->jwtTokenAuthenticator->supports($request);

        // There is no "Authentication" header
        if (!$supports && !$sessionToken) {
            return;
        }

        // We create a "composed" token
        $token = new CartSessionToken($sessionToken);

        try {
            if ($lexikToken = $this->jwtTokenAuthenticator->getCredentials($request)) {
                $token->lexik = $lexikToken;
            }
        } catch (AuthenticationException $e) {
            // The token is not valid (invalid signature, expired...)
            $response = $this->jwtTokenAuthenticator->onAuthenticationFailure($request, $e);
            $event->setResponse($response);
            return;
        }

        try {

            $authToken = $this->authenticationManager->authenticate($token);
            $this->tokenStorage->setToken($authToken);

        } catch (AuthenticationException $e) {
            $response = $this->jwtTokenAuthenticator->onAuthenticationFailure($request, $e);
            $event->setResponse($response);
        }
    }
}
