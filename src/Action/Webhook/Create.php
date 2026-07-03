<?php

namespace AppBundle\Action\Webhook;

use Symfony\Bundle\SecurityBundle\Security;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;

class Create
{
    public function __construct(
        private Security $security,
        private AccessTokenManagerInterface $accessTokenManager)
    {}

    public function __invoke($data)
    {
        $token = $this->security->getToken();

        if ($token instanceof OAuth2Token) {
            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();
            $data->setOauth2Client($client);
        }

        $data->setSecret(base64_encode(random_bytes(32)));

        return $data;
    }
}
