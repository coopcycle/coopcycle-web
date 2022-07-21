<?php

namespace AppBundle\OAuth;

interface CredentialsInterface
{
    /**
     * @return mixed
     */
    public function getAccessToken();

    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken);

    /**
     * @return mixed
     */
    public function getRefreshToken();

    /**
     * @param mixed $refreshToken
     */
    public function setRefreshToken($refreshToken);

    public function hasCredentials(): bool;

    public function clearCredentials();
}
