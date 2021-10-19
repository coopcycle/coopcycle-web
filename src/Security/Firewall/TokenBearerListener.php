<?php

namespace AppBundle\Security\Firewall;

use AppBundle\Security\Authentication\Token\BearerToken;
use AppBundle\Security\ApiKeyManager;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2TokenFactory;

class TokenBearerListener
{
    protected $tokenStorage;
    protected $authenticationManager;
    protected $jwtTokenAuthenticator;
    protected $httpMessageFactory;
    protected $oauth2TokenFactory;
    protected $apiKeyManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        JWTTokenAuthenticator $jwtTokenAuthenticator,
        HttpMessageFactoryInterface $httpMessageFactory,
        OAuth2TokenFactory $oauth2TokenFactory,
        ApiKeyManager $apiKeyManager,
        string $providerKey)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->jwtTokenAuthenticator = $jwtTokenAuthenticator;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->oauth2TokenFactory = $oauth2TokenFactory;
        $this->apiKeyManager = $apiKeyManager;
        $this->providerKey = $providerKey;
    }

    public function __invoke(RequestEvent $event)
    {
        $request = $event->getRequest();

        $supports = $this->jwtTokenAuthenticator->supports($request);

        // There is no Authentication header
        if (!$supports) {
            return;
        }

        // This means the token starts with "ak_"
        if ($this->apiKeyManager->supports($request)) {
            $apiKeyToken = $this->apiKeyManager->getCredentials($request);
            try {
                $this->authenticate($request, $apiKeyToken, $event);
            } catch (AuthenticationException $e) {
                $response = new Response();
                $response->setStatusCode(Response::HTTP_FORBIDDEN);
                $event->setResponse($response);
            }
            return;
        }

        // This works for *BOTH* JWT & OAuth,
        // because the access token for OAuth is actually a JWT,
        // signed with the same key.
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
            $user = null,
            $this->providerKey
        );

        // We create a "composed" token
        $token = new BearerToken($lexikToken, $trikoderToken);

        $this->authenticate($request, $token, $event);
    }

    private function authenticate(Request $request, TokenInterface $token, RequestEvent $event)
    {
        try {

            $authToken = $this->authenticationManager->authenticate($token);
            $this->tokenStorage->setToken($authToken);

        } catch (AuthenticationException $e) {
            $response = $this->jwtTokenAuthenticator->onAuthenticationFailure($request, $e);
            $event->setResponse($response);
        }
    }
}
