<?php

namespace AppBundle\Security\Authentication\Provider;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Security\Authentication\Token\CartSessionToken;
use League\OAuth2\Server\ResourceServer;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CartSessionProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $jwtTokenAuthenticator;
    private $providerKey;

    public function __construct(
        UserProviderInterface $userProvider,
        JWTTokenAuthenticator $jwtTokenAuthenticator,
        string $providerKey,
        IriConverterInterface $iriConverter,
        JWTTokenManagerInterface $tokenManager)
    {
        $this->userProvider = $userProvider;
        $this->jwtTokenAuthenticator = $jwtTokenAuthenticator;
        $this->providerKey = $providerKey;
        $this->iriConverter = $iriConverter;
        $this->tokenManager = $tokenManager;
    }

    private function extractCart($payload)
    {
        if (isset($payload['sub'])) {
            try {
                if ($cart = $this->iriConverter->getItemFromIri($payload['sub'])) {

                    return $cart;
                }
            } catch (InvalidArgumentException $e) {}
        }

        return false;
    }

    public function authenticate(TokenInterface $token)
    {
        try {

            // First, try with Lexik
            // Lexik expects a "username" claim in the JWT payload
            // If it throws an InvalidPayloadException, we can try with Trikoder
            $user = $this->jwtTokenAuthenticator->getUser($token->lexik, $this->userProvider);

            $authToken = $this->jwtTokenAuthenticator->createAuthenticatedToken($user, $this->providerKey);

            if ($token->rawSessionToken) {
                $sessionToken = new JWTUserToken();
                $sessionToken->setRawToken($token->rawSessionToken);

                if ($payload = $this->tokenManager->decode($sessionToken)) {
                    if ($cart = $this->extractCart($payload)) {
                        $authToken->setAttribute('cart', $cart);
                    }
                }
            }

            return $authToken;

        } catch (InvalidPayloadException $e) {

            $payload = $token->lexik->getPayload();

            if ($cart = $this->extractCart($payload)) {
                $authToken = new JWTUserToken([], null, $token->lexik->getCredentials(), $this->providerKey);
                $authToken->setAttribute('cart', $cart);

                return $authToken;
            }

            throw new AuthenticationException();

        } catch (AuthenticationException $e) {
            throw $e;
        }
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof CartSessionToken;
    }
}
