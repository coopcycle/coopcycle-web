<?php

namespace AppBundle\Entity;

class RestaurantMercadopagoAccount
{
    private $id;

    private $restaurant;

    private $mercadopagoAccount;

    private $livemode;

    public function getId()
    {
        return $this->id;
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

    public function getMercadopagoAccount()
    {
        return $this->mercadopagoAccount;
    }

    public function setMercadopagoAccount(MercadopagoAccount $mercadopagoAccount)
    {
        $this->mercadopagoAccount = $mercadopagoAccount;

        return $this;
    }

    public function isLivemode()
    {
        return $this->livemode;
    }

    public function setLivemode($livemode)
    {
        $this->livemode = $livemode;

        return $this;
    }
}
