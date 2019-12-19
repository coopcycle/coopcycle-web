<?php

namespace AppBundle\Security;

use AppBundle\Entity\ApiApp;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;

class TokenStoreExtractor
{
    public function __construct(
        ManagerRegistry $doctrine,
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

        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneByOauth2Client($client);

            return $apiApp->getStore();
        }
    }
}
