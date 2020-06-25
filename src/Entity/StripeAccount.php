<?php

namespace AppBundle\Entity;


class StripeAccount
{
    /**
     * @var int
     */
    private $id;

    private $type;

    private $displayName;

    private $payoutsEnabled;

    private $stripeUserId;

    private $refreshToken;

    private $livemode;

    private $createdAt;

    private $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getDisplayName()
    {
        return $this->displayName;
    }

    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function isPayoutsEnabled()
    {
        return $this->payoutsEnabled;
    }

    public function setPayoutsEnabled($payoutsEnabled)
    {
        $this->payoutsEnabled = $payoutsEnabled;

        return $this;
    }

    public function getStripeUserId()
    {
        return $this->stripeUserId;
    }

    public function setStripeUserId($stripeUserId)
    {
        $this->stripeUserId = $stripeUserId;

        return $this;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getLivemode()
    {
        return $this->livemode;
    }

    public function setLivemode($livemode)
    {
        $this->livemode = $livemode;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
