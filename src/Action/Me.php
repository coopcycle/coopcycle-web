<?php

namespace AppBundle\Action;

use Doctrine\Persistence\ManagerRegistry;
use AppBundle\Entity\ApiApp;
use AppBundle\Security\Authentication\Token\ApiKeyToken;
use Symfony\Bundle\SecurityBundle\Security;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;

class Me
{
    public function __construct(
        private Security $security,
        private AccessTokenManagerInterface $accessTokenManager,
        private ManagerRegistry $doctrine)
    {
    }

    public function __invoke()
    {
        $token = $this->security->getToken();

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

        return $this->security->getUser();
    }
}
