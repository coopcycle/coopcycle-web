<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\MyOptinConsents;
use AppBundle\Action\UpdateOptinConsent;

/**
 * @see https://law.stackexchange.com/questions/29190/gdpr-where-to-store-users-consent
 */
#[ApiResource(
    operations: [
        new Get(),
        new Put(),
        new Patch(),
        new Delete(),
        new GetCollection(uriTemplate: '/me/optin-consents', controller: MyOptinConsents::class),
        new Put(uriTemplate: '/me/optin-consents', controller: UpdateOptinConsent::class)
    ]
)]
class OptinConsent
{
    private $id;
    private $user;
    private $type;
    private $createdAt;
    private $withdrawedAt;
    private $accepted;
    private $asked;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     *
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     *
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     *
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getWithdrawedAt()
    {
        return $this->withdrawedAt;
    }

    /**
     * @param mixed $withdrawedAt
     *
     * @return self
     */
    public function setWithdrawedAt($withdrawedAt)
    {
        $this->withdrawedAt = $withdrawedAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAccepted()
    {
        return $this->accepted;
    }

    /**
     * @param mixed $accepted
     *
     * @return self
     */
    public function setAccepted($accepted)
    {
        $this->accepted = $accepted;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAsked()
    {
        return $this->asked;
    }

    /**
     * @param mixed $asked
     *
     * @return self
     */
    public function setAsked($asked)
    {
        $this->asked = $asked;

        return $this;
    }
}
