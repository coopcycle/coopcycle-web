<?php

namespace AppBundle\Security\Firewall;

use AppBundle\Security\Authentication\Token\BearerToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2TokenFactory;

class TokenBearerListener implements ListenerInterface
{
    protected $tokenStorage;
    protected $authenticationManager;
    protected $jwtTokenAuthenticator;
    protected $httpMessageFactory;
    protected $oAuth2TokenFactory;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        JWTTokenAuthenticator $jwtTokenAuthenticator,
        HttpMessageFactoryInterface $httpMessageFactory,
        OAuth2TokenFactory $oauth2TokenFactory)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->jwtTokenAuthenticator = $jwtTokenAuthenticator;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->oauth2TokenFactory = $oauth2TokenFactory;
    }

    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $supports = $this->jwtTokenAuthenticator->supports($request);

        // There is no Authentication header
        if (!$supports) {
            return;
        }

        try {
            $lexikToken = $this->jwtTokenAuthenticator->getCredentials($request);
        } catch (AuthenticationException $e) {

            // The token is not valid (invalid signature, expired...)
            $response = $this->jwtTokenAuthenticator->onAuthenticationFailure($request, $e);

            $event->setResponse($response);
            return;
        }

        $trikoderToken = $this->oauth2TokenFactory->createOAuth2Token(
            $this->httpMessageFactory->createRequest($request),
            null
        );

        // We create a "composed" token
        $token = new BearerToken($lexikToken, $trikoderToken);

        try {

            $authToken = $this->authenticationManager->authenticate($token);
            $this->tokenStorage->setToken($authToken);

        } catch (AuthenticationException $e) {
            $response = $this->jwtTokenAuthenticator->onAuthenticationFailure($request, $e);
            $event->setResponse($response);
        }
    }
}
