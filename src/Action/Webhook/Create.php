<?php

namespace AppBundle\Action\Webhook;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;

class Create
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AccessTokenManagerInterface $accessTokenManager)
    {}

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
