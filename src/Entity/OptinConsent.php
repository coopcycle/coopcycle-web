<?php

namespace AppBundle\Entity;

/**
 * @see https://law.stackexchange.com/questions/29190/gdpr-where-to-store-users-consent
 */
class OptinConsent
{
    private $id;
    private $user;
    private $type;
    private $createdAt;
    private $withdrawedAt;

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
}
