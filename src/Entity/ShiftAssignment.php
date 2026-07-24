<?php

namespace AppBundle\Entity;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

class ShiftAssignment
{
    protected $id;

    protected ?Shift $shift = null;

    #[Groups(['shift'])]
    protected ?UserInterface $user = null;

    #[Groups(['shift'])]
    protected $createdAt;

    /**
     * Actual worked time reported for this assignment, when it differs from
     * the planned shift. Null = worked as planned.
     */
    #[Groups(['shift'])]
    protected ?ShiftTimeAdjustment $adjustment = null;

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

    public function getAdjustment(): ?ShiftTimeAdjustment
    {
        return $this->adjustment;
    }

    public function setAdjustment(?ShiftTimeAdjustment $adjustment): self
    {
        if (null !== $adjustment) {
            $adjustment->setAssignment($this);
        }
        $this->adjustment = $adjustment;

        return $this;
    }
}
