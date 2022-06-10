<?php

namespace AppBundle\Dabba;

interface OAuthCredentialsInterface
{
    /**
     * @return mixed
     */
    public function getDabbaAccessToken();

    /**
     * @param mixed $accessToken
     */
    public function setDabbaAccessToken($accessToken);

    /**
     * @return mixed
     */
    public function getDabbaRefreshToken();

    /**
     * @param mixed $refreshToken
     */
    public function setDabbaRefreshToken($refreshToken);

    public function hasDabbaCredentials(): bool;

    public function clearDabbaCredentials();
}
