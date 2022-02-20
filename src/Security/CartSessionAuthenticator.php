<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Provider\OAuth2Provider;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2TokenFactory;

/**
 * @see https://symfony.com/doc/5.3/security/custom_authenticator.html
 */
class CartSessionAuthenticator extends AbstractAuthenticator
{
    private $jwtAuthenticator;
    private $jwtManager;
    private $iriConverter;
    private $firewallName;

    public function __construct(
        JWTAuthenticator $jwtAuthenticator,
        JWTTokenManagerInterface $jwtManager,
        TokenExtractorInterface $tokenExtractor,
        IriConverterInterface $iriConverter,
        string $firewallName)
    {
        $this->jwtAuthenticator = $jwtAuthenticator;
        $this->jwtManager = $jwtManager;
        $this->tokenExtractor = $tokenExtractor;
        $this->iriConverter = $iriConverter;
        $this->firewallName = $firewallName;
        $this->sessionTokenExtractor = new AuthorizationHeaderTokenExtractor('Bearer', 'X-CoopCycle-Session');
    }

    public function supports(Request $request): ?bool
    {
        $sessionToken = $this->sessionTokenExtractor->extract($request);
        $supports = $this->jwtAuthenticator->supports($request);

        // There is no "Authentication" header
        if (!$supports && !$sessionToken) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): PassportInterface
    {
        try {

            // First, try with Lexik
            // Lexik expects a "username" claim in the JWT payload
            $passport = $this->jwtAuthenticator->authenticate($request);

            $token = $this->jwtAuthenticator->createAuthenticatedToken($passport, $this->firewallName);

            if ($rawSessionToken = $this->sessionTokenExtractor->extract($request)) {
                $sessionToken = new JWTUserToken();
                $sessionToken->setRawToken($rawSessionToken);

                if ($payload = $this->jwtManager->decode($sessionToken)) {
                    if ($cart = $this->extractCart($payload)) {
                        $token->setAttribute('cart', $cart);
                    }
                }
            }

            $passport->setAttribute('token', $token);

            return $passport;

        } catch (InvalidPayloadException $e) {

            $rawToken = $this->tokenExtractor->extract($request);

            try {
                if (!$payload = $this->jwtManager->parse($rawToken)) {
                    throw new InvalidTokenException('Invalid JWT Token');
                }
            } catch (JWTDecodeFailureException $e) {
                if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
                    throw new ExpiredTokenException();
                }

                throw new InvalidTokenException('Invalid JWT Token', 0, $e);
            }

            if ($cart = $this->extractCart($payload)) {

                $user = new User();
                $user->setUsername($rawToken);

                $token = new JWTUserToken([], $user, $rawToken, $this->firewallName);
                $token->setAttribute('cart', $cart);

                $passport = new SelfValidatingPassport(new UserBadge($token->getCredentials()), [
                    new PreAuthenticatedUserBadge()
                ]);
                $passport->setAttribute('token', $token);

                return $passport;
            }

            throw new AuthenticationException();

        } catch (AuthenticationException $e) {
            throw $e;
        }
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

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return $passport->getAttribute('token');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->jwtAuthenticator->onAuthenticationFailure($request, $exception);
    }
}
