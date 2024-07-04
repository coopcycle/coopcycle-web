<?php

namespace AppBundle\Security;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\User;
use AppBundle\Security\ApiKeyManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * @see https://symfony.com/doc/5.3/security/custom_authenticator.html
 */
class BearerTokenAuthenticator extends AbstractAuthenticator
{
    private TokenExtractorInterface $sessionTokenExtractor;

    public function __construct(
        private ApiKeyManager $apiKeyManager,
        private EntityManagerInterface $entityManager,
        private JWTAuthenticator $jwtAuthenticator,
        private OAuth2Authenticator $oauth2Authenticator,
        private TokenExtractorInterface $tokenExtractor,
        private OrderAccessTokenManager $orderAccessTokenManager,
        private LoggerInterface $logger,
        private string $firewallName)
    {
        $this->sessionTokenExtractor = new AuthorizationHeaderTokenExtractor('Bearer', 'X-CoopCycle-Session');
    }

    public function supports(Request $request): ?bool
    {
        return $this->apiKeyManager->supports($request)
            || $this->jwtAuthenticator->supports($request)
            || $this->oauth2Authenticator->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        // This means the token starts with "ak_"
        if ($this->apiKeyManager->supports($request)) {
            try {
                return $this->authenticateWithApiKey($request);
            } catch (AuthenticationException $e) {
                $this->logAuthenticationException($request, 'ApiKey', $e);
            }
        }

        // Then, try with Lexik
        // Lexik expects a "username" claim in the JWT payload
        // If not, it throws an InvalidPayloadException
        if ($this->jwtAuthenticator->supports($request)) {
            try {
                return $this->authenticateWithLexik($request);
            } catch (AuthenticationException $e) {
                $this->logAuthenticationException($request, 'Lexik', $e);
            }
        }

        // Then, try with League\OAuth2
        if ($this->oauth2Authenticator->supports($request)) {
            try {
                return $this->authenticateWithOAuth2($request);
            } catch (AuthenticationException $e) {
                $this->logAuthenticationException($request, 'OAuth2', $e);
            }
        }

        // Then, try with CartSession token
        try {
            return $this->authenticateWithCartSession($request);
        } catch (AuthenticationException $e) {
            $this->logAuthenticationException($request, 'CartSession token', $e);
            throw $e;
        }
    }

    private function authenticateWithApiKey(Request $request): Passport {
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

    private function authenticateWithLexik(Request $request): Passport {
        $passport = $this->jwtAuthenticator->doAuthenticate($request);

        $token = $this->jwtAuthenticator->createAuthenticatedToken($passport, $this->firewallName);

        if ($rawSessionToken = $this->sessionTokenExtractor->extract($request)) {
            if ($cart = $this->orderAccessTokenManager->parse($rawSessionToken)) {
                $token->setAttribute('cart', $cart);
            }
        }

        $passport->setAttribute('jwt_token', $token);

        return $passport;
    }

    private function authenticateWithOAuth2(Request $request): Passport {
        try {
            $passport = $this->oauth2Authenticator->authenticate($request);
        } catch (\Throwable $e) {
            throw new AuthenticationException("Invalid OAuth2 token", 0, $e);
        }

        $token = $this->oauth2Authenticator->createAuthenticatedToken($passport, $this->firewallName);
        $passport->setAttribute('oauth2_token', $token);

        return $passport;
    }

    private function authenticateWithCartSession(Request $request): Passport {
        $rawToken = $this->tokenExtractor->extract($request);

        if ($cart = $this->orderAccessTokenManager->parse($rawToken)) {

            $user = new User();
            $user->setUsername($rawToken);
            $user->setRoles(['ROLE_AD_HOC_CUSTOMER']);

            $token = new JWTUserToken([], $user, $rawToken, $this->firewallName);
            $token->setAttribute('cart', $cart);

            $passport = new SelfValidatingPassport(new UserBadge($token->getCredentials()), [
                new PreAuthenticatedUserBadge()
            ]);
            $passport->setAttribute('cart_token', $token);

            return $passport;
        }

        throw new AuthenticationException();
    }

    private function logAuthenticationException(Request $request, string $authenticator, AuthenticationException $e)
    {
        $this->logger->info('BearerTokenAuthenticator; '.$request->getRequestUri().' failed to authenticate with '.$authenticator.': '.$e->getMessage(). ' '.$e->getMessageKey());
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

        $oauth2Token = $passport->getAttribute('oauth2_token');

        if ($oauth2Token) {

            return $oauth2Token;
        }

        return $passport->getAttribute('cart_token');
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
