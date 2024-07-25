<?php

namespace AppBundle\Service;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class MockCsrfTokenManager implements CsrfTokenManagerInterface
{

    public function getToken(string $tokenId)
    {
        // TODO: Implement getToken() method.
    }

    public function refreshToken(string $tokenId)
    {
        // TODO: Implement refreshToken() method.
    }

    public function removeToken(string $tokenId)
    {
        // TODO: Implement removeToken() method.
    }

    public function isTokenValid(CsrfToken $token)
    {
        // TODO: Implement isTokenValid() method.
    }
}
