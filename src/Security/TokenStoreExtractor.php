<?php

namespace AppBundle\Security;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Security\Authentication\Token\ApiKeyToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;

class TokenStoreExtractor
{
    private $doctrine;
    private $accessTokenManager;

    public function __construct(
        EntityManagerInterface $doctrine,
        private Security $security,
        AccessTokenManagerInterface $accessTokenManager)
    {
        $this->doctrine = $doctrine;
        $this->accessTokenManager = $accessTokenManager;
    }

    public function extractStore()
    {

        if (null === ($token = $this->security->getToken())) {
            return;
        }

        if ($token instanceof ApiKeyToken) {

            $rawApiKey = $token->getCredentials();

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

    public function extractShop(): ?LocalBusiness
    {
        if (null === ($token = $this->security->getToken())) {

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
