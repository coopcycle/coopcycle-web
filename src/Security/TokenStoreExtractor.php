<?php

namespace AppBundle\Security;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Security\Authentication\Token\ApiKeyToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;

class TokenStoreExtractor
{
    public function __construct(
        EntityManagerInterface $doctrine,
        TokenStorageInterface $tokenStorage,
        AccessTokenManagerInterface $accessTokenManager)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->accessTokenManager = $accessTokenManager;
    }

    public function extractStore()
    {
        if (null === ($token = $this->tokenStorage->getToken())) {
            return;
        }

        if ($token instanceof ApiKeyToken) {

            $rawToken = $token->getCredentials();
            $rawApiKey = substr($rawToken, 3);

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneBy(['apiKey' => $rawApiKey, 'type' => 'api_key']);

            return $apiApp->getStore();
        }

        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneByOauth2Client($client);

            return $apiApp->getStore();
        }
    }

    /**
     * @return LocalBusiness|null
     */
    public function extractShop(): ?LocalBusiness
    {
        if (null === ($token = $this->tokenStorage->getToken())) {

            return null;
        }

        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneByOauth2Client($client);

            return $apiApp->getShop();
        }

        return null;
    }
}
