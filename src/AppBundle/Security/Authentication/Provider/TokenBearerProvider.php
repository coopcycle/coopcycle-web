<?php

namespace AppBundle\Security\Authentication\Provider;

use AppBundle\Security\Authentication\Token\BearerToken;
use AppBundle\Security\StoreTokenAuthenticator;
use League\OAuth2\Server\ResourceServer;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Provider\OAuth2Provider;

class TokenBearerProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $jwtTokenAuthenticator;
    private $jwtTokenAuthenticatorNotExpiring;
    private $oauth2Provider;
    private $providerKey;

    public function __construct(
        UserProviderInterface $userProvider,
        ResourceServer $resourceServer,
        JWTTokenAuthenticator $jwtTokenAuthenticator,
        StoreTokenAuthenticator $jwtTokenAuthenticatorNotExpiring,
        string $providerKey)
    {
        $this->userProvider = $userProvider;
        $this->jwtTokenAuthenticator = $jwtTokenAuthenticator;
        $this->jwtTokenAuthenticatorNotExpiring = $jwtTokenAuthenticatorNotExpiring;
        $this->providerKey = $providerKey;
        // FIXME Inject directly OAuth2Provider
        $this->oauth2Provider = new OAuth2Provider($userProvider, $resourceServer);
    }

    public function authenticate(TokenInterface $token)
    {
        try {

            // First, try with Lexik
            // Lexik expects a "username" claim in the JWT payload
            // If it throws an InvalidPayloadException, we can try with Trikoder

            $jwtTokenAuthenticator = $this->jwtTokenAuthenticator;

            // Also, handle legacy "store tokens", i.e add a "store" attribute to the token
            // FIXME Remove this when "store tokens" are deprecated
            $payload = $token->lexik->getPayload();
            $isStoreToken = isset($payload['store']);

            if ($isStoreToken) {
                $jwtTokenAuthenticator = $this->jwtTokenAuthenticatorNotExpiring;
            }

            $user = $jwtTokenAuthenticator->getUser($token->lexik, $this->userProvider);

            // FIXME Remove this when "store tokens" are deprecated (?)
            if ($isStoreToken && true !== $jwtTokenAuthenticator->checkCredentials($token->lexik, $user)) {
                throw new AuthenticationException(sprintf('User %s is not authorized to authenticate on behalf of store %d',
                    $user->getUsername(), $payload['store']));
            }

            return $jwtTokenAuthenticator->createAuthenticatedToken($user, $this->providerKey);

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
