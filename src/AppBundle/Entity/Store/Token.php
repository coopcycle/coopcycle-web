<?php

namespace AppBundle\Entity\Store;

class Token
{
    private $id;
    private $store;
    private $token;
    private $createdAt;
    private $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    public function getStore()
    {
        return $this->store;
    }

    public function setStore($store)
    {
        $this->store = $store;

        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }
}
