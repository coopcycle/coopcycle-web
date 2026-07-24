<?php

namespace AppBundle\Entity;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A user queuing for a spot on a full shift, first come first served:
 * when an assignee unapplies, the oldest entry is promoted to an assignment.
 */
class ShiftWaitlistEntry
{
    protected $id;

    protected ?Shift $shift = null;

    #[Groups(['shift'])]
    protected ?UserInterface $user = null;

    #[Groups(['shift'])]
    protected $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShift(): ?Shift
    {
        return $this->shift;
    }

    public function setShift(Shift $shift): self
    {
        $this->shift = $shift;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
