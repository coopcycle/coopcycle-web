<?php

namespace AppBundle\Action;

use Doctrine\Persistence\ManagerRegistry;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\User;
use AppBundle\Security\Authentication\Token\ApiKeyToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;

class Me
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        private AccessTokenManagerInterface $accessTokenManager,
        private ManagerRegistry $doctrine)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return ApiApp|User
     */
    public function __invoke()
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof ApiKeyToken) {

            $rawApiKey = $token->getCredentials();

            return $this->doctrine->getRepository(ApiApp::class)
                ->findOneBy(['apiKey' => $rawApiKey, 'type' => 'api_key']);
        }

        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            return $this->doctrine->getRepository(ApiApp::class)
                ->findOneBy(['oauth2Client' => $client]);
        }

        return $this->getUser();
    }
}
