<?php

namespace AppBundle\OAuth;

trait CredentialsTrait
{
    protected $accessToken;

    protected $refreshToken;

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     *
     * @return self
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param string $refreshToken
     *
     * @return self
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function hasCredentials()
    {
        return null !== $this->accessToken && null !== $this->refreshToken;
    }

    public function clearCredentials()
    {
        $this->accessToken = null;
        $this->refreshToken = null;
    }
}
