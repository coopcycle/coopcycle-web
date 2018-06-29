<?php

namespace AppBundle\Security;

use AppBundle\Entity\Store;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class StoreTokenAuthenticator extends JWTTokenAuthenticator
{
    private $storeRepository;
    private $preAuthenticationTokenStorage;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        TokenExtractorInterface $tokenExtractor,
        $doctrine
    ) {
        parent::__construct($jwtManager, $dispatcher, $tokenExtractor);

        $this->storeRepository = $doctrine->getRepository(Store::class);
        $this->preAuthenticationTokenStorage = new TokenStorage();
    }

    private function getStore(PreAuthenticationJWTUserToken $preAuthToken)
    {
        $payload = $preAuthToken->getPayload();

        return $this->storeRepository->find($payload['store']);
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $user = parent::getUser($credentials, $userProvider);

        $this->preAuthenticationTokenStorage->setToken($credentials);

        return $user;
    }

    /*.
     * {@inheritdoc}
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        // TODO Verify that the token has not been revoked, i.e stored in database

        if (!$credentials instanceof PreAuthenticationJWTUserToken) {
            throw new \InvalidArgumentException(
                sprintf('The first argument of the "%s()" method must be an instance of "%s".', __METHOD__, PreAuthenticationJWTUserToken::class)
            );
        }

        $store = $this->getStore($credentials);

        if (!$user->getStores()->contains($store)) {
            return false;
        }

        return parent::checkCredentials($credentials, $user);
    }

    /**
     * {@inheritdoc}
     */
    public function createAuthenticatedToken(UserInterface $user, $providerKey)
    {
        $authToken = parent::createAuthenticatedToken($user, $providerKey);

        $preAuthToken = $this->preAuthenticationTokenStorage->getToken();
        $authToken->setAttribute('store', $this->getStore($preAuthToken));
        $this->preAuthenticationTokenStorage->setToken(null);

        return $authToken;
    }
}
