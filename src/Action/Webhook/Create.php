<?php

namespace AppBundle\Action\Webhook;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;

class Create
{
    public function __construct(
        TokenStorageInterface $tokenStorage,
        AccessTokenManagerInterface $accessTokenManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->accessTokenManager = $accessTokenManager;
    }

    public function __invoke($data)
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof OAuth2Token) {
            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();
            $data->setOauth2Client($client);
        }

        $data->setSecret(base64_encode(random_bytes(32)));

        return $data;
    }
}
