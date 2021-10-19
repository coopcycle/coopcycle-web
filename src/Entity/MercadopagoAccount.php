<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;

class MercadopagoAccount
{
    use Timestampable;

    /**
     * @var int
     */
    private $id;

    private $userId;

    private $accessToken;

    private $refreshToken;

    private $publicKey;

    private $livemode;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param mixed $userId
     *
     * @return self
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param mixed $accessToken
     *
     * @return self
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @param mixed $refreshToken
     *
     * @return self
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @param mixed $publicKey
     *
     * @return self
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * @param mixed $livemode
     *
     * @return self
     */
    public function setLivemode($livemode)
    {
        $this->livemode = $livemode;

        return $this;
    }

    public function getLivemode()
    {
        return $this->livemode;
    }
}
