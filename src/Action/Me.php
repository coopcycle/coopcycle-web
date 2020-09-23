<?php

namespace AppBundle\Action;

use Doctrine\Persistence\ManagerRegistry;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;

class Me
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AccessTokenManagerInterface $accessTokenManager,
        ManagerRegistry $doctrine)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->accessTokenManager = $accessTokenManager;
    }

    /**
     * @return ApiApp|User
     */
    public function __invoke()
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneByOauth2Client($client);

            return $apiApp;
        }

        return $this->getUser();
    }
}
