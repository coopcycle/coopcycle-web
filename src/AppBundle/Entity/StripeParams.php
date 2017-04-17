<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 */
class StripeParams
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $userId;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    // private $publishableKey;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    // private $refreshToken;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    // private $accessToken;

    public function getId()
    {
        return $this->id;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    // public function getPublishableKey()
    // {
    //     return $this->publishableKey;
    // }

    // public function setPublishableKey($publishableKey)
    // {
    //     $this->publishableKey = $publishableKey;

    //     return $this;
    // }

    // public function getRefreshToken()
    // {
    //     return $this->refreshToken;
    // }

    // public function setRefreshToken($refreshToken)
    // {
    //     $this->refreshToken = $refreshToken;

    //     return $this;
    // }

    // public function getAccessToken()
    // {
    //     return $this->accessToken;
    // }

    // public function setAccessToken($accessToken)
    // {
    //     $this->accessToken = $accessToken;

    //     return $this;
    // }
}
