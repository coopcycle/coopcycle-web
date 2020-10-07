<?php

namespace AppBundle\LoopEat;

interface OAuthCredentialsInterface
{
    /**
     * @return mixed
     */
    public function getLoopeatAccessToken();

    /**
     * @param mixed $accessToken
     */
    public function setLoopeatAccessToken($accessToken);

    /**
     * @return mixed
     */
    public function getLoopeatRefreshToken();

    /**
     * @param mixed $refreshToken
     */
    public function setLoopeatRefreshToken($refreshToken);

    public function hasLoopEatCredentials(): bool;

    public function clearLoopEatCredentials();
}
