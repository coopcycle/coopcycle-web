<?php

namespace AppBundle\Security\Authentication\Provider;

use AppBundle\Security\Authentication\Token\BearerToken;
use League\OAuth2\Server\ResourceServer;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Provider\OAuth2Provider;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2TokenFactory;

class TokenBearerProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $jwtTokenAuthenticator;
    private $oauth2Provider;
    private $providerKey;

    public function __construct(
        UserProviderInterface $userProvider,
        ResourceServer $resourceServer,
        JWTTokenAuthenticator $jwtTokenAuthenticator,
        OAuth2TokenFactory $oauth2TokenFactory,
        string $providerKey)
    {
        $this->userProvider = $userProvider;
        $this->jwtTokenAuthenticator = $jwtTokenAuthenticator;
        $this->providerKey = $providerKey;
        // FIXME Inject directly OAuth2Provider
        $this->oauth2Provider = new OAuth2Provider($userProvider, $resourceServer, $oauth2TokenFactory);
    }

    public function authenticate(TokenInterface $token)
    {
        try {

            // First, try with Lexik
            // Lexik expects a "username" claim in the JWT payload
            // If it throws an InvalidPayloadException, we can try with Trikoder
            $user = $this->jwtTokenAuthenticator->getUser($token->lexik, $this->userProvider);

            return $this->jwtTokenAuthenticator->createAuthenticatedToken($user, $this->providerKey);

        } catch (InvalidPayloadException $e) {

            // Then, try with Trikoder
            $token = $this->oauth2Provider->authenticate($token->trikoder);

            return $token;

        } catch (AuthenticationException $e) {
            throw $e;
        }
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof BearerToken;
    }
}
