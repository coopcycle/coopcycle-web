<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
