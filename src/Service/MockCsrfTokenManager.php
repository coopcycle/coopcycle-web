<?php

namespace AppBundle\Service;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class MockCsrfTokenManager implements CsrfTokenManagerInterface
{

    public function getToken(string $tokenId)
    {
        return new CsrfToken($tokenId, 'mocked_token');
    }

    public function refreshToken(string $tokenId)
    {
        return new CsrfToken($tokenId, 'mocked_token');
    }

    public function removeToken(string $tokenId)
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token)
    {
        return true;
    }
}
