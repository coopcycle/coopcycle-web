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

    private $restaurant;

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
     * @return mixed
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    public function getRestaurant()
    {
        return $this->restaurant;
    }

    public function setRestaurant(LocalBusiness $restaurant)
    {
        $this->restaurant = $restaurant;

        return $this;
    }
}
