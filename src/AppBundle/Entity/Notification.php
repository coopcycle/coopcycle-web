<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

class Notification
{
    protected $id;

    protected $user;

    protected $message;

    protected $read = false;

    protected $createdAt;

    public function getId()
    {
        return $this->id;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    public function isRead()
    {
        return $this->read;
    }

    public function setRead($read)
    {
        $this->read = $read;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
