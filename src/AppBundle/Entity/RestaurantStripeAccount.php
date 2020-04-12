<?php

namespace AppBundle\Entity;

class RestaurantStripeAccount
{
    private $id;

    private $restaurant;

    private $stripeAccount;

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

    public function getStripeAccount()
    {
        return $this->stripeAccount;
    }

    public function setStripeAccount(StripeAccount $stripeAccount)
    {
        $this->stripeAccount = $stripeAccount;

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
