<?php

namespace AppBundle\Security;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\User;
use AppBundle\Security\ApiKeyManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
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

/**
 * @see https://symfony.com/doc/5.3/security/custom_authenticator.html
 */
class BearerTokenAuthenticator extends AbstractAuthenticator
{
    private $jwtAuthenticator;
    private $oauth2Authenticator;
    private $apiKeyManager;
    private $entityManager;
    private $firewallName;

    public function __construct(
        JWTAuthenticator $jwtAuthenticator,
        OAuth2Authenticator $oauth2Authenticator,
        ApiKeyManager $apiKeyManager,
        EntityManagerInterface $entityManager,
        string $firewallName)
    {
        $this->jwtAuthenticator = $jwtAuthenticator;
        $this->oauth2Authenticator = $oauth2Authenticator;
        $this->apiKeyManager = $apiKeyManager;
        $this->entityManager = $entityManager;
        $this->firewallName = $firewallName;
    }

    public function supports(Request $request): ?bool
    {
        return $this->jwtAuthenticator->supports($request);
    }

    public function authenticate(Request $request): PassportInterface
    {
        // This means the token starts with "ak_"
        if ($this->apiKeyManager->supports($request)) {

            $token = $this->apiKeyManager->getCredentials($request);

            $apiApp = $this->entityManager
                ->getRepository(ApiApp::class)
                ->findOneBy([
                    'apiKey' => substr($token->getCredentials(), 3),
                    'type' => 'api_key'
                ]);

            if (null === $apiApp) {

                throw new AuthenticationException(sprintf('API Key "%s" does not exist', $token->getCredentials()));
            }

            $user = new User();
            $user->setUsername($token->getCredentials());

            $token->setUser($user);

            $passport = new SelfValidatingPassport(new UserBadge($token->getCredentials()), [
                new PreAuthenticatedUserBadge()
            ]);
            $passport->setAttribute('apikey_token', $token);

            return $passport;
        }

        try {

            // First, try with Lexik
            // Lexik expects a "username" claim in the JWT payload
            // If it throws an InvalidPayloadException, we can try with Trikoder
            $passport = $this->jwtAuthenticator->authenticate($request);

            $token = $this->jwtAuthenticator->createAuthenticatedToken($passport, $this->firewallName);
            $passport->setAttribute('jwt_token', $token);

            return $passport;

        } catch (InvalidPayloadException $e) {

            // Then, try with League\OAuth2
            $passport = $this->oauth2Authenticator->authenticate($request);

            $token = $this->oauth2Authenticator->createAuthenticatedToken($passport, $this->firewallName);
            $passport->setAttribute('oauth2_token', $token);

            return $passport;

        } catch (AuthenticationException $e) {
            throw $e;
        }
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        $apiKeyToken = $passport->getAttribute('apikey_token');

        if ($apiKeyToken) {

            return $apiKeyToken;
        }

        $jwtToken = $passport->getAttribute('jwt_token');

        if ($jwtToken) {

            return $jwtToken;
        }

        return $passport->getAttribute('oauth2_token');
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
