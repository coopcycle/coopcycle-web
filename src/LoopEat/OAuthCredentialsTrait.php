<?php

namespace AppBundle\LoopEat;

trait OAuthCredentialsTrait
{
    protected $loopeatAccessToken;

    protected $loopeatRefreshToken;

    /**
     * @return mixed
     */
    public function getLoopeatAccessToken()
    {
        return $this->loopeatAccessToken;
    }

    /**
     * @param mixed $loopeatAccessToken
     *
     * @return self
     */
    public function setLoopeatAccessToken($loopeatAccessToken)
    {
        $this->loopeatAccessToken = $loopeatAccessToken;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLoopeatRefreshToken()
    {
        return $this->loopeatRefreshToken;
    }

    /**
     * @param mixed $loopeatRefreshToken
     *
     * @return self
     */
    public function setLoopeatRefreshToken($loopeatRefreshToken)
    {
        $this->loopeatRefreshToken = $loopeatRefreshToken;

        return $this;
    }

    public function hasLoopEatCredentials()
    {
        return null !== $this->loopeatAccessToken && null !== $this->loopeatRefreshToken;
    }

    public function clearLoopEatCredentials()
    {
        $this->loopeatAccessToken = null;
        $this->loopeatRefreshToken = null;
    }
}
